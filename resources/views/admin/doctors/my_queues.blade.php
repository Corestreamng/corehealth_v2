@extends('admin.layouts.app')
@section('title', 'My Queue')
@section('page_name', 'Consultations')
@section('subpage_name', 'My Queue')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/fullcalendar/fullcalendar.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/queue-status.css') }}">
    <style>
        /* ── Full-screen layout ───────────────────────────────────────── */
        .content-wrapper { padding: 0 !important; }
        .full-screen-tabs { padding: 0 10px; }
        .full-screen-tabs .tab-content { padding: 0; }
        .full-screen-tabs .nav-tabs {
            background: #fff; padding: 0 10px; border-bottom: 2px solid #dee2e6;
            position: sticky; top: 0; z-index: 50;
        }
        .full-screen-tabs .nav-tabs .nav-link {
            font-weight: 600; font-size: 0.95rem; padding: 12px 24px;
            border: none; border-bottom: 3px solid transparent; color: #6c757d;
        }
        .full-screen-tabs .nav-tabs .nav-link.active {
            color: #0d6efd; border-bottom-color: #0d6efd; background: transparent;
        }
        .full-screen-tabs .nav-tabs .nav-link:hover:not(.active) { color: #495057; }
        .tab-badge { font-size: 0.68rem; vertical-align: middle; margin-left: 4px; }

        /* ── Status Pills ─────────────────────────────────────────────── */
        .status-pill-bar {
            display: flex; gap: 6px; flex-wrap: wrap; align-items: center;
        }
        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px; font-size: 0.78rem;
            font-weight: 500; cursor: pointer; border: 2px solid transparent;
            transition: all 0.2s ease; user-select: none; white-space: nowrap;
        }
        .status-pill:hover { opacity: 0.85; transform: translateY(-1px); }
        .status-pill.active { box-shadow: 0 2px 8px rgba(0,0,0,0.18); transform: translateY(-1px); }
        .status-pill .pill-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .status-pill .pill-count {
            background: rgba(255,255,255,0.3); padding: 0 6px; border-radius: 10px;
            font-size: 0.7rem; font-weight: 700; min-width: 20px; text-align: center;
        }
        .status-pill-all          { background: #f1f3f5; color: #495057; }
        .status-pill-all.active   { background: #495057; color: #fff; border-color: #343a40; }
        .status-pill-waiting      { background: #fff3cd; color: #856404; }
        .status-pill-waiting.active { background: #ffc107; color: #212529; border-color: #e0a800; }
        .status-pill-vitals       { background: #d1ecf1; color: #0c5460; }
        .status-pill-vitals.active { background: #17a2b8; color: #fff; border-color: #138496; }
        .status-pill-ready        { background: #cfe2ff; color: #084298; }
        .status-pill-ready.active { background: #0d6efd; color: #fff; border-color: #0a58ca; }
        .status-pill-consult      { background: #d1e7dd; color: #0f5132; }
        .status-pill-consult.active { background: #198754; color: #fff; border-color: #146c43; }
        .status-pill-scheduled    { background: #e8daef; color: #5b2c6f; }
        .status-pill-scheduled.active { background: #6f42c1; color: #fff; border-color: #59359a; }
        .status-pill-completed    { background: #e9ecef; color: #495057; }
        .status-pill-completed.active { background: #6c757d; color: #fff; border-color: #5a6268; }

        /* ── View Toggle ──────────────────────────────────────────────── */
        .view-toggle .btn { font-size: 0.8rem; padding: 4px 14px; }
        .view-toggle .btn.active { font-weight: 600; }

        /* ── Calendar Styles ──────────────────────────────────────────── */
        #unified-calendar .fc-event {
            cursor: pointer; border-radius: 4px; padding: 2px 5px;
            font-size: 0.78rem; border-left-width: 4px !important;
        }
        #unified-calendar .fc-event .fc-time { font-weight: 600; }
        #unified-calendar .fc-event.event-emergency { border-left: 4px solid #dc3545 !important; }
        #unified-calendar .fc-event .event-icon { margin-right: 3px; font-size: 0.7rem; }
        .fc-event-popover { max-width: 300px; z-index: 9999; }
        .fc-event-popover .popover-body { font-size: 0.82rem; padding: 8px 12px; }
        .fc-event-popover .pop-row { margin-bottom: 3px; }
        .fc-event-popover .pop-label { color: #888; font-size: 0.72rem; }

        /* ── Table Styles ────────────────────────────────────────────── */
        .badge.bg-purple { background-color: #7c3aed !important; color: #fff; }
        .badge.bg-purple-subtle { background-color: #ede9fe !important; }
        .text-purple { color: #7c3aed !important; }
        .source-badge { font-size: 0.7rem; padding: 2px 6px; }
        .mini-timer { font-family: 'Courier New', monospace; font-size: 0.75rem; }
        .mini-timer.timer-paused { animation: pulse 1.5s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        /* ── Event Context Menu ───────────────────────────────────────── */
        .event-context-menu {
            position: fixed; z-index: 10000; background: #fff;
            border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.18);
            min-width: 200px; padding: 6px 0; display: none;
        }
        .event-context-menu .ctx-item {
            display: flex; align-items: center; gap: 8px; padding: 8px 16px;
            cursor: pointer; font-size: 0.82rem; color: #333; transition: background 0.15s;
        }
        .event-context-menu .ctx-item:hover { background: #f0f4ff; }
        .event-context-menu .ctx-item i { width: 18px; text-align: center; }
        .event-context-menu .ctx-divider { border-top: 1px solid #eee; margin: 4px 0; }

        /* ── Quick-Stat Header ────────────────────────────────────────── */
        .queue-stats-row {
            display: flex; gap: 8px; flex-wrap: wrap;
        }
        .queue-stat-card {
            flex: 1; min-width: 90px; text-align: center; padding: 10px 8px;
            border-radius: 8px; background: #f8f9fa; border: 1px solid #e9ecef;
        }
        .queue-stat-card .stat-num { font-size: 1.4rem; font-weight: 700; line-height: 1; }
        .queue-stat-card .stat-label { font-size: 0.68rem; color: #6c757d; margin-top: 2px; }

        /* ── History tab inner tabs ───────────────────────────────────── */
        .history-inner-tabs .nav-link { font-size: 0.85rem; padding: 8px 18px; }

        /* ── MOBILE LAYOUT ────────────────────────────────────────────── */
        @media (max-width: 767.98px) {
            /* ── Compact Stats Strip ── */
            .queue-stats-row {
                flex-wrap: nowrap;
                overflow-x: auto;
                gap: 8px;
                padding: 4px 2px 8px;
                -webkit-overflow-scrolling: touch;
            }
            .queue-stats-row::-webkit-scrollbar { display: none; }
            .queue-stat-card {
                min-width: 80px;
                flex: 0 0 auto;
                padding: 8px 10px;
                background: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 10px !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            }
            .queue-stat-card .stat-num { font-size: 1.15rem; font-weight: 800; }
            .queue-stat-card .stat-label { font-size: 0.62rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }

            /* ── Scrollable Pill Bar ── */
            .status-pill-bar {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 6px;
                gap: 6px;
                -webkit-overflow-scrolling: touch;
            }
            .status-pill-bar::-webkit-scrollbar { display: none; }

            /* ── Hide view toggle on mobile ── */
            .view-toggle { display: none !important; }

            /* ── Hide DataTable button toolbar on mobile ── */
            .dataTables_wrapper .dt-buttons { display: none !important; }

            /* ── Add padding to prevent fixed footer blocking last card ── */
            .dataTables_wrapper, #history-lists-pane {
                margin-bottom: 120px !important;
            }

            /* ── Search Input Styling ── */
            .dataTables_wrapper .dataTables_filter {
                width: 100%;
                float: none !important;
                text-align: left !important;
                margin-bottom: 12px;
            }
            .dataTables_wrapper .dataTables_filter label {
                width: 100%;
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
                color: #64748b;
                font-size: 0.82rem;
            }
            .dataTables_wrapper .dataTables_filter input {
                width: 100% !important;
                min-width: 0 !important;
                border-radius: 10px !important;
                border: 1px solid #cbd5e1 !important;
                padding: 8px 12px !important;
                font-size: 0.85rem !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }

            /* ── Tab bar compact ── */
            #mainDoctorTabs .nav-link { font-size: 0.8rem; padding: 8px 14px; font-weight: 600; }

            /* ── Mobile Queue Card Responsive ── */
            .queue-card {
                border-radius: 16px;
                padding: 14px;
                gap: 10px;
            }
            .queue-card-header {
                flex-wrap: wrap;
                gap: 10px;
            }
            .queue-card-avatar {
                width: 38px;
                height: 38px;
                font-size: 0.95rem;
            }
            .queue-card-name {
                font-size: 0.95rem;
            }
            .queue-card-demo {
                font-size: 0.78rem;
            }
            .queue-card-meta {
                font-size: 0.78rem;
                gap: 6px;
            }
            .queue-card-badges {
                flex-direction: row;
                align-items: center;
                gap: 4px;
                flex-wrap: wrap;
            }
            .queue-card-badges .badge {
                font-size: 0.65rem;
                padding: 3px 8px;
            }
            .queue-card-details {
                flex-direction: column;
                gap: 4px;
            }
            .queue-card-detail-item {
                font-size: 0.8rem;
            }
            .queue-card-reason {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            .queue-card-actions {
                flex-direction: column;
                gap: 8px;
                padding-top: 10px;
            }
            .queue-card-action-btn {
                width: 100% !important;
                text-align: center !important;
                padding: 10px 16px !important;
                font-size: 0.85rem !important;
            }
            .queue-card-secondary-actions {
                width: 100%;
                justify-content: stretch;
            }
            .queue-card-secondary-actions .btn {
                flex: 1;
                justify-content: center;
                font-size: 0.75rem;
                padding: 6px 8px !important;
            }
        } /* End of @media (max-width: 767.98px) */

        /* ── Card-based DataTables Global Flex Layout ── */
            .card-datatable {
                width: 100% !important;
                border-collapse: separate;
                border-spacing: 0 20px;
            }
            .card-datatable thead {
                display: none !important;
            }
            .card-datatable tbody tr {
                background: transparent !important;
            }
            .card-datatable tbody tr:hover {
                background: transparent !important;
            }
            .card-datatable td {
                border: none !important;
                padding: 0 !important;
                background: transparent !important;
            }
            .card-datatable td:first-child {
                display: none !important;
            }

            /* ── Queue Card Styling ── */
            .queue-card {
                display: flex;
                flex-direction: column;
                gap: 16px;
                background: #ffffff;
                border: 1px solid var(--primary-color);
                border-radius: 24px;
                padding: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.03);
                transition: all 0.2s ease;
            }
            .queue-card:hover {
                box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            }
            
            /* Header: Avatar, Info, Badges */
            .queue-card-header {
                display: flex;
                align-items: flex-start;
                gap: 14px;
            }
            .queue-card-avatar {
                width: 45px;
                height: 45px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 1.1rem;
                position: relative;
                flex-shrink: 0;
                background-color: #e2e8f0;
                color: #475569;
            }
            .queue-card-status-dot {
                position: absolute;
                bottom: 0;
                right: 2px;
                width: 12px;
                height: 12px;
                border: 2px solid white;
                border-radius: 50%;
            }
            .queue-card-patient-info {
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-width: 0;
            }
            .queue-card-name {
                font-size: 1.1rem;
                font-weight: 700;
                color: #1e293b;
                letter-spacing: -0.01em;
            }
            .queue-card-name a {
                color: inherit;
                text-decoration: none;
            }
            .queue-card-name a:hover {
                color: #0d6efd;
            }
            .queue-card-demo {
                font-weight: 500;
                font-size: 0.85rem;
                color: #64748b;
                margin-left: 4px;
            }
            .queue-card-meta {
                font-size: 0.85rem;
                color: #64748b;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 2px;
            }
            .queue-card-separator {
                color: #cbd5e1;
                font-size: 0.8rem;
            }
            .queue-card-badges {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 8px;
                flex-shrink: 0;
            }
            .queue-card-badges .badge {
                font-size: 0.75rem;
                padding: 5px 12px;
                border-radius: 20px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }

            /* Details Strip */
            .queue-card-details {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 16px;
                background-color: transparent;
                padding: 0;
                border: none;
                margin-top: 4px;
            }
            .queue-card-detail-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 0.85rem;
                font-weight: 500;
                color: #475569;
            }
            .queue-card-detail-item i {
                color: #94a3b8;
                font-size: 1rem;
            }
            .queue-card-detail-item .badge {
                font-size: 0.75rem;
                padding: 4px 10px;
                border-radius: 12px;
            }

            /* Reason / Note */
            .queue-card-reason {
                font-size: 0.85rem;
                color: #475569;
                font-style: italic;
                padding: 8px 14px;
                border-left: 3px solid #cbd5e1;
                background: #f8fafc;
                border-radius: 0 8px 8px 0;
            }

            /* Actions */
            .queue-card-actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                padding-top: 14px;
                border-top: 1px solid #f1f5f9;
            }
            .queue-card-action-btn {
                border-radius: 20px !important;
                font-weight: 600 !important;
                padding: 6px 20px !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                transition: transform 0.1s ease;
            }
            .queue-card-action-btn:active {
                transform: scale(0.98);
            }
            .queue-card-secondary-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .queue-card-secondary-actions .btn {
                border-radius: 20px !important;
                padding: 4px 12px !important;
                display: flex;
                align-items: center;
                gap: 4px;
                font-size: 0.8rem;
                font-weight: 500;
                width: auto !important;
                height: auto !important;
                min-width: max-content !important;
            }
            .form-select-sm, .form-control-sm {
                border-radius: 6px !important;
                border: 1px solid #cbd5e1 !important;
                font-size: 0.85rem !important;
                color: #334155 !important;
                background-color: #fff !important;
                height: 36px !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
                transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            }
            .form-select-sm {
                padding: 0.25rem 2rem 0.25rem 0.75rem !important;
            }
            .form-control-sm {
                padding: 0.25rem 0.75rem !important;
            }
            .form-select-sm:focus, .form-control-sm:focus {
                border-color: #86b7fe !important;
                outline: 0 !important;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
            }
            #appt_date_fetch_btn {
                height: 36px;
                display: inline-flex;
                align-items: center;
                border-radius: 6px;
                padding: 0 16px;
                font-weight: 600;
            }
    </style>
@endpush
@section('content')

    {{-- ══ Two Main Tabs ════════════════════════════════════════════════ --}}
    <div class="full-screen-tabs">
        <div class="d-flex justify-content-between align-items-center bg-white border-bottom pe-3 me-0" style="position: sticky; top: 0; z-index: 50;">
            <ul class="nav nav-tabs border-bottom-0" id="mainDoctorTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="appt-calendar-tab" data-bs-toggle="tab" data-bs-target="#appt-calendar-pane" type="button" role="tab">
                        <i class="mdi mdi-calendar-clock"></i> My Appt Calendar
                        <span class="badge bg-primary tab-badge" id="tab-badge-active">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-lists-tab" data-bs-toggle="tab" data-bs-target="#history-lists-pane" type="button" role="tab">
                        <i class="mdi mdi-history"></i> Encounter Hist / Admissions
                    </button>
                </li>
            </ul>
            @if(appsettings()->enable_ei_doctor)
            <div class="py-1 pe-2">
                <button class="btn btn-danger btn-sm font-weight-bold shadow-sm" onclick="showEmergencyIntakeModal()">
                    <i class="mdi mdi-ambulance"></i> Emergency Intake
                </button>
            </div>
            @endif
        </div>

        <div class="tab-content">
            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- TAB 1: MY APPT CALENDAR                                      --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade show active" id="appt-calendar-pane" role="tabpanel">

                {{-- Quick Stats Bar --}}
                <div class="queue-stats-row mb-3 mt-3 px-2">
                    <div class="queue-stat-card" style="border-left: 3px solid #ffc107;">
                        <div class="stat-num text-warning" id="stat-waiting">0</div>
                        <div class="stat-label">Waiting</div>
                    </div>
                    <div class="queue-stat-card" style="border-left: 3px solid #17a2b8;">
                        <div class="stat-num text-info" id="stat-vitals">0</div>
                        <div class="stat-label">Vitals</div>
                    </div>
                    <div class="queue-stat-card" style="border-left: 3px solid #0d6efd;">
                        <div class="stat-num text-primary" id="stat-ready">0</div>
                        <div class="stat-label">Ready</div>
                    </div>
                    <div class="queue-stat-card" style="border-left: 3px solid #198754;">
                        <div class="stat-num text-success" id="stat-consult">0</div>
                        <div class="stat-label">In Consult</div>
                    </div>
                    <div class="queue-stat-card" style="border-left: 3px solid #6f42c1;">
                        <div class="stat-num" style="color:#6f42c1;" id="stat-scheduled">0</div>
                        <div class="stat-label">Scheduled</div>
                        <div class="stat-sub text-muted" style="font-size:0.65rem;" id="stat-scheduled-detail"></div>
                    </div>
                    <div class="queue-stat-card" style="border-left: 3px solid #6c757d;">
                        <div class="stat-num text-secondary" id="stat-completed">0</div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="queue-stat-card" style="border-left: 3px solid #212529;">
                        <div class="stat-num" id="stat-total">0</div>
                        <div class="stat-label">Total Active</div>
                    </div>
                </div>

                {{-- Date Range Filter --}}
                <div class="d-flex gap-2 mb-2 mt-2 px-2 flex-wrap align-items-end">
                    <div>
                        <label class="form-label mb-1 small text-muted d-block">From</label>
                        <input type="date" class="form-control form-control-sm" id="appt_start_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                    </div>
                    <div>
                        <label class="form-label mb-1 small text-muted d-block">To</label>
                        <input type="date" class="form-control form-control-sm" id="appt_end_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}" style="max-width:150px;">
                    </div>
                    <div>
                        <label class="form-label mb-1 small text-muted d-block">Source</label>
                        <select class="form-select form-select-sm" id="appt_source_filter" style="min-width:110px;">
                            <option value="all">All Sources</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="walk_in">Walk-in</option>
                            <option value="emergency">Emergency</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="referral">Referral</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-1 small text-muted d-block">Priority</label>
                        <select class="form-select form-select-sm" id="appt_priority_filter" style="min-width:110px;">
                            <option value="all">All Priorities</option>
                            <option value="emergency">Emergency</option>
                            <option value="urgent">Urgent</option>
                            <option value="routine">Routine</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-1 small text-muted d-block">Clinic</label>
                        <select class="form-select form-select-sm" id="appt_clinic_filter" style="min-width:120px; max-width:180px;">
                            <option value="all">All Clinics</option>
                            @if(isset($filterClinics))
                                @foreach($filterClinics as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-1 small text-muted d-block">Sort By</label>
                        <select class="form-select form-select-sm" id="appt_sort_filter" style="min-width:140px;">
                            <option value="newest">Time (Newest First)</option>
                            <option value="oldest">Time (Oldest First)</option>
                            <option value="patient_az">Patient (A-Z)</option>
                            <option value="patient_za">Patient (Z-A)</option>
                            <option value="priority">Priority (Highest)</option>
                        </select>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-primary" id="appt_date_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                    </div>
                </div>

                {{-- Toolbar: Status Pills + View Toggle --}}
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 px-2">
                    <div class="status-pill-bar" id="statusPillBar">
                        <span class="status-pill status-pill-all active" data-status="all">
                            <span class="pill-dot" style="background:#495057;"></span> All
                            <span class="pill-count" id="pill-all">0</span>
                        </span>
                        <span class="status-pill status-pill-waiting" data-status="1">
                            <span class="pill-dot" style="background:#ffc107;"></span> Waiting
                            <span class="pill-count" id="pill-1">0</span>
                        </span>
                        <span class="status-pill status-pill-vitals" data-status="2">
                            <span class="pill-dot" style="background:#17a2b8;"></span> Vitals
                            <span class="pill-count" id="pill-2">0</span>
                        </span>
                        <span class="status-pill status-pill-ready" data-status="3">
                            <span class="pill-dot" style="background:#0d6efd;"></span> Ready
                            <span class="pill-count" id="pill-3">0</span>
                        </span>
                        <span class="status-pill status-pill-consult" data-status="4">
                            <span class="pill-dot" style="background:#198754;"></span> In Consultation
                            <span class="pill-count" id="pill-4">0</span>
                        </span>
                        <span class="status-pill status-pill-scheduled" data-status="6">
                            <span class="pill-dot" style="background:#6f42c1;"></span> Scheduled
                            <span class="pill-count" id="pill-6">0</span>
                        </span>
                        <span class="status-pill status-pill-completed" data-status="5">
                            <span class="pill-dot" style="background:#6c757d;"></span> Completed
                            <span class="pill-count" id="pill-5">0</span>
                        </span>
                    </div>
                    <div class="btn-group btn-group-sm view-toggle" role="group">
                        <button type="button" class="btn btn-outline-secondary" id="btn-calendar-view">
                            <i class="mdi mdi-calendar"></i> Calendar
                        </button>
                        <button type="button" class="btn btn-outline-secondary active" id="btn-table-view">
                            <i class="mdi mdi-table"></i> Table
                        </button>
                    </div>
                </div>

                {{-- Calendar View --}}
                <div id="calendar-wrapper" style="display:none;" class="px-2">
                    <div id="unified-calendar" style="min-height:580px;"></div>
                </div>

                {{-- Table View (default) --}}
                <div id="table-wrapper" class="px-2">
                    <div class="table-responsive">
                        <table class="card-datatable" id="unified-queue-table" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th>Card</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════ --}}
            {{-- TAB 2: ENCOUNTER HISTORY / ADMISSIONS                         --}}
            {{-- ══════════════════════════════════════════════════════════════ --}}
            <div class="tab-pane fade" id="history-lists-pane" role="tabpanel">
                <div class="mt-3 px-2">
                    <ul class="nav nav-tabs history-inner-tabs" id="historyInnerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="prev_data_tab" data-bs-toggle="tab" data-bs-target="#prev" type="button" role="tab">
                                <i class="mdi mdi-clock-outline"></i> Previous Encounters
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="my_admissions_tab" data-bs-toggle="tab" data-bs-target="#my_admissions" type="button" role="tab">
                                <i class="mdi mdi-bed"></i> My Admissions
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="other_admissions_tab" data-bs-toggle="tab" data-bs-target="#other_admissions" type="button" role="tab">
                                <i class="mdi mdi-bed-outline"></i> Other Admissions
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="my_referrals_tab" data-bs-toggle="tab" data-bs-target="#my_referrals" type="button" role="tab">
                                <i class="mdi mdi-account-switch"></i> My Referrals <span class="badge bg-info ms-1" id="my-referral-tab-count" style="display:none;">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="all_referrals_tab" data-bs-toggle="tab" data-bs-target="#all_referrals" type="button" role="tab">
                                <i class="mdi mdi-swap-horizontal-bold"></i> All Referrals
                            </button>
                        </li>
                    </ul>
                    @php
                        $filterClinics = \App\Models\Clinic::orderBy('name')->get();
                        $filterHmos    = \App\Models\Hmo::orderBy('name')->get();
                        $filterDoctors = \App\Models\Staff::whereHas('user', fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'DOCTOR')))
                            ->with('user')->get()->sortBy(fn($s) => userfullname($s->user_id));
                    @endphp
                    <div class="tab-content" id="historyInnerTabContent">
                        {{-- ── Previous Encounters ──────────────────────────────── --}}
                        <div class="tab-pane fade show active" id="prev" role="tabpanel">
                            <div class="card-modern mt-2">
                                <div class="card-body">
                                    <div class="d-flex gap-2 mb-2 flex-wrap align-items-end">
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">From</label>
                                            <input type="date" class="form-control form-control-sm" id="prev_start_date" value="{{ date('Y-m-d', strtotime('-7 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">To</label>
                                            <input type="date" class="form-control form-control-sm" id="prev_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Clinic</label>
                                            <select class="form-select form-select-sm" id="prev_clinic_filter" style="max-width:160px;">
                                                <option value="">All Clinics</option>
                                                @foreach($filterClinics as $fc)
                                                    <option value="{{ $fc->id }}">{{ $fc->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">HMO</label>
                                            <select class="form-select form-select-sm" id="prev_hmo_filter" style="max-width:160px;">
                                                <option value="">All HMOs</option>
                                                @foreach($filterHmos as $fh)
                                                    <option value="{{ $fh->id }}">{{ $fh->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Sort By</label>
                                            <select class="form-select form-select-sm" id="prev_sort_filter" style="min-width:140px;">
                                                <option value="newest">Time (Newest First)</option>
                                                <option value="oldest">Time (Oldest First)</option>
                                                <option value="patient_az">Patient (A-Z)</option>
                                                <option value="patient_za">Patient (Z-A)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="prev_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="card-datatable" id="prev_consult_list" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Card</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- ── My Admissions ────────────────────────────────────── --}}
                        <div class="tab-pane fade" id="my_admissions" role="tabpanel">
                            <div class="card-modern mt-2">
                                <div class="card-body">
                                    <div class="d-flex gap-2 mb-2 flex-wrap align-items-end">
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">From</label>
                                            <input type="date" class="form-control form-control-sm" id="my_adm_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">To</label>
                                            <input type="date" class="form-control form-control-sm" id="my_adm_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">HMO</label>
                                            <select class="form-select form-select-sm" id="my_adm_hmo_filter" style="max-width:160px;">
                                                <option value="">All HMOs</option>
                                                @foreach($filterHmos as $fh)
                                                    <option value="{{ $fh->id }}">{{ $fh->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Sort By</label>
                                            <select class="form-select form-select-sm" id="my_adm_sort_filter" style="min-width:140px;">
                                                <option value="newest">Time (Newest First)</option>
                                                <option value="oldest">Time (Oldest First)</option>
                                                <option value="patient_az">Patient (A-Z)</option>
                                                <option value="patient_za">Patient (Z-A)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="my_adm_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="card-datatable" id="my_admissions_list" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Card</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- ── Other Admissions ─────────────────────────────────── --}}
                        <div class="tab-pane fade" id="other_admissions" role="tabpanel">
                            <div class="card-modern mt-2">
                                <div class="card-body">
                                    <div class="d-flex gap-2 mb-2 flex-wrap align-items-end">
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">From</label>
                                            <input type="date" class="form-control form-control-sm" id="other_adm_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">To</label>
                                            <input type="date" class="form-control form-control-sm" id="other_adm_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Doctor</label>
                                            <select class="form-select form-select-sm" id="other_adm_doctor_filter" style="max-width:160px;">
                                                <option value="">All Doctors</option>
                                                @foreach($filterDoctors as $fd)
                                                    <option value="{{ $fd->user_id }}">{{ userfullname($fd->user_id) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">HMO</label>
                                            <select class="form-select form-select-sm" id="other_adm_hmo_filter" style="max-width:160px;">
                                                <option value="">All HMOs</option>
                                                @foreach($filterHmos as $fh)
                                                    <option value="{{ $fh->id }}">{{ $fh->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Sort By</label>
                                            <select class="form-select form-select-sm" id="other_adm_sort_filter" style="min-width:140px;">
                                                <option value="newest">Time (Newest First)</option>
                                                <option value="oldest">Time (Oldest First)</option>
                                                <option value="patient_az">Patient (A-Z)</option>
                                                <option value="patient_za">Patient (Z-A)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="other_adm_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="card-datatable" id="other_admissions_list" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Card</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- ── My Referrals (sent by me or targeted at me) ──── --}}
                        <div class="tab-pane fade" id="my_referrals" role="tabpanel">
                            <div class="card-modern mt-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted"><i class="mdi mdi-information-outline me-1"></i>Referrals you created or that are directed to you/your clinic</small>
                                    </div>
                                    <div class="d-flex gap-2 mb-2 flex-wrap align-items-end">
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">From</label>
                                            <input type="date" class="form-control form-control-sm" id="my_ref_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">To</label>
                                            <input type="date" class="form-control form-control-sm" id="my_ref_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Status</label>
                                            <select class="form-select form-select-sm" id="my_ref_status_filter" style="max-width:140px;">
                                                <option value="">All Statuses</option>
                                                <option value="pending" selected>Pending</option>
                                                <option value="booked">Booked</option>
                                                <option value="completed">Completed</option>
                                                <option value="declined">Declined</option>
                                                <option value="cancelled">Cancelled</option>
                                                <option value="referred_out">Referred Out</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Direction</label>
                                            <select class="form-select form-select-sm" id="my_ref_direction_filter" style="max-width:140px;">
                                                <option value="">All</option>
                                                <option value="sent">Sent by Me</option>
                                                <option value="received">Targeted at Me</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Type</label>
                                            <select class="form-select form-select-sm" id="my_ref_type_filter" style="max-width:130px;">
                                                <option value="">All Types</option>
                                                <option value="internal">Internal</option>
                                                <option value="external">External</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Sort By</label>
                                            <select class="form-select form-select-sm" id="my_ref_sort_filter" style="min-width:140px;">
                                                <option value="newest">Time (Newest First)</option>
                                                <option value="oldest">Time (Oldest First)</option>
                                                <option value="patient_az">Patient (A-Z)</option>
                                                <option value="patient_za">Patient (Z-A)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="my_ref_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="card-datatable" id="my_referrals_list" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Card</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- ── All Referrals (hospital-wide) ────────────────── --}}
                        <div class="tab-pane fade" id="all_referrals" role="tabpanel">
                            <div class="card-modern mt-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted"><i class="mdi mdi-information-outline me-1"></i>All referrals across the hospital. Read-only for referrals that don't involve you.</small>
                                    </div>
                                    <div class="d-flex gap-2 mb-2 flex-wrap align-items-end">
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">From</label>
                                            <input type="date" class="form-control form-control-sm" id="all_ref_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">To</label>
                                            <input type="date" class="form-control form-control-sm" id="all_ref_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Status</label>
                                            <select class="form-select form-select-sm" id="all_ref_status_filter" style="max-width:140px;">
                                                <option value="">All Statuses</option>
                                                <option value="pending">Pending</option>
                                                <option value="booked">Booked</option>
                                                <option value="completed">Completed</option>
                                                <option value="declined">Declined</option>
                                                <option value="cancelled">Cancelled</option>
                                                <option value="referred_out">Referred Out</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Clinic</label>
                                            <select class="form-select form-select-sm" id="all_ref_clinic_filter" style="max-width:160px;">
                                                <option value="">All Clinics</option>
                                                @foreach($filterClinics as $fc)
                                                    <option value="{{ $fc->id }}">{{ $fc->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Doctor</label>
                                            <select class="form-select form-select-sm" id="all_ref_doctor_filter" style="max-width:160px;">
                                                <option value="">All Doctors</option>
                                                @foreach($filterDoctors as $fd)
                                                    <option value="{{ $fd->id }}">{{ userfullname($fd->user_id) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Type</label>
                                            <select class="form-select form-select-sm" id="all_ref_type_filter" style="max-width:130px;">
                                                <option value="">All Types</option>
                                                <option value="internal">Internal</option>
                                                <option value="external">External</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-1 small text-muted d-block">Sort By</label>
                                            <select class="form-select form-select-sm" id="all_ref_sort_filter" style="min-width:140px;">
                                                <option value="newest">Time (Newest First)</option>
                                                <option value="oldest">Time (Oldest First)</option>
                                                <option value="patient_az">Patient (A-Z)</option>
                                                <option value="patient_za">Patient (Z-A)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="all_ref_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="card-datatable" id="all_referrals_list" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Card</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Referral Detail Modal ═══════════════════════════════════════ --}}
    <div class="modal fade" id="refDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="mdi mdi-account-switch text-primary me-2"></i>Referral Details</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="refDetailModalBody">
                    <div class="text-center py-4"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="refDetailAcceptBtn"><i class="mdi mdi-check-circle me-1"></i>Accept</button>
                    <button type="button" class="btn btn-warning btn-sm d-none" id="refDetailDeclineBtn"><i class="mdi mdi-close-circle me-1"></i>Decline</button>
                    <button type="button" class="btn btn-outline-dark btn-sm d-none" id="refDetailPrintBtn"><i class="mdi mdi-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Event Context Menu ════════════════════════════════════════════ --}}
    <div class="event-context-menu" id="eventContextMenu">
        <div class="ctx-item text-muted" data-action="status-info" style="display:none;cursor:default;opacity:0.7;font-weight:600;"><i class="ctx-status-icon mdi mdi-information"></i> <span class="ctx-status-label">Status</span></div>
        <div class="ctx-item" data-action="encounter"><i class="fa fa-street-view text-success"></i> Open Encounter</div>
        <div class="ctx-item text-muted" data-action="encounter-blocked" style="display:none;cursor:default;opacity:0.6;"><i class="mdi mdi-alert-circle text-danger"></i> <span class="ctx-blocked-reason">Delivery Blocked</span></div>
        <div class="ctx-item" data-action="checkin"><i class="mdi mdi-login text-primary"></i> Check-In</div>
        <div class="ctx-divider"></div>
        <div class="ctx-item" data-action="reschedule"><i class="mdi mdi-calendar-refresh text-info"></i> Reschedule</div>
        <div class="ctx-item" data-action="reassign"><i class="mdi mdi-account-switch text-warning"></i> Change Doctor</div>
        <div class="ctx-divider"></div>
        <div class="ctx-item" data-action="cancel"><i class="mdi mdi-cancel text-danger"></i> Cancel</div>
        <div class="ctx-item" data-action="noshow"><i class="mdi mdi-account-remove text-secondary"></i> No-Show</div>
        <div class="ctx-divider"></div>
        <div class="ctx-item text-muted" data-action="next-step-hint" style="display:none;cursor:default;opacity:0.7;font-size:0.78rem;"><i class="mdi mdi-lightbulb-outline text-warning"></i> <span class="ctx-next-step"></span></div>
    </div>

    {{-- Reschedule Appointment Modal (Doctor Page) --}}
    <div class="modal fade" id="docRescheduleAppointmentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="mdi mdi-calendar-edit"></i> Reschedule Appointment</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <form id="doc-reschedule-form">
                    <div class="modal-body">
                        <input type="hidden" id="doc-reschedule-appt-id">
                        <div class="alert alert-light border mb-3">
                            <div class="d-flex justify-content-between">
                                <span><strong>Patient:</strong> <span id="doc-reschedule-patient-name"></span></span>
                                <span class="badge bg-secondary" id="doc-reschedule-count-info"></span>
                            </div>
                            <div class="text-muted small mt-1">Original: <span id="doc-reschedule-original-date"></span></div>
                        </div>
                        <div class="form-group mb-3">
                            <label><i class="mdi mdi-calendar"></i> New Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="doc-reschedule-date" required min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="form-group mb-3">
                            <label><i class="mdi mdi-clock-outline"></i> Time Slot <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="doc-reschedule-custom-time-toggle">
                                    <label class="form-check-label small text-muted" for="doc-reschedule-custom-time-toggle">Custom time</label>
                                </div>
                            </div>
                            <select class="form-control" id="doc-reschedule-time">
                                <option value="">-- Select date first --</option>
                            </select>
                            <input type="time" class="form-control d-none" id="doc-reschedule-custom-time-input" placeholder="HH:MM">
                        </div>
                        <div class="form-group mb-3">
                            <label><i class="mdi mdi-note-text"></i> Reason</label>
                            <select class="form-control" id="doc-reschedule-reason">
                                <option value="">-- Select reason --</option>
                                <option value="Patient requested">Patient requested</option>
                                <option value="Doctor schedule change">Doctor schedule change</option>
                                <option value="Emergency rescheduling">Emergency rescheduling</option>
                                <option value="Clinic unavailable">Clinic unavailable</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="mdi mdi-calendar-edit"></i> Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Reassign Doctor Modal (Doctor Page) --}}
    <div class="modal fade" id="docReassignDoctorModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background:#6f42c1; color:#fff;">
                    <h5 class="modal-title"><i class="mdi mdi-account-switch"></i> Reassign Doctor</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
                </div>
                <form id="doc-reassign-form">
                    <div class="modal-body">
                        <input type="hidden" id="doc-reassign-appt-id">
                        <div class="alert alert-light border mb-3">
                            <strong>Patient:</strong> <span id="doc-reassign-patient-name"></span>
                        </div>
                        <div class="form-group mb-3">
                            <label><i class="mdi mdi-doctor"></i> New Doctor <span class="text-danger">*</span></label>
                            <select class="form-control" id="doc-reassign-doctor" required>
                                <option value="">-- Select Doctor --</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label><i class="mdi mdi-note-text"></i> Reason</label>
                            <select class="form-control" id="doc-reassign-reason">
                                <option value="">-- Select reason --</option>
                                <option value="Doctor on leave">Doctor on leave</option>
                                <option value="Doctor unavailable">Doctor unavailable</option>
                                <option value="Patient request">Patient request</option>
                                <option value="Schedule conflict">Schedule conflict</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" style="background:#6f42c1; color:#fff;"><i class="mdi mdi-account-switch"></i> Reassign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  Confirmation Modal (Check-in) -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="modal fade" id="confirmCheckInModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title mb-0"><i class="mdi mdi-login me-1"></i> Check In Patient</h6>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Are you sure you want to check in this appointment?</p>
                    <small class="text-muted">The patient's status will change to <strong>Waiting</strong>. They will appear in the waiting queue and can proceed to vitals or consultation.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-success" id="confirmCheckInBtn"><i class="mdi mdi-check me-1"></i> Check In</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  Confirmation Modal (Cancel Appointment) -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="modal fade" id="confirmCancelApptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white py-2">
                    <h6 class="modal-title mb-0"><i class="mdi mdi-calendar-remove me-1"></i> Cancel Appointment</h6>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to cancel this appointment?</p>
                    <small class="text-muted d-block mb-3">This will remove the appointment from the schedule. The patient will need to book a new appointment if they wish to be seen.</small>
                    <div class="form-group mb-0">
                        <label for="cancelApptReason" class="form-label fw-bold small">Reason for Cancellation <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control form-control-sm" id="cancelApptReason" rows="2" placeholder="e.g. Patient requested cancellation, scheduling conflict..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Go Back</button>
                    <button type="button" class="btn btn-sm btn-danger" id="confirmCancelApptBtn"><i class="mdi mdi-close-circle me-1"></i> Cancel Appointment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  Confirmation Modal (No-Show) -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="modal fade" id="confirmNoShowModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark py-2">
                    <h6 class="modal-title mb-0"><i class="mdi mdi-account-off me-1"></i> Mark No-Show</h6>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Mark this appointment as a <strong>No-Show</strong>?</p>
                    <small class="text-muted">This indicates the patient did not attend their scheduled appointment. This will be recorded in their appointment history.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-warning" id="confirmNoShowBtn"><i class="mdi mdi-account-off me-1"></i> Mark No-Show</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  Confirmation Modal (Accept Referral) -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="modal fade" id="confirmAcceptRefModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title mb-0"><i class="mdi mdi-check-circle me-1"></i> Accept Referral</h6>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Accept this referral?</p>
                    <small class="text-muted">A new encounter will be started for the patient and they will be added to your consultation queue. You can begin reviewing their case immediately.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-success" id="confirmAcceptRefBtn"><i class="mdi mdi-check-circle me-1"></i> Accept Referral</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!--  Decline Referral Modal (with reason) -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="modal fade" id="declineRefReasonModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white py-2">
                    <h6 class="modal-title mb-0"><i class="mdi mdi-close-circle me-1"></i> Decline Referral</h6>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Please provide a reason for declining this referral.</p>
                    <small class="text-muted d-block mb-3">The referring doctor will be notified with your reason so they can make alternative arrangements for the patient.</small>
                    <div class="form-group mb-0">
                        <label for="declineRefReason" class="form-label fw-bold small">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="declineRefReason" rows="2" placeholder="e.g. Patient outside my specialty, scheduling conflict..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-danger" id="confirmDeclineRefBtn"><i class="mdi mdi-close-circle me-1"></i> Decline Referral</button>
                </div>
            </div>
        </div>
    </div>

@endsection
@include('admin.doctors.partials._queue_scripts')
