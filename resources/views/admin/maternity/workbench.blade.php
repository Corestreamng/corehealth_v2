@extends('admin.layouts.app')

@section('title', 'Maternity Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
@php
    $hosColor = appsettings()->hos_color ?? '#0066cc';
    $sett = appsettings();
@endphp
<style>
    :root {
        --hospital-primary: {{ appsettings('hos_color', '#007bff') }};
        --hospital-primary-rgb: 0, 123, 255;
        --maternity-primary: #e91e8f;
        --maternity-primary-light: #fce4f3;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
    }

    /* ── Workbench Container ── */
    .maternity-workbench-container {
        display: flex;
        height: calc(100vh - 70px);
        overflow: hidden;
        background: #f5f6fa;
    }

    /* ── Left Panel ── */
    #left-panel {
        width: 320px;
        min-width: 320px;
        background: #fff;
        border-right: 1px solid #e3e6f0;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }

    .panel-section {
        padding: 16px;
        border-bottom: 1px solid #f0f0f0;
    }

    .panel-section-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #8e94a4;
        margin-bottom: 10px;
    }

    /* Queue Items */
    .queue-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s;
        margin-bottom: 4px;
        font-size: 0.85rem;
        color: #4a5568;
    }
    .queue-item:hover { background: var(--maternity-primary-light); }
    .queue-item.active { background: var(--maternity-primary-light); color: var(--maternity-primary); font-weight: 600; }
    .queue-item .badge {
        min-width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    /* Quick Actions */
    .quick-action-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
        font-size: 0.82rem;
        color: #4a5568;
        transition: all 0.15s;
        margin-bottom: 6px;
    }
    .quick-action-btn:hover { border-color: var(--maternity-primary); color: var(--maternity-primary); background: var(--maternity-primary-light); }
    .quick-action-btn i { font-size: 1.1rem; width: 24px; text-align: center; }

    /* ── Main Workspace ── */
    #main-workspace {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #f5f6fa;
    }

    /* Empty State */
    #empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #a0aec0;
    }
    #empty-state i { font-size: 5rem; margin-bottom: 16px; }
    #empty-state h4 { font-weight: 700; color: #718096; }
    #empty-state p { font-size: 0.9rem; }

    /* Patient Header */
    #patient-header {
        display: none;
        padding: 16px 24px;
        background: #fff;
        border-bottom: 1px solid #e3e6f0;
    }
    #patient-header.active { display: flex; align-items: center; gap: 24px; }

    .patient-meta { flex: 1; }
    .patient-meta h5 { font-weight: 700; margin-bottom: 2px; }
    .patient-meta .meta-row { display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.8rem; color: #718096; }
    .patient-meta .meta-row span { display: flex; align-items: center; gap: 4px; }

    .enrollment-badges { display: flex; gap: 8px; flex-wrap: wrap; }
    .enrollment-badges .badge { font-size: 0.72rem; padding: 4px 10px; border-radius: 12px; }

    /* Workspace Tabs */
    .workspace-tabs {
        display: none;
        padding: 0 24px;
        background: #fff;
        border-bottom: 1px solid #e3e6f0;
        overflow-x: auto;
        white-space: nowrap;
    }
    .workspace-tabs.active { display: flex; }
    .workspace-tab {
        padding: 12px 16px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #8e94a4;
        border: none;
        background: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .workspace-tab:hover { color: var(--maternity-primary); }
    .workspace-tab.active { color: var(--maternity-primary); border-bottom-color: var(--maternity-primary); }

    /* Workspace Content */
    #workspace-content {
        display: none;
        flex: 1;
        overflow-y: auto;
        padding: 24px;
    }
    #workspace-content.active { display: block; }

    .workspace-tab-content { display: none; }
    .workspace-tab-content.active { display: block; }

    /* ── Risk Badges ── */
    .risk-low { background: #d4edda; color: #155724; }
    .risk-moderate { background: #fff3cd; color: #856404; }
    .risk-high { background: #f8d7da; color: #721c24; }
    .risk-very_high { background: #721c24; color: #fff; }

    /* ── Status Badges ── */
    .status-active { background: #cce5ff; color: #004085; }
    .status-delivered { background: #d4edda; color: #155724; }
    .status-postnatal { background: #e2d5f1; color: #563d7c; }
    .status-completed { background: #d6d8db; color: #383d41; }

    /* ── Coming Soon Placeholder ── */
    .coming-soon {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #a0aec0;
        text-align: center;
    }
    .coming-soon i { font-size: 3rem; margin-bottom: 12px; color: var(--maternity-primary); opacity: 0.4; }
    .coming-soon h5 { color: #718096; font-weight: 700; }
    .coming-soon p { font-size: 0.85rem; max-width: 400px; }

    /* ── Mobile ── */
    @media (max-width: 768px) {
        .maternity-workbench-container { flex-direction: column; height: auto; }
        #left-panel { width: 100%; min-width: unset; max-height: 50vh; }
        #main-workspace { height: 60vh; }
    }
</style>

<div class="maternity-workbench-container">
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{--                       LEFT PANEL                              --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="left-panel">
        {{-- Patient Search --}}
        <div class="panel-section">
            @include('admin.partials.patient_search_html')
        </div>

        {{-- Queues --}}
        <div class="panel-section">
            <div class="panel-section-title">
                <i class="mdi mdi-format-list-bulleted"></i> Queues
            </div>
            <div class="queue-item" data-queue="active-anc" onclick="showQueue('active-anc')">
                <span><i class="mdi mdi-human-pregnant"></i> Active ANC</span>
                <span class="badge bg-primary" id="queue-count-active-anc">0</span>
            </div>
            <div class="queue-item" data-queue="due-visits" onclick="showQueue('due-visits')">
                <span><i class="mdi mdi-calendar-clock"></i> Due for Visit</span>
                <span class="badge bg-warning text-dark" id="queue-count-due-visits">0</span>
            </div>
            <div class="queue-item" data-queue="upcoming-edd" onclick="showQueue('upcoming-edd')">
                <span><i class="mdi mdi-baby-carriage"></i> EDD This Week</span>
                <span class="badge bg-danger" id="queue-count-upcoming-edd">0</span>
            </div>
            <div class="queue-item" data-queue="postnatal" onclick="showQueue('postnatal')">
                <span><i class="mdi mdi-heart-pulse"></i> Postnatal</span>
                <span class="badge bg-info" id="queue-count-postnatal">0</span>
            </div>
            <div class="queue-item" data-queue="overdue-immunization" onclick="showQueue('overdue-immunization')">
                <span><i class="mdi mdi-needle"></i> Overdue Immunizations</span>
                <span class="badge bg-warning text-dark" id="queue-count-overdue-immunization">0</span>
            </div>
            <div class="queue-item" data-queue="high-risk" onclick="showQueue('high-risk')">
                <span><i class="mdi mdi-alert-circle"></i> High Risk</span>
                <span class="badge bg-danger" id="queue-count-high-risk">0</span>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="panel-section">
            <div class="panel-section-title">
                <i class="mdi mdi-lightning-bolt"></i> Quick Actions
            </div>
            <button class="quick-action-btn" onclick="showNewEnrollmentModal()">
                <i class="mdi mdi-plus-circle text-success"></i> New Enrollment
            </button>
            <button class="quick-action-btn" onclick="showReports()">
                <i class="mdi mdi-chart-bar text-info"></i> Reports
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{--                      MAIN WORKSPACE                           --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="main-workspace">
        {{-- Empty State --}}
        <div id="empty-state">
            <i class="mdi mdi-mother-nurse"></i>
            <h4>Maternity Workbench</h4>
            <p>Search for a patient or select from a queue to begin</p>
        </div>

        {{-- Queue View --}}
        <div id="queue-view" style="display:none;">
            <div style="padding: 24px;">
                <h5 id="queue-view-title" style="font-weight:700;"></h5>
                <div id="queue-view-content"></div>
            </div>
        </div>

        {{-- Reports View --}}
        <div id="reports-view" style="display:none;">
            <div style="padding: 24px;">
                <div class="coming-soon">
                    <i class="mdi mdi-chart-areaspline"></i>
                    <h5>Maternity Reports</h5>
                    <p>Comprehensive reporting for ANC coverage, delivery statistics, immunization coverage, and more. Coming soon.</p>
                </div>
            </div>
        </div>

        {{-- Patient Header --}}
        <div id="patient-header">
            <div class="patient-meta">
                <h5 id="patient-name"></h5>
                <div class="meta-row">
                    <span><i class="mdi mdi-card-account-details"></i> <span id="patient-file-no"></span></span>
                    <span><i class="mdi mdi-cake-variant"></i> <span id="patient-age"></span></span>
                    <span><i class="mdi mdi-phone"></i> <span id="patient-phone"></span></span>
                </div>
                <div class="meta-row mt-1" id="enrollment-meta" style="display:none;">
                    <span><i class="mdi mdi-calendar-heart"></i> LMP: <strong id="enrollment-lmp"></strong></span>
                    <span><i class="mdi mdi-baby-face-outline"></i> EDD: <strong id="enrollment-edd"></strong></span>
                    <span><i class="mdi mdi-timer-sand"></i> GA: <strong id="enrollment-ga"></strong></span>
                    <span>G<strong id="enrollment-gravida"></strong> P<strong id="enrollment-para"></strong> A<strong id="enrollment-abortions"></strong></span>
                </div>
            </div>
            <div class="enrollment-badges">
                <span class="badge" id="badge-risk" style="display:none;"></span>
                <span class="badge" id="badge-status" style="display:none;"></span>
            </div>
        </div>

        {{-- Workspace Tabs --}}
        <div class="workspace-tabs" id="workspace-tabs">
            <button class="workspace-tab active" data-tab="overview">Overview</button>
            <button class="workspace-tab" data-tab="anc-visits">ANC Visits</button>
            <button class="workspace-tab" data-tab="investigations">Investigations</button>
            <button class="workspace-tab" data-tab="prescriptions">Prescriptions</button>
            <button class="workspace-tab" data-tab="delivery">Delivery</button>
            <button class="workspace-tab" data-tab="baby-records">Baby Records</button>
            <button class="workspace-tab" data-tab="postnatal">Postnatal</button>
            <button class="workspace-tab" data-tab="growth-chart">Growth Chart</button>
            <button class="workspace-tab" data-tab="immunization">Immunization</button>
            <button class="workspace-tab" data-tab="timeline">Timeline</button>
            <button class="workspace-tab" data-tab="notes">Clinical Notes</button>
        </div>

        {{-- Workspace Tab Content --}}
        <div id="workspace-content">
            {{-- Overview Tab --}}
            <div class="workspace-tab-content active" id="overview-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-view-dashboard-outline"></i>
                    <h5>Enrollment Overview</h5>
                    <p>Pregnancy progress, risk alerts, recent activity, and enrollment details will be displayed here.</p>
                </div>
            </div>

            {{-- ANC Visits Tab --}}
            <div class="workspace-tab-content" id="anc-visits-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-clipboard-pulse-outline"></i>
                    <h5>ANC Visits</h5>
                    <p>Record and track antenatal care visits — weight, blood pressure, fundal height, foetal heart rate, urine analysis, and more.</p>
                </div>
            </div>

            {{-- Investigations Tab --}}
            <div class="workspace-tab-content" id="investigations-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-test-tube"></i>
                    <h5>Investigations</h5>
                    <p>Order lab tests (FBC, blood group, VDRL, HIV, urinalysis) and imaging (ultrasound) — requests go to the Lab and Imaging workbenches.</p>
                </div>
            </div>

            {{-- Prescriptions Tab --}}
            <div class="workspace-tab-content" id="prescriptions-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-pill"></i>
                    <h5>Prescriptions</h5>
                    <p>Prescribe medications (folic acid, iron, calcium, anti-malarials) — orders go to the Pharmacy workbench.</p>
                </div>
            </div>

            {{-- Delivery Tab --}}
            <div class="workspace-tab-content" id="delivery-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-baby-carriage"></i>
                    <h5>Delivery</h5>
                    <p>Partograph monitoring during labour. Record delivery summary, register newborn(s), and capture APGAR scores.</p>
                </div>
            </div>

            {{-- Baby Records Tab --}}
            <div class="workspace-tab-content" id="baby-records-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-baby-face-outline"></i>
                    <h5>Baby Records</h5>
                    <p>View registered babies, birth details, immediate newborn care checklist, and link to growth monitoring.</p>
                </div>
            </div>

            {{-- Postnatal Tab --}}
            <div class="workspace-tab-content" id="postnatal-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-mother-heart"></i>
                    <h5>Postnatal Care</h5>
                    <p>Record postnatal visits (WHO schedule: 24h, Day 3, Week 1-2, Week 6) for both mother and baby. Includes postpartum depression screening.</p>
                </div>
            </div>

            {{-- Growth Chart Tab --}}
            <div class="workspace-tab-content" id="growth-chart-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-chart-line"></i>
                    <h5>Growth Chart</h5>
                    <p>WHO Growth Standards — interactive weight-for-age, length-for-age, and head circumference charts from birth to 5 years.</p>
                </div>
            </div>

            {{-- Immunization Tab --}}
            <div class="workspace-tab-content" id="immunization-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-needle"></i>
                    <h5>Immunization</h5>
                    <p>Nigeria NPI immunization schedule (BCG, OPV, Penta, PCV, Rota, IPV, Measles, Yellow Fever, Meningitis). Reuses the existing immunization system from the Nursing Workbench.</p>
                </div>
            </div>

            {{-- Timeline Tab --}}
            <div class="workspace-tab-content" id="timeline-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-timeline-clock-outline"></i>
                    <h5>Timeline</h5>
                    <p>Chronological view of all events — ANC visits, investigations, prescriptions, delivery, postnatal visits, growth measurements, immunizations.</p>
                </div>
            </div>

            {{-- Clinical Notes Tab --}}
            <div class="workspace-tab-content" id="notes-tab">
                <div class="coming-soon">
                    <i class="mdi mdi-note-text-outline"></i>
                    <h5>Clinical Notes</h5>
                    <p>Doctor and nursing notes with maternity-specific templates (ANC Booking, Labour Admission, Delivery Note, Discharge Summary).</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════ --}}
{{--                        JAVASCRIPT                                  --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
<script>
    // ── State Variables ─────────────────────────────────────────────
    let currentPatientId = null;
    let currentEnrollmentId = null;

    // ── Hide All Views ──────────────────────────────────────────────
    function hideAllViews() {
        $('#empty-state').hide();
        $('#queue-view').removeClass('active').hide();
        $('#reports-view').removeClass('active').hide();
        $('#patient-header').removeClass('active');
        $('#workspace-tabs').removeClass('active');
        $('#workspace-content').removeClass('active').hide();
        $('.queue-item').removeClass('active');
    }

    // ── Show Queue ──────────────────────────────────────────────────
    function showQueue(queueType) {
        hideAllViews();
        $('#queue-view').show();
        $(`.queue-item[data-queue="${queueType}"]`).addClass('active');

        const titles = {
            'active-anc': 'Active ANC Mothers',
            'due-visits': 'Due for Visit Today',
            'upcoming-edd': 'EDD This Week',
            'postnatal': 'Postnatal Follow-up',
            'overdue-immunization': 'Overdue Immunizations',
            'high-risk': 'High-Risk Pregnancies'
        };
        $('#queue-view-title').text(titles[queueType] || 'Queue');
        $('#queue-view-content').html('<p class="text-muted">Loading...</p>');

        // TODO: Load queue data via AJAX
    }

    // ── Show Reports ────────────────────────────────────────────────
    function showReports() {
        hideAllViews();
        $('#reports-view').show();
    }

    // ── Load Patient ────────────────────────────────────────────────
    function loadPatient(patientId) {
        hideAllViews();
        currentPatientId = patientId;

        // Show patient workspace
        $('#patient-header').addClass('active');
        $('#workspace-tabs').addClass('active');
        $('#workspace-content').addClass('active').show();

        // TODO: Load patient details and enrollment via AJAX
        $('#patient-name').text('Loading...');
    }

    // ── New Enrollment Modal ────────────────────────────────────────
    function showNewEnrollmentModal() {
        // TODO: Open enrollment form modal
        alert('New Enrollment form coming soon — will allow selecting a patient, maternity service for billing, LMP, and booking details.');
    }

    // ── Tab Switching ───────────────────────────────────────────────
    $(document).ready(function() {
        // Tab click handler
        $('.workspace-tab').on('click', function() {
            const tab = $(this).data('tab');

            // Update tab buttons
            $('.workspace-tab').removeClass('active');
            $(this).addClass('active');

            // Update tab content
            $('.workspace-tab-content').removeClass('active');
            $(`#${tab}-tab`).addClass('active');
        });

        // Load queue counts on page load
        loadQueueCounts();

        // Refresh queue counts every 60 seconds
        setInterval(loadQueueCounts, 60000);
    });

    // ── Load Queue Counts ───────────────────────────────────────────
    function loadQueueCounts() {
        $.get("{{ route('maternity-workbench.queue.counts') }}", function(data) {
            $('#queue-count-active-anc').text(data.active_anc || 0);
            $('#queue-count-due-visits').text(data.due_visits || 0);
            $('#queue-count-upcoming-edd').text(data.upcoming_edd || 0);
            $('#queue-count-postnatal').text(data.postnatal || 0);
            $('#queue-count-overdue-immunization').text(data.overdue_immunization || 0);
            $('#queue-count-high-risk').text(data.high_risk || 0);
        });
    }
</script>
@endsection
