@extends('admin.layouts.app')

@section('title', 'Surgery Workbench')

@push('styles')
    <link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
    @php
        $hosColor = appsettings('hos_color', '#0066cc');
    @endphp

    <style>
        :root {
            --surgery-primary: {{ $hosColor }};
            --surgery-blue: #1565c0;
            --priority-emergency: #c62828;
            --priority-urgent: #e65100;
            --priority-routine: #2e7d32;
            --status-requested: #1565c0;
            --status-scheduled: #0277bd;
            --status-in-progress: #f57f17;
            --status-completed: #2e7d32;
            --status-cancelled: #616161;
        }

        /* ── Page Layout ── */
        .sw-page {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 70px);
            background: #f0f2f5;
        }

        /* ── Page Header ── */
        .sw-header {
            background: linear-gradient(135deg, #1a237e 0%, var(--surgery-primary) 60%, color-mix(in srgb, var(--surgery-primary) 70%, #1565c0) 100%);
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .sw-header-title {
            flex: 1;
            min-width: 200px;
        }

        .sw-header-title h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sw-header-title small {
            font-size: 0.8rem;
            opacity: 0.75;
        }

        .sw-header-search {
            position: relative;
            flex: 0 0 300px;
        }

        .sw-header-search input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: none;
            border-radius: 2rem;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 0.9rem;
            outline: none;
            transition: background 0.2s;
        }

        .sw-header-search input::placeholder {
            color: rgba(255, 255, 255, 0.65);
        }

        .sw-header-search input:focus {
            background: rgba(255, 255, 255, 0.25);
        }

        .sw-header-search .sw-search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.65);
            pointer-events: none;
        }

        .sw-search-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            right: 0;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            z-index: 1050;
            display: none;
            max-height: 320px;
            overflow-y: auto;
        }

        .sw-search-item {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #f1f3f5;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: background 0.15s;
        }

        .sw-search-item:last-child {
            border-bottom: none;
        }

        .sw-search-item:hover {
            background: #f0f4ff;
        }

        .sw-search-item-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1565c0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .sw-search-item-name {
            font-weight: 600;
            color: #1a1a2e;
            font-size: 0.9rem;
        }

        .sw-search-item-meta {
            font-size: 0.78rem;
            color: #6c757d;
        }

        /* ── Stats Bar ── */
        .sw-stats-bar {
            background: white;
            border-bottom: 2px solid #dee2e6;
            display: flex;
            gap: 0;
            overflow-x: auto;
            flex-shrink: 0;
        }

        .sw-stat-tab {
            flex: 1;
            min-width: 130px;
            padding: 0.9rem 1rem;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            position: relative;
            font-family: inherit;
        }

        .sw-stat-tab:hover {
            background: #f8f9fa;
        }

        .sw-stat-tab.active {
            background: #f0f4ff;
            border-bottom-color: var(--surgery-blue);
        }

        .sw-stat-count {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
            display: block;
        }

        .sw-stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 0.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }

        .sw-stat-tab[data-status="requested"] .sw-stat-count {
            color: var(--status-requested);
        }

        .sw-stat-tab[data-status="scheduled"] .sw-stat-count {
            color: var(--status-scheduled);
        }

        .sw-stat-tab[data-status="in_progress"] .sw-stat-count {
            color: var(--status-in-progress);
        }

        .sw-stat-tab[data-status="completed"] .sw-stat-count {
            color: var(--status-completed);
        }

        .sw-stat-tab[data-status="cancelled"] .sw-stat-count {
            color: var(--status-cancelled);
        }

        /* ── Filter Bar ── */
        .sw-filters {
            background: white;
            padding: 0.65rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            border-bottom: 1px solid #dee2e6;
        }

        .sw-filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6c757d;
            white-space: nowrap;
        }

        .sw-filter-select {
            padding: 0.35rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            font-size: 0.85rem;
            color: #495057;
            background: white;
            cursor: pointer;
        }

        .sw-filter-select:focus {
            outline: none;
            border-color: var(--surgery-blue);
        }

        .sw-filter-date {
            padding: 0.35rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            font-size: 0.85rem;
            color: #495057;
        }

        .sw-filter-date:focus {
            outline: none;
            border-color: var(--surgery-blue);
        }

        .sw-filters-right {
            margin-left: auto;
            display: flex;
            gap: 0.5rem;
        }

        .sw-btn-refresh {
            padding: 0.35rem 0.85rem;
            background: #f0f4ff;
            color: var(--surgery-blue);
            border: 1px solid #c5d8f6;
            border-radius: 0.375rem;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s;
        }

        .sw-btn-refresh:hover {
            background: var(--surgery-blue);
            color: white;
        }

        /* Patient context banner */
        #sw-patient-banner {
            display: none;
            align-items: center;
            gap: 10px;
            background: color-mix(in srgb, var(--surgery-primary) 12%, #fff);
            border: 1.5px solid color-mix(in srgb, var(--surgery-primary) 35%, transparent);
            border-radius: 0.5rem;
            padding: 8px 14px;
            margin: 0 0 10px 0;
            font-size: 0.88rem;
        }
        #sw-patient-banner.visible { display: flex; }
        #sw-patient-banner .spb-icon {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--surgery-primary); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }
        #sw-patient-banner .spb-name {
            font-weight: 700; color: #1a237e; font-size: 0.95rem;
        }
        #sw-patient-banner .spb-meta {
            color: #546e7a; font-size: 0.82rem;
        }
        #sw-patient-banner .spb-badge {
            display: inline-block; padding: 1px 8px; border-radius: 20px;
            background: color-mix(in srgb, var(--surgery-primary) 18%, #fff);
            color: var(--surgery-primary); font-size: 0.78rem; font-weight: 600;
            border: 1px solid color-mix(in srgb, var(--surgery-primary) 30%, transparent);
            margin-right: 4px;
        }
        #sw-patient-banner .spb-exit {
            margin-left: auto; flex-shrink: 0;
            background: none; border: 1.5px solid color-mix(in srgb, var(--surgery-primary) 40%, transparent);
            color: var(--surgery-primary); border-radius: 6px;
            padding: 3px 12px; font-size: 0.82rem; font-weight: 600;
            cursor: pointer; transition: all 0.15s;
        }
        #sw-patient-banner .spb-exit:hover {
            background: var(--surgery-primary); color: #fff;
        }

        .sw-view-toggle {
            display: flex;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .sw-view-btn {
            padding: 0.35rem 0.6rem;
            background: white;
            border: none;
            cursor: pointer;
            color: #6c757d;
            transition: all 0.15s;
        }

        .sw-view-btn.active {
            background: var(--surgery-blue);
            color: white;
        }

        /* ── Board Content ── */
        .sw-board {
            flex: 1;
            padding: 1.25rem;
            overflow-y: auto;
        }

        /* ── Procedure Card ── */
        .sw-proc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1rem;
        }

        .sw-proc-list {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .proc-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.07);
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
            border-left: 4px solid #dee2e6;
            cursor: pointer;
        }

        .proc-card:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.13);
            transform: translateY(-2px);
        }

        .proc-card.priority-emergency {
            border-left-color: var(--priority-emergency);
            animation: emergency-pulse 2.2s ease-in-out infinite;
        }

        @keyframes emergency-pulse {
            0%, 100% { box-shadow: 0 2px 6px rgba(198,40,40,.15); }
            50% { box-shadow: 0 2px 18px rgba(198,40,40,.45), 0 0 0 3px rgba(198,40,40,.12); }
        }

        .proc-card.priority-urgent {
            border-left-color: var(--priority-urgent);
        }

        .proc-card.priority-routine {
            border-left-color: #dee2e6;
        }

        .proc-card-header {
            padding: 0.85rem 1rem 0.5rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .proc-card-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1a237e;
            line-height: 1.3;
        }

        .proc-card-category {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.1rem;
        }

        .proc-card-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            flex-shrink: 0;
        }

        .badge-status {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .badge-status.requested {
            background: #e3f2fd;
            color: #1565c0;
        }

        .badge-status.scheduled {
            background: #e1f5fe;
            color: #0277bd;
        }

        .badge-status.in_progress {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-status.completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-status.cancelled {
            background: #f5f5f5;
            color: #616161;
        }

        .badge-priority {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 0.15rem 0.45rem;
            border-radius: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-priority.emergency {
            background: #ffebee;
            color: #c62828;
        }

        .badge-priority.urgent {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-priority.routine {
            display: none;
        }

        .badge-consent {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 1rem;
            text-transform: uppercase;
        }

        .badge-consent.pending {
            background: #ffebee;
            color: #b71c1c;
        }

        .badge-consent.obtained {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-consent.waived,
        .badge-consent.not_required {
            background: #f5f5f5;
            color: #616161;
        }

        .proc-card-patient {
            padding: 0 1rem 0.65rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .proc-card-patient-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #1565c0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .proc-card-patient-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: #212529;
        }

        .proc-card-patient-meta {
            font-size: 0.76rem;
            color: #6c757d;
        }

        .proc-card-details {
            padding: 0.5rem 1rem;
            border-top: 1px solid #f0f2f5;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.25rem;
            font-size: 0.78rem;
            color: #495057;
        }

        .proc-card-detail-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .proc-card-detail-item i {
            color: #adb5bd;
            font-size: 0.8rem;
        }

        .proc-card-footer {
            padding: 0.6rem 1rem;
            background: #f8faff;
            border-top: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .proc-card-footer-meta {
            font-size: 0.75rem;
            color: #868e96;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .proc-card-footer-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-open-proc {
            padding: 0.35rem 0.85rem;
            background: var(--surgery-blue);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            transition: background 0.15s;
            text-decoration: none;
        }

        .btn-open-proc:hover {
            background: #1a237e;
            color: white;
            text-decoration: none;
        }

        /* ── List view variant ── */
        .sw-proc-list .proc-card {
            display: flex;
            flex-direction: row;
            align-items: center;
        }

        .sw-proc-list .proc-card-header {
            flex: 1;
            padding: 0.75rem 1rem;
        }

        .sw-proc-list .proc-card-patient {
            padding: 0.75rem 0;
            border: none;
        }

        .sw-proc-list .proc-card-details {
            border-top: none;
            padding: 0.75rem 0.5rem;
            flex: 1;
        }

        .sw-proc-list .proc-card-footer {
            padding: 0.75rem 1rem;
            border-top: none;
            background: transparent;
        }

        /* ── Empty state ── */
        .sw-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #adb5bd;
        }

        .sw-empty i {
            font-size: 4rem;
            display: block;
            margin-bottom: 1rem;
        }

        .sw-empty h4 {
            color: #6c757d;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .sw-empty p {
            font-size: 0.9rem;
            margin: 0;
        }

        /* ── Loading ── */
        .sw-loading {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .sw-loading i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.75rem;
        }

        /* ── Section divider when mixing statuses ── */
        .sw-status-section {
            margin-bottom: 1.5rem;
        }

        .sw-status-section-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #495057;
        }

        .sw-status-section-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #dee2e6;
        }

        .sw-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .sw-status-dot.requested {
            background: var(--status-requested);
        }

        .sw-status-dot.scheduled {
            background: var(--status-scheduled);
        }

        .sw-status-dot.in_progress {
            background: var(--status-in-progress);
        }

        .sw-status-dot.completed {
            background: var(--status-completed);
        }

        .sw-status-dot.cancelled {
            background: var(--status-cancelled);
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .sw-proc-grid {
                grid-template-columns: 1fr;
            }

            .sw-header-search {
                flex: 0 0 100%;
                order: 3;
            }

            .sw-stat-tab {
                min-width: 100px;
            }

            .sw-filters {
                padding: 0.5rem 0.75rem;
            }
        }
    </style>

    <div class="sw-page">

        {{-- ── Page Header ── --}}
        <div class="sw-header">
            <div class="sw-header-title">
                <h2>
                    <i class="mdi mdi-scalpel"></i>
                    Surgery Workbench
                </h2>
                <small>Procedure scheduling &amp; management</small>
            </div>
            <div class="sw-header-search" style="min-width: 300px; flex: 1; max-width: 400px; margin-left: auto;">
                @include('admin.partials.patient_search_widget', [
                    'id' => 'sw-patient-search-widget',
                    'searchRoute' => route('surgery-workbench.search-patients'),
                    'context' => 'surgery',
                    'placeholder' => 'Search patient, file no…'
                ])
            </div>
        </div>

        {{-- ── Stats / Status Tabs ── --}}
        <div class="sw-stats-bar" id="sw-stats-bar">
            <button class="sw-stat-tab" data-status="all" id="tab-all">
                <span class="sw-stat-count" style="color: #37474f;" id="count-all">—</span>
                <span class="sw-stat-label"><i class="mdi mdi-view-grid-outline"></i> All</span>
            </button>
            <button class="sw-stat-tab active" data-status="requested" id="tab-requested">
                <span class="sw-stat-count" id="count-requested">—</span>
                <span class="sw-stat-label"><i class="mdi mdi-clipboard-plus-outline"></i> Requested</span>
            </button>
            <button class="sw-stat-tab" data-status="scheduled" id="tab-scheduled">
                <span class="sw-stat-count" id="count-scheduled">—</span>
                <span class="sw-stat-label"><i class="mdi mdi-calendar-clock"></i> Scheduled</span>
            </button>
            <button class="sw-stat-tab" data-status="in_progress" id="tab-in-progress">
                <span class="sw-stat-count" id="count-in-progress">—</span>
                <span class="sw-stat-label"><i class="mdi mdi-progress-clock"></i> In Progress</span>
            </button>
            <button class="sw-stat-tab" data-status="completed" id="tab-completed">
                <span class="sw-stat-count" id="count-completed">—</span>
                <span class="sw-stat-label"><i class="mdi mdi-check-circle-outline"></i> Completed</span>
            </button>
            <button class="sw-stat-tab" data-status="cancelled" id="tab-cancelled">
                <span class="sw-stat-count" id="count-cancelled">—</span>
                <span class="sw-stat-label"><i class="mdi mdi-close-circle-outline"></i> Cancelled</span>
            </button>
        </div>

        {{-- ── Filter Toolbar ── --}}
        <div class="sw-filters">
            <span class="sw-filter-label">Priority:</span>
            <select class="sw-filter-select" id="filter-priority">
                <option value="">All Priorities</option>
                <option value="emergency">Emergency</option>
                <option value="urgent">Urgent</option>
                <option value="routine">Routine</option>
            </select>

            <span class="sw-filter-label">Consent:</span>
            <select class="sw-filter-select" id="filter-consent">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="obtained">Obtained</option>
                <option value="waived">Waived</option>
            </select>

            <span class="sw-filter-label">Date:</span>
            <button class="sw-btn-refresh" id="btn-filter-today" title="Show today's OR schedule" style="background:var(--surgery-primary);color:#fff;border-color:var(--surgery-primary);">
                <i class="mdi mdi-calendar-today"></i> Today
            </button>
            <input type="date" class="sw-filter-date" id="filter-date" title="Filter by scheduled date">
            <button class="sw-btn-refresh" id="btn-clear-date" style="display:none;" title="Clear date filter">
                <i class="mdi mdi-close"></i>
            </button>

            <div class="sw-filters-right">
                <button class="sw-btn-refresh" id="btn-refresh-queue">
                    <i class="mdi mdi-refresh"></i> Refresh
                </button>
                <div class="sw-view-toggle">
                    <button class="sw-view-btn active" id="btn-view-grid" title="Grid view">
                        <i class="mdi mdi-view-grid"></i>
                    </button>
                    <button class="sw-view-btn" id="btn-view-list" title="List view">
                        <i class="mdi mdi-view-list"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Patient Context Banner ── --}}
        <div id="sw-patient-banner">
            <div class="spb-icon"><i class="mdi mdi-account"></i></div>
            <div>
                <div class="spb-name" id="spb-name"></div>
                <div class="spb-meta" id="spb-meta"></div>
            </div>
            <div style="margin-left: auto; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button class="btn btn-sm btn-primary" id="btn-book-procedure" style="font-size: 0.82rem; font-weight: 600; padding: 3px 12px; border-radius: 6px;">
                    <i class="mdi mdi-plus-circle"></i> Book Procedure
                </button>
                <button class="spb-exit" id="spb-exit-btn" style="margin-left: 0;">
                    <i class="mdi mdi-close"></i> Exit
                </button>
            </div>
        </div>

        {{-- ── Board ── --}}
        <div class="sw-board" id="sw-board">
            <div class="sw-loading" id="sw-loading">
                <i class="mdi mdi-loading mdi-spin"></i>
                Loading procedures…
            </div>
            <div id="sw-procedures-container"></div>
        </div>

    </div>

    @include('admin.partials.clinical_orders_modal')

    @push('scripts')
        <script src="{{ asset('js/patient-search-widget.js') }}?v={{ time() }}"></script>
        <script src="{{ asset('js/clinical-orders-shared.js') }}?v={{ filemtime(public_path('js/clinical-orders-shared.js')) }}"></script>
        <script>
            (function() {
                'use strict';

                /* ── Config ── */
                const ROUTES = {
                    queueCounts: '{{ route('surgery-workbench.queue-counts') }}',
                    queue: '{{ route('surgery-workbench.queue') }}',
                    searchPatients: '{{ route('surgery-workbench.search-patients') }}',
                };

                /* ── State ── */
                let currentStatus = 'requested';
                let currentPriority = '';
                let currentConsent = '';
                let currentDate = '';
                let searchTerm = '';
                let selectedPatientId = null;
                let viewMode = 'grid'; // 'grid' | 'list'
                let searchTimer = null;
                let refreshTimer = null;

                /* ── Helpers ── */
                function initials(name) {
                    return name ? name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() : '?';
                }

                function statusLabel(s) {
                    const map = {
                        requested: 'Requested',
                        scheduled: 'Scheduled',
                        in_progress: 'In Progress',
                        completed: 'Completed',
                        cancelled: 'Cancelled'
                    };
                    return map[s] || s;
                }

                function consentLabel(c) {
                    const map = {
                        pending: 'Consent Pending',
                        obtained: 'Consent OK',
                        waived: 'Waived',
                        not_required: 'N/A'
                    };
                    return map[c] || c;
                }

                /* ── Load Stats ── */
                function loadStats() {
                    $.get(ROUTES.queueCounts, function(data) {
                        const total = (data.requested || 0) + (data.scheduled || 0) + (data.in_progress || 0) + (
                            data.completed || 0) + (data.cancelled || 0);
                        $('#count-all').text(total);
                        $('#count-requested').text(data.requested || 0);
                        $('#count-scheduled').text(data.scheduled || 0);
                        $('#count-in-progress').text(data.in_progress || 0);
                        $('#count-completed').text(data.completed || 0);
                        $('#count-cancelled').text(data.cancelled || 0);
                    });
                }

                /* ── Load Queue ── */
                function loadQueue() {
                    $('#sw-loading').show();
                    $('#sw-procedures-container').empty();

                    const params = {};
                    if (currentStatus && currentStatus !== 'all') params.status = currentStatus;
                    if (currentPriority) params.priority = currentPriority;
                    if (currentConsent) params.consent = currentConsent;
                    if (currentDate) params.date = currentDate;
                    if (searchTerm) params.search = searchTerm;
                    if (selectedPatientId) params.patient_id = selectedPatientId;

                    $.get(ROUTES.queue, params, function(resp) {
                        $('#sw-loading').hide();
                        renderProcedures(resp.data || []);
                    }).fail(function() {
                        $('#sw-loading').hide();
                        $('#sw-procedures-container').html(
                            '<div class="sw-empty"><i class="mdi mdi-alert-circle-outline"></i><h4>Failed to load</h4><p>Please try refreshing.</p></div>'
                            );
                    });
                }

                /* ── Render Procedures ── */
                function renderProcedures(procedures) {
                    _lastData = procedures || [];
                    const $container = $('#sw-procedures-container');
                    $container.empty();

                    if (!procedures.length) {
                        $container.html(`
            <div class="sw-empty">
                <i class="mdi mdi-calendar-blank-outline"></i>
                <h4>No procedures found</h4>
                <p>Try adjusting your filters or status tab</p>
            </div>`);
                        return;
                    }

                    // When showing "all", group by status
                    if (currentStatus === 'all') {
                        const order = ['in_progress', 'scheduled', 'requested', 'completed', 'cancelled'];
                        const grouped = {};
                        order.forEach(s => grouped[s] = []);
                        procedures.forEach(p => {
                            const s = p.procedure_status;
                            if (!grouped[s]) grouped[s] = [];
                            grouped[s].push(p);
                        });

                        let hasAny = false;
                        order.forEach(status => {
                            const group = grouped[status];
                            if (!group.length) return;
                            hasAny = true;
                            const $section = $('<div class="sw-status-section"></div>');
                            $section.append(`
                <div class="sw-status-section-header">
                    <span class="sw-status-dot ${status}"></span>
                    ${statusLabel(status)}
                    <span style="color:#adb5bd;font-weight:400;">(${group.length})</span>
                </div>
            `);
                            const $grid = $('<div></div>').addClass(viewMode === 'list' ? 'sw-proc-list' :
                                'sw-proc-grid');
                            group.forEach(p => $grid.append(buildCard(p)));
                            $section.append($grid);
                            $container.append($section);
                        });

                        if (!hasAny) {
                            $container.html(
                                '<div class="sw-empty"><i class="mdi mdi-calendar-blank-outline"></i><h4>No procedures found</h4><p>Try adjusting your filters</p></div>'
                                );
                        }
                        return;
                    }

                    const $grid = $('<div></div>').addClass(viewMode === 'list' ? 'sw-proc-list' : 'sw-proc-grid');
                    procedures.forEach(p => $grid.append(buildCard(p)));
                    $container.append($grid);
                }

                /* ── Build a single procedure card ── */
                function buildCard(p) {
                    const priorityClass = `priority-${p.priority || 'routine'}`;
                    const statusClass = p.procedure_status.replace('_', '-');
                    const consentClass = p.consent_status || 'pending';
                    const avatarInitials = initials(p.patient_name);

                    const priorityBadge = (p.priority && p.priority !== 'routine') ?
                        `<span class="badge-priority ${p.priority}">${p.priority.toUpperCase()}</span>` :
                        '';

                    const consentBadge = p.consent_status ?
                        `<span class="badge-consent ${consentClass}">${consentLabel(p.consent_status)}</span>` :
                        '';

                    const scheduledLine = p.scheduled_date ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-calendar"></i>${p.scheduled_date}${p.scheduled_time ? ' ' + p.scheduled_time : ''}</span>` :
                        '';

                    const orLine = p.operating_room ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-map-marker-outline"></i>${p.operating_room}</span>` :
                        '';

                    const hmoLine = p.hmo ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-card-account-details-outline"></i>${p.hmo}</span>` :
                        '';

                    const requestedLine = p.requested_on ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-clock-outline"></i>Requested ${p.requested_on}</span>` :
                        '';

                    return `
    <div class="proc-card ${priorityClass}" data-proc-id="${p.id}" data-show-url="${p.show_url}">
        <div class="proc-card-header">
            <div>
                <div class="proc-card-name">${escHtml(p.service_name)}</div>
                ${p.category ? `<div class="proc-card-category">${escHtml(p.category)}</div>` : ''}
            </div>
            <div class="proc-card-badges">
                <span class="badge-status ${p.procedure_status}">${statusLabel(p.procedure_status)}</span>
                ${priorityBadge}
                ${consentBadge}
            </div>
        </div>
        <div class="proc-card-patient">
            <div class="proc-card-patient-avatar">${avatarInitials}</div>
            <div>
                <div class="proc-card-patient-name">${escHtml(p.patient_name)}</div>
                <div class="proc-card-patient-meta">
                    File: ${escHtml(p.file_no || '—')}
                    ${p.hmo ? ' · ' + escHtml(p.hmo) : ''}
                </div>
            </div>
        </div>
        <div class="proc-card-details">
            ${scheduledLine}
            ${orLine}
            ${hmoLine}
            ${requestedLine}
        </div>
        <div class="proc-card-footer">
            <div class="proc-card-footer-meta">
                ${p.team_count ? `<span><i class="mdi mdi-account-group-outline"></i> ${p.team_count} team member${p.team_count !== 1 ? 's' : ''}</span>` : '<span style="color:#c62828;font-weight:600;"><i class="mdi mdi-account-group-outline"></i> No team assigned</span>'}
                ${p.requested_by ? `<span><i class="mdi mdi-doctor"></i>${escHtml(p.requested_by)}</span>` : ''}
            </div>
            <a class="btn-open-proc" href="${p.show_url}" target="_blank" onclick="event.stopPropagation();">
                <i class="mdi mdi-open-in-new"></i> Open
            </a>
        </div>
    </div>`;
                }

                function escHtml(str) {
                    if (!str) return '';
                    return String(str)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                }

                /* ── Patient Search Integration ── */
                $('#sw-patient-search-widget-input').on('patient-selected', function(e, patientData) {
                    const name    = patientData.name;
                    const fileNo  = patientData.file_no || '';
                    const age     = patientData.age || '';
                    const gender  = patientData.gender || '';
                    const hmo     = patientData.hmo || '';
                    const total   = parseInt(patientData.total_procedures || 0);
                    const active  = parseInt(patientData.active_procedures || 0);
                    
                    searchTerm = '';
                    selectedPatientId = patientData.id;
                    
                    currentStatus = 'all';
                    $('.sw-stat-tab').removeClass('active');
                    $('#tab-all').addClass('active');
                    
                    // Build banner content
                    let meta = [];
                    if (fileNo) meta.push('<i class="mdi mdi-identifier"></i> ' + fileNo);
                    if (age)    meta.push(age + ' yrs');
                    if (gender && gender !== 'N/A') meta.push(gender);
                    if (hmo)    meta.push('<i class="mdi mdi-shield-plus-outline"></i> ' + hmo);
                    
                    let badges = '';
                    if (active > 0) badges += '<span class="spb-badge"><i class="mdi mdi-progress-clock"></i> ' + active + ' In OR</span>';
                    if (total  > 0) badges += '<span class="spb-badge">' + total + ' procedure' + (total > 1 ? 's' : '') + '</span>';
                    
                    $('#spb-name').text(name);
                    $('#spb-meta').html(meta.join(' &nbsp;&middot;&nbsp; ') + (badges ? '&nbsp;&nbsp;' + badges : ''));
                    $('#sw-patient-banner').addClass('visible');

                    // Initialize ClinicalOrdersKit for this patient
                    if (window.ClinicalOrdersKit && typeof window.ClinicalOrdersKit.init === 'function') {
                        window.ClinicalOrdersKit.init({
                            patientId: patientData.id
                        });
                    }

                    loadQueue();
                });

                function clearPatientFilter() {
                    searchTerm = '';
                    selectedPatientId = null;
                    $('#sw-patient-banner').removeClass('visible');
                    currentStatus = 'requested';
                    $('.sw-stat-tab').removeClass('active');
                    $('#tab-requested').addClass('active');
                    loadQueue();
                }

                $('#sw-patient-search-widget-input').on('patient-cleared', function() {
                    clearPatientFilter();
                });

                // Exit patient view (banner button)
                $('#spb-exit-btn').on('click', function() {
                    $('#sw-patient-search-widget-clear').trigger('click'); // Let widget handle UI state
                    clearPatientFilter();
                });

                // Book Procedure button
                $('#btn-book-procedure').on('click', function() {
                    if (window.ClinicalOrdersKit) {
                        $('#clinical_orders_modal').modal('show');
                        setTimeout(function() {
                            if (typeof window.ClinicalOrdersKit.switchCpSubTab === 'function') {
                                window.ClinicalOrdersKit.switchCpSubTab('cp-procedures');
                            } else {
                                $('#cp-procedures-tab').tab('show');
                            }
                        }, 200);
                    }
                });

                // Refresh queue when clinical orders modal is closed
                $('#clinical_orders_modal').on('hidden.bs.modal', function () {
                    if (refreshTimer) {
                        loadStats();
                        loadQueue();
                    }
                });

                /* ── Cache for re-render without reload ── */
                let _lastData = [];

                function renderFromCache() {
                    renderProcedures(_lastData);
                }

                /* ── Auto-refresh every 90s ── */
                function startAutoRefresh() {
                    clearInterval(refreshTimer);
                    refreshTimer = setInterval(function() {
                        loadStats();
                        // Silently reload queue in background
                        const params = {};
                        if (currentStatus && currentStatus !== 'all') params.status = currentStatus;
                        if (currentPriority) params.priority = currentPriority;
                        if (currentConsent) params.consent = currentConsent;
                        if (currentDate) params.date = currentDate;
                        if (searchTerm) params.search = searchTerm;
                        $.get(ROUTES.queue, params, function(resp) {
                            renderProcedures(resp.data || []);
                        });
                    }, 90000);
                }

                /* ── Init ── */
                $(function() {
                    loadStats();
                    loadQueue();
                    startAutoRefresh();

                    // Highlight in_progress tab if there are any in-progress procedures
                    setTimeout(function() {
                        const ipCount = parseInt($('#count-in-progress').text()) || 0;
                        if (ipCount > 0 && currentStatus === 'requested') {
                            // Visual pulse on in_progress tab
                            $('#tab-in-progress').css('animation', 'none');
                        }
                    }, 2000);
                });

            })();
        </script>
    @endpush
@endsection
