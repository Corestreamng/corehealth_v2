@extends('admin.layouts.app')
@section('title', 'Consultations Categories')
@section('page_name', 'Consultations')
@section('subpage_name', 'Categories')
@push('styles')
    <link rel="stylesheet" href="{{ asset('plugins/fullcalendar/fullcalendar.min.css') }}">
    <style>
        .doctor-calendar-toggle .btn { font-size: 0.8rem; padding: 4px 12px; }
        .doctor-calendar-toggle .btn.active { font-weight: 600; }
        .doctor-calendar-legend { display: flex; gap: 12px; flex-wrap: wrap; }
        .doctor-calendar-legend .legend-item { display: flex; align-items: center; gap: 4px; font-size: 0.75rem; }
        .doctor-calendar-legend .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        #doctor-appointments-calendar .fc-event { cursor: pointer; border-radius: 3px; padding: 1px 4px; font-size: 0.78rem; }
        #doctor-appointments-calendar .fc-event .fc-time { font-weight: 600; }
        .doctor-appt-popover { max-width: 280px; }
        .doctor-appt-popover .popover-body { font-size: 0.82rem; }
    </style>
@endpush
@section('content')
    <div class="card-modern mb-2">
        <div class="card-body">
            <form id="dateRangeForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" class="form-control form-control-sm" id="start_date" name="start_date"
                                value="{{ date('Y-m-d', strtotime('-1 day')) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" class="form-control form-control-sm" id="end_date" name="end_date"
                                value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" id="fetchData" class="btn btn-primary btn-sm d-block">
                                Fetch Data
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="new_tab" data-bs-toggle="tab" data-bs-target="#new" type="button"
                role="tab" aria-controls="new" aria-selected="true">New <span class="badge bg-primary ms-1" id="badge_new">0</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cont_data_tab" data-bs-toggle="tab" data-bs-target="#cont" type="button"
                role="tab" aria-controls="cont_data" aria-selected="false">Continuing <span class="badge bg-info ms-1" id="badge_cont">0</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="scheduled_tab" data-bs-toggle="tab" data-bs-target="#scheduled" type="button"
                role="tab" aria-controls="scheduled" aria-selected="false">
                <i class="mdi mdi-calendar-clock me-1"></i>Scheduled <span class="badge bg-purple ms-1" id="badge_scheduled">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prev_data_tab" data-bs-toggle="tab" data-bs-target="#prev" type="button"
                role="tab" aria-controls="prev_data" aria-selected="false">Previous</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="my_admissions_tab" data-bs-toggle="tab" data-bs-target="#my_admissions"
                type="button" role="tab" aria-controls="my_admissions" aria-selected="false">My admissions</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="other_admissions_tab" data-bs-toggle="tab" data-bs-target="#other_admissions"
                type="button" role="tab" aria-controls="other_admissions" aria-selected="false">Other
                admissions</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="new" role="tabpanel" aria-labelledby="new_tab">
            <div class="card-modern mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="new_consult_list" style="width: 100%">
                        <thead>
                            <th>#</th>
                            <th>Patient Name</th>
                            <th>File No</th>
                            <th>Priority</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>HMO/Insurance</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Action</th>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="cont" role="tabpanel" aria-labelledby="cont_tab">
            <div class="card-modern mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="cont_consult_list" style="width: 100%">
                        <thead>
                            <th>#</th>
                            <th>Patient Name</th>
                            <th>File No</th>
                            <th>Priority</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>HMO/Insurance</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Action</th>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="scheduled" role="tabpanel" aria-labelledby="scheduled_tab">
            {{-- Toggle + Legend row --}}
            <div class="d-flex justify-content-between align-items-center mt-2 mb-2 px-2">
                <div class="btn-group btn-group-sm doctor-calendar-toggle" role="group">
                    <button type="button" class="btn btn-outline-secondary active" id="doctor-sched-table-btn">
                        <i class="mdi mdi-table"></i> Table
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="doctor-sched-calendar-btn">
                        <i class="mdi mdi-calendar"></i> Calendar
                    </button>
                </div>
                <div class="doctor-calendar-legend" id="doctor-calendar-legend" style="display:none;">
                    <span class="legend-item"><span class="legend-dot" style="background:#7c3aed"></span> Scheduled</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#ffc107"></span> Waiting</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#17a2b8"></span> Vitals</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#28a745"></span> Ready</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#007bff"></span> In Consultation</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#6c757d"></span> Completed</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#dc3545"></span> Cancelled</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#fd7e14"></span> No-Show</span>
                </div>
            </div>

            {{-- Table view (default) --}}
            <div id="doctor-sched-table-wrapper">
                <div class="card-modern">
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-bordered table-striped" id="scheduled_consult_list" style="width: 100%">
                            <thead>
                                <th>#</th>
                                <th>Patient Name</th>
                                <th>File No</th>
                                <th>Appointment Date</th>
                                <th>Time Slot</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Clinic</th>
                                <th>Reason</th>
                                <th>Action</th>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Calendar view (hidden by default) --}}
            <div id="doctor-sched-calendar-wrapper" style="display:none;">
                <div class="card-modern">
                    <div class="card-body">
                        <div id="doctor-appointments-calendar" style="min-height: 550px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="prev" role="tabpanel" aria-labelledby="prev_tab">
            <div class="card-modern mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="prev_consult_list"
                        style="width: 100%">
                        <thead>
                            <th>#</th>
                            <th>Patient Name</th>
                            <th>File No</th>
                            <th>HMO/Insurance</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Delivery</th>
                            <th>Action</th>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="my_admissions" role="tabpanel" aria-labelledby="my_admissions_tab">
            <div class="card-modern mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="my_admissions_list"
                        style="width: 100%">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Patient</th>
                                <th>File No</th>
                                <th>HMO/Insurance </th>
                                <th>HMO No</th>
                                <th>Requested By</th>
                                <th>Bills</th>
                                <th>Bed</th>
                                <th>View</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="other_admissions" role="tabpanel" aria-labelledby="other_admissions_tab">
            <div class="card-modern mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="other_admissions_list"
                        style="width: 100%">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Patient</th>
                                <th>File No</th>
                                <th>HMO/Insurance </th>
                                <th>HMO No</th>
                                <th>Requested By</th>
                                <th>Bills</th>
                                <th>Bed</th>
                                <th>View</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script src="{{ asset('plugins/daterangepicker/moment.js') }}" defer></script>
    <script src="{{ asset('plugins/fullcalendar/fullcalendar.min.js') }}" defer></script>
    <style>
        .badge.bg-purple { background-color: #7c3aed !important; color: #fff; }
        .source-badge { font-size: 0.7rem; padding: 2px 6px; }
        .mini-timer { font-family: 'Courier New', monospace; font-size: 0.75rem; }
        .mini-timer.timer-paused { animation: pulse 1.5s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
    </style>
    <script>
        // ─── Column definitions ────────────────────────────────────────
        function getColumns(selector) {
            if (selector === '#my_admissions_list' || selector === '#other_admissions_list') {
                return [
                    { data: "DT_RowIndex", name: "DT_RowIndex" },
                    { data: "patient", name: "patient" },
                    { data: "file_no", name: "file_no" },
                    { data: "hmo", name: "hmo" },
                    { data: "hmo_no", name: "hmo_no" },
                    { data: "doctor_id", name: "doctor_id" },
                    { data: "billed_by", name: "billed_by" },
                    { data: "bed_id", name: "bed_id" },
                    { data: "show", name: "show" }
                ];
            }

            if (selector === '#scheduled_consult_list') {
                return [
                    { data: "DT_RowIndex", name: "DT_RowIndex" },
                    { data: "patient_name", name: "patient_name" },
                    { data: "file_no", name: "file_no" },
                    { data: "appointment_date", name: "appointment_date" },
                    { data: "time_slot", name: "time_slot" },
                    { data: "priority", name: "priority" },
                    { data: "status", name: "status" },
                    { data: "clinic", name: "clinic" },
                    { data: "reason", name: "reason" },
                    { data: "action", name: "action", orderable: false, searchable: false }
                ];
            }

            if (selector === '#prev_consult_list') {
                return [
                    { data: "DT_RowIndex", name: "DT_RowIndex" },
                    { data: "fullname", name: "fullname" },
                    { data: "file_no", name: "file_no" },
                    { data: "hmo_id", name: "hmo_id" },
                    { data: "clinic_id", name: "clinic_id" },
                    { data: "staff_id", name: "staff_id" },
                    { data: "created_at", name: "created_at" },
                    { data: "delivery_status", name: "delivery_status" },
                    { data: "view", name: "view" }
                ];
            }

            // New + Continuing tabs: include Source and Status columns
            return [
                { data: "DT_RowIndex", name: "DT_RowIndex" },
                { data: "fullname", name: "fullname" },
                { data: "file_no", name: "file_no" },
                { data: "priority", name: "priority" },
                { data: "source", name: "source", orderable: false },
                { data: "status_badge", name: "status_badge", orderable: false },
                { data: "hmo_id", name: "hmo_id" },
                { data: "clinic_id", name: "clinic_id" },
                { data: "staff_id", name: "staff_id" },
                { data: "created_at", name: "created_at" },
                { data: "view", name: "view" }
            ];
        }

        // ─── DataTable initializer ─────────────────────────────────────
        function initializeDataTable(selector, ajaxUrl) {
            if ($.fn.DataTable.isDataTable(selector)) {
                $(selector).DataTable().ajax.reload(null, false);
                return;
            }
            $(selector).DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": ajaxUrl,
                    "type": "GET",
                    "data": function(d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                "columns": getColumns(selector),
                "paging": true,
                "drawCallback": function() {
                    // Start mini-timers for in-consultation entries
                    initMiniTimers();
                }
            });
        }

        // ─── Initialize all DataTables ─────────────────────────────────
        $(function() {
            initializeDataTable('#new_consult_list', "{{ url('NewEncounterList') }}");
            initializeDataTable('#cont_consult_list', "{{ url('ContEncounterList') }}");
            initializeDataTable('#prev_consult_list', "{{ url('PrevEncounterList') }}");
            initializeDataTable('#scheduled_consult_list', "{{ route('appointments.doctor.list') }}");
            initializeDataTable('#my_admissions_list', "{{ route('my-admission-requests-list') }}");
            initializeDataTable('#other_admissions_list', "{{ route('admission-requests-list') }}");

            // Load initial badge counts
            loadDoctorQueueCounts();

            // ── Auto-Refresh (30s) ────────────────────────────────────
            setInterval(function() {
                // Reload the active tab's DataTable
                var activeTabId = $('#myTab .nav-link.active').attr('id');
                var tableMap = {
                    'new_tab': '#new_consult_list',
                    'cont_data_tab': '#cont_consult_list',
                    'prev_data_tab': '#prev_consult_list',
                    'scheduled_tab': '#scheduled_consult_list',
                    'my_admissions_tab': '#my_admissions_list',
                    'other_admissions_tab': '#other_admissions_list'
                };
                var tableSelector = tableMap[activeTabId];
                if (tableSelector && $.fn.DataTable.isDataTable(tableSelector)) {
                    $(tableSelector).DataTable().ajax.reload(null, false);
                }
                // Also refresh calendar if on scheduled tab in calendar mode
                if (activeTabId === 'scheduled_tab' && doctorCalendarInitialized && $('#doctor-sched-calendar-wrapper').is(':visible')) {
                    refreshDoctorCalendar();
                }
                // Refresh badge counts
                loadDoctorQueueCounts();
            }, 30000);
        });

        // ─── Refresh all DataTables ────────────────────────────────────
        function refreshDataTables() {
            var tables = ['#new_consult_list', '#cont_consult_list', '#prev_consult_list', '#scheduled_consult_list', '#my_admissions_list', '#other_admissions_list'];
            tables.forEach(function(selector) {
                if ($.fn.DataTable.isDataTable(selector)) {
                    $(selector).DataTable().ajax.reload(null, false);
                }
            });
            loadDoctorQueueCounts();
        }

        // ─── Fetch Data button ─────────────────────────────────────────
        $('#fetchData').on('click', function() {
            refreshDataTables();
        });

        // ─── Load Doctor Queue Counts (tab badges) ────────────────────
        function loadDoctorQueueCounts() {
            $.ajax({
                url: "{{ route('appointments.doctor.queue-counts') }}",
                type: 'GET',
                success: function(counts) {
                    $('#badge_new').text(counts.waiting || 0);
                    $('#badge_cont').text(counts.vitals_pending || 0);
                    $('#badge_scheduled').text(counts.scheduled || 0);
                },
                error: function() {
                    // Silently fail on count refresh
                }
            });
        }

        // ─── Doctor Scheduled Calendar ───────────────────────────────
        var doctorCalendarInitialized = false;

        // Toggle between table and calendar views
        $('#doctor-sched-table-btn').on('click', function() {
            $(this).addClass('active');
            $('#doctor-sched-calendar-btn').removeClass('active');
            $('#doctor-sched-table-wrapper').show();
            $('#doctor-sched-calendar-wrapper').hide();
            $('#doctor-calendar-legend').hide();
        });

        $('#doctor-sched-calendar-btn').on('click', function() {
            $(this).addClass('active');
            $('#doctor-sched-table-btn').removeClass('active');
            $('#doctor-sched-table-wrapper').hide();
            $('#doctor-sched-calendar-wrapper').show();
            $('#doctor-calendar-legend').show();
            if (!doctorCalendarInitialized) {
                initDoctorCalendar();
                doctorCalendarInitialized = true;
            }
        });

        function initDoctorCalendar() {
            $('#doctor-appointments-calendar').fullCalendar({
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                defaultView: 'agendaWeek',
                allDaySlot: false,
                slotDuration: '00:15:00',
                minTime: '07:00:00',
                maxTime: '21:00:00',
                height: 'auto',
                editable: false,
                eventLimit: true,
                events: function(start, end, timezone, callback) {
                    $.ajax({
                        url: "{{ route('appointments.calendar-events') }}",
                        type: 'GET',
                        data: {
                            start: start.format('YYYY-MM-DD'),
                            end: end.format('YYYY-MM-DD'),
                            doctor_id: '{{ optional(\App\Models\Staff::where("user_id", auth()->id())->first())->id ?? "" }}'
                        },
                        success: function(events) {
                            callback(events);
                        },
                        error: function() {
                            callback([]);
                        }
                    });
                },
                eventRender: function(event, element) {
                    // Priority indicator
                    if (event.priority === 'urgent' || event.priority === 'emergency') {
                        element.find('.fc-content').prepend('<i class="mdi mdi-alert-circle" style="color:#fff;margin-right:3px;"></i>');
                    }

                    // Tooltip
                    var tooltip = '<strong>' + event.patient_name + '</strong><br>' +
                        '<i class="mdi mdi-card-account-details"></i> ' + (event.file_no || '') + '<br>' +
                        '<i class="mdi mdi-hospital-building"></i> ' + (event.clinic || '') + '<br>' +
                        '<i class="mdi mdi-tag"></i> ' + (event.status_label || '') + '<br>' +
                        '<i class="mdi mdi-clock"></i> ' + moment(event.start).format('h:mm A');

                    element.attr('data-toggle', 'popover')
                        .attr('data-html', 'true')
                        .attr('data-trigger', 'hover')
                        .attr('data-placement', 'top')
                        .attr('data-content', tooltip)
                        .attr('data-container', 'body');

                    element.popover({ container: 'body' });
                },
                eventClick: function(event, jsEvent, view) {
                    // Remove any existing popovers
                    $('.popover').remove();
                    // Alert or action on click
                    var info = 'Patient: ' + event.patient_name + '\n' +
                        'File No: ' + (event.file_no || 'N/A') + '\n' +
                        'Clinic: ' + (event.clinic || 'N/A') + '\n' +
                        'Status: ' + (event.status_label || 'N/A') + '\n' +
                        'Time: ' + moment(event.start).format('h:mm A');
                    // For now, just show the info. Can wire to check-in etc later.
                    toastr.info(info.replace(/\n/g, '<br>'), 'Appointment Details', { timeOut: 5000, closeButton: true, progressBar: true });
                },
                viewRender: function(view) {
                    // Close any lingering popovers on view change
                    $('.popover').remove();
                }
            });
        }

        function refreshDoctorCalendar() {
            if (doctorCalendarInitialized) {
                $('#doctor-appointments-calendar').fullCalendar('refetchEvents');
            }
        }

        // ─── Mini-Timers for In-Consultation queue entries ─────────────
        function initMiniTimers() {
            $('.mini-timer[data-started]').each(function() {
                var $el = $(this);
                if ($el.data('timer-init')) return; // already initialized
                $el.data('timer-init', true);

                var startedAt = new Date($el.data('started'));
                var pausedSeconds = parseInt($el.data('paused-seconds')) || 0;
                var isPaused = $el.data('is-paused') == true || $el.data('is-paused') === 'true' || $el.data('is-paused') == 1;
                var lastPausedAt = $el.data('last-paused-at') ? new Date($el.data('last-paused-at')) : null;

                if (isPaused) {
                    $el.addClass('timer-paused');
                }

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
    </script>

@endsection
