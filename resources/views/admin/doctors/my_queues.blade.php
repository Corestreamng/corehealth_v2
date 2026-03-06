@extends('admin.layouts.app')
@section('title', 'My Queue')
@section('page_name', 'Consultations')
@section('subpage_name', 'My Queue')
@push('styles')
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
    </style>
@endpush
@section('content')

    {{-- ══ Two Main Tabs ════════════════════════════════════════════════ --}}
    <div class="full-screen-tabs">
        <ul class="nav nav-tabs" id="mainDoctorTabs" role="tablist">
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
                        <table class="table table-sm table-bordered table-striped table-hover" id="unified-queue-table" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th>Patient</th>
                                    <th>Source / Time</th>
                                    <th>Status</th>
                                    <th>Delivery</th>
                                    <th style="width:140px;">Action</th>
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
                                            <label class="form-label mb-0 small text-muted">From</label>
                                            <input type="date" class="form-control form-control-sm" id="prev_start_date" value="{{ date('Y-m-d', strtotime('-7 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">To</label>
                                            <input type="date" class="form-control form-control-sm" id="prev_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">Clinic</label>
                                            <select class="form-select form-select-sm" id="prev_clinic_filter" style="max-width:160px;">
                                                <option value="">All Clinics</option>
                                                @foreach($filterClinics as $fc)
                                                    <option value="{{ $fc->id }}">{{ $fc->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">HMO</label>
                                            <select class="form-select form-select-sm" id="prev_hmo_filter" style="max-width:160px;">
                                                <option value="">All HMOs</option>
                                                @foreach($filterHmos as $fh)
                                                    <option value="{{ $fh->id }}">{{ $fh->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="prev_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-striped" id="prev_consult_list" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Patient Name</th>
                                                    <th>File No</th>
                                                    <th>HMO/Insurance</th>
                                                    <th>Clinic</th>
                                                    <th>Doctor</th>
                                                    <th>Time</th>
                                                    <th>Delivery</th>
                                                    <th>Action</th>
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
                                            <label class="form-label mb-0 small text-muted">From</label>
                                            <input type="date" class="form-control form-control-sm" id="my_adm_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">To</label>
                                            <input type="date" class="form-control form-control-sm" id="my_adm_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">HMO</label>
                                            <select class="form-select form-select-sm" id="my_adm_hmo_filter" style="max-width:160px;">
                                                <option value="">All HMOs</option>
                                                @foreach($filterHmos as $fh)
                                                    <option value="{{ $fh->id }}">{{ $fh->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="my_adm_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-striped" id="my_admissions_list" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>SN</th>
                                                    <th>Patient</th>
                                                    <th>File No</th>
                                                    <th>HMO/Insurance</th>
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
                        {{-- ── Other Admissions ─────────────────────────────────── --}}
                        <div class="tab-pane fade" id="other_admissions" role="tabpanel">
                            <div class="card-modern mt-2">
                                <div class="card-body">
                                    <div class="d-flex gap-2 mb-2 flex-wrap align-items-end">
                                        <div>
                                            <label class="form-label mb-0 small text-muted">From</label>
                                            <input type="date" class="form-control form-control-sm" id="other_adm_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">To</label>
                                            <input type="date" class="form-control form-control-sm" id="other_adm_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">Doctor</label>
                                            <select class="form-select form-select-sm" id="other_adm_doctor_filter" style="max-width:160px;">
                                                <option value="">All Doctors</option>
                                                @foreach($filterDoctors as $fd)
                                                    <option value="{{ $fd->user_id }}">{{ userfullname($fd->user_id) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">HMO</label>
                                            <select class="form-select form-select-sm" id="other_adm_hmo_filter" style="max-width:160px;">
                                                <option value="">All HMOs</option>
                                                @foreach($filterHmos as $fh)
                                                    <option value="{{ $fh->id }}">{{ $fh->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="other_adm_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-striped" id="other_admissions_list" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>SN</th>
                                                    <th>Patient</th>
                                                    <th>File No</th>
                                                    <th>HMO/Insurance</th>
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
                        {{-- ── My Referrals (sent by me or targeted at me) ──── --}}
                        <div class="tab-pane fade" id="my_referrals" role="tabpanel">
                            <div class="card-modern mt-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted"><i class="mdi mdi-information-outline me-1"></i>Referrals you created or that are directed to you/your clinic</small>
                                    </div>
                                    <div class="d-flex gap-2 mb-2 flex-wrap align-items-end">
                                        <div>
                                            <label class="form-label mb-0 small text-muted">From</label>
                                            <input type="date" class="form-control form-control-sm" id="my_ref_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">To</label>
                                            <input type="date" class="form-control form-control-sm" id="my_ref_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">Status</label>
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
                                            <label class="form-label mb-0 small text-muted">Direction</label>
                                            <select class="form-select form-select-sm" id="my_ref_direction_filter" style="max-width:140px;">
                                                <option value="">All</option>
                                                <option value="sent">Sent by Me</option>
                                                <option value="received">Targeted at Me</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">Type</label>
                                            <select class="form-select form-select-sm" id="my_ref_type_filter" style="max-width:130px;">
                                                <option value="">All Types</option>
                                                <option value="internal">Internal</option>
                                                <option value="external">External</option>
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="my_ref_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-striped table-hover" id="my_referrals_list" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Patient</th>
                                                    <th>File No</th>
                                                    <th>Urgency</th>
                                                    <th>Type</th>
                                                    <th>From</th>
                                                    <th>To</th>
                                                    <th>Reason</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th style="min-width:130px;">Actions</th>
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
                                            <label class="form-label mb-0 small text-muted">From</label>
                                            <input type="date" class="form-control form-control-sm" id="all_ref_start_date" value="{{ date('Y-m-d', strtotime('-30 days')) }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">To</label>
                                            <input type="date" class="form-control form-control-sm" id="all_ref_end_date" value="{{ date('Y-m-d') }}" style="max-width:150px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">Status</label>
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
                                            <label class="form-label mb-0 small text-muted">Clinic</label>
                                            <select class="form-select form-select-sm" id="all_ref_clinic_filter" style="max-width:160px;">
                                                <option value="">All Clinics</option>
                                                @foreach($filterClinics as $fc)
                                                    <option value="{{ $fc->id }}">{{ $fc->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">Doctor</label>
                                            <select class="form-select form-select-sm" id="all_ref_doctor_filter" style="max-width:160px;">
                                                <option value="">All Doctors</option>
                                                @foreach($filterDoctors as $fd)
                                                    <option value="{{ $fd->id }}">{{ userfullname($fd->user_id) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label mb-0 small text-muted">Type</label>
                                            <select class="form-select form-select-sm" id="all_ref_type_filter" style="max-width:130px;">
                                                <option value="">All Types</option>
                                                <option value="internal">Internal</option>
                                                <option value="external">External</option>
                                            </select>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-primary" id="all_ref_fetch_btn"><i class="mdi mdi-magnify"></i> Fetch</button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered table-striped table-hover" id="all_referrals_list" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Patient</th>
                                                    <th>File No</th>
                                                    <th>Urgency</th>
                                                    <th>Type</th>
                                                    <th>From</th>
                                                    <th>To</th>
                                                    <th>Reason</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th style="min-width:130px;">Actions</th>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
        <div class="ctx-item" data-action="encounter"><i class="fa fa-street-view text-success"></i> Open Encounter</div>
        <div class="ctx-item text-muted" data-action="encounter-blocked" style="display:none;cursor:default;opacity:0.6;"><i class="mdi mdi-alert-circle text-danger"></i> <span class="ctx-blocked-reason">Delivery Blocked</span></div>
        <div class="ctx-item" data-action="checkin"><i class="mdi mdi-login text-primary"></i> Check-In</div>
        <div class="ctx-divider"></div>
        <div class="ctx-item" data-action="reschedule"><i class="mdi mdi-calendar-refresh text-info"></i> Reschedule</div>
        <div class="ctx-item" data-action="reassign"><i class="mdi mdi-account-switch text-warning"></i> Change Doctor</div>
        <div class="ctx-divider"></div>
        <div class="ctx-item" data-action="cancel"><i class="mdi mdi-cancel text-danger"></i> Cancel</div>
        <div class="ctx-item" data-action="noshow"><i class="mdi mdi-account-remove text-secondary"></i> No-Show</div>
    </div>

@endsection
@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script src="{{ asset('plugins/daterangepicker/moment.js') }}" defer></script>
    <script src="{{ asset('plugins/fullcalendar/fullcalendar.min.js') }}" defer></script>
    <script>
    $(function() {

        // ═══════════════════════════════════════════════════════════════
        //  State
        // ═══════════════════════════════════════════════════════════════
        var currentStatusFilter = 'all';
        var calendarInitialized = false;
        var currentView = 'table'; // 'table' or 'calendar'
        var contextEvent = null;   // Currently right-clicked event
        var doctorStaffId = '{{ optional(\App\Models\Staff::where("user_id", auth()->id())->first())->id ?? "" }}';
        var historyTablesInitialized = false;
        var referralTablesInitialized = false;

        // ═══════════════════════════════════════════════════════════════
        //  Main Tab: Lazy-init history tables when tab 2 is shown
        // ═══════════════════════════════════════════════════════════════
        $('#history-lists-tab').on('shown.bs.tab', function() {
            if (!historyTablesInitialized) {
                initSecondaryTable('#prev_consult_list', "{{ url('PrevEncounterList') }}");
                initSecondaryTable('#my_admissions_list', "{{ route('my-admission-requests-list') }}");
                initSecondaryTable('#other_admissions_list', "{{ route('admission-requests-list') }}");
                historyTablesInitialized = true;
            }
        });

        // Lazy-init referral tables only when their inner tabs are first shown
        $('#my_referrals_tab').on('shown.bs.tab', function() {
            if (!referralTablesInitialized) {
                initReferralTable('#my_referrals_list', "{{ route('referrals.doctor-list') }}", true);
                referralTablesInitialized = true;
            }
        });
        var allReferralTableInitialized = false;
        $('#all_referrals_tab').on('shown.bs.tab', function() {
            if (!allReferralTableInitialized) {
                initReferralTable('#all_referrals_list', "{{ route('referrals.all-list') }}", false);
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
            ajax: {
                url: "{{ route('appointments.doctor.unified-list') }}",
                type: 'GET',
                data: function(d) {
                    d.start_date = moment().format('YYYY-MM-DD');
                    d.end_date   = moment().add(30, 'days').format('YYYY-MM-DD');
                    d.status_filter = currentStatusFilter;
                }
            },
            columns: [
                { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false, width: "30px" },
                { data: "patient_info", name: "patient_name", className: "align-middle" },
                { data: "source_time", name: "source_time", orderable: false, className: "align-middle text-center", width: "120px" },
                { data: "status_badge", name: "status_badge", orderable: false, className: "align-middle text-center", width: "150px" },
                { data: "delivery_badge", name: "delivery_badge", orderable: false, className: "align-middle text-center", width: "130px" },
                { data: "action", name: "action", orderable: false, searchable: false, className: "align-middle text-center", width: "140px" }
            ],
            paging: true,
            drawCallback: function() { initMiniTimers(); },
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

        // ═══════════════════════════════════════════════════════════════
        //  Unified Calendar (FullCalendar v2)
        // ═══════════════════════════════════════════════════════════════

        // ── Smooth refresh: diff-based update to avoid blink ────────
        var _docCalLastEvents = {};  // id → JSON fingerprint

        function smoothRefreshDocCal() {
            if (!calendarInitialized) return;
            var view = $('#unified-calendar').fullCalendar('getView');
            $.ajax({
                url: "{{ route('appointments.doctor.unified-events') }}",
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
                        url: "{{ route('appointments.doctor.unified-events') }}",
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
            $menu.find('[data-action="encounter"]').toggle(!!event.encounter_url && event.can_deliver !== false);
            $menu.find('[data-action="encounter-blocked"]').toggle(isActive && event.can_deliver === false);
            if (event.can_deliver === false) {
                $menu.find('.ctx-blocked-reason').text(event.delivery_reason || 'Delivery Blocked');
            }
            $menu.find('[data-action="checkin"]').toggle(event.event_type === 'appointment' && event.status == 6);
            $menu.find('[data-action="reschedule"]').toggle(event.event_type === 'appointment' && event.status == 6);
            $menu.find('[data-action="reassign"]').toggle(event.event_type === 'appointment' && event.status == 6);
            $menu.find('[data-action="cancel"]').toggle(event.status != 5 && event.status != 0);
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

            // Position near click
            var x = jsEvent.pageX, y = jsEvent.pageY;
            var menuW = 260, menuH = 300;
            if (x + menuW > $(window).width()) x = $(window).width() - menuW - 10;
            if (y + menuH > $(window).scrollTop() + $(window).height()) y = y - menuH;

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
                        window.location.href = '/encounters/create?patient_id=' + evt.patient_id + '&queue_id=' + evt.queue_id;
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
            if (!confirm('Check in this appointment?')) return;
            $.ajax({
                url: "{{ route('appointments.check-in', ['appointment' => '__AID__']) }}".replace('__AID__', apptId),
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
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
        }

        function doCancelAppointment(apptId) {
            var reason = prompt('Cancellation reason (optional):');
            if (reason === null) return;
            $.ajax({
                url: "{{ route('appointments.cancel', ['appointment' => '__AID__']) }}".replace('__AID__', apptId),
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
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
        }

        function doNoShow(apptId) {
            if (!confirm('Mark this appointment as No-Show?')) return;
            $.ajax({
                url: "{{ route('appointments.no-show', ['appointment' => '__AID__']) }}".replace('__AID__', apptId),
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
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
        }

        // ═══════════════════════════════════════════════════════════════
        //  Badge & Stats Refresh
        // ═══════════════════════════════════════════════════════════════
        function loadQueueCounts() {
            $.ajax({
                url: "{{ route('appointments.doctor.queue-counts') }}",
                type: 'GET',
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
                    if (schedToday > 0) schedDetail += schedToday + ' today';
                    if (schedFuture > 0) schedDetail += (schedDetail ? ', ' : '') + schedFuture + ' upcoming';
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
            // Previous
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
                    hmo_id:     $('#prev_hmo_filter').val()
                };
            }
            if (selector === '#my_admissions_list') {
                return {
                    start_date: $('#my_adm_start_date').val(),
                    end_date:   $('#my_adm_end_date').val(),
                    hmo_id:     $('#my_adm_hmo_filter').val()
                };
            }
            if (selector === '#other_admissions_list') {
                return {
                    start_date: $('#other_adm_start_date').val(),
                    end_date:   $('#other_adm_end_date').val(),
                    doctor_id:  $('#other_adm_doctor_filter').val(),
                    hmo_id:     $('#other_adm_hmo_filter').val()
                };
            }
            return {};
        }

        // Init secondary tables on first show of history tab (lazy)
        // See #history-lists-tab shown.bs.tab handler above

        // Fetch button handlers for all three tabs
        $('#prev_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#prev_consult_list')) {
                $('#prev_consult_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#my_adm_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#my_admissions_list')) {
                $('#my_admissions_list').DataTable().ajax.reload(null, false);
            }
        });
        $('#other_adm_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#other_admissions_list')) {
                $('#other_admissions_list').DataTable().ajax.reload(null, false);
            }
        });

        // ═══════════════════════════════════════════════════════════════
        //  Referral DataTables
        // ═══════════════════════════════════════════════════════════════
        var referralColumns = [
            { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false, width: '30px' },
            { data: "patient_name", name: "patient_name" },
            { data: "patient_file_no", name: "patient_file_no" },
            { data: "urgency_badge", name: "urgency_badge", orderable: false },
            { data: "type_badge", name: "type_badge", orderable: false },
            { data: "from_info", name: "from_info" },
            { data: "to_info", name: "to_info" },
            { data: "reason_short", name: "reason_short" },
            { data: "status_badge", name: "status_badge", orderable: false },
            { data: "time", name: "time" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
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
                order: [[9, 'desc']]
            });
        }

        function getReferralFilterData(selector) {
            if (selector === '#my_referrals_list') {
                return {
                    start_date: $('#my_ref_start_date').val(),
                    end_date:   $('#my_ref_end_date').val(),
                    status:     $('#my_ref_status_filter').val(),
                    direction:  $('#my_ref_direction_filter').val(),
                    referral_type: $('#my_ref_type_filter').val()
                };
            }
            if (selector === '#all_referrals_list') {
                return {
                    start_date:    $('#all_ref_start_date').val(),
                    end_date:      $('#all_ref_end_date').val(),
                    status:        $('#all_ref_status_filter').val(),
                    clinic_id:     $('#all_ref_clinic_filter').val(),
                    doctor_id:     $('#all_ref_doctor_filter').val(),
                    referral_type: $('#all_ref_type_filter').val()
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
        $('#all_ref_fetch_btn').on('click', function() {
            if ($.fn.DataTable.isDataTable('#all_referrals_list')) {
                $('#all_referrals_list').DataTable().ajax.reload(null, false);
            }
        });

        // ═══════════════════════════════════════════════════════════════
        //  Referral Actions (View, Accept, Decline)
        // ═══════════════════════════════════════════════════════════════
        var _activeRefId = null;
        var _activeRefData = null;

        // View referral detail
        $(document).on('click', '.btn-view-ref-detail', function() {
            var refId = $(this).data('id');
            _activeRefId = refId;
            $('#refDetailModalBody').html('<div class="text-center py-4"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
            $('#refDetailAcceptBtn, #refDetailDeclineBtn, #refDetailPrintBtn').addClass('d-none');
            var modal = new bootstrap.Modal(document.getElementById('refDetailModal'));
            modal.show();

            $.get("{{ url('referrals') }}/" + refId + "/detail", function(data) {
                _activeRefData = data;
                var ref = data.referral;
                var html = '';

                // Patient info
                html += '<div class="card border-0 bg-light mb-3">';
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
                html += '<div class="card border h-100"><div class="card-header bg-light py-1"><small class="fw-bold"><i class="mdi mdi-arrow-up-bold text-danger me-1"></i>Referred From</small></div>';
                html += '<div class="card-body py-2"><small>' + (ref.referring_doctor || 'N/A') + '</small>';
                if (ref.referring_clinic) html += '<br><small class="text-muted">' + ref.referring_clinic + '</small>';
                html += '</div></div>';
                html += '</div>';
                html += '<div class="col-md-6">';
                html += '<div class="card border h-100"><div class="card-header bg-light py-1"><small class="fw-bold"><i class="mdi mdi-arrow-down-bold text-success me-1"></i>Referred To</small></div>';
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
                html += '<div class="card border mb-3"><div class="card-header bg-light py-1"><small class="fw-bold"><i class="mdi mdi-clipboard-pulse me-1 text-primary"></i>Clinical Information</small></div>';
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
            $.post("{{ url('referrals') }}/" + _activeRefId + "/accept", {
                _token: '{{ csrf_token() }}'
            }, function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Referral accepted');
                    bootstrap.Modal.getInstance(document.getElementById('refDetailModal')).hide();
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
            var reason = prompt('Reason for declining this referral:');
            if (!reason) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post("{{ url('referrals') }}/" + _activeRefId + "/decline", {
                _token: '{{ csrf_token() }}',
                reason: reason
            }, function(res) {
                if (res.success) {
                    toastr.success('Referral declined');
                    bootstrap.Modal.getInstance(document.getElementById('refDetailModal')).hide();
                    if ($.fn.DataTable.isDataTable('#my_referrals_list')) $('#my_referrals_list').DataTable().ajax.reload(null, false);
                    if ($.fn.DataTable.isDataTable('#all_referrals_list')) $('#all_referrals_list').DataTable().ajax.reload(null, false);
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to decline');
            }).always(function() {
                $btn.prop('disabled', false).html('<i class="mdi mdi-close-circle me-1"></i>Decline');
            });
        });

        // Print referral from modal
        $('#refDetailPrintBtn').on('click', function() {
            if (!_activeRefId || !_activeRefData) return;
            $.get("{{ url('referrals') }}/" + _activeRefId + "/detail", function(data) {
                buildAndPrintReferralLetter(data);
            });
        });

        // Quick accept from table row
        $(document).on('click', '.btn-accept-ref', function() {
            var refId = $(this).data('id');
            if (!confirm('Accept this referral? A new encounter will be started for the patient.')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.post("{{ url('referrals') }}/" + refId + "/accept", {
                _token: '{{ csrf_token() }}'
            }, function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Referral accepted');
                    if ($.fn.DataTable.isDataTable('#my_referrals_list')) $('#my_referrals_list').DataTable().ajax.reload(null, false);
                    if ($.fn.DataTable.isDataTable('#all_referrals_list')) $('#all_referrals_list').DataTable().ajax.reload(null, false);
                    if (res.encounter_url) window.open(res.encounter_url, '_blank');
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed');
                $btn.prop('disabled', false).html('<i class="mdi mdi-check-circle"></i>');
            });
        });

        // Quick decline from table row
        $(document).on('click', '.btn-decline-ref', function() {
            var refId = $(this).data('id');
            var reason = prompt('Reason for declining:');
            if (!reason) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post("{{ url('referrals') }}/" + refId + "/decline", {
                _token: '{{ csrf_token() }}',
                reason: reason
            }, function(res) {
                if (res.success) {
                    toastr.success('Referral declined');
                    if ($.fn.DataTable.isDataTable('#my_referrals_list')) $('#my_referrals_list').DataTable().ajax.reload(null, false);
                    if ($.fn.DataTable.isDataTable('#all_referrals_list')) $('#all_referrals_list').DataTable().ajax.reload(null, false);
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed');
                $btn.prop('disabled', false);
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
            printWin.document.write('<html><head><title>Referral Letter</title><style>body{font-family:serif;padding:20px;} .referral-letter{max-width:700px;margin:auto;} .letter-header{text-align:center;border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:15px;} .patient-info-table td{padding:4px 8px;border:1px solid #ccc;}</style></head><body>');
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
            Swal.fire({
                title: 'Reassign Doctor',
                html: `
                    <p class="text-start mb-2"><strong>Patient:</strong> ${patientName}</p>
                    <div class="mb-3 text-start">
                        <label class="form-label">New Doctor</label>
                        <select id="swal-reassign-doctor" class="form-select">
                            <option value="">Loading doctors...</option>
                        </select>
                    </div>
                    <div class="mb-2 text-start">
                        <label class="form-label">Reason (optional)</label>
                        <input id="swal-reassign-reason" class="form-control" placeholder="Reason for reassignment">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="mdi mdi-account-switch"></i> Reassign',
                confirmButtonColor: '#6f42c1',
                didOpen: () => {
                    // Load doctors from the same clinic
                    $.get('{{ url("reception/clinics") }}/' + clinicId + '/doctors', function(doctors) {
                        var $select = $('#swal-reassign-doctor');
                        $select.empty().append('<option value="">-- Select Doctor --</option>');
                        doctors.forEach(function(doc) {
                            $select.append('<option value="' + doc.id + '">' + doc.name + '</option>');
                        });
                    }).fail(function() {
                        $('#swal-reassign-doctor').empty().append('<option value="">Error loading doctors</option>');
                    });
                },
                preConfirm: () => {
                    var doctorId = $('#swal-reassign-doctor').val();
                    if (!doctorId) {
                        Swal.showValidationMessage('Please select a doctor');
                        return false;
                    }
                    return { doctor_id: doctorId, reason: $('#swal-reassign-reason').val() };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('appointments.reassign', ['appointment' => '__AID__']) }}".replace('__AID__', appointmentId),
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            doctor_id: result.value.doctor_id,
                            reason: result.value.reason
                        },
                        success: function(res) {
                            if (res.success) {
                                toastr.success(res.message || 'Doctor reassigned successfully.');
                                refreshAll();
                            } else {
                                toastr.error(res.message || 'Reassignment failed.');
                            }
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON?.message || 'Reassignment failed.');
                        }
                    });
                }
            });
        }

        // ═══════════════════════════════════════════════════════════════
        //  Reschedule Appointment (Doctor Page)
        // ═══════════════════════════════════════════════════════════════
        function openRescheduleModal(appointmentId, patientName, clinicId, doctorId, currentDate, rescheduleCount) {
            var useCustomTime = false;

            Swal.fire({
                title: 'Reschedule Appointment',
                width: 480,
                html: `
                    <p class="text-start mb-2"><strong>Patient:</strong> ${patientName}</p>
                    <div class="mb-3 text-start">
                        <label class="form-label">New Date <span class="text-danger">*</span></label>
                        <input type="date" id="swal-resch-date" class="form-control" min="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div id="swal-time-group" class="mb-3 text-start">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Time Slot <span class="text-danger">*</span></span>
                            <span class="form-check form-switch ms-2">
                                <input class="form-check-input" type="checkbox" id="swal-custom-time-toggle">
                                <label class="form-check-label small" for="swal-custom-time-toggle">Custom</label>
                            </span>
                        </label>
                        <select id="swal-resch-time" class="form-select">
                            <option value="">-- pick a date first --</option>
                        </select>
                        <input type="time" id="swal-resch-custom-time" class="form-control d-none" placeholder="HH:MM">
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label">Reason</label>
                        <select id="swal-resch-reason" class="form-select">
                            <option value="">-- Select reason --</option>
                            <option value="Patient requested">Patient requested</option>
                            <option value="Doctor schedule change">Doctor schedule change</option>
                            <option value="Emergency rescheduling">Emergency rescheduling</option>
                            <option value="Clinic unavailable">Clinic unavailable</option>
                        </select>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="mdi mdi-calendar-edit"></i> Reschedule',
                confirmButtonColor: '#f0ad4e',
                didOpen: () => {
                    // Fetch slots when date changes
                    $(document).on('change.swal_resch', '#swal-resch-date', function() {
                        if ($('#swal-custom-time-toggle').is(':checked')) return;
                        var date = $(this).val();
                        if (!date || !clinicId) return;
                        $('#swal-resch-time').empty().append('<option value="">Loading...</option>');
                        $.get('{{ route("appointments.available-slots") }}', { date: date, clinic_id: clinicId, doctor_id: doctorId }, function(resp) {
                            var $sel = $('#swal-resch-time');
                            $sel.empty().append('<option value="">-- Select Time --</option>');
                            if (resp.success && resp.slots && resp.slots.length) {
                                var hasSlots = false;
                                resp.slots.forEach(function(slot) {
                                    if (slot.available) {
                                        hasSlots = true;
                                        $sel.append('<option value="' + slot.time + '">' + slot.time + '</option>');
                                    }
                                });
                                if (!hasSlots) $sel.append('<option value="" disabled>No slots — use custom time</option>');
                            } else {
                                $sel.append('<option value="" disabled>No slots configured — use custom time</option>');
                            }
                        });
                    });

                    $(document).on('change.swal_resch', '#swal-custom-time-toggle', function() {
                        useCustomTime = $(this).is(':checked');
                        if (useCustomTime) {
                            $('#swal-resch-time').addClass('d-none');
                            $('#swal-resch-custom-time').removeClass('d-none');
                        } else {
                            $('#swal-resch-custom-time').addClass('d-none').val('');
                            $('#swal-resch-time').removeClass('d-none');
                        }
                    });
                },
                willClose: () => {
                    $(document).off('change.swal_resch');
                },
                preConfirm: () => {
                    var date = $('#swal-resch-date').val();
                    var time = useCustomTime ? $('#swal-resch-custom-time').val() : $('#swal-resch-time').val();
                    if (!date) {
                        Swal.showValidationMessage('Please select a date');
                        return false;
                    }
                    if (!time) {
                        Swal.showValidationMessage('Please select or enter a time');
                        return false;
                    }
                    return {
                        date: date,
                        time: time,
                        custom_time: useCustomTime ? 1 : 0,
                        reason: $('#swal-resch-reason').val()
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('appointments.reschedule', ['appointment' => '__AID__']) }}".replace('__AID__', appointmentId),
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            appointment_date: result.value.date,
                            start_time: result.value.time,
                            custom_time: result.value.custom_time,
                            reason: result.value.reason
                        },
                        success: function(res) {
                            if (res.success) {
                                toastr.success(res.message || 'Appointment rescheduled.');
                                refreshAll();
                            } else {
                                Swal.fire('Failed', res.message || 'Could not reschedule', 'error');
                            }
                        },
                        error: function(xhr) {
                            Swal.fire('Error', xhr.responseJSON?.message || 'Reschedule failed', 'error');
                        }
                    });
                }
            });
        }

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
    </script>
@endsection
