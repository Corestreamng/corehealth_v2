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

    $(function() {

        // ═══════════════════════════════════════════════════════════════
        //  State
        // ═══════════════════════════════════════════════════════════════
        var currentStatusFilter = 'all';
        var calendarInitialized = false;
        var currentView = 'table'; // 'table' or 'calendar'
        var contextEvent = null;   // Currently right-clicked event
        var doctorStaffId = window.WORKBENCH_CONFIG?.staffId || '';
        var historyTablesInitialized = false;
        var referralTablesInitialized = false;

        // ═══════════════════════════════════════════════════════════════
        //  Main Tab: Lazy-init history tables when tab 2 is shown
        // ═══════════════════════════════════════════════════════════════
        $('#history-lists-tab').on('shown.bs.tab', function() {
            if (!historyTablesInitialized) {
                initSecondaryTable('#prev_consult_list', wbUrl('PrevEncounterList'));
                initSecondaryTable('#my_admissions_list', wbRoute('my-admission-requests-list', '/my-admission-requests-list'));
                initSecondaryTable('#other_admissions_list', wbRoute('admission-requests-list', '/admission-requests-list'));
                historyTablesInitialized = true;
            }
        });

        // Lazy-init referral tables only when their inner tabs are first shown
        $('#my_referrals_tab').on('shown.bs.tab', function() {
            if (!referralTablesInitialized) {
                initReferralTable('#my_referrals_list', wbRoute('referrals.doctor-list', '/referrals/doctor-list'), true);
                referralTablesInitialized = true;
            }
        });
        var allReferralTableInitialized = false;
        $('#all_referrals_tab').on('shown.bs.tab', function() {
            if (!allReferralTableInitialized) {
                initReferralTable('#all_referrals_list', wbRoute('referrals.all-list', '/referrals/all-list'), false);
                allReferralTableInitialized = true;
            }
        });

        // ═══════════════════════════════════════════════════════════════
        //  Status Pills
        // ═══════════════════════════════════════════════════════════════
        $('#statusPillBar').on('click', '.status-pill', function() {
            $('#statusPillBar .status-pill').removeClass('active');
            $(this).addClass('active');
            currentStatusFilter = $(this).data('status');
            refreshCurrentView();
        });

        // ═══════════════════════════════════════════════════════════════
        //  View Toggle
        // ═══════════════════════════════════════════════════════════════
        $('#btn-calendar-view').on('click', function() {
            $(this).addClass('active');
            $('#btn-table-view').removeClass('active');
            $('#table-wrapper').hide();
            $('#calendar-wrapper').show();
            currentView = 'calendar';
            if (!calendarInitialized) {
                initUnifiedCalendar();
                calendarInitialized = true;
            } else {
                smoothRefreshDocCal();
            }
        });

        $('#btn-table-view').on('click', function() {
            $(this).addClass('active');
            $('#btn-calendar-view').removeClass('active');
            $('#calendar-wrapper').hide();
            $('#table-wrapper').show();
            currentView = 'table';
            reloadUnifiedTable();
        });

        function refreshCurrentView() {
            if (currentView === 'calendar' && calendarInitialized) {
                smoothRefreshDocCal();
            } else {
                reloadUnifiedTable();
            }
        }

        // ═══════════════════════════════════════════════════════════════
        //  Unified DataTable
        // ═══════════════════════════════════════════════════════════════
        var unifiedTable = $('#unified-queue-table').DataTable({
            dom: 'Bfrtip',
            iDisplayLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
            processing: true,
            serverSide: true,
            searchDelay: 500,
            ajax: {
                url: wbRoute('appointments.doctor.unified-list', '/appointments/doctor/unified-list'),
                type: 'GET',
                data: function(d) {
                    d.start_date = $('#appt_start_date').val() || moment().format('YYYY-MM-DD');
                    d.end_date   = $('#appt_end_date').val() || moment().add(30, 'days').format('YYYY-MM-DD');
                    d.status_filter = currentStatusFilter;
                    d.source_filter = $('#appt_source_filter').val() || 'all';
                    d.priority_filter = $('#appt_priority_filter').val() || 'all';
                    d.clinic_filter = $('#appt_clinic_filter').val() || 'all';
                    d.sort_filter = $('#appt_sort_filter').val() || 'newest';
                }
            },
            columns: [
                { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false, width: "30px" },
                { data: "card_html", name: "patient_name", orderable: false }
            ],
            paging: true,
            drawCallback: function() { initMiniTimers(); },
            initComplete: function() {
                // Add placeholder to search box
                var $input = $(this).closest('.dataTables_wrapper').find('input[type="search"]');
                $input.attr('placeholder', 'Search by name, file no, HMO, clinic...');
                $input.css('min-width', '250px');
            },
            language: {
                emptyTable: '<div class="text-center py-4"><i class="mdi mdi-calendar-check-outline" style="font-size:2rem;color:#ccc;"></i><br><span class="text-muted">No queue entries for today</span></div>'
            }
        });

        function reloadUnifiedTable() {
            unifiedTable.ajax.reload(null, false);
        }

        // Check-in from table action button
        $(document).on('click', '.btn-checkin-appt', function() {
            var apptId = $(this).data('id');
            doCheckIn(apptId);
        });

        // Cancel from table action button
        $(document).on('click', '.btn-cancel-appt', function() {
            var apptId = $(this).data('id');
            doCancelAppointment(apptId);
        });

        // No-Show from table action button
        $(document).on('click', '.btn-noshow-appt', function() {
            var apptId = $(this).data('id');
            doNoShow(apptId);
        });

        // ═══════════════════════════════════════════════════════════════
        //  Unified Calendar (FullCalendar v2)
        // ═══════════════════════════════════════════════════════════════

        // ── Smooth refresh: diff-based update to avoid blink ────────
        var _docCalLastEvents = {};  // id → JSON fingerprint

        function smoothRefreshDocCal() {
            if (!calendarInitialized) return;
            var view = $('#unified-calendar').fullCalendar('getView');
            $.ajax({
                url: wbRoute('appointments.doctor.unified-events', '/appointments/doctor/unified-events'),
                type: 'GET',
                data: {
                    start: view.start.format('YYYY-MM-DD'),
                    end: view.end.format('YYYY-MM-DD'),
                    status: currentStatusFilter === 'all' ? '' : currentStatusFilter
                },
                success: function(newEvents) {
                    var cal = $('#unified-calendar');
                    var newMap = {};
                    (newEvents || []).forEach(function(e) {
                        var fp = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
                        newMap[e.id] = { data: e, fingerprint: fp };
                    });

                    // Remove events that are gone or changed
                    var existing = cal.fullCalendar('clientEvents');
                    existing.forEach(function(ev) {
                        var n = newMap[ev.id];
                        if (!n) {
                            cal.fullCalendar('removeEvents', ev.id);
                        } else if (n.fingerprint !== _docCalLastEvents[ev.id]) {
                            cal.fullCalendar('removeEvents', ev.id);
                        } else {
                            delete newMap[ev.id];
                        }
                    });

                    // Add new or changed events
                    Object.keys(newMap).forEach(function(id) {
                        cal.fullCalendar('renderEvent', newMap[id].data, true);
                    });

                    // Store fingerprints for next diff
                    _docCalLastEvents = {};
                    (newEvents || []).forEach(function(e) {
                        _docCalLastEvents[e.id] = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
                    });
                }
            });
        }

        function initUnifiedCalendar() {
            $('#unified-calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                defaultView: 'agendaWeek',
                allDaySlot: false,
                slotDuration: '00:15:00',
                minTime: '06:00:00',
                maxTime: '22:00:00',
                height: 'auto',
                contentHeight: 600,
                editable: false,
                eventLimit: true,
                slotEventOverlap: false,
                nowIndicator: true,

                events: function(start, end, timezone, callback) {
                    $.ajax({
                        url: wbRoute('appointments.doctor.unified-events', '/appointments/doctor/unified-events'),
                        type: 'GET',
                        data: {
                            start: start.format('YYYY-MM-DD'),
                            end: end.format('YYYY-MM-DD'),
                            status: currentStatusFilter === 'all' ? '' : currentStatusFilter
                        },
                        success: function(events) {
                            // Store fingerprints so first smooth refresh can diff
                            _docCalLastEvents = {};
                            (events || []).forEach(function(e) {
                                _docCalLastEvents[e.id] = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
                            });
                            callback(events);
                        },
                        error: function() { callback([]); }
                    });
                },

                eventRender: function(event, element) {
                    // Source icon
                    var srcIcon = '';
                    if (event.event_type === 'appointment') {
                        srcIcon = '<i class="mdi mdi-calendar-check event-icon" style="opacity:0.8;"></i>';
                    } else if (event.source === 'emergency') {
                        srcIcon = '<i class="fa fa-bolt event-icon" style="color:#ffc107;"></i>';
                    } else {
                        srcIcon = '<i class="mdi mdi-walk event-icon" style="opacity:0.8;"></i>';
                    }
                    element.find('.fc-title').prepend(srcIcon);

                    // Priority marker
                    if (event.priority === 'urgent' || event.priority === 'emergency') {
                        element.css('border-left', '4px solid #dc3545');
                    }

                    // Delivery blocked visual indicator (striped pattern)
                    if (event.can_deliver === false) {
                        element.css({
                            'background': 'repeating-linear-gradient(45deg, ' + event.color + ', ' + event.color + ' 10px, rgba(255,255,255,0.15) 10px, rgba(255,255,255,0.15) 12px)',
                            'border-right': '3px solid #dc3545'
                        });
                        element.find('.fc-title').append(' <i class="mdi mdi-alert-circle" style="color:#ffc107;font-size:0.85rem;" title="' + (event.delivery_reason || 'Blocked') + '"></i>');
                    }

                    // Build popover content
                    var popContent = '<div class="pop-row"><span class="pop-label">Patient</span><br><strong>' + (event.patient_name || 'N/A') + '</strong></div>' +
                        '<div class="pop-row"><span class="pop-label">File No</span><br>' + (event.file_no || '-') + '</div>' +
                        (event.hmo ? '<div class="pop-row"><span class="pop-label">HMO</span><br>' + event.hmo + '</div>' : '') +
                        '<div class="pop-row"><span class="pop-label">Clinic</span><br>' + (event.clinic || '-') + '</div>' +
                        '<div class="pop-row"><span class="pop-label">Status</span><br><span class="badge" style="background:' + event.color + ';">' + (event.status_label || '') + '</span></div>' +
                        '<div class="pop-row"><span class="pop-label">Time</span><br>' + moment(event.start).format('h:mm A') + '</div>' +
                        (event.reason ? '<div class="pop-row"><span class="pop-label">Note</span><br>' + event.reason + '</div>' : '');

                    // In-consultation timer info
                    if (event.timer) {
                        popContent += '<div class="pop-row"><span class="pop-label">Timer</span><br><i class="mdi mdi-timer"></i> Running' + (event.timer.is_paused ? ' (Paused)' : '') + '</div>';
                    }

                    // Delivery status (small text, not badges)
                    if (event.can_deliver === false) {
                        popContent += '<div class="pop-row"><span class="pop-label">Delivery</span><br><span style="color:#dc3545;font-size:0.8em;"><i class="mdi mdi-alert-circle"></i> ' + (event.delivery_reason || 'Blocked') + '</span></div>';
                    } else if (event.event_type === 'queue') {
                        popContent += '<div class="pop-row"><span class="pop-label">Delivery</span><br><span style="color:#198754;font-size:0.8em;"><i class="mdi mdi-check-circle"></i> Ready</span></div>';
                    }

                    // Next step guidance
                    if (event.next_step) {
                        popContent += '<div class="pop-row" style="border-top:1px solid #eee;padding-top:4px;margin-top:2px;"><span class="pop-label" style="color:#0d6efd;">Next Step</span><br><em style="color:#0d6efd;font-size:0.82em;"><i class="mdi mdi-arrow-right-circle"></i> ' + event.next_step + '</em></div>';
                    }

                    element.attr('data-toggle', 'popover')
                        .attr('data-html', 'true')
                        .attr('data-trigger', 'hover')
                        .attr('data-placement', 'top')
                        .attr('data-content', popContent)
                        .attr('data-container', 'body');
                    element.popover({ container: 'body', html: true });
                },

                eventClick: function(event, jsEvent, view) {
                    jsEvent.preventDefault();
                    jsEvent.stopPropagation();
                    $('.popover').remove();
                    showEventContextMenu(event, jsEvent);
                },

                viewRender: function(view) {
                    $('.popover').remove();
                }
            });
        }

        // ═══════════════════════════════════════════════════════════════
        //  Event Context Menu
        // ═══════════════════════════════════════════════════════════════
        function showEventContextMenu(event, jsEvent) {
            contextEvent = event;
            var $menu = $('#eventContextMenu');

            // Show/hide items based on status/type + delivery
            var isActive = event.status == 1 || event.status == 2 || event.status == 3 || event.status == 4;
            var isTerminal = event.status == 0 || event.status == 5 || event.status == 7;
            var hasEncounterAccess = (!!event.encounter_url || (isActive && event.queue_id)) && event.can_deliver !== false;

            // Status info line for terminal states
            if (isTerminal) {
                var statusText = event.status == 0 ? 'Cancelled' : (event.status == 5 ? 'Completed' : 'No-Show');
                var statusIcon = event.status == 0 ? 'mdi mdi-cancel text-danger' : (event.status == 5 ? 'mdi mdi-check-circle text-success' : 'mdi mdi-account-remove text-secondary');
                $menu.find('.ctx-status-icon').attr('class', 'ctx-status-icon ' + statusIcon);
                $menu.find('.ctx-status-label').text(statusText);
            }
            $menu.find('[data-action="status-info"]').toggle(isTerminal);

            $menu.find('[data-action="encounter"]').toggle(hasEncounterAccess);
            $menu.find('[data-action="encounter-blocked"]').toggle(isActive && event.can_deliver === false);
            if (event.can_deliver === false) {
                $menu.find('.ctx-blocked-reason').text(event.delivery_reason || 'Delivery Blocked');
            }
            $menu.find('[data-action="checkin"]').toggle(event.event_type === 'appointment' && event.status == 6);
            // Reschedule available for scheduled and no-show appointments
            $menu.find('[data-action="reschedule"]').toggle(event.event_type === 'appointment' && (event.status == 6 || event.status == 7));
            $menu.find('[data-action="reassign"]').toggle(event.event_type === 'appointment' && event.status == 6);
            $menu.find('[data-action="cancel"]').toggle(event.event_type === 'appointment' && (event.status == 6 || event.status == 1 || event.status == 2));
            $menu.find('[data-action="noshow"]').toggle(event.event_type === 'appointment' && event.status == 6);

            // Next-step hint for non-obvious states
            var showHint = false;
            if (event.next_step) {
                // Show hint for: WAITING/VITALS_PENDING/READY (when user might be confused)
                // Don't show for SCHEDULED (has check-in button) or IN_CONSULTATION (has encounter)
                if (event.status == 1 || event.status == 2 || event.status == 3) {
                    showHint = true;
                }
                // Also show for SCHEDULED appointments as a subtle hint
                if (event.status == 6) {
                    showHint = true;
                }
            }
            $menu.find('[data-action="next-step-hint"]').toggle(showHint);
            if (showHint) {
                $menu.find('.ctx-next-step').text(event.next_step);
            }

            // Hide dividers when their section is empty
            $menu.find('.ctx-divider').each(function() {
                var $prev = $(this).prevAll('.ctx-item:first');
                var $next = $(this).nextAll('.ctx-item:first');
                var prevVisible = $prev.length && $prev.css('display') !== 'none';
                var nextVisible = $next.length && $next.css('display') !== 'none';
                $(this).toggle(prevVisible && nextVisible);
            });

            // Position near click
            var x = jsEvent.pageX, y = jsEvent.pageY;
            var menuW = 260, menuH = 300;
            if (x + menuW> $(window).width()) x = $(window).width() - menuW - 10;
            if (y + menuH> $(window).scrollTop() + $(window).height()) y = y - menuH;

            $menu.css({ left: x, top: y }).show();
        }

        // Context menu actions
        $('#eventContextMenu').on('click', '.ctx-item', function() {
            var action = $(this).data('action');
            var evt = contextEvent;
            $('#eventContextMenu').hide();
            if (!evt) return;

            switch(action) {
                case 'encounter':
                    if (evt.encounter_url) {
                        window.location.href = evt.encounter_url;
                    } else if (evt.queue_id) {
                        window.location.href = wbUrl('/encounters/create?patient_id=') + evt.patient_id + '&queue_id=' + evt.queue_id;
                    }
                    break;
                case 'checkin':
                    doCheckIn(evt.record_id);
                    break;
                case 'reschedule':
                    openRescheduleModal(evt.record_id, evt.patient_name || 'Patient', evt.clinic_id, evt.doctor_id, evt.start || '', evt.reschedule_count || 0);
                    break;
                case 'reassign':
                    openReassignModal(evt.record_id, evt.patient_name || 'Patient', evt.clinic_id);
                    break;
                case 'cancel':
                    if (evt.event_type === 'appointment') {
                        doCancelAppointment(evt.record_id);
                    } else {
                        toastr.warning('Queue cancellation can be done from the encounter screen.', 'Info');
                    }
                    break;
                case 'noshow':
                    doNoShow(evt.record_id);
                    break;
                case 'next-step-hint':
                case 'encounter-blocked':
                    // Informational items — no action
                    break;
            }
        });

        // Close context menu on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#eventContextMenu').length) {
                $('#eventContextMenu').hide();
            }
        });

        // ═══════════════════════════════════════════════════════════════
        //  API Actions
        // ═══════════════════════════════════════════════════════════════
        function doCheckIn(apptId) {
            _pendingActionId = apptId;
            $('#confirmCheckInModal').modal('show');
        }

        $(document).on('click', '#confirmCheckInBtn', function() {
            var apptId = _pendingActionId;
            $('#confirmCheckInModal').modal('hide');
            $.ajax({
                url: wbRoute('appointments.check-in', '/appointments/check-in').replace('__AID__', apptId),
                type: 'POST',
                data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Checked in successfully.');
                        toastr.info('Patient is now <b>Waiting</b>. If payment is pending, they need to visit billing first.', 'What\'s Next?', { timeOut: 7000, enableHtml: true });
                        refreshAll();
                    } else {
                        toastr.error(res.message || 'Check-in failed.');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Check-in failed.');
                }
            });
        });

        function doCancelAppointment(apptId) {
            _pendingActionId = apptId;
            $('#cancelApptReason').val('');
            $('#confirmCancelApptModal').modal('show');
        }

        $(document).on('click', '#confirmCancelApptBtn', function() {
            var apptId = _pendingActionId;
            var reason = $('#cancelApptReason').val() || '';
            $('#confirmCancelApptModal').modal('hide');
            $.ajax({
                url: wbRoute('appointments.cancel', '/appointments/cancel').replace('__AID__', apptId),
                type: 'POST',
                data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || ''), reason: reason },
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message || 'Cancelled.');
                        refreshAll();
                    } else {
                        toastr.error(res.message || 'Failed to cancel.');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to cancel.');
                }
            });
        });

        function doNoShow(apptId) {
            _pendingActionId = apptId;
            $('#confirmNoShowModal').modal('show');
        }

        $(document).on('click', '#confirmNoShowBtn', function() {
            var apptId = _pendingActionId;
            $('#confirmNoShowModal').modal('hide');
            $.ajax({
                url: wbRoute('appointments.no-show', '/appointments/no-show').replace('__AID__', apptId),
                type: 'POST',
                data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
                success: function(res) {
                    if (res.success) {
                        toastr.warning(res.message || 'Marked as no-show.');
                        refreshAll();
                    } else {
                        toastr.error(res.message || 'Failed.');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed.');
                }
            });
        });

        // ═══════════════════════════════════════════════════════════════
        //  Badge & Stats Refresh
        // ═══════════════════════════════════════════════════════════════
        function loadQueueCounts() {
            var start = $('#appt_start_date').val() || moment().format('YYYY-MM-DD');
            var end = $('#appt_end_date').val() || moment().add(30, 'days').format('YYYY-MM-DD');
            var clinic = $('#appt_clinic_filter').val() || 'all';

            $.ajax({
                url: wbRoute('appointments.doctor.queue-counts', '/appointments/doctor/queue-counts'),
                type: 'GET',
                data: { start_date: start, end_date: end, clinic_filter: clinic },
                success: function(c) {
                    var waiting = c.new || c.waiting || 0;
                    var vitals  = c.vitals_pending || 0;
                    var ready   = c.ready || 0;
                    var consult = c.in_consultation || 0;
                    var sched   = c.scheduled || 0;
                    var schedToday  = c.scheduled_today || 0;
                    var schedFuture = c.scheduled_future || 0;
                    var compl   = c.completed || 0;
                    var total   = waiting + vitals + ready + consult;

                    // Stats cards
                    $('#stat-waiting').text(waiting);
                    $('#stat-vitals').text(vitals);
                    $('#stat-ready').text(ready);
                    $('#stat-consult').text(consult);
                    $('#stat-scheduled').text(sched);
                    // Scheduled breakdown subtitle
                    var schedDetail = '';
                    if (schedToday> 0) schedDetail += schedToday + ' today';
                    if (schedFuture> 0) schedDetail += (schedDetail ? ', ' : '') + schedFuture + ' upcoming';
                    $('#stat-scheduled-detail').text(schedDetail);

                    $('#stat-completed').text(compl);
                    $('#stat-total').text(total);

                    // Pill counts
                    var all = waiting + vitals + ready + consult + sched;
                    $('#pill-all').text(all);
                    $('#pill-1').text(waiting);
                    $('#pill-2').text(vitals);
                    $('#pill-3').text(ready);
                    $('#pill-4').text(consult);
                    $('#pill-6').text(sched);
                    $('#pill-5').text(compl);

                    // Tab badge
                    $('#tab-badge-active').text(total);
                },
                error: function() { /* silent */ }
            });
        }

        // ═══════════════════════════════════════════════════════════════
        //  Refresh Everything
        // ═══════════════════════════════════════════════════════════════
        function refreshAll() {
            loadQueueCounts();
            if (currentView === 'calendar' && calendarInitialized) {
                smoothRefreshDocCal();
            }
            if (currentView === 'table') {
                reloadUnifiedTable();
            }
        }

        // Initial load
        loadQueueCounts();

        // ── 30-second Auto-Refresh ───────────────────────────────────
        setInterval(function() {
            refreshAll();
            // Also reload active history inner tab if history pane is visible
            if ($('#history-lists-pane').hasClass('active') && historyTablesInitialized) {
                var activeSecTab = $('#historyInnerTabs .nav-link.active').attr('id');
                var secMap = {
                    'prev_data_tab': '#prev_consult_list',
                    'my_admissions_tab': '#my_admissions_list',
                    'other_admissions_tab': '#other_admissions_list',
                    'my_referrals_tab': '#my_referrals_list',
                    'all_referrals_tab': '#all_referrals_list'
                };
                var secSelector = secMap[activeSecTab];
                if (secSelector && $.fn.DataTable.isDataTable(secSelector)) {
                    $(secSelector).DataTable().ajax.reload(null, false);
                }
            }
        }, 30000);

        // ═══════════════════════════════════════════════════════════════
        //  Secondary DataTables (Previous, Admissions)
        // ═══════════════════════════════════════════════════════════════
        function getSecondaryColumns(selector) {
            return [
                { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false, width: '30px' },
                { data: "card_html", name: "card_html", orderable: false }
            ];
        }

        function initSecondaryTable(selector, ajaxUrl) {
            if ($.fn.DataTable.isDataTable(selector)) {
                $(selector).DataTable().ajax.reload(null, false);
                return;
            }
            $(selector).DataTable({
                dom: 'Bfrtip',
                iDisplayLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                processing: true,
                serverSide: true,
                ajax: {
                    url: ajaxUrl,
                    type: 'GET',
                    data: function(d) {
                        var filters = getTableFilterData(selector);
                        Object.keys(filters).forEach(function(k) { d[k] = filters[k]; });
                    }
                },
                columns: getSecondaryColumns(selector),
                paging: true
            });
        }

        /**
         * Return filter parameters for each table based on its own inputs.
         */
        function getTableFilterData(selector) {
            if (selector === '#prev_consult_list') {
                return {
                    start_date: $('#prev_start_date').val(),
                    end_date:   $('#prev_end_date').val(),
                    clinic_id:  $('#prev_clinic_filter').val(),
                    hmo_id:     $('#prev_hmo_filter').val(),
                    sort_filter: $('#prev_sort_filter').val() || 'newest'
                };
            }
            if (selector === '#my_admissions_list') {
                return {
                    start_date: $('#my_adm_start_date').val(),
                    end_date:   $('#my_adm_end_date').val(),
                    hmo_id:     $('#my_adm_hmo_filter').val(),
                    sort_filter: $('#my_adm_sort_filter').val() || 'newest'
                };
            }
            if (selector === '#other_admissions_list') {
                return {
                    start_date: $('#other_adm_start_date').val(),
                    end_date:   $('#other_adm_end_date').val(),
                    doctor_id:  $('#other_adm_doctor_filter').val(),
                    hmo_id:     $('#other_adm_hmo_filter').val(),
                    sort_filter: $('#other_adm_sort_filter').val() || 'newest'
                };
            }
            return {};
        }

        // Init secondary tables on first show of history tab (lazy)
        // See #history-lists-tab shown.bs.tab handler above

        // Fetch button handlers for all three tabs
        $('#appt_date_fetch_btn').on('click', function() {
            refreshCurrentView();
        });
        $('#appt_source_filter, #appt_priority_filter, #appt_clinic_filter, #appt_sort_filter').on('change', function() {
            refreshCurrentView();
        });
        $('#prev_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#prev_consult_list')) {
                $('#prev_consult_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#prev_sort_filter').on('change', function() {
            if ($.fn.DataTable.isDataTable('#prev_consult_list')) {
                $('#prev_consult_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#my_adm_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#my_admissions_list')) {
                $('#my_admissions_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#my_adm_sort_filter').on('change', function() {
            if ($.fn.DataTable.isDataTable('#my_admissions_list')) {
                $('#my_admissions_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#other_adm_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#other_admissions_list')) {
                $('#other_admissions_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#other_adm_sort_filter').on('change', function() {
            if ($.fn.DataTable.isDataTable('#other_admissions_list')) {
                $('#other_admissions_list').DataTable().ajax.reload(null, false);
            }
        });

        // ═══════════════════════════════════════════════════════════════
        //  Referral DataTables
        // ═══════════════════════════════════════════════════════════════
        var referralColumns = [
            { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false, width: '30px' },
            { data: "card_html", name: "card_html", orderable: false }
        ];

        function initReferralTable(selector, ajaxUrl, isMine) {
            if ($.fn.DataTable.isDataTable(selector)) {
                $(selector).DataTable().ajax.reload(null, false);
                return;
            }
            $(selector).DataTable({
                dom: 'Bfrtip',
                iDisplayLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                processing: true,
                serverSide: true,
                ajax: {
                    url: ajaxUrl,
                    type: 'GET',
                    data: function(d) {
                        var filters = getReferralFilterData(selector);
                        Object.keys(filters).forEach(function(k) { d[k] = filters[k]; });
                    }
                },
                columns: referralColumns,
                paging: true,
                order: []
            });
        }

        function getReferralFilterData(selector) {
            if (selector === '#my_referrals_list') {
                return {
                    start_date: $('#my_ref_start_date').val(),
                    end_date:   $('#my_ref_end_date').val(),
                    status:     $('#my_ref_status_filter').val(),
                    direction:  $('#my_ref_direction_filter').val(),
                    referral_type: $('#my_ref_type_filter').val(),
                    sort_filter: $('#my_ref_sort_filter').val() || 'newest'
                };
            }
            if (selector === '#all_referrals_list') {
                return {
                    start_date:    $('#all_ref_start_date').val(),
                    end_date:      $('#all_ref_end_date').val(),
                    status:        $('#all_ref_status_filter').val(),
                    clinic_id:     $('#all_ref_clinic_filter').val(),
                    doctor_id:     $('#all_ref_doctor_filter').val(),
                    referral_type: $('#all_ref_type_filter').val(),
                    sort_filter: $('#all_ref_sort_filter').val() || 'newest'
                };
            }
            return {};
        }

        // Fetch buttons for referral tabs
        $('#my_ref_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#my_referrals_list')) {
                $('#my_referrals_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#my_ref_sort_filter').on('change', function() {
            if ($.fn.DataTable.isDataTable('#my_referrals_list')) {
                $('#my_referrals_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#all_ref_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#all_referrals_list')) {
                $('#all_referrals_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#all_ref_sort_filter').on('change', function() {
            if ($.fn.DataTable.isDataTable('#all_referrals_list')) {
                $('#all_referrals_list').DataTable().ajax.reload(null, false);
            }
        });

        // ═══════════════════════════════════════════════════════════════
        //  Referral Actions (View, Accept, Decline)
        // ═══════════════════════════════════════════════════════════════
        var _activeRefId = null;
        var _activeRefData = null;
        var _pendingActionId = null;
        var _pendingActionSource = null;
        var _pendingActionBtn = null;

        // View referral detail
        $(document).on('click', '.btn-view-ref-detail', function() {
            var refId = $(this).data('id');
            _activeRefId = refId;
            $('#refDetailModalBody').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
            $('#refDetailAcceptBtn, #refDetailDeclineBtn, #refDetailPrintBtn').addClass('d-none');
            $('#refDetailModal').modal('show');

            $.get(wbUrl('referrals/' + refId + '/detail'), function(data) {
                _activeRefData = data;
                var ref = data.referral;
                var html = '';

                // Patient info
                html += '<div class="card-modern border-0 bg-light mb-3">';
                html += '<div class="card-body py-2">';
                html += '<div class="row">';
                html += '<div class="col-md-6"><small class="text-muted">Patient</small><br><strong><i class="mdi mdi-account me-1"></i>' + (ref.patient_name || 'N/A') + '</strong></div>';
                html += '<div class="col-md-3"><small class="text-muted">File No</small><br><strong>' + (ref.patient_file_no || 'N/A') + '</strong></div>';
                html += '<div class="col-md-3"><small class="text-muted">Date</small><br><strong>' + (ref.created_at || '') + '</strong></div>';
                html += '</div></div></div>';

                // Status row
                var urgBadges = { 'emergency': 'bg-danger', 'urgent': 'bg-warning text-dark', 'routine': 'bg-secondary' };
                var stBadges = { 'pending': 'bg-warning text-dark', 'booked': 'bg-primary', 'completed': 'bg-success', 'declined': 'bg-dark', 'cancelled': 'bg-danger', 'referred_out': 'bg-purple text-white' };
                html += '<div class="mb-3">';
                html += '<span class="badge ' + (urgBadges[ref.urgency] || 'bg-secondary') + ' me-1"><i class="mdi mdi-alert me-1"></i>' + (ref.urgency || 'routine') + '</span>';
                html += '<span class="badge ' + (stBadges[ref.status] || 'bg-secondary') + ' me-1">' + (ref.status || '') + '</span>';
                html += '<span class="badge ' + (ref.referral_type === 'internal' ? 'bg-info' : 'bg-dark') + '">' + (ref.referral_type || '') + '</span>';
                html += '</div>';

                // Referral info
                html += '<div class="row mb-3">';
                html += '<div class="col-md-6">';
                html += '<div class="card-modern border h-100"><div class="card-header bg-light py-1"><small class="fw-bold"><i class="mdi mdi-arrow-up-bold text-danger me-1"></i>Referred From</small></div>';
                html += '<div class="card-body py-2"><small>' + (ref.referring_doctor || 'N/A') + '</small>';
                if (ref.referring_clinic) html += '<br><small class="text-muted">' + ref.referring_clinic + '</small>';
                html += '</div></div>';
                html += '</div>';
                html += '<div class="col-md-6">';
                html += '<div class="card-modern border h-100"><div class="card-header bg-light py-1"><small class="fw-bold"><i class="mdi mdi-arrow-down-bold text-success me-1"></i>Referred To</small></div>';
                html += '<div class="card-body py-2">';
                if (ref.referral_type === 'internal') {
                    html += '<small>' + (ref.target_clinic || 'Any Clinic') + '</small>';
                    if (ref.target_doctor) html += '<br><small class="text-muted">' + ref.target_doctor + '</small>';
                } else {
                    html += '<small>' + (ref.external_facility_name || 'External Facility') + '</small>';
                    if (ref.external_doctor_name) html += '<br><small class="text-muted">Dr. ' + ref.external_doctor_name + '</small>';
                }
                html += '</div></div>';
                html += '</div>';
                html += '</div>';

                // Clinical info
                html += '<div class="card-modern border mb-3"><div class="card-header bg-light py-1"><small class="fw-bold"><i class="mdi mdi-clipboard-pulse me-1 text-primary"></i>Clinical Information</small></div>';
                html += '<div class="card-body py-2">';
                if (ref.provisional_diagnosis) html += '<p class="mb-1"><small><strong>Diagnosis:</strong> ' + ref.provisional_diagnosis + '</small></p>';
                if (ref.clinical_summary) html += '<p class="mb-1"><small><strong>Summary:</strong> ' + ref.clinical_summary + '</small></p>';
                html += '<p class="mb-0"><small><strong>Reason:</strong> ' + (ref.reason || 'N/A') + '</small></p>';
                html += '</div></div>';

                // Action notes
                if (ref.action_notes) {
                    html += '<div class="alert alert-info py-2"><small><strong>Action Notes:</strong> ' + ref.action_notes + '</small></div>';
                }

                $('#refDetailModalBody').html(html);

                // Show action buttons based on context
                if (ref.status === 'pending') {
                    if (ref.is_targeted_at_me) {
                        $('#refDetailAcceptBtn').removeClass('d-none');
                    }
                    $('#refDetailDeclineBtn').removeClass('d-none');
                }
                if (ref.referral_type === 'external') {
                    $('#refDetailPrintBtn').removeClass('d-none');
                }
            }).fail(function() {
                $('#refDetailModalBody').html('<div class="alert alert-danger">Failed to load referral details</div>');
            });
        });

        // Accept referral from modal
        $('#refDetailAcceptBtn').on('click', function() {
            if (!_activeRefId) return;
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Accepting...');
            $.post(wbUrl('referrals/' + _activeRefId + '/accept'), {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')}, function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Referral accepted');
                    $('#refDetailModal').modal('hide');
                    // Reload referral tables
                    if ($.fn.DataTable.isDataTable('#my_referrals_list')) $('#my_referrals_list').DataTable().ajax.reload(null, false);
                    if ($.fn.DataTable.isDataTable('#all_referrals_list')) $('#all_referrals_list').DataTable().ajax.reload(null, false);
                    if (res.encounter_url) {
                        window.open(res.encounter_url, '_blank');
                    }
                } else {
                    toastr.error(res.message || 'Failed');
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to accept');
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="mdi mdi-check-circle me-1"></i>Accept');
            });
        });

        // Decline referral from modal
        $('#refDetailDeclineBtn').on('click', function() {
            if (!_activeRefId) return;
            _pendingActionId = _activeRefId;
            _pendingActionSource = 'refDetail';
            $('#declineRefReason').val('');
            $('#refDetailModal').modal('hide');
            $('#declineRefReasonModal').modal('show');
        });

        // Print referral from modal
        $('#refDetailPrintBtn').on('click', function() {
            if (!_activeRefId || !_activeRefData) return;
            $.get(wbUrl('referrals/' + _activeRefId + '/detail'), function(data) {
                buildAndPrintReferralLetter(data);
            });
        });

        // Quick accept from table row
        $(document).on('click', '.btn-accept-ref', function() {
            var refId = $(this).data('id');
            _pendingActionId = refId;
            _pendingActionSource = 'quickAccept';
            _pendingActionBtn = $(this);
            $('#confirmAcceptRefModal').modal('show');
        });

        // Quick decline from table row
        $(document).on('click', '.btn-decline-ref', function() {
            var refId = $(this).data('id');
            _pendingActionId = refId;
            _pendingActionSource = 'quickDecline';
            _pendingActionBtn = $(this);
            $('#declineRefReason').val('');
            $('#declineRefReasonModal').modal('show');
        });

        // ═══════════════════════════════════════════════════════════════
        //  Modal Confirm Handlers (Accept & Decline Referral)
        // ═══════════════════════════════════════════════════════════════

        // Confirm accept referral (from quick-accept button)
        $(document).on('click', '#confirmAcceptRefBtn', function() {
            var refId = _pendingActionId;
            $('#confirmAcceptRefModal').modal('hide');
            var $btn = _pendingActionBtn;
            if ($btn) $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.post(wbUrl('referrals/' + refId + '/accept'), {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')}, function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Referral accepted');
                    if ($.fn.DataTable.isDataTable('#my_referrals_list')) $('#my_referrals_list').DataTable().ajax.reload(null, false);
                    if ($.fn.DataTable.isDataTable('#all_referrals_list')) $('#all_referrals_list').DataTable().ajax.reload(null, false);
                    if (res.encounter_url) window.open(res.encounter_url, '_blank');
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed');
                if ($btn) $btn.prop('disabled', false).html('<i class="mdi mdi-check-circle"></i>');
            });
        });

        // Confirm decline referral (from both refDetail and quick-decline)
        $(document).on('click', '#confirmDeclineRefBtn', function() {
            var refId = _pendingActionId;
            var reason = $('#declineRefReason').val();
            if (!reason || !reason.trim()) {
                $('#declineRefReason').addClass('is-invalid');
                return;
            }
            $('#declineRefReason').removeClass('is-invalid');
            $('#declineRefReasonModal').modal('hide');

            var $btn = _pendingActionBtn || $('#refDetailDeclineBtn');
            $btn.prop('disabled', true);
            $.post(wbUrl('referrals/' + refId + '/decline'), {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || ''),
                reason: reason
            }, function(res) {
                if (res.success) {
                    toastr.success('Referral declined');
                    if ($.fn.DataTable.isDataTable('#my_referrals_list')) $('#my_referrals_list').DataTable().ajax.reload(null, false);
                    if ($.fn.DataTable.isDataTable('#all_referrals_list')) $('#all_referrals_list').DataTable().ajax.reload(null, false);
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to decline');
            }).always(function() {
                $btn.prop('disabled', false);
                if (_pendingActionSource === 'refDetail') {
                    $btn.html('<i class="mdi mdi-close-circle me-1"></i>Decline');
                }
            });
        });

        /**
         * Build and print A4 referral letter (for external referrals).
         */
        function buildAndPrintReferralLetter(data) {
            var ref = data.referral;
            var hospital = data.hospital || {};
            var printHtml = '';
            printHtml += '<div class="referral-letter">';
            printHtml += '<div class="letter-header">';
            if (hospital.logo) printHtml += '<img src="' + hospital.logo + '" style="max-height:60px;" alt="Logo"><br>';
            printHtml += '<h3 style="margin:5px 0;">' + (hospital.name || '') + '</h3>';
            printHtml += '<small>' + (hospital.address || '') + '</small><br>';
            printHtml += '<small>Tel: ' + (hospital.phones || '') + ' | Email: ' + (hospital.email || '') + '</small>';
            printHtml += '</div>';
            printHtml += '<h4 style="text-align:center;margin:15px 0;">REFERRAL LETTER</h4>';
            printHtml += '<table class="patient-info-table" style="width:100%;margin-bottom:15px;">';
            printHtml += '<tr><td style="width:50%;"><strong>Patient:</strong> ' + (ref.patient_name || '') + '</td><td><strong>File No:</strong> ' + (ref.patient_file_no || '') + '</td></tr>';
            printHtml += '<tr><td><strong>Date:</strong> ' + (ref.created_at || '') + '</td><td><strong>Urgency:</strong> ' + (ref.urgency || '') + '</td></tr>';
            printHtml += '</table>';
            printHtml += '<p><strong>Referred To:</strong> ' + (ref.external_facility_name || 'External Facility') + '</p>';
            if (ref.external_doctor_name) printHtml += '<p><strong>Attention:</strong> Dr. ' + ref.external_doctor_name + '</p>';
            printHtml += '<p><strong>Reason:</strong> ' + (ref.reason || '') + '</p>';
            if (ref.provisional_diagnosis) printHtml += '<p><strong>Diagnosis:</strong> ' + ref.provisional_diagnosis + '</p>';
            if (ref.clinical_summary) printHtml += '<p><strong>Clinical Summary:</strong> ' + ref.clinical_summary + '</p>';
            printHtml += '<div style="margin-top:40px;"><p>Referring Doctor: ________________</p>';
            printHtml += '<p>' + (ref.referring_doctor || '') + '</p></div>';
            printHtml += '</div>';

            var printWin = window.open('', '_blank', 'width=800,height=1000');
            printWin.document.write('<html><head><title>Referral Letter</title></head><body>');
            printWin.document.write(printHtml);
            printWin.document.write('</body></html>');
            printWin.document.close();
            printWin.print();
        }

        // ═══════════════════════════════════════════════════════════════
        //  Doctor Reassignment
        // ═══════════════════════════════════════════════════════════════
        $(document).on('click', '.btn-reassign-queue', function() {
            var apptId = $(this).data('id');
            var patientName = $(this).data('patient') || 'Patient';
            var clinicId = $(this).data('clinic') || 0;
            openReassignModal(apptId, patientName, clinicId);
        });

        $(document).on('click', '.btn-reschedule-queue', function() {
            var apptId = $(this).data('id');
            var patientName = $(this).data('patient') || 'Patient';
            var clinicId = $(this).data('clinic') || 0;
            var doctorId = $(this).data('doctor') || 0;
            openRescheduleModal(apptId, patientName, clinicId, doctorId, '', 0);
        });

        function openReassignModal(appointmentId, patientName, clinicId) {
            $('#doc-reassign-appt-id').val(appointmentId);
            $('#doc-reassign-patient-name').text(patientName);
            $('#doc-reassign-doctor').empty().append('<option value="">Loading doctors...</option>');
            $('#doc-reassign-reason').val('');
            $('#docReassignDoctorModal').modal('show');

            $.get(wbUrl('reception/clinics/' + clinicId + '/doctors'), function(doctors) {
                var $sel = $('#doc-reassign-doctor');
                $sel.empty().append('<option value="">-- Select Doctor --</option>');
                if (doctors && doctors.length) {
                    doctors.forEach(function(doc) {
                        $sel.append('<option value="' + doc.id + '">' + doc.name + '</option>');
                    });
                }
            }).fail(function() {
                $('#doc-reassign-doctor').empty().append('<option value="">Error loading doctors</option>');
            });
        }

        $(document).on('submit', '#doc-reassign-form', function(e) {
            e.preventDefault();
            var apptId = $('#doc-reassign-appt-id').val();
            var doctorId = $('#doc-reassign-doctor').val();
            if (!doctorId) { toastr.warning('Please select a doctor.'); return; }
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Reassigning...');

            $.ajax({
                url: wbRoute('appointments.reassign', '/appointments/reassign').replace('__AID__', apptId),
                type: 'POST',
                data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || ''), doctor_id: doctorId, reason: $('#doc-reassign-reason').val() },
                success: function(res) {
                    if (res.success) { toastr.success(res.message || 'Doctor reassigned successfully.'); $('#docReassignDoctorModal').modal('hide'); refreshAll(); }
                    else { toastr.error(res.message || 'Reassignment failed.'); }
                },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Reassignment failed.'); },
                complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-account-switch"></i> Reassign'); }
            });
        });

        // ═══════════════════════════════════════════════════════════════
        //  Reschedule Appointment (Doctor Page)
        // ═══════════════════════════════════════════════════════════════
        var docReschClinicId = null;
        var docReschDoctorId = null;

        function openRescheduleModal(appointmentId, patientName, clinicId, doctorId, currentDate, rescheduleCount) {
            docReschClinicId = clinicId;
            docReschDoctorId = doctorId;
            $('#doc-reschedule-appt-id').val(appointmentId);
            $('#doc-reschedule-patient-name').text(patientName);
            $('#doc-reschedule-original-date').text(currentDate ? moment(currentDate).format('YYYY-MM-DD') : '');
            $('#doc-reschedule-count-info').text('Reschedule #' + (parseInt(rescheduleCount || 0) + 1));
            $('#doc-reschedule-date').val('');
            $('#doc-reschedule-time').empty().append('<option value="">-- Select date first --</option>').removeClass('d-none');
            $('#doc-reschedule-custom-time-input').addClass('d-none').val('');
            $('#doc-reschedule-custom-time-toggle').prop('checked', false);
            $('#doc-reschedule-reason').val('');
            $('#docRescheduleAppointmentModal').modal('show');
        }

        $(document).on('change', '#doc-reschedule-date', function() {
            if ($('#doc-reschedule-custom-time-toggle').is(':checked')) return;
            var date = $(this).val();
            if (!date || !docReschClinicId) return;
            var $sel = $('#doc-reschedule-time');
            $sel.empty().append('<option value="">Loading...</option>');
            $.get(wbRoute('appointments.available-slots', '/appointments/available-slots'), { date: date, clinic_id: docReschClinicId, doctor_id: docReschDoctorId }, function(resp) {
                $sel.empty().append('<option value="">-- Select Time --</option>');
                if (resp.success && resp.slots && resp.slots.length) {
                    var hasSlots = false;
                    resp.slots.forEach(function(slot) {
                        if (slot.available) { hasSlots = true; $sel.append('<option value="' + slot.time + '">' + slot.time + '</option>'); }
                    });
                    if (!hasSlots) $sel.append('<option value="" disabled>No slots — use custom time</option>');
                } else {
                    $sel.append('<option value="" disabled>No slots configured — use custom time</option>');
                }
            });
        });

        $(document).on('change', '#doc-reschedule-custom-time-toggle', function() {
            var isCustom = $(this).is(':checked');
            if (isCustom) {
                $('#doc-reschedule-time').addClass('d-none');
                $('#doc-reschedule-custom-time-input').removeClass('d-none');
            } else {
                $('#doc-reschedule-custom-time-input').addClass('d-none').val('');
                $('#doc-reschedule-time').removeClass('d-none');
                if ($('#doc-reschedule-date').val()) $('#doc-reschedule-date').trigger('change');
            }
        });

        $(document).on('submit', '#doc-reschedule-form', function(e) {
            e.preventDefault();
            var apptId = $('#doc-reschedule-appt-id').val();
            var isCustomTime = $('#doc-reschedule-custom-time-toggle').is(':checked');
            var startTime = isCustomTime ? $('#doc-reschedule-custom-time-input').val() : $('#doc-reschedule-time').val();
            if (!startTime) { toastr.warning('Please select or enter a time.'); return; }
            var $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Rescheduling...');

            $.ajax({
                url: wbRoute('appointments.reschedule', '/appointments/reschedule').replace('__AID__', apptId),
                type: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || ''),
                    appointment_date: $('#doc-reschedule-date').val(),
                    start_time: startTime,
                    custom_time: isCustomTime ? 1 : 0,
                    reason: $('#doc-reschedule-reason').val()
                },
                success: function(res) {
                    if (res.success) { toastr.success(res.message || 'Appointment rescheduled.'); $('#docRescheduleAppointmentModal').modal('hide'); refreshAll(); }
                    else { toastr.error(res.message || 'Could not reschedule.'); }
                },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Reschedule failed.'); },
                complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-calendar-edit"></i> Reschedule'); }
            });
        });

        // ═══════════════════════════════════════════════════════════════
        //  Mini-Timers
        // ═══════════════════════════════════════════════════════════════
        function initMiniTimers() {
            $('.mini-timer[data-started]').each(function() {
                var $el = $(this);
                if ($el.data('timer-init')) return;
                $el.data('timer-init', true);

                var startedAt = new Date($el.data('started'));
                var pausedSeconds = parseInt($el.data('paused-seconds')) || 0;
                var isPaused = $el.data('is-paused') == true || $el.data('is-paused') === 'true' || $el.data('is-paused') == 1;
                var lastPausedAt = $el.data('last-paused-at') ? new Date($el.data('last-paused-at')) : null;

                if (isPaused) $el.addClass('timer-paused');

                setInterval(function() {
                    if (isPaused) return;
                    var now = new Date();
                    var total = Math.floor((now - startedAt) / 1000) - pausedSeconds;
                    if (isPaused && lastPausedAt) {
                        total -= Math.floor((now - lastPausedAt) / 1000);
                    }
                    total = Math.max(0, total);
                    var h = Math.floor(total / 3600);
                    var m = Math.floor((total % 3600) / 60);
                    var s = total % 60;
                    $el.find('.timer-value').text(
                        String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0')
                    );
                }, 1000);
            });
        }

    });
