@extends('admin.layouts.app')
@section('title', 'HMO Workbench')
@section('page_name', 'HMO Management')
@section('subpage_name', 'HMO Workbench')
@section('content')

<style>
    :root {
        --hospital-primary: {{ appsettings('hos_color', '#007bff') }};
    }

    /* Modern Card Styling */
    .stat-card-modern {
        border-radius: 12px;
        border: none;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-card-modern .card-body {
        padding: 1.25rem;
    }

    .stat-card-modern h6 {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
        opacity: 0.8;
    }

    .stat-card-modern h2 {
        font-weight: 700;
        margin-bottom: 0;
    }

    .stat-card-modern .stat-icon {
        font-size: 3rem;
        opacity: 0.3;
    }

    /* Queue Card Styling */
    .queue-card-modern {
        border-radius: 10px;
        border: none;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .queue-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .queue-card-modern .card-body {
        padding: 1rem;
    }

    .queue-card-modern h6 {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
        opacity: 0.9;
    }

    .queue-card-modern h3 {
        font-weight: 700;
        margin-bottom: 0;
    }

    .queue-card-modern .queue-icon {
        font-size: 2rem;
        opacity: 0.4;
    }

    /* Filter Card Modern */
    .filter-card-modern {
        border-radius: 12px;
        border: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .filter-card-modern .card-header {
        border-radius: 12px 12px 0 0;
        background: linear-gradient(135deg, var(--hospital-primary) 0%, #5a67d8 100%);
        padding: 0.75rem 1.25rem;
    }

    /* Tabs Modern */
    .workbench-tabs-modern .nav-link {
        border-radius: 8px 8px 0 0;
        font-weight: 500;
        padding: 0.75rem 1.25rem;
        transition: all 0.2s;
    }

    .workbench-tabs-modern .nav-link.active {
        background: var(--hospital-primary);
        color: white;
        border-color: var(--hospital-primary);
    }

    /* Page Header */
    .workbench-header {
        margin-bottom: 1.5rem;
    }

    .workbench-title {
        font-weight: 700;
        color: var(--hospital-primary);
        margin-bottom: 0.25rem;
    }

    .workbench-subtitle {
        color: #6c757d;
        margin-bottom: 0;
    }

    .workbench-date {
        color: #6c757d;
        font-size: 0.9rem;
    }

    /* Action Link Style */
    .stat-action-link {
        font-size: 0.8rem;
        margin-top: 0.5rem;
        display: inline-block;
    }

    .stat-action-link:hover {
        opacity: 1;
    }

    /* Clinical Context Modal Styling */
    #clinical-context-modal .modal-dialog {
        max-width: 90vw;
    }

    #clinical-context-modal .modal-body {
        padding: 0;
        max-height: 80vh;
        overflow-y: auto;
    }

    #clinical-tabs {
        border-bottom: 1px solid #dee2e6;
        background: #f8f9fa;
        padding: 0.5rem 1rem 0 1rem;
    }

    #clinical-tabs .nav-link {
        border: none;
        color: #6c757d;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        border-radius: 0.5rem 0.5rem 0 0;
        transition: all 0.2s;
    }

    #clinical-tabs .nav-link:hover {
        background: #e9ecef;
        color: #495057;
    }

    #clinical-tabs .nav-link.active {
        background: white;
        color: var(--hospital-primary);
        border-bottom: 2px solid var(--hospital-primary);
    }

    .clinical-tab-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .clinical-tab-header h6 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #212529;
    }

    .clinical-tab-body {
        padding: 1rem;
        max-height: 60vh;
        overflow-y: auto;
    }

    .refresh-clinical-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Vital Entry Cards */
    .vital-entry {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .vital-entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    .vital-date {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }

    .vital-entry-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
    }

    .vital-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        transition: all 0.2s;
        cursor: help;
    }

    .vital-item:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }

    .vital-item i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: #6c757d;
    }

    .vital-value {
        font-size: 1.25rem;
        font-weight: 600;
        color: #212529;
    }

    .vital-label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    /* Note Entry Cards */
    .note-entry {
        background: white;
        border: 1px solid #e9ecef;
        border-left: 4px solid var(--hospital-primary);
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .note-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .note-doctor {
        font-weight: 600;
        color: #212529;
    }

    .note-date {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .note-diagnosis {
        margin-bottom: 0.75rem;
    }

    .diagnosis-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: #e7f3ff;
        color: var(--hospital-primary);
        border-radius: 1rem;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .note-content {
        color: #495057;
        line-height: 1.6;
    }

    .specialty-tag {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        background: #6c757d;
        color: white;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        margin-left: 0.5rem;
    }

    /* Medication Cards */
    .medication-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .medication-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
    }

    .medication-name {
        font-weight: 600;
        color: #212529;
        font-size: 1rem;
    }

    .medication-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-active {
        background: #d4edda;
        color: #155724;
    }

    .status-completed {
        background: #d1ecf1;
        color: #0c5460;
    }

    .medication-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #e9ecef;
    }

    .medication-detail-item {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .medication-detail-item strong {
        color: #495057;
    }

    /* Cursor pointer for cards */
    .cursor-pointer {
        cursor: pointer;
    }

    .cursor-pointer:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.2s;
    }

    /* ── Patient Focus Mode ─────────────────────────────────────── */
    .pf-panel { display: none; }
    .pf-panel.active { display: block; }
    .pf-search-wrap { position: relative; z-index: 1060; }
    .pf-search-wrap .pf-results {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1070;
        background: #fff; border: 1px solid #dee2e6; border-top: none;
        border-radius: 0 0 8px 8px; max-height: 340px; overflow-y: auto;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15); display: none;
    }
    .pf-results .pf-result-item {
        padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f0f0f0;
        display: flex; align-items: center; gap: 12px; transition: background 0.15s;
    }
    .pf-results .pf-result-item:hover { background: #f0f4ff; }
    .pf-results .pf-result-item.pf-ri-active { background: #e8f0fe; }
    .pf-results .pf-result-item img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    .pf-results .pf-result-item .pf-ri-info { flex: 1; }
    .pf-results .pf-result-item .pf-ri-name { font-weight: 600; font-size: 0.9rem; }
    .pf-results .pf-result-item .pf-ri-meta { font-size: 0.78rem; color: #6c757d; }
    .pf-results .pf-result-item .pf-ri-badge { font-size: 0.7rem; }

    /* Recent patients chips */
    .pf-recent-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
    .pf-recent-chip {
        display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px;
        border-radius: 20px; background: #f0f4ff; border: 1px solid #d6e0f5;
        font-size: 0.8rem; font-weight: 500; color: #4a5568; cursor: pointer;
        transition: all 0.15s;
    }
    .pf-recent-chip:hover { background: #dbeafe; border-color: #93b4f5; }
    .pf-recent-chip img { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; }

    /* Welcome/empty state */
    .pf-welcome {
        text-align: center; padding: 4rem 2rem;
        background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
        border-radius: 12px; border: 2px dashed #d6e0f5;
    }
    .pf-welcome i { font-size: 4rem; color: #93b4f5; margin-bottom: 1rem; display: block; }
    .pf-welcome h5 { font-weight: 700; color: #4a5568; }
    .pf-welcome p { color: #6c757d; font-size: 0.9rem; max-width: 400px; margin: 0 auto; }

    .pf-patient-card {
        border-radius: 12px; border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .pf-patient-card .pf-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid var(--hospital-primary); }
    .pf-patient-card .pf-name { font-weight: 700; font-size: 1.1rem; margin-bottom: 2px; }
    .pf-patient-card .pf-detail { font-size: 0.82rem; color: #6c757d; }
    .pf-patient-card .pf-detail i { width: 18px; text-align: center; }
    .pf-patient-card .pf-balance-positive { color: #28a745; font-weight: 700; }
    .pf-patient-card .pf-balance-negative { color: #dc3545; font-weight: 700; }
    .pf-patient-card .pf-balance-zero { color: #6c757d; font-weight: 700; }
    .pf-quick-actions { display: flex; flex-direction: column; gap: 6px; padding: 10px; }
    .pf-quick-actions .btn { font-size: 0.8rem; border-radius: 8px; text-align: left; padding: 6px 12px; }

    .pf-stats-row .pf-stat-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 600;
        background: #f0f4ff; color: #4a5568; margin-right: 8px; margin-bottom: 6px;
    }
    .pf-stats-row .pf-stat-chip .pf-stat-count { font-size: 1rem; font-weight: 700; }

    #pfTabs .nav-link { font-weight: 500; padding: 0.6rem 1rem; border-radius: 8px 8px 0 0; font-size: 0.88rem; }
    #pfTabs .nav-link.active { background: var(--hospital-primary); color: #fff; border-color: var(--hospital-primary); }
    #pfTabs .nav-link .badge { font-size: 0.72rem; }

    .pf-request-table { font-size: 0.85rem; }
    .pf-request-table th { font-size: 0.78rem; font-weight: 600; white-space: nowrap; background: #f8f9fa; }
    .pf-request-table td { vertical-align: middle; }
    .pf-request-table .btn-sm { font-size: 0.75rem; border-radius: 4px; white-space: nowrap; }
    .pf-action-cell { display: flex; flex-direction: column; gap: 3px; min-width: 90px; }
    .pf-action-cell .btn { display: block; width: 100%; text-align: left; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; }

    .pf-empty-state { text-align: center; padding: 3rem 1rem; color: #adb5bd; }
    .pf-empty-state i { font-size: 3rem; margin-bottom: 0.5rem; display: block; }
</style>

<section class="content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center workbench-header">
            <div>
                <h3 class="workbench-title">
                    <i class="mdi mdi-shield-check mr-2"></i>HMO Executive Workbench
                </h3>
                <p class="workbench-subtitle">Claims Validation & Management Dashboard</p>
            </div>
            <div class="workbench-date d-flex align-items-center" style="gap: 8px;">
                <div class="btn-group" id="viewSwitcher" style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                    <button class="btn btn-sm view-switch-btn active" data-view="patient" style="font-weight: 600; border: none; padding: 6px 14px;">
                        <i class="mdi mdi-account-search mr-1"></i>Patient
                    </button>
                    <button class="btn btn-sm view-switch-btn" data-view="stats" style="font-weight: 600; border: none; padding: 6px 14px;">
                        <i class="mdi mdi-chart-bar mr-1"></i>Stats
                    </button>
                    <button class="btn btn-sm view-switch-btn" data-view="queue" style="font-weight: 600; border: none; padding: 6px 14px;">
                        <i class="mdi mdi-view-dashboard mr-1"></i>Queue
                    </button>
                </div>
                @if(appsettings()->enable_ei_hmo)
<button class="btn btn-danger btn-sm" onclick="showEmergencyIntakeModal()">
                    <i class="mdi mdi-ambulance"></i> Emergency Intake
                </button>
@endif
                <span><i class="mdi mdi-calendar mr-1"></i>{{ date('l, F j, Y') }}</span>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- STATS PANEL                                            --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div id="statsPanel" style="display: none;">
            <!-- Financial Summary -->
            <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Pending Claims Value</h6>
                                <h2 id="pending_claims_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-cash-multiple stat-icon"></i>
                        </div>
                        <a href="javascript:void(0)" class="text-white-50 stat-action-link preset-card" data-preset="">
                            <i class="mdi mdi-arrow-right mr-1"></i>View Pending
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Approved Today Value</h6>
                                <h2 id="approved_today_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-cash-check stat-icon"></i>
                        </div>
                        <a href="javascript:void(0)" class="text-white-50 stat-action-link preset-card" data-preset="today_approved">
                            <i class="mdi mdi-arrow-right mr-1"></i>View Approved
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Rejected Today Value</h6>
                                <h2 id="rejected_today_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-cash-remove stat-icon"></i>
                        </div>
                        <a href="javascript:void(0)" class="text-white-50 stat-action-link preset-card" data-preset="today_rejected">
                            <i class="mdi mdi-arrow-right mr-1"></i>View Rejected
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Monthly Claims Total</h6>
                                <h2 id="monthly_claims_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-calendar-month stat-icon"></i>
                        </div>
                        <small class="text-white-50">{{ date('F Y') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="queue-card-modern text-white" data-tab-target="pending" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Pending Validation</h6>
                                <h3 id="pending_count">0</h3>
                            </div>
                            <i class="mdi mdi-clock-alert queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white" data-tab-target="express" style="background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%); cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Express (Auto)</h6>
                                <h3 id="express_count">0</h3>
                            </div>
                            <i class="mdi mdi-flash queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="today_approved" style="background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Approved Today</h6>
                                <h3 id="approved_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-up queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="today_rejected" style="background: linear-gradient(135deg, #f45c43 0%, #eb3349 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Rejected Today</h6>
                                <h3 id="rejected_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-down queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="overdue" style="background: linear-gradient(135deg, #434343 0%, #000000 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Overdue (>4h)</h6>
                                <h3 id="overdue_count">0</h3>
                            </div>
                            <i class="mdi mdi-alert-circle queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white" id="emergency-hmo-card" style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); display: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Emergency Patients</h6>
                                <h3 id="emergency_count">0</h3>
                            </div>
                            <i class="fa fa-bolt queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="high_value" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                    <div class="card-body text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">High Value (>50k)</h6>
                                <h3>🎯</h3>
                            </div>
                            <i class="mdi mdi-currency-ngn queue-icon" style="opacity: 0.2;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <!-- Admin & Report Shortcuts -->
            <div class="card-modern border-0 mt-4" style="border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <div class="card-body">
                    <h5 class="mb-3" style="font-weight: 700; color: var(--hospital-primary, #1a73e8);"><i class="mdi mdi-lightning-bolt mr-1"></i>Quick Access</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="mdi mdi-file-chart mr-1"></i>Reports</h6>
                            <div class="list-group list-group-flush">
                                <a href="{{ route('hmo.reports') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-chart-bar mr-2 text-primary"></i>Reports Dashboard</a>
                                <a href="{{ route('hmo.reports.claims') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-clipboard-list mr-2 text-info"></i>Claims Report</a>
                                <a href="{{ route('hmo.reports.outstanding') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-clock-alert mr-2 text-warning"></i>Outstanding Report</a>
                                <a href="{{ route('hmo.reports.monthly') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-calendar-month mr-2 text-success"></i>Monthly Summary</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="mdi mdi-chart-line mr-1"></i>Analytics</h6>
                            <div class="list-group list-group-flush">
                                <a href="{{ route('hmo.reports.utilization') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-chart-pie mr-2 text-purple"></i>Utilization Report</a>
                                <a href="{{ route('hmo.reports.auth-codes') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-key mr-2 text-danger"></i>Auth Codes</a>
                                <a href="{{ route('hmo.reports.remittances') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-bank-transfer mr-2 text-success"></i>Remittances</a>
                                <a href="{{ route('hmo.export-claims') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-download mr-2 text-secondary"></i>Export Claims</a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted mb-2"><i class="mdi mdi-cog mr-1"></i>Administration</h6>
                            <div class="list-group list-group-flush">
                                <a href="{{ route('hmo-tariffs.index') }}" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-currency-ngn mr-2 text-primary"></i>Tariff Management</a>
                                <a href="{{ route('hmo.reports') }}#patient" class="list-group-item list-group-item-action border-0 px-2 py-2" style="border-radius:8px;"><i class="mdi mdi-account-group mr-2 text-info"></i>Patient Report</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> {{-- end #statsPanel --}}

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- PATIENT FOCUS PANEL (primary/default view)             --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div id="patientFocusPanel" class="pf-panel active">
            <!-- Search bar -->
            <div class="card-modern border-0 mb-3" style="border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: visible; position: relative; z-index: 10;">
                <div class="card-body py-3" style="overflow: visible;">
                    <div class="pf-search-wrap">
                        <div class="input-group input-group-lg">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="border-radius: 10px 0 0 10px; background: var(--hospital-primary); color: #fff; border: none;">
                                    <i class="mdi mdi-account-search" style="font-size: 1.3rem;"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control" id="pfSearchInput" placeholder="Search patient by name, file number, phone number, or HMO number..." style="border-radius: 0 10px 10px 0; border-left: none; font-size: 1rem; padding: 10px 16px;" autocomplete="off">
                        </div>
                        <div class="pf-results" id="pfSearchResults"></div>
                    </div>
                    <!-- Recent patients -->
                    <div class="pf-recent-bar" id="pfRecentBar" style="display: none;">
                        <span class="text-muted small"><i class="mdi mdi-clock-outline mr-1"></i>Recent:</span>
                        <div id="pfRecentChips"></div>
                    </div>
                </div>
            </div>

            <!-- Welcome state (shown when no patient selected) -->
            <div id="pfWelcome" class="pf-welcome">
                <i class="mdi mdi-account-search-outline"></i>
                <h5>Search for a Patient</h5>
                <p>Type a patient name, file number, or HMO number above to view and manage their HMO requests.</p>
            </div>

            <!-- Filter bar (shown after patient selection) -->
            <div id="pfFilterBar" class="card-modern border-0 mb-3" style="display:none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body py-2">
                    <div class="row align-items-end" style="gap: 4px 0;">
                        <div class="col-md-2">
                            <label class="small text-muted mb-0">Date From</label>
                            <input type="date" class="form-control form-control-sm pf-filter" id="pfFilterDateFrom" style="border-radius:8px;">
                        </div>
                        <div class="col-md-2">
                            <label class="small text-muted mb-0">Date To</label>
                            <input type="date" class="form-control form-control-sm pf-filter" id="pfFilterDateTo" style="border-radius:8px;">
                        </div>
                        <div class="col">
                            <label class="small text-muted mb-0">Type</label>
                            <select class="form-control form-control-sm pf-filter" id="pfFilterType" style="border-radius:8px;">
                                <option value="">All Types</option>
                                <option value="product">Product</option>
                                <option value="service">Service</option>
                                <option value="procedure">Procedure</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="small text-muted mb-0">Coverage</label>
                            <select class="form-control form-control-sm pf-filter" id="pfFilterCoverage" style="border-radius:8px;">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small text-muted mb-0">Search Requests</label>
                            <input type="text" class="form-control form-control-sm pf-filter" id="pfFilterSearch" placeholder="Item name, code..." style="border-radius:8px;">
                        </div>
                        <div class="col-auto d-flex" style="gap:4px;">
                            <button class="btn btn-sm btn-outline-info" id="pfShowAllDates" style="border-radius:8px;" title="Show all dates">
                                <i class="mdi mdi-calendar-remove"></i> All
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" id="pfFilterReset" style="border-radius:8px;" title="Clear all filters">
                                <i class="mdi mdi-filter-remove"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" id="pfRefreshData" style="border-radius:8px;" title="Refresh patient data">
                                <i class="mdi mdi-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient card + requests (shown after selection) -->
            <div id="pfContent" style="display: none;">
                <div class="row">
                    {{-- Left sidebar: Patient card --}}
                    <div class="col-md-3">
                        <div class="pf-patient-card card-modern mb-3">
                            <div class="card-body text-center">
                                <img src="" id="pfPatientPhoto" class="pf-avatar mb-2" alt="Patient">
                                <div class="pf-name d-flex justify-content-center align-items-center gap-2 mb-1">
                                    <span id="pfPatientName"></span>
                                    <button class="btn btn-sm btn-danger btn-manage-alerts p-1 ms-2" id="btn-manage-alerts" style="display:none; font-size: 0.75rem; border-radius: 4px;">
                                        <i class="mdi mdi-alert-octagon"></i> Alerts
                                    </button>
                                </div>
                                <div class="sticky-header-alerts text-start mb-2" style="max-height: 80px; overflow-y: auto;"></div>
                                <div class="pf-detail mb-1"><i class="mdi mdi-folder-account"></i> <span id="pfPatientFileNo"></span></div>
                                <div class="pf-detail mb-2" id="pfAgeRow"><i class="mdi mdi-cake-variant"></i> <span id="pfPatientAge"></span></div>
                                <hr class="my-2">
                                <div class="text-left">
                                    <div class="pf-detail mb-1"><i class="mdi mdi-hospital-building"></i> HMO: <strong id="pfPatientHmo"></strong></div>
                                    <div class="pf-detail mb-1"><i class="mdi mdi-card-account-details"></i> HMO#: <strong id="pfPatientHmoNo"></strong></div>
                                    <div class="pf-detail mb-1" id="pfSchemeRow" style="display:none;"><i class="mdi mdi-tag-outline"></i> Scheme: <strong id="pfPatientScheme"></strong></div>
                                    <div class="pf-detail mb-1"><i class="mdi mdi-phone"></i> <span id="pfPatientPhone"></span></div>
                                    <div class="pf-detail mb-1"><i class="mdi mdi-gender-male-female"></i> <span id="pfPatientGender"></span></div>
                                    <div class="pf-detail mb-1"><i class="mdi mdi-wallet"></i> Balance: <strong id="pfPatientBalance">₦0</strong></div>
                                </div>
                            </div>
                            <div class="pf-quick-actions">
                                <button class="btn btn-sm btn-outline-primary pf-view-clinical">
                                    <i class="mdi mdi-stethoscope mr-2"></i>Clinical Context
                                </button>
                                <button class="btn btn-sm btn-outline-warning" id="pfEditPatientBtn">
                                    <i class="mdi mdi-account-edit mr-2"></i>Edit Patient
                                </button>
                                <button class="btn btn-sm btn-outline-info pf-view-history">
                                    <i class="mdi mdi-history mr-2"></i>Claim History
                                </button>
                                <a class="btn btn-sm btn-outline-success" id="pfPrintReportLink" href="#" target="_blank">
                                    <i class="mdi mdi-printer mr-2"></i>Print Report
                                </a>
                                <a class="btn btn-sm btn-outline-secondary" id="pfOpenFileLink" href="#" target="_blank">
                                    <i class="mdi mdi-folder-open mr-2"></i>Open Patient File
                                </a>
                            </div>
                        </div>

                        <!-- Summary chips -->
                        <div class="card-modern border-0 mb-3" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-body py-2" id="pfSummaryChips">
                                <div class="pf-stats-row">
                                    <div class="pf-stat-chip" style="background: #fff3cd;"><span class="pf-stat-count" id="pfSumPending">0</span> Pending</div>
                                    <div class="pf-stat-chip" style="background: #e8eaf6;"><span class="pf-stat-count" id="pfSumAwaiting">0</span> Awaiting</div>
                                    <div class="pf-stat-chip" style="background: #d4edda;"><span class="pf-stat-count" id="pfSumApproved">0</span> Approved</div>
                                    <div class="pf-stat-chip" style="background: #d1ecf1;"><span class="pf-stat-count" id="pfSumExpress">0</span> Express</div>
                                    <div class="pf-stat-chip" style="background: #f8d7da;"><span class="pf-stat-count" id="pfSumRejected">0</span> Rejected</div>
                                </div>
                                <hr class="my-2">
                                <div class="pf-detail"><i class="mdi mdi-sigma text-primary"></i> Total Requests: <strong id="pfSumTotal">0</strong></div>
                                <div class="pf-detail"><i class="mdi mdi-cash-multiple text-success"></i> Approved Claims: <strong id="pfSumClaimsApproved">₦0</strong></div>
                                <div class="pf-detail"><i class="mdi mdi-cash-check text-info"></i> Approved Payable: <strong id="pfSumPayableApproved">₦0</strong></div>
                                <div class="pf-detail"><i class="mdi mdi-clock-alert text-warning"></i> Pending Claims: <strong id="pfSumClaimsPending">₦0</strong></div>
                            </div>
                        </div>
                    </div>

                    {{-- Right panel: Request tabs --}}
                    <div class="col-md-9">
                        <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="card-header bg-white" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                                <ul class="nav nav-tabs card-header-tabs" id="pfTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="tab" href="#pf-tab-pending" role="tab">
                                            <i class="mdi mdi-clock-alert mr-1"></i>Pending <span class="badge badge-warning ml-1" id="pfBadgePending">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#pf-tab-awaiting" role="tab">
                                            <i class="mdi mdi-key-alert mr-1"></i>Awaiting Code <span class="badge ml-1" id="pfBadgeAwaiting" style="background:#7c4dff; color:#fff;">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#pf-tab-approved" role="tab">
                                            <i class="mdi mdi-check-circle mr-1"></i>Approved <span class="badge badge-success ml-1" id="pfBadgeApproved">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#pf-tab-express" role="tab">
                                            <i class="mdi mdi-flash mr-1"></i>Express <span class="badge badge-info ml-1" id="pfBadgeExpress">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#pf-tab-rejected" role="tab">
                                            <i class="mdi mdi-close-circle mr-1"></i>Rejected <span class="badge badge-danger ml-1" id="pfBadgeRejected">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#pf-tab-past" role="tab">
                                            <i class="mdi mdi-cash mr-1"></i>Past/Billed <span class="badge badge-secondary ml-1" id="pfBadgePast">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#pf-tab-admissions" role="tab">
                                            <i class="mdi mdi-hospital-building mr-1"></i>Admissions <span class="badge badge-dark ml-1" id="pfBadgeAdmissions" style="display:none;">0</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body p-0">
                                <!-- Batch action bar (patient focus) -->
                                <div id="pfBatchBar" class="px-3 py-2" style="display:none; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-bottom: 1px solid #e9ecef;">
                                    <span class="mr-2 font-weight-bold"><span class="badge badge-primary" id="pfSelectedCount" style="border-radius:6px;">0</span> selected</span>
                                    <button class="btn btn-sm btn-success pf-batch-approve" style="border-radius:6px;"><i class="mdi mdi-check-all mr-1"></i>Approve</button>
                                    <button class="btn btn-sm btn-danger ml-1 pf-batch-reject" style="border-radius:6px;"><i class="mdi mdi-close-circle-multiple mr-1"></i>Reject</button>
                                </div>

                                <div class="tab-content">
                                    <!-- Pending tab -->
                                    <div class="tab-pane fade show active" id="pf-tab-pending" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover pf-request-table mb-0">
                                                <thead><tr>
                                                    <th width="30"><input type="checkbox" class="pf-select-all" data-tab="pending"></th>
                                                    <th>Item</th><th>Type</th><th>Qty</th><th>Claims</th><th>Payable</th><th>Coverage</th><th>Date</th><th>Actions</th>
                                                </tr></thead>
                                                <tbody id="pfBodyPending"></tbody>
                                            </table>
                                        </div>
                                        <div class="pf-pagination d-flex justify-content-between align-items-center px-3 py-2" data-tab="pending" style="border-top:1px solid #e9ecef; font-size:0.85rem;">
                                            <span class="text-muted pf-page-info">Showing 0 of 0</span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-prev" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-left"></i></button>
                                                <span class="mx-2 pf-page-num">1</span>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-next" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-right"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Awaiting Code tab -->
                                    <div class="tab-pane fade" id="pf-tab-awaiting" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover pf-request-table mb-0">
                                                <thead><tr>
                                                    <th width="30"><input type="checkbox" class="pf-select-all" data-tab="awaiting_code"></th>
                                                    <th>Item</th><th>Type</th><th>Qty</th><th>Claims</th><th>Payable</th><th>Auth Code</th><th>Date</th><th>Actions</th>
                                                </tr></thead>
                                                <tbody id="pfBodyAwaiting"></tbody>
                                            </table>
                                        </div>
                                        <div class="pf-pagination d-flex justify-content-between align-items-center px-3 py-2" data-tab="awaiting_code" style="border-top:1px solid #e9ecef; font-size:0.85rem;">
                                            <span class="text-muted pf-page-info">Showing 0 of 0</span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-prev" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-left"></i></button>
                                                <span class="mx-2 pf-page-num">1</span>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-next" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-right"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Approved tab -->
                                    <div class="tab-pane fade" id="pf-tab-approved" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover pf-request-table mb-0">
                                                <thead><tr>
                                                    <th>Item</th><th>Type</th><th>Qty</th><th>Claims</th><th>Payable</th><th>Auth Code</th><th>Validated By</th><th>Date</th><th>Actions</th>
                                                </tr></thead>
                                                <tbody id="pfBodyApproved"></tbody>
                                            </table>
                                        </div>
                                        <div class="pf-pagination d-flex justify-content-between align-items-center px-3 py-2" data-tab="approved" style="border-top:1px solid #e9ecef; font-size:0.85rem;">
                                            <span class="text-muted pf-page-info">Showing 0 of 0</span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-prev" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-left"></i></button>
                                                <span class="mx-2 pf-page-num">1</span>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-next" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-right"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Express tab -->
                                    <div class="tab-pane fade" id="pf-tab-express" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover pf-request-table mb-0">
                                                <thead><tr>
                                                    <th>Item</th><th>Type</th><th>Qty</th><th>Claims</th><th>Payable</th><th>Date</th><th>Actions</th>
                                                </tr></thead>
                                                <tbody id="pfBodyExpress"></tbody>
                                            </table>
                                        </div>
                                        <div class="pf-pagination d-flex justify-content-between align-items-center px-3 py-2" data-tab="express" style="border-top:1px solid #e9ecef; font-size:0.85rem;">
                                            <span class="text-muted pf-page-info">Showing 0 of 0</span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-prev" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-left"></i></button>
                                                <span class="mx-2 pf-page-num">1</span>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-next" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-right"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Rejected tab -->
                                    <div class="tab-pane fade" id="pf-tab-rejected" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover pf-request-table mb-0">
                                                <thead><tr>
                                                    <th>Item</th><th>Type</th><th>Qty</th><th>Claims</th><th>Payable</th><th>Reason</th><th>Validated By</th><th>Date</th><th>Actions</th>
                                                </tr></thead>
                                                <tbody id="pfBodyRejected"></tbody>
                                            </table>
                                        </div>
                                        <div class="pf-pagination d-flex justify-content-between align-items-center px-3 py-2" data-tab="rejected" style="border-top:1px solid #e9ecef; font-size:0.85rem;">
                                            <span class="text-muted pf-page-info">Showing 0 of 0</span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-prev" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-left"></i></button>
                                                <span class="mx-2 pf-page-num">1</span>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-next" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-right"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Past/Billed tab -->
                                    <div class="tab-pane fade" id="pf-tab-past" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover pf-request-table mb-0">
                                                <thead><tr>
                                                    <th>Item</th><th>Type</th><th>Qty</th><th>Claims</th><th>Payable</th><th>Coverage</th><th>Status</th><th>Date</th><th>Actions</th>
                                                </tr></thead>
                                                <tbody id="pfBodyPast"></tbody>
                                            </table>
                                        </div>
                                        <div class="pf-pagination d-flex justify-content-between align-items-center px-3 py-2" data-tab="past" style="border-top:1px solid #e9ecef; font-size:0.85rem;">
                                            <span class="text-muted pf-page-info">Showing 0 of 0</span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-prev" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-left"></i></button>
                                                <span class="mx-2 pf-page-num">1</span>
                                                <button class="btn btn-sm btn-outline-secondary pf-page-next" disabled style="border-radius:6px;"><i class="mdi mdi-chevron-right"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Admissions tab -->
                                    <div class="tab-pane fade" id="pf-tab-admissions" role="tabpanel">
                                        @include('admin.partials.admissions-module')
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- END: Patient Focus Panel --}}

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- MAIN QUEUE PANEL (toggled view)                        --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div id="mainQueuePanel" style="display: none;">

        <!-- Filters Card -->
        <div class="filter-card-modern mb-4">
            <div class="card-header text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-weight: 600;">
                    <i class="mdi mdi-filter-variant mr-2"></i>Filters & Search
                </h6>
                <a href="{{ route('hmo.export-claims') }}" class="btn btn-sm btn-light" id="exportBtn" style="border-radius: 6px;">
                    <i class="mdi mdi-download mr-1"></i>Export Claims
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Search</label>
                            <input type="text" class="form-control form-control-sm" id="search_input" placeholder="Patient name, file no, HMO no, request ID..." style="border-radius: 6px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">HMO</label>
                            <select class="form-control form-control-sm" id="filter_hmo" style="border-radius: 6px;">
                                <option value="">All HMOs</option>
                                @foreach($hmos as $hmo)
                                    <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Coverage Mode</label>
                            <select class="form-control form-control-sm" id="filter_coverage" style="border-radius: 6px;">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Service Type</label>
                            <select class="form-control form-control-sm" id="filter_service_type" style="border-radius: 6px;">
                                <option value="">All Types</option>
                                <option value="product">Products</option>
                                <option value="service">Services</option>
                                <option value="procedure">Procedures</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Date From</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_from" style="border-radius: 6px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Date To</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_to" style="border-radius: 6px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Validated By</label>
                            <select class="form-control form-control-sm select2" id="filter_validated_by" style="border-radius: 6px;">
                                <option value="">All Validators</option>
                                @foreach($validators as $v)
                                    <option value="{{ $v->id }}">{{ $v->firstname }} {{ $v->surname }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Reception</label>
                            <select class="form-control form-control-sm" id="filter_reception_validated" style="border-radius: 6px;">
                                <option value="">All</option>
                                <option value="1">Validated by Reception</option>
                                <option value="0">Not Validated</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group mb-2">
                            <label class="small">&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block btn-sm" id="applyFilters" style="border-radius: 6px; background: var(--hospital-primary); border-color: var(--hospital-primary);">
                                <i class="mdi mdi-magnify"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Actions Bar -->
        <div class="card-modern border-0 mb-3" id="batchActionsBar" style="display:none; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div class="card-body py-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="mr-3 font-weight-bold"><span id="selectedCount" class="badge badge-primary" style="border-radius: 6px; font-size: 0.9rem;">0</span> items selected</span>
                        <button type="button" class="btn btn-sm btn-success" id="batchApproveBtn" style="border-radius: 6px;">
                            <i class="mdi mdi-check-all mr-1"></i>Batch Approve
                        </button>
                        <button type="button" class="btn btn-sm btn-danger ml-2" id="batchRejectBtn" style="border-radius: 6px;">
                            <i class="mdi mdi-close-circle-multiple mr-1"></i>Batch Reject
                        </button>
                        <button type="button" class="btn btn-sm ml-2" id="batchAuthCodeBtn" style="border-radius: 6px; background: #7c4dff; color: #fff; border: none; display: none;">
                            <i class="mdi mdi-key-plus mr-1"></i>Enter Auth Codes
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSelectionBtn" style="border-radius: 6px;">
                            <i class="mdi mdi-close mr-1"></i>Clear Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs and DataTable -->
        <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div class="card-header bg-white" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                <ul class="nav nav-tabs card-header-tabs workbench-tabs-modern" id="workbenchTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                            <i class="mdi mdi-clock-alert mr-1"></i>Pending <span class="badge badge-warning ml-1" id="pending_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="express-tab" data-toggle="tab" href="#express" role="tab">
                            <i class="mdi mdi-flash mr-1"></i>Express <span class="badge badge-success ml-1" id="express_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="approved-tab" data-toggle="tab" href="#approved" role="tab">
                            <i class="mdi mdi-check-circle mr-1"></i>Approved <span class="badge badge-info ml-1" id="approved_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="awaiting-code-tab" data-toggle="tab" href="#awaiting_code" role="tab">
                            <i class="mdi mdi-key-alert mr-1"></i>Awaiting Code <span class="badge ml-1" id="awaiting_code_badge" style="border-radius: 6px; background:#7c4dff; color:#fff;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="rejected-tab" data-toggle="tab" href="#rejected" role="tab">
                            <i class="mdi mdi-close-circle mr-1"></i>Rejected <span class="badge badge-danger ml-1" id="rejected_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="claims-tab" data-toggle="tab" href="#claims" role="tab">
                            <i class="mdi mdi-cash mr-1"></i>Claims <span class="badge badge-primary ml-1" id="claims_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="all-tab" data-toggle="tab" href="#all" role="tab">
                            <i class="mdi mdi-view-list mr-1"></i>All <span class="badge badge-secondary ml-1" id="all_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="table-responsive">
                        <table id="requestsTable" class="table table-sm table-bordered table-striped table-hover display" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th width="30"><input type="checkbox" id="selectAllCheckbox" title="Select All"></th>
                                    <th>Patient & Actions</th>
                                    <th>Request Info</th>
                                    <th>Item Details</th>
                                    <th>Pricing</th>
                                    <th>Coverage & Payment</th>
                                    <th>Status & Validation</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div> {{-- END: mainQueuePanel --}}
    </div>
</section>
@include('admin.partials.clinical_context_modal')
@include('admin.partials.treatment-plan-viewer-modal')
@include('admin.partials.patient-form-modal')

<!-- View Details Modal -->
@include('admin.hmo.partials._modals')

@include('admin.hmo.partials._scripts')
