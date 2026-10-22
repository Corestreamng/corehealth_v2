@extends('admin.layouts.app')

@section('title', 'Surgery Workbench')
@section('is_workbench', true)
@section('body_class', 'is-workbench has-workbench')

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/workbench-cockpit-scaling.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/surgery-workbench.css') }}">
@endpush

@section('content')
    <div class="surgery-workbench-container sw-page">

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
            <div style="margin-left: auto; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <button class="btn btn-sm btn-light" id="btn-edit-patient" title="Edit Patient" style="display: none; font-size: 0.82rem; font-weight: 600; padding: 3px 12px; border-radius: 6px;">
                    <i class="mdi mdi-pencil"></i> Edit
                </button>
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
        <script src="{{ versioned_asset('js/patient-search-widget.js') }}"></script>
        @include('admin.partials.treatment-plan-viewer-modal')
        <script src="{{ versioned_asset('js/clinical-orders-shared.js') }}"></script>
        <script>
            window.SURGERY_WORKBENCH_CONFIG = {
                routes: {
                    queueCounts: @json(route('surgery-workbench.queue-counts')),
                    queue: @json(route('surgery-workbench.queue')),
                    searchPatients: @json(route('surgery-workbench.search-patients')),
                }
            };
        </script>
        <script src="{{ versioned_asset('js/surgery-workbench.js') }}"></script>
    @endpush
    @include('admin.partials.patient-form-modal')
@endsection
