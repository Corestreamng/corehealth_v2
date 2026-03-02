@extends('admin.layouts.app')

@section('title', 'Maternity Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/clinical-orders-shared.css') }}">
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
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
        --maternity-pink: #e91e8a;
        --maternity-pink-rgb: 233, 30, 138;
    }

    /* ═══ SHARED: Main Layout (identical to nursing workbench) ═══ */
    .nursing-workbench-container {
        display: flex;
        min-height: calc(100vh - 100px);
        gap: 0;
    }

    .left-panel {
        width: 20%;
        min-width: 250px;
        border-right: 2px solid #e9ecef;
        display: flex;
        flex-direction: column;
        background: #f8f9fa;
    }

    /* ═══ SHARED: Search Container ═══ */
    .search-container {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    #patient-search-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
        font-size: 0.95rem;
    }

    #patient-search-input:focus {
        border-color: var(--maternity-pink);
        outline: none;
        box-shadow: 0 0 0 3px rgba(var(--maternity-pink-rgb), 0.1);
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: none;
    }

    .search-result-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f1f3f5;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .search-result-item:hover, .search-result-item.active { background: #f8f9fa; }
    .search-result-item img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    .search-result-info { flex: 1; }
    .search-result-name { font-weight: 600; color: #212529; margin-bottom: 0.25rem; }
    .search-result-details { font-size: 0.85rem; color: #6c757d; }

    .pending-badge {
        background: var(--maternity-pink);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* ═══ SHARED: Queue Widget ═══ */
    .queue-widget { padding: 1rem; border-bottom: 1px solid #dee2e6; }
    .queue-widget h6 { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 1rem; }

    .queue-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        background: white;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .queue-item:hover { transform: translateX(5px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .queue-item.active { border-left: 3px solid var(--maternity-pink); background: #fdf2f8; }
    .queue-item-label { font-size: 0.9rem; color: #495057; }

    .queue-count {
        font-size: 1.25rem;
        font-weight: 700;
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
    }
    .queue-count.anc { background: #fce4ec; color: #c2185b; }
    .queue-count.edd { background: #fff3e0; color: #e65100; }
    .queue-count.postnatal { background: #e3f2fd; color: #1565c0; }
    .queue-count.overdue { background: #ffebee; color: #c62828; }
    .queue-count.high-risk { background: #fbe9e7; color: #bf360c; }
    .queue-count.due-visit { background: #fff8e1; color: #f57f17; }

    .btn-queue-all {
        width: 100%;
        margin-top: 0.5rem;
        background: var(--maternity-pink);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-queue-all:hover { opacity: 0.9; transform: translateY(-2px); }

    /* ═══ SHARED: Quick Actions ═══ */
    .quick-actions { padding: 1rem; flex: 1; }
    .quick-actions h6 { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 1rem; }

    .quick-action-btn {
        width: 100%;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .quick-action-btn:hover:not(:disabled) { border-color: var(--maternity-pink); background: #fdf2f8; }
    .quick-action-btn:disabled { opacity: 0.5; cursor: not-allowed; background: #f5f5f5; }
    .quick-action-btn i { font-size: 1.25rem; }

    /* ═══ SHARED: Main Workspace ═══ */
    .main-workspace {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: white;
    }

    /* ═══ SHARED: Patient Header ═══ */
    .patient-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, var(--maternity-pink), #ad1457);
        color: white;
        display: none;
    }
    .patient-header.active { display: block; }

    .patient-header-top { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0; }
    .patient-name { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
    .patient-meta { display: flex; gap: 1.5rem; font-size: 0.95rem; opacity: 0.95; flex-wrap: wrap; }
    .patient-meta-item { display: flex; align-items: center; gap: 0.5rem; }

    .btn-expand-patient {
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.3);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        text-transform: lowercase;
        font-weight: 500;
    }
    .btn-expand-patient:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
    .btn-expand-patient.expanded i { transform: rotate(180deg); }

    .patient-details-expanded {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        border-top: 1px solid rgba(255,255,255,0.2);
        margin-top: 0;
    }
    .patient-details-expanded.show { max-height: 1000px; margin-top: 1rem; padding-top: 1rem; }

    .patient-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        padding: 0.5rem 0;
    }

    .patient-detail-item {
        background: rgba(255,255,255,0.15);
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid rgba(255,255,255,0.2);
    }
    .patient-detail-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; margin-bottom: 0.25rem; font-weight: 600; }
    .patient-detail-value { font-size: 0.95rem; font-weight: 500; word-break: break-word; }
    .patient-detail-item.full-width { grid-column: 1 / -1; }
    .patient-detail-value.text-content { max-height: 100px; overflow-y: auto; line-height: 1.5; font-size: 0.9rem; }

    .allergies-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
    .allergy-tag {
        background: rgba(220,53,69,0.2);
        border: 1px solid rgba(220,53,69,0.5);
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* ═══ SHARED: Empty State ═══ */
    .empty-state {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        text-align: center;
        padding: 2rem;
    }
    .empty-state i { font-size: 5rem; margin-bottom: 1.5rem; opacity: 0.3; }
    .empty-state h3 { font-size: 1.5rem; margin-bottom: 0.5rem; }
    .empty-state p { font-size: 1rem; margin-bottom: 1.5rem; }

    /* ═══ SHARED: Queue View ═══ */
    .queue-view { flex: 1; display: none; flex-direction: column; overflow: hidden; }
    .queue-view.active { display: flex; }

    .queue-view-header {
        padding: 1rem 1.5rem;
        background: white;
        border-bottom: 2px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .queue-view-header h4 { margin: 0; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }

    .btn-close-queue {
        padding: 0.5rem 1rem;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }
    .btn-close-queue:hover { background: #5a6268; }

    .queue-view-content { flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8f9fa; }

    /* ═══ SHARED: Queue Cards ═══ */
    .queue-card {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
        cursor: pointer;
    }
    .queue-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.15); transform: translateY(-2px); }
    .queue-card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem; }
    .queue-card-patient-name { font-size: 1.1rem; font-weight: 700; color: #2c3e50; margin-bottom: 0.25rem; }
    .queue-card-patient-meta { display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.875rem; color: #6c757d; }
    .queue-card-patient-meta-item { display: flex; align-items: center; gap: 0.25rem; }

    /* ═══ SHARED: Workspace Content ═══ */
    .workspace-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; display: none; min-height: 0; }
    .workspace-content.active { display: flex; }

    .workspace-tabs {
        display: flex;
        border-bottom: 2px solid #dee2e6;
        background: #f8f9fa;
        flex-shrink: 0;
        overflow-x: auto;
        overflow-y: hidden;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .workspace-tabs::-webkit-scrollbar { height: 4px; }
    .workspace-tabs::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }

    .workspace-tab {
        padding: 1rem 1.5rem;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-weight: 600;
        color: #6c757d;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .workspace-tab:hover { color: var(--maternity-pink); background: rgba(var(--maternity-pink-rgb), 0.05); }
    .workspace-tab.active { color: var(--maternity-pink); border-bottom-color: var(--maternity-pink); background: white; }

    .workspace-tab-badge {
        background: var(--danger);
        color: white;
        padding: 0.125rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .workspace-tab-content { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0; padding-bottom: 4rem; display: none; min-height: 0; }
    .workspace-tab-content.active { display: block; }

    /* ═══ SHARED: Panel Header ═══ */
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: var(--maternity-pink);
        color: white;
    }
    .panel-header h5 { margin: 0; font-size: 1rem; font-weight: 600; }
    .btn-view-work-pane {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
        display: none;
    }

    /* ═══ SHARED: Workspace Navbar ═══ */
    .workspace-navbar {
        display: none;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    .workspace-navbar-actions { display: flex; gap: 0.5rem; }
    .btn-back-to-search, .btn-toggle-search, .btn-clinical-context {
        background: var(--maternity-pink);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-clinical-context:hover:not(:disabled) {
        background: #c4177a;
    }
    .btn-clinical-context:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #6c757d;
    }

    /* ═══ Clinical Context Modal Overrides (nursing parity) ═══ */
    #clinical-context-modal .modal-dialog {
        max-width: 90vw;
    }
    #clinical-context-modal .modal-body {
        padding: 0;
        max-height: 80vh;
        overflow-y: auto;
    }
    /* Prevent medication cards from breaking out of modal */
    #clinical-meds-container {
        position: relative;
        max-width: 100%;
        overflow: hidden;
    }
    #clinical-meds-container .medication-card,
    #clinical-meds-container .card {
        position: relative !important;
        width: auto !important;
        max-width: 100% !important;
        height: auto !important;
        transform: none !important;
        top: auto !important;
        left: auto !important;
        right: auto !important;
        bottom: auto !important;
    }
    #clinical-meds-container * {
        position: relative !important;
        max-width: 100% !important;
    }

    /* ═══ MATERNITY: Rich Editor Styling ═══ */
    .ck-editor__editable_inline { min-height: 100px; max-height: 300px; }
    .ck-editor__editable_inline:focus { border-color: var(--maternity-pink) !important; box-shadow: 0 0 0 0.15rem rgba(233,30,99,0.15) !important; }
    .ck.ck-toolbar { border-radius: 0.5rem 0.5rem 0 0 !important; }
    .ck.ck-editor__main>.ck-editor__editable { border-radius: 0 0 0.5rem 0.5rem !important; }

    /* ═══ MATERNITY: Form Section Grouping ═══ */
    .mat-form-section { border: 1px solid #e9ecef; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; background: #fafbfc; }
    .mat-form-section-title { font-weight: 600; font-size: 0.85rem; color: var(--maternity-pink); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem; border-bottom: 1px solid #e9ecef; padding-bottom: 0.5rem; }
    .mat-form-help { font-size: 0.78rem; color: #6c757d; margin-top: 0.2rem; }
    .mat-form-help i { color: var(--maternity-pink); margin-right: 0.2rem; }
    .mat-tooltip-icon { color: var(--maternity-pink); cursor: help; margin-left: 0.3rem; font-size: 0.8rem; }
    .mat-info-banner { background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border: 1px solid #bbdefb; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.82rem; color: #37474f; display: flex; align-items: flex-start; gap: 0.5rem; }
    .mat-info-banner i { color: var(--maternity-pink); font-size: 1.1rem; margin-top: 0.1rem; }

    /* ═══ MATERNITY-SPECIFIC: Card Styles ═══ */
    .card-modern {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid #e9ecef;
        overflow: hidden;
    }
    .card-modern .card-header { padding: 0.75rem 1rem; font-size: 0.9rem; border-bottom: 1px solid #e9ecef; }
    .card-modern .card-body { padding: 1rem; }

    /* Enrollment Status Badges */
    .enrollment-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .enrollment-badge.active { background: #d4edda; color: #155724; }
    .enrollment-badge.delivered { background: #cce5ff; color: #004085; }
    .enrollment-badge.postnatal { background: #fff3cd; color: #856404; }
    .enrollment-badge.completed { background: #e2e3e5; color: #383d41; }
    .enrollment-badge.high-risk { background: #f8d7da; color: #721c24; }

    /* Risk Level Indicator */
    .risk-indicator { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.6rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .risk-indicator.low { background: #d4edda; color: #155724; }
    .risk-indicator.moderate { background: #fff3cd; color: #856404; }
    .risk-indicator.high { background: #f8d7da; color: #721c24; }

    /* Gestational Age Pill */
    .ga-pill { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.3rem 0.75rem; background: rgba(233,30,138,0.1); color: var(--maternity-pink); border-radius: 1rem; font-weight: 700; font-size: 0.85rem; }

    /* ANC Visit Card */
    .anc-visit-card {
        background: white;
        border-radius: 0.75rem;
        border-left: 4px solid var(--maternity-pink);
        padding: 1rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .anc-visit-card .visit-number { font-size: 0.8rem; font-weight: 700; color: var(--maternity-pink); text-transform: uppercase; }
    .anc-visit-card .visit-date { font-size: 0.85rem; color: #6c757d; }
    .anc-visit-card .visit-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem; margin-top: 0.75rem; }
    .anc-visit-card .visit-detail-label { font-size: 0.7rem; text-transform: uppercase; color: #6c757d; }
    .anc-visit-card .visit-detail-value { font-size: 0.9rem; font-weight: 600; color: #2c3e50; }

    /* Timeline */
    .timeline-container { position: relative; padding-left: 30px; }
    .timeline-container::before { content: ''; position: absolute; left: 12px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before { content: ''; position: absolute; left: -24px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: var(--maternity-pink); border: 2px solid white; box-shadow: 0 0 0 2px #dee2e6; }
    .timeline-item.booking::before { background: var(--info); }
    .timeline-item.anc_visit::before { background: var(--maternity-pink); }
    .timeline-item.delivery::before { background: var(--success); }
    .timeline-item.postnatal::before { background: var(--warning); }
    .timeline-date { font-size: 0.75rem; color: #6c757d; }
    .timeline-title { font-weight: 600; color: #2c3e50; }
    .timeline-detail { font-size: 0.85rem; color: #6c757d; }

    /* Partograph Grid */
    .partograph-chart { overflow-x: auto; }
    .partograph-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .partograph-table th, .partograph-table td { padding: 0.5rem; border: 1px solid #dee2e6; text-align: center; }
    .partograph-table th { background: #f8f9fa; font-weight: 600; white-space: nowrap; }

    /* Baby Card */
    .baby-card {
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e9ecef;
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }
    .baby-card .baby-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .baby-card .baby-name { font-size: 1.1rem; font-weight: 700; color: #2c3e50; }
    .baby-card .baby-sex { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.6rem; border-radius: 1rem; font-size: 0.8rem; font-weight: 600; }
    .baby-card .baby-sex.male { background: #e3f2fd; color: #1565c0; }
    .baby-card .baby-sex.female { background: #fce4ec; color: #c2185b; }
    .baby-card .baby-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; }
    .baby-card .baby-metric-label { font-size: 0.7rem; text-transform: uppercase; color: #6c757d; }
    .baby-card .baby-metric-value { font-size: 0.95rem; font-weight: 600; }

    /* Immunization Schedule */
    .imm-schedule-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid #f1f3f5; }
    .imm-schedule-item:last-child { border-bottom: none; }
    .imm-status-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .imm-status-dot.given { background: #28a745; }
    .imm-status-dot.overdue { background: #dc3545; }
    .imm-status-dot.upcoming { background: #dee2e6; }
    .imm-vaccine-name { font-weight: 600; color: #2c3e50; flex: 1; }
    .imm-age-label { font-size: 0.8rem; color: #6c757d; }
    .imm-due-date { font-size: 0.8rem; color: #6c757d; }

    /* ═══ SHARED: Responsive (identical to nursing workbench) ═══ */
    @media (max-width: 767px) {
        .nursing-workbench-container { flex-direction: column; }
        .left-panel { width: 100%; min-width: 100%; border-right: none; border-bottom: 2px solid #e9ecef; }
        .main-workspace { display: none; }
        .main-workspace.active { display: flex; }
        .left-panel.hidden { display: none; }
        .workspace-navbar { display: flex; }
        .btn-view-work-pane { display: flex; }
        .panel-header { display: flex; }
    }

    @media (min-width: 768px) {
        .left-panel { display: flex !important; }
        .left-panel.hidden { display: flex !important; width: 0; min-width: 0; overflow: hidden; border: none; padding: 0; }
        .main-workspace { display: flex !important; }
        .workspace-navbar { display: flex !important; }
        .btn-back-to-search { display: none !important; }
        .btn-toggle-search { display: flex !important; }
    }

    /* Form sections */
    .form-section { padding: 1.25rem; border-bottom: 1px solid #e9ecef; }
    .form-section-title { font-size: 0.9rem; font-weight: 700; color: var(--maternity-pink); text-transform: uppercase; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .form-section-title i { font-size: 1.1rem; }

    /* Stat cards */
    .mat-stat-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border-left: 4px solid transparent;
    }
    .mat-stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; }
    .mat-stat-value { font-size: 1.15rem; font-weight: 700; color: #2d3748; }
    .mat-stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: #718096; }

    .mat-stat-pink { border-left-color: var(--maternity-pink); }
    .mat-stat-pink .mat-stat-icon { background: linear-gradient(135deg, #e91e8a, #ad1457); }
    .mat-stat-green { border-left-color: #28a745; }
    .mat-stat-green .mat-stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
    .mat-stat-blue { border-left-color: #17a2b8; }
    .mat-stat-blue .mat-stat-icon { background: linear-gradient(135deg, #17a2b8, #007bff); }
    .mat-stat-orange { border-left-color: #fd7e14; }
    .mat-stat-orange .mat-stat-icon { background: linear-gradient(135deg, #fd7e14, #e65100); }
    .mat-stat-red { border-left-color: #dc3545; }
    .mat-stat-red .mat-stat-icon { background: linear-gradient(135deg, #dc3545, #b71c1c); }

    /* Timeline icons */
    .timeline-item { position: relative; padding-left: 28px; }
    .timeline-item .timeline-icon { position: absolute; left: 0; top: 2px; font-size: 1.1rem; }
    .timeline-item[style*="cursor:pointer"]:hover { background: rgba(0,0,0,0.02); border-radius: 4px; }

    /* Notes */
    .note-card { background: white; border-radius: 0.5rem; border-left: 3px solid var(--maternity-pink); padding: 0.75rem 1rem; margin-bottom: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .note-time { font-size: 0.75rem; color: #6c757d; }
    .note-author { font-weight: 600; font-size: 0.85rem; color: #2c3e50; }
    .note-body { font-size: 0.9rem; margin-top: 0.5rem; }
</style>

<!-- ═══════════════════════════════════════════════════════════════
     HTML STRUCTURE (shared layout with nursing workbench)
     ═══════════════════════════════════════════════════════════════ -->

<div class="nursing-workbench-container">
    <!-- ── Left Panel: Patient Search & Queue (SHARED PATTERN) ── -->
    <div class="left-panel" id="left-panel">
        <div class="panel-header">
            <h5><i class="mdi mdi-baby-carriage"></i> Maternity</h5>
            <button class="btn-view-work-pane" id="btn-view-work-pane" title="View Work Pane">
                <i class="fa fa-arrow-right"></i> Work Pane
            </button>
        </div>

        {{-- SHARED partial: patient search input + dropdown --}}
        @include('admin.partials.patient_search_html')

        <div class="queue-widget">
            <h6><i class="mdi mdi-clipboard-list"></i> MATERNITY QUEUES</h6>
            <div class="queue-item" data-filter="active-anc">
                <span class="queue-item-label"><i class="mdi mdi-mother-nurse"></i> Active ANC</span>
                <span class="queue-count anc" id="queue-active-anc-count">0</span>
            </div>
            <div class="queue-item" data-filter="due-visits">
                <span class="queue-item-label"><i class="mdi mdi-calendar-clock"></i> Due Visits</span>
                <span class="queue-count due-visit" id="queue-due-visits-count">0</span>
            </div>
            <div class="queue-item" data-filter="upcoming-edd">
                <span class="queue-item-label"><i class="mdi mdi-calendar-star"></i> Upcoming EDD</span>
                <span class="queue-count edd" id="queue-upcoming-edd-count">0</span>
            </div>
            <div class="queue-item" data-filter="postnatal">
                <span class="queue-item-label"><i class="mdi mdi-account-heart"></i> Postnatal</span>
                <span class="queue-count postnatal" id="queue-postnatal-count">0</span>
            </div>
            <div class="queue-item" data-filter="overdue-immunization">
                <span class="queue-item-label"><i class="mdi mdi-needle"></i> Overdue Immunizations</span>
                <span class="queue-count overdue" id="queue-overdue-imm-count">0</span>
            </div>
            <div class="queue-item" data-filter="high-risk" style="border-left: 3px solid #dc3545;">
                <span class="queue-item-label"><i class="mdi mdi-alert"></i> High Risk</span>
                <span class="queue-count high-risk" id="queue-high-risk-count">0</span>
            </div>
            <button class="btn-queue-all" id="refresh-queues-btn">
                <i class="mdi mdi-refresh"></i> Refresh Queues
            </button>
        </div>

        <div class="quick-actions">
            <h6><i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-enroll-patient" disabled title="Select a patient first">
                <i class="mdi mdi-clipboard-plus text-success"></i>
                <span>New Enrollment</span>
            </button>
            <button class="quick-action-btn" id="btn-quick-vitals" disabled title="Select a patient first">
                <i class="mdi mdi-heart-pulse text-danger"></i>
                <span>Quick Vitals</span>
            </button>
            <button class="quick-action-btn" id="btn-maternity-reports">
                <i class="mdi mdi-chart-box-outline text-info"></i>
                <span>Reports & Analytics</span>
            </button>
            <button class="quick-action-btn" id="btn-print-anc-card" disabled title="Enroll/select patient first">
                <i class="mdi mdi-printer text-primary"></i>
                <span>Print ANC Card</span>
            </button>
            <button class="quick-action-btn" id="btn-print-road-card" disabled title="Enroll/select patient first">
                <i class="mdi mdi-card-account-details-outline text-primary"></i>
                <span>Print Road to Health</span>
            </button>
            <button class="quick-action-btn" id="btn-maternity-audit" disabled title="Enroll/select patient first">
                <i class="mdi mdi-shield-search text-warning"></i>
                <span>Audit Trail</span>
            </button>
        </div>
    </div>

    <!-- ── Main Workspace (SHARED PATTERN) ── -->
    <div class="main-workspace" id="main-workspace">
        <!-- Navbar (mobile) -->
        <div class="workspace-navbar" id="workspace-navbar">
            <button class="btn-back-to-search" id="btn-back-to-search">
                <i class="fa fa-arrow-left"></i> Back
            </button>
            <div class="workspace-navbar-actions">
                <button class="btn-toggle-search" id="btn-toggle-search">
                    <i class="fa fa-bars"></i>
                </button>
                <button class="btn-clinical-context" id="btn-clinical-context" disabled title="Select a patient first">
                    <i class="fa fa-heartbeat"></i> Clinical Context
                </button>
            </div>
        </div>

        <!-- Empty State (SHARED PATTERN) -->
        <div class="empty-state" id="empty-state">
            <i class="mdi mdi-baby-carriage"></i>
            <h3>Select a patient to begin</h3>
            <p>Search for a female patient or pick from the maternity queues</p>
            <button class="btn btn-lg" id="view-queue-btn" style="background: var(--maternity-pink); color: white;">
                📋 View Active ANC Queue
            </button>
        </div>

        <!-- Queue View (SHARED PATTERN) -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-mother-nurse"></i> Active ANC</h4>
                <button class="btn-close-queue" id="btn-close-queue">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" id="queue-view-content">
                <p class="text-muted text-center py-4">Loading...</p>
            </div>
        </div>

        <!-- Reports View -->
        <div class="queue-view" id="reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box"></i> Maternity Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" id="reports-content">
                <div class="text-center p-4 text-muted"><i class="mdi mdi-loading mdi-spin mdi-48px"></i></div>
            </div>
        </div>

        <!-- Patient Header (SHARED PATTERN) -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div style="flex: 1;">
                    <div class="patient-name" id="patient-name"></div>
                    <div class="patient-meta" id="patient-meta"></div>
                </div>
                <button class="btn btn-sm btn-info" id="btn-print-anc-card" title="Print ANC Card" style="display:none;">
                    <i class="mdi mdi-card-account-details"></i> ANC Card
                </button>
                <button class="btn btn-sm btn-success" id="btn-print-road-card" title="Print Road to Health Card" style="display:none;">
                    <i class="mdi mdi-baby-face-outline"></i> Road to Health
                </button>
                <button class="btn-expand-patient" id="btn-expand-patient" title="Show more details">
                    <span class="btn-expand-text">more biodata</span>
                    <i class="mdi mdi-chevron-down"></i>
                </button>
            </div>
            <div class="patient-details-expanded" id="patient-details-expanded">
                <div class="patient-details-grid" id="patient-details-grid"></div>
            </div>
        </div>

        <!-- Workspace Content (SHARED PATTERN) -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="overview">
                    <i class="mdi mdi-view-dashboard"></i>
                    <span>Overview</span>
                </button>
                <button class="workspace-tab" data-tab="enrollment">
                    <i class="mdi mdi-clipboard-plus"></i>
                    <span>Enrollment</span>
                </button>
                <button class="workspace-tab" data-tab="history">
                    <i class="mdi mdi-clipboard-text-clock"></i>
                    <span>Mother's History</span>
                </button>
                <button class="workspace-tab" data-tab="anc">
                    <i class="mdi mdi-stethoscope"></i>
                    <span>ANC Visits</span>
                </button>
                <button class="workspace-tab" data-tab="clinical-orders">
                    <i class="mdi mdi-flask"></i>
                    <span>Clinical Orders</span>
                </button>
                <button class="workspace-tab" data-tab="delivery">
                    <i class="mdi mdi-baby-carriage"></i>
                    <span>Delivery</span>
                </button>
                <button class="workspace-tab" data-tab="baby">
                    <i class="mdi mdi-baby-face-outline"></i>
                    <span>Baby Records</span>
                </button>
                <button class="workspace-tab" data-tab="postnatal">
                    <i class="mdi mdi-account-heart"></i>
                    <span>Postnatal</span>
                </button>
                <button class="workspace-tab" data-tab="immunization">
                    <i class="mdi mdi-shield-check"></i>
                    <span>Immunization</span>
                </button>
                <button class="workspace-tab" data-tab="vitals">
                    <i class="mdi mdi-heart-pulse"></i>
                    <span>Vitals</span>
                </button>
                <button class="workspace-tab" data-tab="notes">
                    <i class="mdi mdi-note-text"></i>
                    <span>Notes</span>
                </button>
                <button class="workspace-tab" data-tab="audit">
                    <i class="mdi mdi-shield-search"></i>
                    <span>Audit Trail</span>
                </button>
            </div>

            <!-- ═══ OVERVIEW TAB ═══ -->
            <div class="workspace-tab-content active" id="overview-tab">
                <div class="p-3" id="overview-content">
                    <p class="text-muted text-center py-3">Select a patient to view overview</p>
                </div>
            </div>

            <!-- ═══ ENROLLMENT TAB ═══ -->
            <div class="workspace-tab-content" id="enrollment-tab">
                <div class="p-3">
                    <!-- Enrollment form (dynamic: show form if not enrolled, show details if enrolled) -->
                    <div id="enrollment-content">
                        <p class="text-muted text-center py-3">Select a patient first</p>
                    </div>
                </div>
            </div>

            <!-- ═══ MOTHER'S HISTORY TAB ═══ -->
            <div class="workspace-tab-content" id="history-tab">
                <div class="p-3">
                    <div id="history-content">
                        <p class="text-muted text-center py-3">Enroll patient to view history</p>
                    </div>
                </div>
            </div>

            <!-- ═══ ANC VISITS TAB ═══ -->
            <div class="workspace-tab-content" id="anc-tab">
                <div class="p-3">
                    <div id="anc-content">
                        <p class="text-muted text-center py-3">Enroll patient to record ANC visits</p>
                    </div>
                </div>
            </div>

            <!-- ═══ CLINICAL ORDERS TAB ═══ -->
            <div class="workspace-tab-content" id="clinical-orders-tab">
                <div class="p-3">
                    <div id="clinical-orders-content">
                        <p class="text-muted text-center py-3">Enroll patient to use clinical orders</p>
                    </div>
                </div>
            </div>

            <!-- ═══ DELIVERY TAB ═══ -->
            <div class="workspace-tab-content" id="delivery-tab">
                <div class="p-3">
                    <div id="delivery-content">
                        <p class="text-muted text-center py-3">Enroll patient to record delivery</p>
                    </div>
                </div>
            </div>

            <!-- ═══ BABY RECORDS TAB ═══ -->
            <div class="workspace-tab-content" id="baby-tab">
                <div class="p-3">
                    <div id="baby-content">
                        <p class="text-muted text-center py-3">Delivery must be recorded first</p>
                    </div>
                </div>
            </div>

            <!-- ═══ POSTNATAL TAB ═══ -->
            <div class="workspace-tab-content" id="postnatal-tab">
                <div class="p-3">
                    <div id="postnatal-content">
                        <p class="text-muted text-center py-3">Delivery must be recorded first</p>
                    </div>
                </div>
            </div>

            <!-- ═══ IMMUNIZATION TAB ═══ -->
            <div class="workspace-tab-content" id="immunization-tab">
                <div class="p-3">
                    <div id="immunization-content">
                        <p class="text-muted text-center py-3">Register baby to view immunization schedule</p>
                    </div>
                </div>
            </div>

            <!-- ═══ VITALS TAB (uses shared partial) ═══ -->
            <div class="workspace-tab-content" id="vitals-tab">
                @include('admin.partials.unified_vitals')
            </div>

            <!-- ═══ NOTES TAB ═══ -->
            <div class="workspace-tab-content" id="notes-tab">
                <div class="p-3">
                    <div id="notes-content">
                        <p class="text-muted text-center py-3">Select a patient to view notes</p>
                    </div>
                </div>
            </div>

            <!-- ═══ AUDIT TAB ═══ -->
            <div class="workspace-tab-content" id="audit-tab">
                <div class="p-3">
                    <div id="audit-content">
                        <p class="text-muted text-center py-3">Enroll/select patient to view audit trail</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shared Administer Vaccine Modal (reused from nursing workflow) --}}
<div class="modal fade" id="administerVaccineModalShared" tabindex="-1" role="dialog" aria-labelledby="administerVaccineModalSharedLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="administerVaccineModalSharedLabel">
                    <i class="mdi mdi-needle"></i> Administer Vaccine
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3" id="imm-modal-schedule-info">
                    <div class="row">
                        <div class="col-md-6"><strong><i class="mdi mdi-needle"></i> Vaccine:</strong> <span id="imm-modal-vaccine-name">-</span></div>
                        <div class="col-md-3"><strong><i class="mdi mdi-numeric"></i> Dose:</strong> <span id="imm-modal-dose-label">-</span></div>
                        <div class="col-md-3"><strong><i class="mdi mdi-calendar"></i> Due:</strong> <span id="imm-modal-due-date">-</span></div>
                    </div>
                </div>

                <div class="store-selection-panel mb-4 p-3 rounded" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #81c784;">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-2" style="font-size: 1rem;"><i class="mdi mdi-store text-success"></i> Step 1: Select Dispensing Store</label>
                            <select id="imm-modal-vaccine-store" class="form-control form-control-lg" style="border: 2px solid #388e3c; font-weight: 500;" required>
                                <option value="">-- Choose Store --</option>
                                @foreach($stores ?? [] as $store)
                                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div id="imm-modal-vaccine-store-info" class="p-3 bg-white rounded shadow-sm" style="display: none;">
                                <h6 class="text-success mb-2"><i class="mdi mdi-package-variant"></i> Store Stock</h6>
                                <div id="imm-modal-vaccine-store-stock" class="small"></div>
                            </div>
                            <div id="imm-modal-vaccine-store-placeholder" class="p-3 text-muted text-center">
                                <i class="mdi mdi-arrow-left-bold mdi-24px"></i>
                                <p class="mb-0 small">Select store first</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="imm-modal-vaccine-search"><i class="mdi mdi-magnify"></i> Step 2: Search Vaccine Product *</label>
                    <input type="text" class="form-control" id="imm-modal-vaccine-search" placeholder="Type to search for vaccine product from inventory..." autocomplete="off">
                    <ul class="list-group" id="imm-modal-vaccine-results" style="display:none; position:absolute; z-index:1050; max-height:200px; overflow-y:auto; width:calc(100% - 30px); box-shadow:0 4px 6px rgba(0,0,0,0.1);"></ul>
                </div>

                <div class="card-modern mb-3 d-none" id="imm-modal-selected-product-card">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="imm-modal-selected-product-name">-</strong>
                                <br><small class="text-muted" id="imm-modal-selected-product-details">-</small>
                                <br><small id="imm-modal-selected-product-stock" class="text-success"></small>
                            </div>
                            <div class="text-right">
                                <span class="badge badge-primary" id="imm-modal-selected-product-price">₦0.00</span>
                                <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="imm-modal-remove-product"><i class="mdi mdi-close"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="imm-modal-schedule-id">
                <input type="hidden" id="imm-modal-product-id">

                <h6 class="text-muted mb-3"><i class="mdi mdi-clipboard-text"></i> Step 3: Administration Details</h6>
                <form id="imm-modal-immunization-form">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-site"><i class="mdi mdi-map-marker"></i> Administration Site *</label>
                            <select class="form-control" id="imm-modal-vaccine-site" required>
                                <option value="">Select Site</option>
                                <option value="Left Deltoid">Left Deltoid (Arm)</option>
                                <option value="Right Deltoid">Right Deltoid (Arm)</option>
                                <option value="Left Vastus Lateralis">Left Vastus Lateralis (Thigh)</option>
                                <option value="Right Vastus Lateralis">Right Vastus Lateralis (Thigh)</option>
                                <option value="Left Gluteal">Left Gluteal</option>
                                <option value="Right Gluteal">Right Gluteal</option>
                                <option value="Oral">Oral</option>
                                <option value="Intranasal">Intranasal</option>
                                <option value="Intradermal">Intradermal</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-route"><i class="mdi mdi-routes"></i> Route *</label>
                            <select class="form-control" id="imm-modal-vaccine-route" required>
                                <option value="">Select Route</option>
                                <option value="Intramuscular">Intramuscular (IM)</option>
                                <option value="Subcutaneous">Subcutaneous (SC)</option>
                                <option value="Intradermal">Intradermal (ID)</option>
                                <option value="Oral">Oral (PO)</option>
                                <option value="Intranasal">Intranasal</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="imm-modal-vaccine-batch-select"><i class="mdi mdi-package-variant"></i> Select Batch <span class="badge badge-info badge-sm ml-1" title="Auto-selects FIFO">FIFO</span></label>
                            <select class="form-control" id="imm-modal-vaccine-batch-select"><option value="">-- Select store first --</option></select>
                            <small class="text-muted" id="imm-modal-vaccine-batch-help">Select product to see available batches</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="imm-modal-vaccine-expiry"><i class="mdi mdi-calendar-alert"></i> Expiry Date</label>
                            <input type="date" class="form-control" id="imm-modal-vaccine-expiry" readonly>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="imm-modal-vaccine-time"><i class="mdi mdi-clock"></i> Administration Time *</label>
                            <input type="datetime-local" class="form-control" id="imm-modal-vaccine-time" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-manufacturer"><i class="mdi mdi-factory"></i> Manufacturer</label>
                            <input type="text" class="form-control" id="imm-modal-vaccine-manufacturer" placeholder="Vaccine manufacturer">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="imm-modal-vaccine-vis"><i class="mdi mdi-file-document"></i> VIS Date Given</label>
                            <input type="date" class="form-control" id="imm-modal-vaccine-vis" title="Vaccine Information Statement date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="imm-modal-vaccine-notes"><i class="mdi mdi-note-text"></i> Notes / Reactions</label>
                        <textarea class="form-control" id="imm-modal-vaccine-notes" rows="2" placeholder="Any observations, reactions, or notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-success btn-lg" id="imm-modal-submit-immunization"><i class="mdi mdi-check"></i> Record Immunization</button>
            </div>
        </div>
    </div>
</div>

{{-- Dose-mode toggle rendered as hidden HTML; moved into dynamic container via JS --}}
<div id="mco-dose-mode-toggle-source" style="display:none;">
    @include('admin.partials.dose-mode-toggle', ['prefix' => 'mco_'])
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- MATERNITY FORM MODALS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}

{{-- 1. Add Medical History Modal --}}
<div class="modal fade" id="addHistoryModal" tabindex="-1" aria-labelledby="addHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addHistoryModalLabel"><i class="mdi mdi-clipboard-text-clock"></i> Add Medical History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i> Record relevant past medical, surgical, obstetric, family, or social history that may affect pregnancy management.</div>
                <form id="add-history-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-clipboard-list"></i> History Details</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category <span class="mat-tooltip-icon" title="Medical: chronic illnesses. Surgical: previous operations. Obstetric: prior pregnancy complications. Family: inherited conditions. Social: smoking, alcohol, occupation"><i class="mdi mdi-help-circle"></i></span></label>
                                <select name="category" class="form-select" required>
                                    <option value="medical">Medical</option>
                                    <option value="surgical">Surgical</option>
                                    <option value="obstetric">Obstetric</option>
                                    <option value="family">Family</option>
                                    <option value="social">Social</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year <span class="mat-tooltip-icon" title="Year of diagnosis or occurrence"><i class="mdi mdi-help-circle"></i></span></label>
                                <input type="number" name="year" class="form-control" min="1950" placeholder="e.g. 2022">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description <span class="text-danger">*</span></label>
                                <input type="text" name="description" class="form-control" placeholder="e.g. Gestational diabetes in 2020" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" placeholder="Additional details">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-history"><i class="mdi mdi-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- 2. Add Previous Pregnancy Modal --}}
<div class="modal fade" id="addPregnancyModal" tabindex="-1" aria-labelledby="addPregnancyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addPregnancyModalLabel"><i class="mdi mdi-baby-carriage"></i> Add Previous Pregnancy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i> Document each prior pregnancy to build a complete obstetric profile. This helps assess current risk factors.</div>
                <form id="add-pregnancy-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-calendar-clock"></i> Pregnancy Details</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Year</label><input type="number" name="year" class="form-control" min="1950" placeholder="e.g. 2021"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Duration (wks) <span class="mat-tooltip-icon" title="Gestational age at delivery. Term: 37–42 weeks. Preterm: <37 weeks"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="duration_weeks" class="form-control" min="1" max="45" placeholder="e.g. 39"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Place of Delivery</label><input type="text" name="place_of_delivery" class="form-control" placeholder="e.g. General Hospital"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Baby Sex</label><select name="baby_sex" class="form-select"><option value="">-- Select --</option><option value="male">Male</option><option value="female">Female</option></select></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Birth Weight (kg) <span class="mat-tooltip-icon" title="Normal: 2.5–4.0 kg. Low birth weight may recur in subsequent pregnancies"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="birth_weight_kg" class="form-control" step="0.1" placeholder="e.g. 3.2"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-clipboard-check"></i> Outcome</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Outcome <span class="mat-tooltip-icon" title="Alive: live birth. Dead: neonatal death. Stillbirth: fetal death ≥20 weeks or ≥500g"><i class="mdi mdi-help-circle"></i></span></label><select name="outcome" class="form-select"><option value="alive">Alive</option><option value="dead">Dead</option><option value="stillbirth">Stillbirth</option></select></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Complications</label><input type="text" name="complications" class="form-control" placeholder="e.g. Pre-eclampsia, PPH"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" placeholder="Additional observations"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-pregnancy"><i class="mdi mdi-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- 3. Add Growth Record Modal --}}
<div class="modal fade" id="addGrowthModal" tabindex="-1" aria-labelledby="addGrowthModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="addGrowthModalLabel"><i class="mdi mdi-chart-line"></i> Add Growth Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-form-help mb-3"><i class="mdi mdi-information"></i> Track baby's growth over time. Compare with WHO growth standards for age-appropriate percentiles.</div>
                <form id="growth-record-form">
                    <input type="hidden" name="baby_id" id="growth-baby-id">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="record_date" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Weight (kg) <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Expected: birth weight regained by day 10–14. Gain ~150–200g/week in first 3 months"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="weight_kg" class="form-control" step="0.01" placeholder="e.g. 3.50" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Length (cm) <span class="mat-tooltip-icon" title="Expected growth: ~3–4 cm/month in first 3 months"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="length_height_cm" class="form-control" step="0.1" placeholder="e.g. 52.0"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Head Circ (cm) <span class="mat-tooltip-icon" title="Expected: ~1 cm/month growth in first year"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="head_circumference_cm" class="form-control" step="0.1" placeholder="e.g. 36.0"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">MUAC (cm) <span class="mat-tooltip-icon" title="Mid-Upper Arm Circumference. ≥11.5 cm = normal, <11.5 cm = at risk"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="muac_cm" class="form-control" step="0.1" placeholder="e.g. 12.0"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-info text-white" id="btn-save-growth"><i class="mdi mdi-check"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- 3b. Add/Edit Partograph Entry Modal --}}
<div class="modal fade" id="addPartographModal" tabindex="-1" aria-labelledby="addPartographModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addPartographModalLabel"><i class="mdi mdi-chart-timeline-variant"></i> Add Partograph Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i><div>Record labour progress and maternal/fetal observations at this time point. Use clinically measured values only.</div></div>
                <form id="partograph-form">
                    <input type="hidden" id="partograph-delivery-id">
                    <input type="hidden" id="partograph-entry-id">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-clock-outline"></i> Timing & Progress</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Recorded At <span class="text-danger">*</span></label><input type="datetime-local" name="recorded_at" class="form-control" required><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Exact time of this observation</div></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Cervical Dilation (cm) <span class="text-danger">*</span></label><input type="number" name="cervical_dilation_cm" class="form-control" min="0" max="10" step="0.1" required><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> 0 = closed, 10 = fully dilated. Active labour ≥ 4 cm</div></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Descent of Head <span class="mat-tooltip-icon" title="Fifths palpable above pelvic brim. 5/5 = free, 0/5 = fully engaged"><i class="mdi mdi-help-circle"></i></span></label><input type="text" name="descent" class="form-control" placeholder="e.g. 5/5, 3/5, 0/5"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Fifths of head palpable above brim (5/5 = free, 0/5 = fully engaged)</div></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-heart-pulse"></i> Fetal & Contractions</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Contractions /10 min <span class="mat-tooltip-icon" title="Count contractions felt in 10 minutes. Active labour: ≥3 per 10 min"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="contractions_per_10min" class="form-control" min="0" max="20"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Active labour: ≥ 3 contractions in 10 min</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Duration (sec) <span class="mat-tooltip-icon" title="Duration of each contraction in seconds. <20s = mild, 20-40s = moderate, >40s = strong"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="contraction_duration_sec" class="form-control" min="0" max="180"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> &lt;20s mild, 20–40s moderate, &gt;40s strong</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Fetal Heart Rate (bpm) <span class="mat-tooltip-icon" title="Normal FHR: 110–160 bpm. <110 = bradycardia, >160 = tachycardia"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="fetal_heart_rate" class="form-control" min="60" max="220"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Normal: 110–160 bpm</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Amniotic Fluid <span class="mat-tooltip-icon" title="C = Clear (normal), I = Intact membranes, M = Meconium-stained (fetal distress risk), B = Bloody, A = Absent"><i class="mdi mdi-help-circle"></i></span></label><select name="amniotic_fluid" class="form-select"><option value="">-- Select --</option><option value="intact">I — Intact</option><option value="clear">C — Clear</option><option value="meconium_stained">M — Meconium stained</option><option value="bloody">B — Bloody</option><option value="absent">A — Absent</option></select></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Moulding <span class="mat-tooltip-icon" title="Overlap of fetal skull bones. None = no overlap, += reducible, ++/+++ = irreducible (higher risk of obstruction)"><i class="mdi mdi-help-circle"></i></span></label><select name="moulding" class="form-select"><option value="">-- Select --</option><option value="none">None (0)</option><option value="+">+ (bones touching)</option><option value="++">++ (overlapping, reducible)</option><option value="+++">+++ (overlapping, irreducible)</option></select></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-account-heart"></i> Maternal Monitoring</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Pulse (bpm) <span class="mat-tooltip-icon" title="Normal maternal pulse: 60–100 bpm. Tachycardia may indicate dehydration, infection, or haemorrhage"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="maternal_pulse" class="form-control" min="20" max="220"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Normal: 60–100 bpm</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">BP Systolic <span class="mat-tooltip-icon" title="Normal: 90–139 mmHg. ≥140 may indicate pre-eclampsia/eclampsia"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="maternal_bp_systolic" class="form-control" min="40" max="300"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">BP Diastolic <span class="mat-tooltip-icon" title="Normal: 60–89 mmHg. ≥90 is significant hypertension in labour"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="maternal_bp_diastolic" class="form-control" min="20" max="220"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Temp (°C) <span class="mat-tooltip-icon" title="Normal: 36.5–37.5°C. ≥38°C may indicate chorioamnionitis or infection"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="maternal_temp_c" class="form-control" step="0.1" min="30" max="45"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Normal: 36.5–37.5°C</div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Urine Output (ml)</label><input type="number" name="urine_output_ml" class="form-control" min="0"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Adequate: ≥ 30 ml/hr</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Urine Protein</label><select name="urine_protein" class="form-select"><option value="">-- Select --</option><option value="nil">Nil</option><option value="trace">Trace</option><option value="+">+</option><option value="++">++</option><option value="+++">+++</option></select><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> ≥++ with high BP → pre-eclampsia</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Oxytocin Dose</label><input type="text" name="oxytocin_dose" class="form-control" placeholder="e.g. 10 IU in 500ml"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">IV Fluids</label><input type="text" name="iv_fluids" class="form-control" placeholder="e.g. Ringer's Lactate 1L"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Document type and volume of IV fluids</div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3"><label class="form-label">Medications / Notes</label><textarea name="medications" class="form-control" rows="2" placeholder="Additional medications, observations, or clinical notes"></textarea></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-success" id="btn-save-partograph"><i class="mdi mdi-check"></i> Save Entry</button>
            </div>
        </div>
    </div>
</div>

{{-- 4. Add Clinical Note Modal --}}
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addNoteModalLabel"><i class="mdi mdi-note-plus"></i> Add Clinical Note</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i><div>Use the rich editor to write structured clinical notes. You can use <strong>headings, bold, lists</strong> and <strong>tables</strong> for clear documentation.</div></div>
                <form id="add-note-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-tag"></i> Note Classification</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Note Type <span class="text-danger">*</span></label>
                                <select name="note_type_id" class="form-select" id="modal-note-type-select" required></select>
                                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Select the category (e.g. Progress, Discharge, Counselling)</div>
                            </div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-text-box"></i> Note Content</div>
                        <div id="mat-note-editor-modal"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-note"><i class="mdi mdi-check"></i> Save Note</button>
            </div>
        </div>
    </div>
</div>

{{-- 5. Postnatal Visit Modal --}}
<div class="modal fade" id="postnatalModal" tabindex="-1" aria-labelledby="postnatalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="postnatalModalLabel"><i class="mdi mdi-account-heart"></i> Record Postnatal Visit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i><div>Postnatal visits assess <strong>mother's recovery</strong> and <strong>baby's wellbeing</strong>. WHO recommends visits within 24h, Day 3, Week 1–2, and Week 6 post-delivery.</div></div>
                <form id="postnatal-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-calendar-clock"></i> Visit Information</div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Visit Type <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="WHO recommended schedule: Within 24h, Day 3, Week 1–2, Week 6"><i class="mdi mdi-help-circle"></i></span></label><select name="visit_type" class="form-select" required><option value="within_24h">Within 24 hours</option><option value="day_3">Day 3</option><option value="week_1_2">Week 1–2</option><option value="week_6">Week 6</option><option value="other">Other</option></select></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Visit Date <span class="text-danger">*</span></label><input type="date" name="visit_date" class="form-control" required></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-mother-heart"></i> Mother Assessment</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">General Condition</label><select name="general_condition" class="form-select"><option value="">-- Select --</option><option>Good</option><option>Fair</option><option>Poor</option></select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Blood Pressure</label><input type="text" name="blood_pressure" class="form-control" placeholder="e.g. 120/80"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Monitor for postpartum hypertension</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Lochia <span class="mat-tooltip-icon" title="Normal: decreasing red→pink→white over weeks. Offensive smell may indicate infection"><i class="mdi mdi-help-circle"></i></span></label><select name="lochia" class="form-select"><option value="">-- Select --</option><option>Normal</option><option>Offensive</option><option>Heavy</option></select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">FP Counselled <span class="mat-tooltip-icon" title="Was the mother counselled about family planning options?"><i class="mdi mdi-help-circle"></i></span></label><select name="family_planning_counselled" class="form-select"><option value="0">No</option><option value="1">Yes</option></select></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-baby-face-outline"></i> Baby Assessment</div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Baby Weight (kg)</label><input type="number" name="baby_weight_kg" class="form-control" step="0.01" placeholder="e.g. 3.20"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Up to 10% weight loss in first week is normal</div></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Baby Feeding</label><select name="baby_feeding" class="form-select"><option value="">-- Select --</option><option>Exclusive breastfeeding</option><option>Formula</option><option>Mixed</option></select></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-note-text"></i> Clinical Notes</div>
                        <div id="mat-postnatal-notes-editor-modal"></div>
                        <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Document findings, concerns, counselling given, and plan of care</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-info text-white" id="btn-save-postnatal"><i class="mdi mdi-check"></i> Save Visit</button>
            </div>
        </div>
    </div>
</div>

{{-- 6. ANC Visit Modal --}}
<div class="modal fade" id="ancVisitModal" tabindex="-1" aria-labelledby="ancVisitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: var(--maternity-pink);">
                <h5 class="modal-title" id="ancVisitModalLabel"><i class="mdi mdi-stethoscope"></i> Record ANC Visit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i><div>Record the antenatal care visit details. <strong>Vital signs</strong> and <strong>obstetric examination findings</strong> are grouped separately. Fields marked <span class="text-danger">*</span> are required.</div></div>
                <form id="anc-visit-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-calendar-clock"></i> Visit Information</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Visit Date <span class="text-danger">*</span></label><input type="date" name="visit_date" class="form-control" required></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Gestational Age (weeks) <span class="text-danger">*</span></label><input type="number" name="gestational_age_weeks" class="form-control" min="1" max="45" placeholder="e.g. 28" required><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Current gestational age calculated from LMP</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Visit Type</label><select name="visit_type" class="form-select"><option value="">Auto-detect</option><option value="booking">Booking</option><option value="routine">Routine</option><option value="emergency">Emergency</option><option value="specialist_referral">Specialist Referral</option></select><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Leave blank for auto-detection (booking/routine)</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Next Appointment</label><input type="date" name="next_appointment" class="form-control"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Schedule the next ANC visit date</div></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-heart-pulse"></i> Maternal Vitals</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Weight (kg)</label><input type="number" name="weight_kg" class="form-control" step="0.1" placeholder="e.g. 68.5"><div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Monitor weight gain trend each visit</div></div>
                            <div class="col-md-3 mb-3"><label class="form-label">BP Systolic <span class="mat-tooltip-icon" title="Top number of blood pressure. Normal: 90–139 mmHg"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="blood_pressure_systolic" class="form-control" min="50" max="250" placeholder="e.g. 120"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">BP Diastolic <span class="mat-tooltip-icon" title="Bottom number. Normal: 60–89 mmHg. ≥90 may indicate pre-eclampsia"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="blood_pressure_diastolic" class="form-control" min="30" max="150" placeholder="e.g. 80"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Haemoglobin (g/dL) <span class="mat-tooltip-icon" title="Normal in pregnancy: 10–14 g/dL. <10 = anaemia"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="haemoglobin" class="form-control" step="0.1" placeholder="e.g. 11.5"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-human-pregnant"></i> Obstetric Examination</div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Fundal Height (cm) <span class="mat-tooltip-icon" title="Symphysis-fundal height — roughly equals gestational age in weeks (±2cm)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="fundal_height_cm" class="form-control" step="0.1" placeholder="e.g. 28"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Fetal Heart Rate (bpm) <span class="mat-tooltip-icon" title="Normal FHR: 110–160 bpm"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="fetal_heart_rate" class="form-control" min="60" max="200" placeholder="e.g. 140"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Presentation <span class="mat-tooltip-icon" title="Cephalic: head-first (normal). Breech: buttocks/feet first. Transverse: sideways"><i class="mdi mdi-help-circle"></i></span></label><select name="presentation" class="form-select"><option value="">-- Select --</option><option>Cephalic</option><option>Breech</option><option>Transverse</option><option>Oblique</option></select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Oedema <span class="mat-tooltip-icon" title="+: pedal only. ++: lower legs. +++: generalized/facial (pre-eclampsia warning)"><i class="mdi mdi-help-circle"></i></span></label><select name="oedema" class="form-select"><option value="">-- Select --</option><option>None</option><option>+</option><option>++</option><option>+++</option></select></div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">Foetal Movement <span class="mat-tooltip-icon" title="Absent/reduced movement may indicate fetal distress"><i class="mdi mdi-help-circle"></i></span></label><select name="foetal_movement" class="form-select"><option value="">-- Select --</option><option value="present">Present</option><option value="absent">Absent</option><option value="reduced">Reduced</option></select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Urine Protein <span class="mat-tooltip-icon" title="≥++ with raised BP may indicate pre-eclampsia"><i class="mdi mdi-help-circle"></i></span></label><select name="urine_protein" class="form-select"><option value="">-- Select --</option><option value="nil">Nil</option><option value="trace">Trace</option><option value="+">+</option><option value="++">++</option><option value="+++">+++</option></select></div>
                            <div class="col-md-3 mb-3"><label class="form-label">Urine Glucose <span class="mat-tooltip-icon" title="Persistent glycosuria warrants screening for gestational diabetes"><i class="mdi mdi-help-circle"></i></span></label><select name="urine_glucose" class="form-select"><option value="">-- Select --</option><option value="nil">Nil</option><option value="trace">Trace</option><option value="+">+</option><option value="++">++</option><option value="+++">+++</option></select></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-note-text"></i> Clinical Notes</div>
                        <div id="mat-anc-notes-editor-modal"></div>
                        <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Document clinical findings, counselling given, concerns, and plan of care</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn text-white" style="background: var(--maternity-pink);" id="btn-save-anc-visit"><i class="mdi mdi-check"></i> Save Visit</button>
            </div>
        </div>
    </div>
</div>

{{-- 7. Register Baby Modal --}}
<div class="modal fade" id="registerBabyModal" tabindex="-1" aria-labelledby="registerBabyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="registerBabyModalLabel"><i class="mdi mdi-baby-face-outline"></i> Register Baby</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mat-info-banner mb-3"><i class="mdi mdi-information"></i> Record the newborn's identity, measurements, and immediate care provided at birth.</div>
                <form id="register-baby-form">
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-account"></i> Identity</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Surname <span class="text-danger">*</span></label><input type="text" name="baby_surname" class="form-control" placeholder="Baby's surname" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" name="baby_firstname" class="form-control" placeholder="Baby's first name" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Sex <span class="text-danger">*</span></label><select name="sex" class="form-select" required><option value="">-- Select --</option><option value="male">Male</option><option value="female">Female</option><option value="ambiguous">Ambiguous</option></select></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-human-child"></i> Anthropometrics</div>
                        <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Normal birth weight: 2.5–4.0 kg. Normal length: 48–53 cm. Normal head circumference: 33–37 cm</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">Birth Weight (kg) <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Normal: 2.5–4.0 kg. Low birth weight: <2.5 kg. Macrosomia: >4.0 kg"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="birth_weight_kg" class="form-control" step="0.01" min="0.3" max="8" placeholder="e.g. 3.20" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Length (cm) <span class="mat-tooltip-icon" title="Crown-to-heel length. Normal: 48–53 cm at term"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="length_cm" class="form-control" step="0.1" placeholder="e.g. 50.0"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">Head Circumference (cm) <span class="mat-tooltip-icon" title="Occipitofrontal circumference. Normal: 33–37 cm"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="head_circumference_cm" class="form-control" step="0.1" placeholder="e.g. 35.0"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-heart-pulse"></i> APGAR Scores</div>
                        <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Score 0–10: <strong>A</strong>ppearance, <strong>P</strong>ulse, <strong>G</strong>rimace, <strong>A</strong>ctivity, <strong>R</strong>espiration. 7–10: Normal. 4–6: Needs assistance. 0–3: Critical</div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label">APGAR at 1 min <span class="mat-tooltip-icon" title="First assessment at 1 minute after birth"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="apgar_1_min" class="form-control" min="0" max="10" placeholder="0–10"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">APGAR at 5 min <span class="mat-tooltip-icon" title="Second assessment at 5 minutes — most predictive of outcome"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="apgar_5_min" class="form-control" min="0" max="10" placeholder="0–10"></div>
                            <div class="col-md-4 mb-3"><label class="form-label">APGAR at 10 min <span class="mat-tooltip-icon" title="Third assessment at 10 minutes (if earlier scores low)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="apgar_10_min" class="form-control" min="0" max="10" placeholder="0–10"></div>
                        </div>
                    </div>
                    <div class="mat-form-section">
                        <div class="mat-form-section-title"><i class="mdi mdi-medical-bag"></i> Immediate Care</div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Feeding Method <span class="mat-tooltip-icon" title="WHO recommends exclusive breastfeeding for the first 6 months"><i class="mdi mdi-help-circle"></i></span></label><select name="feeding_method" class="form-select"><option value="exclusive_breastfeeding">Exclusive Breastfeeding</option><option value="formula">Formula</option><option value="mixed">Mixed</option></select></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Immediate Immunizations & Prophylaxis <span class="mat-tooltip-icon" title="Standard birth-dose immunizations per national schedule"><i class="mdi mdi-help-circle"></i></span></label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="bcg_given" value="1"><label class="form-check-label">BCG</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="opv0_given" value="1"><label class="form-check-label">OPV-0</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="hbv0_given" value="1"><label class="form-check-label">HBV-0</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="vitamin_k_given" value="1"><label class="form-check-label">Vitamin K</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="eye_prophylaxis" value="1"><label class="form-check-label">Eye Prophylaxis</label></div>
                                </div>
                                <div class="mat-form-help mt-1"><i class="mdi mdi-information"></i> BCG: tuberculosis. OPV-0: polio. HBV-0: hepatitis B. Vitamin K: prevents haemorrhagic disease. Eye prophylaxis: prevents ophthalmia neonatorum</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-success" id="btn-save-baby"><i class="mdi mdi-check"></i> Register Baby</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.treatment-plan-modal')
@include('admin.partials.re-prescribe-encounter-modal')
@include('admin.partials.clinical_context_modal')
@include('admin.partials.invest_res_modal', ['save_route' => 'lab.saveResult'])
@include('admin.partials.invest_res_js')
@include('admin.partials.invest_res_view_imaging_modal')
@include('admin.partials.invest_res_view_imaging_js')

@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
<script src="{{ asset('js/immunization-module.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
{{-- SHARED partial: patient search JS with maternity context --}}
@include('admin.partials.patient_search_js', ['search_context' => 'maternity'])

<script>
// ═══════════════════════════════════════════════════════════════
// GLOBAL STATE (mirrors nursing workbench pattern)
// ═══════════════════════════════════════════════════════════════
let currentPatient = null;
let currentPatientData = null;
let currentEnrollment = null;
let currentEnrollmentId = null;
const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

// Edit mode state tracking (shared pattern for modal reuse)
let _editMode = null;   // null = create, 'anc'|'postnatal'|'baby'|'note'|'history'|'pregnancy' = edit
let _editId = null;      // ID of record being edited

// Data caches for edit pre-fill
let _ancVisitsCache = [];
let _postnatalVisitsCache = [];
let _notesCache = [];

// ═══════════════════════════════════════════════════════════════
// RICH TEXT EDITOR HELPER (CKEditor5)
// ═══════════════════════════════════════════════════════════════
const MaternityEditors = {};
function initMaternityEditor(selector, key) {
    const el = document.querySelector(selector);
    if (!el || MaternityEditors[key]) return Promise.resolve(MaternityEditors[key]);
    return ClassicEditor.create(el, {
        toolbar: {
            items: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'blockQuote', 'insertTable', 'undo', 'redo']
        }
    }).then(editor => {
        MaternityEditors[key] = editor;
        return editor;
    }).catch(err => console.error('CKEditor init error:', err));
}
function destroyMaternityEditor(key) {
    if (MaternityEditors[key]) {
        MaternityEditors[key].destroy().catch(() => {});
        delete MaternityEditors[key];
    }
}
function getEditorData(key, fallbackSelector) {
    if (MaternityEditors[key]) return MaternityEditors[key].getData();
    return $(fallbackSelector).val() || '';
}

// ═══════════════════════════════════════════════════════════════
// VIEW MANAGEMENT (SHARED with nursing workbench — identical)
// ═══════════════════════════════════════════════════════════════
function hideAllViews() {
    $('#empty-state').hide();
    $('#queue-view').removeClass('active').hide();
    $('#reports-view').removeClass('active').hide();
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active').hide();
}

function showQueue(filter) {
    hideAllViews();
    const titles = {
        'active-anc': '<i class="mdi mdi-mother-nurse"></i> Active ANC Patients',
        'due-visits': '<i class="mdi mdi-calendar-clock"></i> Due Visits',
        'upcoming-edd': '<i class="mdi mdi-calendar-star"></i> Upcoming EDD (Next 4 Weeks)',
        'postnatal': '<i class="mdi mdi-account-heart"></i> Postnatal Patients',
        'overdue-immunization': '<i class="mdi mdi-needle"></i> Overdue Immunizations',
        'high-risk': '<i class="mdi mdi-alert"></i> High Risk Patients',
    };
    $('#queue-view-title').html(titles[filter] || titles['active-anc']);
    $('.queue-item').removeClass('active');
    $(`.queue-item[data-filter="${filter}"]`).addClass('active');
    $('#queue-view').addClass('active').css('display', 'flex');
    if (window.innerWidth < 768) { $('#left-panel').addClass('hidden'); $('#main-workspace').addClass('active'); }
    loadQueueData(filter);
}

function hideQueue() {
    $('#queue-view').removeClass('active').css('display', 'none');
    $('.queue-item').removeClass('active');
    if (currentPatient) {
        $('#patient-header').addClass('active');
        $('#workspace-content').show().addClass('active');
    } else {
        $('#empty-state').show();
    }
    if (window.innerWidth < 768) { $('#main-workspace').removeClass('active'); $('#left-panel').removeClass('hidden'); }
}

// ═══════════════════════════════════════════════════════════════
// QUEUE DATA (maternity-specific endpoints, shared card pattern)
// ═══════════════════════════════════════════════════════════════
function loadQueueData(filter) {
    const $container = $('#queue-view-content');
    $container.html('<div class="text-center p-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading...</div>');

    const urls = {
        'active-anc': '{{ route("maternity-workbench.queue.active-anc") }}',
        'due-visits': '{{ route("maternity-workbench.queue.due-visits") }}',
        'upcoming-edd': '{{ route("maternity-workbench.queue.upcoming-edd") }}',
        'postnatal': '{{ route("maternity-workbench.queue.postnatal") }}',
        'overdue-immunization': '{{ route("maternity-workbench.queue.overdue-immunization") }}',
        'high-risk': '{{ route("maternity-workbench.queue.high-risk") }}',
    };

    $.ajax({
        url: urls[filter] || urls['active-anc'],
        method: 'GET',
        success: function(data) {
            const items = Array.isArray(data) ? data : (data.data || []);
            if (items.length === 0) {
                $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-account-off" style="font-size: 3rem;"></i><br>No patients in this queue</div>');
                return;
            }
            renderQueueCards(items, filter);
        },
        error: function() {
            $container.html('<div class="text-center p-4 text-danger"><i class="mdi mdi-alert-circle" style="font-size: 3rem;"></i><br>Failed to load queue</div>');
        }
    });
}

function renderQueueCards(items, filter) {
    const $container = $('#queue-view-content');
    let html = '';
    items.forEach(function(item) {
        let badge = '';
        let detail = '';
        const pid = item.patient_id || item.baby_id;

        if (filter === 'active-anc') {
            badge = `<span class="risk-indicator ${item.risk_level}">${item.risk_level}</span>`;
            detail = `<span><i class="mdi mdi-calendar"></i> EDD: ${item.edd}</span>
                      <span><i class="mdi mdi-stethoscope"></i> GA: ${item.gestational_age || 'N/A'}</span>
                      <span><i class="mdi mdi-counter"></i> Visits: ${item.anc_visits || 0}</span>`;
        } else if (filter === 'due-visits') {
            badge = `<span class="badge bg-warning text-dark">${item.days_overdue}d overdue</span>`;
            detail = `<span><i class="mdi mdi-calendar-clock"></i> Due: ${item.next_appointment}</span>`;
        } else if (filter === 'upcoming-edd') {
            badge = `<span class="badge bg-info">${item.days_to_edd}d to EDD</span>`;
            detail = `<span><i class="mdi mdi-calendar-star"></i> EDD: ${item.edd}</span>
                      <span class="risk-indicator ${item.risk_level}">${item.risk_level}</span>`;
        } else if (filter === 'postnatal') {
            badge = `<span class="enrollment-badge ${item.status}">${item.status}</span>`;
            detail = `<span><i class="mdi mdi-calendar"></i> Delivered: ${item.delivery_date}</span>
                      <span><i class="mdi mdi-baby-face"></i> Babies: ${item.baby_count || 0}</span>
                      <span><i class="mdi mdi-clock"></i> ${item.days_postpartum || 0}d postpartum</span>`;
        } else if (filter === 'overdue-immunization') {
            detail = `<span><i class="mdi mdi-baby-face"></i> ${item.baby_name}</span>
                      <span><i class="mdi mdi-mother-nurse"></i> Mother: ${item.mother_name}</span>
                      <span><i class="mdi mdi-clock"></i> Age: ${item.age}</span>`;
        } else if (filter === 'high-risk') {
            badge = `<span class="enrollment-badge ${item.status}">${item.status}</span>`;
            const risks = item.risk_factors ? (Array.isArray(item.risk_factors) ? item.risk_factors.join(', ') : item.risk_factors) : 'N/A';
            detail = `<span><i class="mdi mdi-alert"></i> ${risks}</span>
                      <span><i class="mdi mdi-calendar"></i> EDD: ${item.edd}</span>`;
        }

        html += `<div class="queue-card" onclick="loadPatient(${pid})">
            <div class="queue-card-header">
                <div>
                    <div class="queue-card-patient-name">${item.name || item.baby_name || 'Unknown'}</div>
                    <div class="queue-card-patient-meta">
                        <span class="queue-card-patient-meta-item"><i class="mdi mdi-folder"></i> ${item.file_no || 'N/A'}</span>
                        ${detail}
                    </div>
                </div>
                ${badge}
            </div>
        </div>`;
    });
    $container.html(html);
}

function loadQueueCounts() {
    $.ajax({
        url: '{{ route("maternity-workbench.queue.counts") }}',
        method: 'GET',
        success: function(data) {
            $('#queue-active-anc-count').text(data.active_anc || 0);
            $('#queue-due-visits-count').text(data.due_visits || 0);
            $('#queue-upcoming-edd-count').text(data.upcoming_edd || 0);
            $('#queue-postnatal-count').text(data.postnatal || 0);
            $('#queue-overdue-imm-count').text(data.overdue_immunization || 0);
            $('#queue-high-risk-count').text(data.high_risk || 0);
        }
    });
}

// ═══════════════════════════════════════════════════════════════
// PATIENT LOADING (SHARED pattern with nursing workbench)
// ═══════════════════════════════════════════════════════════════
function loadPatient(patientId) {
    currentPatient = patientId;
    hideAllViews();

    $('#workspace-content').show().addClass('active');
    $('#patient-header').addClass('active');
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Enable quick actions
    $('#btn-enroll-patient').prop('disabled', false);
    $('#btn-quick-vitals').prop('disabled', false);
    $('#btn-print-anc-card').prop('disabled', false);
    $('#btn-print-road-card').prop('disabled', false);
    $('#btn-maternity-audit').prop('disabled', false);
    $('#btn-clinical-context').prop('disabled', false).attr('title', 'View clinical context for patient');

    $.ajax({
        url: `/maternity-workbench/patient/${patientId}/details`,
        method: 'GET',
        success: function(data) {
            currentPatientData = data;

            // SHARED function: display patient header (same as nursing)
            displayPatientInfo(data);

            // Store enrollment
            currentEnrollment = data.enrollment;
            currentEnrollmentId = data.enrollment ? data.enrollment.id : null;

            // Show/hide print buttons based on enrollment
            if (currentEnrollmentId) {
                $('#btn-print-anc-card').show();
                $('#btn-print-road-card').show();
            } else {
                $('#btn-print-anc-card').hide();
                $('#btn-print-road-card').hide();
            }

            // Load overview
            populateOverviewTab(data);

            // Initialize shared vitals partial
            if (typeof window.initUnifiedVitals === 'function') {
                window.initUnifiedVitals(patientId);
            }

            // Load enrollment tab content
            loadEnrollmentTab();

            switchWorkspaceTab('overview');
        },
        error: function(xhr) {
            console.error('Failed to load patient:', xhr);
            toastr.error('Failed to load patient data');
        }
    });
}

// ═══════════════════════════════════════════════════════════════
// DISPLAY PATIENT INFO (SHARED with nursing workbench — same pattern)
// ═══════════════════════════════════════════════════════════════
function displayPatientInfo(patient) {
    // Build name with enrollment badge
    let nameSuffix = '';
    if (patient.enrollment) {
        nameSuffix = ` <span class="enrollment-badge ${patient.enrollment.status}">${patient.enrollment.status.toUpperCase()}</span>`;
        if (patient.enrollment.risk_level === 'high') {
            nameSuffix += ` <span class="risk-indicator high">HIGH RISK</span>`;
        }
    }
    $('#patient-name').html(`${patient.name} (#${patient.file_no})${nameSuffix}`);

    let metaHtml = `
        <div class="patient-meta-item"><i class="mdi mdi-account"></i><span>${patient.age} ${patient.gender}</span></div>
        <div class="patient-meta-item"><i class="mdi mdi-water"></i><span>${patient.blood_group} ${patient.genotype !== 'N/A' ? '(' + patient.genotype + ')' : ''}</span></div>
        <div class="patient-meta-item"><i class="mdi mdi-phone"></i><span>${patient.phone}</span></div>
    `;

    if (patient.enrollment) {
        const e = patient.enrollment;
        if (e.gestational_age) {
            metaHtml += `<div class="patient-meta-item"><span class="ga-pill"><i class="mdi mdi-baby-carriage"></i> GA: ${e.gestational_age}</span></div>`;
        }
        if (e.edd) {
            metaHtml += `<div class="patient-meta-item"><i class="mdi mdi-calendar-star"></i><span>EDD: ${e.edd}</span></div>`;
        }
        metaHtml += `<div class="patient-meta-item"><i class="mdi mdi-human-pregnant"></i><span>G${e.gravida || '?'}P${e.parity || '?'}</span></div>`;
    }
    $('#patient-meta').html(metaHtml);

    // Build expanded details grid (SHARED pattern)
    let detailsHtml = '';
    const fields = [
        { icon: 'mdi-calendar-clock', label: 'Age', value: patient.age },
        { icon: 'mdi-gender-female', label: 'Gender', value: patient.gender },
        { icon: 'mdi-water', label: 'Blood Group', value: patient.blood_group },
        { icon: 'mdi-dna', label: 'Genotype', value: patient.genotype },
        { icon: 'mdi-phone', label: 'Phone', value: patient.phone },
        { icon: 'mdi-map-marker', label: 'Address', value: patient.address },
        { icon: 'mdi-hospital-building', label: 'HMO', value: patient.hmo },
        { icon: 'mdi-card-account-details', label: 'HMO No', value: patient.hmo_no },
    ];
    fields.forEach(function(f) {
        detailsHtml += `<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi ${f.icon}"></i> ${f.label}</div><div class="patient-detail-value">${f.value || 'N/A'}</div></div>`;
    });

    // Enrollment-specific details
    if (patient.enrollment) {
        const e = patient.enrollment;
        const enrollFields = [
            { icon: 'mdi-clipboard-plus', label: 'Booking Date', value: e.booking_date },
            { icon: 'mdi-calendar', label: 'LMP', value: e.lmp },
            { icon: 'mdi-calendar-star', label: 'EDD', value: e.edd },
            { icon: 'mdi-baby-carriage', label: 'Gestational Age', value: e.gestational_age },
            { icon: 'mdi-human-pregnant', label: 'Gravida/Parity', value: `G${e.gravida || '?'} P${e.parity || '?'}` },
            { icon: 'mdi-scale', label: 'Booking Weight', value: e.booking_weight_kg ? e.booking_weight_kg + ' kg' : 'N/A' },
            { icon: 'mdi-arrow-up-down', label: 'Height', value: e.height_cm ? e.height_cm + ' cm' : 'N/A' },
            { icon: 'mdi-gauge', label: 'Booking BP', value: e.booking_bp || 'N/A' },
            { icon: 'mdi-clock-outline', label: 'Remaining Days', value: e.remaining_days !== null ? e.remaining_days + ' days' : 'N/A' },
        ];
        enrollFields.forEach(function(f) {
            detailsHtml += `<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi ${f.icon}"></i> ${f.label}</div><div class="patient-detail-value">${f.value || 'N/A'}</div></div>`;
        });
    }

    // Allergies (SHARED pattern from nursing workbench)
    let allergiesArray = [];
    if (patient.allergies) {
        if (Array.isArray(patient.allergies)) { allergiesArray = patient.allergies; }
        else if (typeof patient.allergies === 'string') {
            try { const p = JSON.parse(patient.allergies); allergiesArray = Array.isArray(p) ? p : [p]; } catch(e) { allergiesArray = patient.allergies.split(',').map(a => a.trim()).filter(a => a); }
        } else if (typeof patient.allergies === 'object') { allergiesArray = Object.values(patient.allergies).filter(a => a); }
    }
    if (allergiesArray.length > 0) {
        detailsHtml += `<div class="patient-detail-item full-width"><div class="patient-detail-label"><i class="mdi mdi-alert-circle"></i> Allergies</div><div class="patient-detail-value"><div class="allergies-list">${allergiesArray.map(a => `<span class="allergy-tag"><i class="mdi mdi-alert"></i> ${a}</span>`).join('')}</div></div></div>`;
    }

    // Risk factors
    if (patient.enrollment && patient.enrollment.risk_factors && patient.enrollment.risk_factors.length > 0) {
        const risks = patient.enrollment.risk_factors;
        const riskHtml = (Array.isArray(risks) ? risks : [risks]).map(r => `<span class="allergy-tag" style="background: rgba(220,53,69,0.15); border-color: rgba(220,53,69,0.4);"><i class="mdi mdi-alert"></i> ${r}</span>`).join('');
        detailsHtml += `<div class="patient-detail-item full-width"><div class="patient-detail-label"><i class="mdi mdi-alert-octagon"></i> Risk Factors</div><div class="patient-detail-value"><div class="allergies-list">${riskHtml}</div></div></div>`;
    }

    $('#patient-details-grid').html(detailsHtml);

    // SHARED: Toggle expand/collapse (identical to nursing)
    $('#btn-expand-patient').off('click').on('click', function() {
        $(this).toggleClass('expanded');
        $('#patient-details-expanded').toggleClass('show');
    });
}

// ═══════════════════════════════════════════════════════════════
// TAB SWITCHING (SHARED pattern with nursing workbench)
// ═══════════════════════════════════════════════════════════════
function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $('.workspace-tab-content').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');
    $(`#${tab}-tab`).addClass('active');

    if (!currentPatient) return;

    switch(tab) {
        case 'overview': populateOverviewTab(currentPatientData); break;
        case 'enrollment': loadEnrollmentTab(); break;
        case 'history': loadHistoryTab(); break;
        case 'anc': loadAncTab(); break;
        case 'clinical-orders': loadClinicalOrdersTab(); break;
        case 'delivery': loadDeliveryTab(); break;
        case 'baby': loadBabyTab(); break;
        case 'postnatal': loadPostnatalTab(); break;
        case 'immunization': loadImmunizationTab(); break;
        case 'notes': loadNotesTab(); break;
        case 'audit': loadAuditTab(); break;
        case 'vitals':
            if (typeof window.initUnifiedVitals === 'function') { window.initUnifiedVitals(currentPatient); }
            break;
    }
}

// ═══════════════════════════════════════════════════════════════
// OVERVIEW TAB
// ═══════════════════════════════════════════════════════════════
function populateOverviewTab(data) {
    const e = data.enrollment;
    const v = data.last_vitals;

    let html = '';

    // ── Pregnancy Progress Bar ──────────────────────────────────
    if (e && e.lmp && e.edd) {
        const gaText = e.gestational_age || 'N/A';
        // Parse GA weeks from text like "32 weeks, 4 days"
        const gaMatch = gaText.match(/(\d+)\s*weeks?/i);
        const gaWeeks = gaMatch ? parseInt(gaMatch[1]) : 0;
        const gaDayMatch = gaText.match(/(\d+)\s*days?/i);
        const gaDays = gaDayMatch ? parseInt(gaDayMatch[1]) : 0;
        const totalGaDays = gaWeeks * 7 + gaDays;
        const totalDays = 280; // 40 weeks
        const progressPct = Math.min(100, Math.max(0, (totalGaDays / totalDays) * 100));
        const trimester = gaWeeks < 13 ? 1 : (gaWeeks < 28 ? 2 : 3);
        const trimesterLabel = ['', '1st Trimester', '2nd Trimester', '3rd Trimester'][trimester];
        const progressColor = gaWeeks > 41 ? '#dc3545' : (gaWeeks >= 37 ? '#ffc107' : '#28a745');
        const postDates = e.remaining_days !== null && e.remaining_days < 0;

        html += `
        <div class="card-modern mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold small"><i class="mdi mdi-baby-carriage"></i> Pregnancy Progress — ${trimesterLabel}</span>
                    <span class="small">${gaText} ${postDates ? '<span class="badge bg-danger">POST-DATES</span>' : ''}</span>
                </div>
                <div class="position-relative" style="height: 22px; background: #f0f0f0; border-radius: 11px; overflow: hidden;">
                    <div style="position:absolute; left:0; top:0; height:100%; width:${progressPct}%; background: ${progressColor}; border-radius:11px; transition: width 0.5s;"></div>
                    <div style="position:absolute; left:${(12/40)*100}%; top:0; height:100%; width:1px; background: rgba(0,0,0,0.15);" title="End of 1st Trimester (12w)"></div>
                    <div style="position:absolute; left:${(28/40)*100}%; top:0; height:100%; width:1px; background: rgba(0,0,0,0.15);" title="End of 2nd Trimester (28w)"></div>
                    <span style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); font-size:0.7rem; font-weight:600; color:#333; text-shadow: 0 0 3px #fff;">${gaWeeks}w${gaDays}d / 40w</span>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size:0.65rem; color:#999;">
                    <span>LMP: ${e.lmp}</span>
                    <span style="left:${(12/40)*100}%; position:relative;">12w</span>
                    <span style="left:${(28/40)*100}%; position:relative;">28w</span>
                    <span>EDD: ${e.edd}</span>
                </div>
            </div>
        </div>`;
    }

    // ── Stat Cards Row ─────────────────────────────────────────
    html += '<div class="row">';
    if (e) {
        // ANC Visits
        html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card mat-stat-pink" style="cursor:pointer;" onclick="switchWorkspaceTab('anc')">
                <div class="mat-stat-icon"><i class="mdi mdi-stethoscope" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.anc_visit_count || 0}</div><div class="mat-stat-label">ANC Visits</div></div>
            </div>
        </div>`;
        // Babies
        html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card mat-stat-green" style="cursor:pointer;" onclick="switchWorkspaceTab('baby')">
                <div class="mat-stat-icon"><i class="mdi mdi-baby-face" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.baby_count || 0}</div><div class="mat-stat-label">Babies</div></div>
            </div>
        </div>`;
        // Postnatal
        html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card mat-stat-blue" style="cursor:pointer;" onclick="switchWorkspaceTab('postnatal')">
                <div class="mat-stat-icon"><i class="mdi mdi-account-heart" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.postnatal_visit_count || 0}</div><div class="mat-stat-label">Postnatal</div></div>
            </div>
        </div>`;
        // Days to EDD
        const eddBg = (e.remaining_days !== null && e.remaining_days < 0) ? 'mat-stat-red' : 'mat-stat-orange';
        html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card ${eddBg}">
                <div class="mat-stat-icon"><i class="mdi mdi-clock-outline" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.remaining_days !== null ? (e.remaining_days < 0 ? Math.abs(e.remaining_days) + 'd over' : e.remaining_days + 'd') : 'N/A'}</div><div class="mat-stat-label">To EDD</div></div>
            </div>
        </div>`;
        // Risk Level
        const riskColors = { low: '#28a745', moderate: '#ffc107', high: '#fd7e14', very_high: '#dc3545' };
        const riskBg = riskColors[e.risk_level] || '#6c757d';
        html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card" style="border-left: 4px solid ${riskBg}; cursor:pointer;" onclick="switchWorkspaceTab('enrollment')">
                <div class="mat-stat-icon"><i class="mdi mdi-shield-alert" style="font-size:1.5rem; color:${riskBg};"></i></div>
                <div><div class="mat-stat-value" style="color:${riskBg}; text-transform:capitalize;">${(e.risk_level || 'low').replace('_', ' ')}</div><div class="mat-stat-label">Risk Level</div></div>
            </div>
        </div>`;
        // BMI
        const bmi = (e.booking_weight_kg && e.height_cm) ? (e.booking_weight_kg / ((e.height_cm / 100) ** 2)).toFixed(1) : null;
        const bmiColor = bmi ? (bmi < 18.5 ? '#17a2b8' : (bmi < 25 ? '#28a745' : (bmi < 30 ? '#ffc107' : '#dc3545'))) : '#6c757d';
        const bmiLabel = bmi ? (bmi < 18.5 ? 'Underweight' : (bmi < 25 ? 'Normal' : (bmi < 30 ? 'Overweight' : 'Obese'))) : '';
        html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card" style="border-left: 4px solid ${bmiColor};">
                <div class="mat-stat-icon"><i class="mdi mdi-weight" style="font-size:1.5rem; color:${bmiColor};"></i></div>
                <div><div class="mat-stat-value" style="color:${bmiColor};">${bmi || 'N/A'}</div><div class="mat-stat-label">BMI ${bmiLabel ? '(' + bmiLabel + ')' : ''}</div></div>
            </div>
        </div>`;
    }
    html += '</div>';

    // ── Alerts Panel ────────────────────────────────────────────
    if (e) {
        const alerts = [];
        // Post-dates alert
        if (e.remaining_days !== null && e.remaining_days < 0) {
            alerts.push({ type: 'danger', icon: 'mdi-alert-circle', text: `Post-dates by ${Math.abs(e.remaining_days)} days — consider induction assessment`, tab: 'delivery' });
        }
        // High risk alert
        if (e.risk_level === 'high' || e.risk_level === 'very_high') {
            const riskDesc = e.risk_factors ? ': ' + e.risk_factors : '';
            alerts.push({ type: 'warning', icon: 'mdi-shield-alert', text: `High-risk pregnancy${riskDesc}`, tab: 'enrollment' });
        }
        // Near term
        if (e.remaining_days !== null && e.remaining_days >= 0 && e.remaining_days <= 14) {
            alerts.push({ type: 'info', icon: 'mdi-calendar-clock', text: `Near term — EDD in ${e.remaining_days} days (${e.edd})`, tab: null });
        }
        // Low ANC attendance
        const gaMatch2 = (e.gestational_age || '').match(/(\d+)\s*weeks?/i);
        const gaW = gaMatch2 ? parseInt(gaMatch2[1]) : 0;
        const expectedVisits = gaW < 16 ? 1 : (gaW < 28 ? 2 : (gaW < 36 ? 3 : 4));
        if (gaW >= 16 && (e.anc_visit_count || 0) < expectedVisits) {
            alerts.push({ type: 'warning', icon: 'mdi-stethoscope', text: `ANC visits below schedule: ${e.anc_visit_count}/${expectedVisits} expected by ${gaW} weeks`, tab: 'anc' });
        }
        // Abnormal BP from last vitals
        if (v && v.bp && v.bp !== 'N/A') {
            const bpParts = v.bp.split('/');
            if (bpParts.length === 2) {
                const sys = parseInt(bpParts[0]);
                const dia = parseInt(bpParts[1]);
                if (sys >= 140 || dia >= 90) {
                    alerts.push({ type: 'danger', icon: 'mdi-heart-pulse', text: `Elevated BP: ${v.bp} mmHg — screen for pre-eclampsia`, tab: 'vitals' });
                }
            }
        }
        // Obese BMI
        const bmiVal = (e.booking_weight_kg && e.height_cm) ? (e.booking_weight_kg / ((e.height_cm / 100) ** 2)) : null;
        if (bmiVal && bmiVal >= 30) {
            alerts.push({ type: 'warning', icon: 'mdi-weight', text: `Booking BMI ${bmiVal.toFixed(1)} — increased risk for GDM, pre-eclampsia`, tab: 'enrollment' });
        }

        if (alerts.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="small fw-bold text-muted mb-2"><i class="mdi mdi-bell-alert"></i> Clinical Alerts</h6>';
            alerts.forEach(a => {
                const clickAttr = a.tab ? `style="cursor:pointer;" onclick="switchWorkspaceTab('${a.tab}')"` : '';
                html += `<div class="alert alert-${a.type} py-1 px-2 mb-1 d-flex align-items-center small" ${clickAttr}>
                    <i class="mdi ${a.icon} me-2" style="font-size:1.1rem;"></i> ${a.text}
                    ${a.tab ? '<i class="mdi mdi-chevron-right ms-auto"></i>' : ''}
                </div>`;
            });
            html += '</div>';
        }
    }

    // ── Cards Row ───────────────────────────────────────────────
    html += '<div class="row">';

    // Enrollment Summary
    html += '<div class="col-lg-4 col-md-6 mb-3"><div class="card-modern h-100"><div class="card-header text-white py-2" style="background: var(--maternity-pink);"><h6 class="mb-0"><i class="mdi mdi-clipboard-plus"></i> Enrollment</h6></div><div class="card-body p-2">';
    if (e) {
        html += `<table class="table table-sm table-borderless mb-0">
            <tr><td class="text-muted" style="width:40%;">Status</td><td><span class="enrollment-badge ${e.status}">${e.status}</span></td></tr>
            <tr><td class="text-muted">Entry Point</td><td>${(e.entry_point || '').toUpperCase()}</td></tr>
            <tr><td class="text-muted">Booking</td><td>${e.booking_date || 'N/A'}</td></tr>
            <tr><td class="text-muted">LMP</td><td>${e.lmp || 'N/A'}</td></tr>
            <tr><td class="text-muted">EDD</td><td>${e.edd || 'N/A'}</td></tr>
            <tr><td class="text-muted">GA</td><td>${e.gestational_age || 'N/A'}</td></tr>
            <tr><td class="text-muted">G/P/A</td><td>G${e.gravida || '?'} P${e.parity || '?'}</td></tr>
            <tr><td class="text-muted">Blood Grp</td><td>${e.blood_group || 'N/A'} &nbsp; <span class="text-muted">Geno:</span> ${e.genotype || 'N/A'}</td></tr>
            <tr><td class="text-muted">Height</td><td>${e.height_cm ? e.height_cm + ' cm' : 'N/A'} &nbsp; <span class="text-muted">Wt:</span> ${e.booking_weight_kg ? e.booking_weight_kg + ' kg' : 'N/A'}</td></tr>
        </table>`;
    } else {
        html += '<p class="text-muted text-center py-3 mb-0">Not enrolled — <a href="javascript:void(0)" onclick="switchWorkspaceTab(\'enrollment\')">Enroll now</a></p>';
    }
    html += '</div></div></div>';

    // Latest Vitals
    html += '<div class="col-lg-4 col-md-6 mb-3"><div class="card-modern h-100"><div class="card-header bg-success text-white py-2"><h6 class="mb-0"><i class="mdi mdi-heart-pulse"></i> Latest Vitals</h6></div><div class="card-body p-2">';
    if (v) {
        // Highlight abnormal BP
        let bpClass = '';
        if (v.bp && v.bp !== 'N/A') {
            const bpSplit = v.bp.split('/');
            if (bpSplit.length === 2 && (parseInt(bpSplit[0]) >= 140 || parseInt(bpSplit[1]) >= 90)) bpClass = 'text-danger fw-bold';
        }
        html += `<table class="table table-sm table-borderless mb-0">
            <tr><td class="text-muted" style="width:40%;"><i class="mdi mdi-heart-pulse text-danger"></i> BP</td><td class="${bpClass}">${v.bp || 'N/A'} mmHg</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-thermometer text-warning"></i> Temp</td><td>${v.temp || 'N/A'} °C</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-heart text-danger"></i> Heart Rate</td><td>${v.heart_rate || 'N/A'} bpm</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-lungs text-info"></i> Resp Rate</td><td>${v.resp_rate || 'N/A'}/min</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-weight text-primary"></i> Weight</td><td>${v.weight || 'N/A'} kg</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-water-percent text-info"></i> SpO2</td><td>${v.spo2 || 'N/A'} %</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-clock text-secondary"></i> Recorded</td><td class="small">${v.time || 'N/A'}</td></tr>
        </table>`;
    } else {
        html += '<p class="text-muted text-center py-3 mb-0">No vitals recorded — <a href="javascript:void(0)" onclick="switchWorkspaceTab(\'vitals\')">Record now</a></p>';
    }
    html += '</div></div></div>';

    // Timeline
    html += '<div class="col-lg-4 col-md-12 mb-3"><div class="card-modern h-100"><div class="card-header bg-info text-white py-2"><h6 class="mb-0"><i class="mdi mdi-timeline"></i> Timeline</h6></div><div class="card-body p-2" style="max-height:280px; overflow-y:auto;" id="overview-timeline">';
    html += '<p class="text-muted text-center py-3 mb-0"><i class="mdi mdi-loading mdi-spin"></i> Loading timeline...</p>';
    html += '</div></div></div>';

    html += '</div>';
    $('#overview-content').html(html);

    // Load timeline with icons & color coding
    if (currentEnrollmentId) {
        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/timeline`, function(resp) {
            if (resp.success && resp.timeline.length > 0) {
                const typeIcons = {
                    enrollment: 'mdi-clipboard-plus',
                    anc: 'mdi-stethoscope',
                    delivery: 'mdi-baby-carriage',
                    baby: 'mdi-baby-face',
                    postnatal: 'mdi-account-heart',
                    immunization: 'mdi-needle',
                    vitals: 'mdi-heart-pulse',
                    lab: 'mdi-test-tube',
                    note: 'mdi-note-text',
                    default: 'mdi-circle-small'
                };
                const typeColors = {
                    enrollment: 'var(--maternity-pink)',
                    anc: '#e91e63',
                    delivery: '#4caf50',
                    baby: '#8bc34a',
                    postnatal: '#2196f3',
                    immunization: '#ff9800',
                    vitals: '#f44336',
                    lab: '#9c27b0',
                    note: '#607d8b',
                    default: '#999'
                };
                const tabMap = { anc: 'anc', delivery: 'delivery', baby: 'baby', postnatal: 'postnatal', immunization: 'immunization', vitals: 'vitals', lab: 'clinical-orders', note: 'notes' };

                let tHtml = '<div class="timeline-container">';
                resp.timeline.forEach(function(item) {
                    const icon = typeIcons[item.type] || typeIcons.default;
                    const color = typeColors[item.type] || typeColors.default;
                    const clickTab = tabMap[item.type];
                    const clickAttr = clickTab ? `style="cursor:pointer;" onclick="switchWorkspaceTab('${clickTab}')"` : '';
                    tHtml += `<div class="timeline-item ${item.type}" ${clickAttr}>
                        <div class="timeline-icon" style="color:${color};"><i class="mdi ${icon}"></i></div>
                        <div class="timeline-date">${item.date || ''}</div>
                        <div class="timeline-title">${item.title}</div>
                        <div class="timeline-detail">${item.detail || ''}</div>
                    </div>`;
                });
                tHtml += '</div>';
                $('#overview-timeline').html(tHtml);
            } else {
                $('#overview-timeline').html('<p class="text-muted text-center py-2 mb-0">No timeline events</p>');
            }
        });
    }
}

// ═══════════════════════════════════════════════════════════════
// ENROLLMENT TAB
// ═══════════════════════════════════════════════════════════════
function loadEnrollmentTab() {
    if (currentEnrollment) {
        renderEnrollmentDetails();
    } else {
        renderEnrollmentForm();
    }
}

function renderEnrollmentForm() {
    const html = `
    <div class="card-modern">
        <div class="card-header text-white" style="background: var(--maternity-pink);">
            <h6 class="mb-0"><i class="mdi mdi-clipboard-plus"></i> New Maternity Enrollment</h6>
        </div>
        <div class="card-body">
            <form id="enrollment-form">
                <input type="hidden" name="patient_id" value="${currentPatient}">
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-door-open"></i> Entry Point</div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Entry Point <span class="text-danger">*</span></label>
                            <select name="entry_point" class="form-select" required>
                                <option value="anc" selected>ANC (Antenatal Care)</option>
                                <option value="delivery">Delivery (Labour Ward)</option>
                                <option value="postnatal">Postnatal</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-calendar"></i> Dates & Obstetric Formula</div>
                    <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Obstetric formula: G = total pregnancies including current, P = deliveries ≥20 weeks, A = living children, Ab = abortions/miscarriages</div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">LMP <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Last Menstrual Period — first day of last normal menstrual cycle. Used to calculate gestational age and EDD."><i class="mdi mdi-help-circle"></i></span></label><input type="date" name="lmp" class="form-control" id="enroll-lmp" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">EDD <span class="mat-tooltip-icon" title="Estimated Date of Delivery — auto-calculated as LMP + 280 days (Naegele's rule)"><i class="mdi mdi-help-circle"></i></span> <small class="text-muted">(auto-calculated)</small></label><input type="date" name="edd" class="form-control" id="enroll-edd"></div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">GA at Booking</label>
                            <div class="form-control bg-light" id="enroll-ga-display" style="font-weight:600; color:#555;">— enter LMP —</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Days to EDD</label>
                            <div class="form-control bg-light" id="enroll-edd-countdown" style="font-weight:600; color:#555;">—</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2 mb-3"><label class="form-label">Gravida <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Total number of pregnancies including current one (G1 = first pregnancy)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="gravida" class="form-control" min="1" placeholder="e.g. 2" required></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Parity <span class="mat-tooltip-icon" title="Number of pregnancies carried to ≥20 weeks (regardless of outcome)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="parity" class="form-control" min="0" value="0" placeholder="e.g. 1"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Alive <span class="mat-tooltip-icon" title="Number of living children from previous pregnancies"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="alive" class="form-control" min="0" value="0" placeholder="e.g. 1"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Abortion / Miscarriage <span class="mat-tooltip-icon" title="Number of pregnancy losses before 20 weeks gestation"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="abortion_miscarriage" class="form-control" min="0" value="0" placeholder="e.g. 0"></div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-human-pregnant"></i> Booking Measurements</div>
                    <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Initial baseline measurements recorded at first antenatal visit (booking visit)</div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Blood Group <span class="mat-tooltip-icon" title="ABO and Rhesus type — Rh-negative mothers need anti-D prophylaxis"><i class="mdi mdi-help-circle"></i></span></label><select name="blood_group" class="form-select"><option value="">-- Select --</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Genotype <span class="mat-tooltip-icon" title="Haemoglobin genotype — SS/SC = Sickle cell disease, AS = carrier trait"><i class="mdi mdi-help-circle"></i></span></label><select name="genotype" class="form-select"><option value="">-- Select --</option><option>AA</option><option>AS</option><option>SS</option><option>AC</option><option>SC</option><option>CC</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Weight (kg)</label><input type="number" name="booking_weight_kg" id="enroll-weight" class="form-control" step="0.1" placeholder="e.g. 65.0"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Height (cm)</label><input type="number" name="height_cm" id="enroll-height" class="form-control" step="0.1" placeholder="e.g. 160.0"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Booking BP <span class="mat-tooltip-icon" title="First blood pressure reading in pregnancy. Format: systolic/diastolic (e.g. 120/80)"><i class="mdi mdi-help-circle"></i></span></label><input type="text" name="booking_bp" class="form-control" placeholder="e.g. 120/80"></div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">BMI <small class="text-muted">(auto-calculated)</small></label>
                            <div class="form-control bg-light" id="enroll-bmi-display" style="font-weight:600;">—</div>
                        </div>
                        <div class="col-md-3 mb-3"><label class="form-label">Risk Level <span class="mat-tooltip-icon" title="Low: uncomplicated pregnancy. Moderate: age >35, prior C-section, mild anaemia. High: pre-eclampsia, multiple gestation. Very High: eclampsia, placenta praevia"><i class="mdi mdi-help-circle"></i></span></label><select name="risk_level" class="form-select"><option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option><option value="very_high">Very High</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Risk Factors <span class="mat-tooltip-icon" title="List specific risk factors: e.g. previous stillbirth, pre-eclampsia history, sickle cell disease, multiple gestation, age >40"><i class="mdi mdi-help-circle"></i></span></label>
                            <textarea name="risk_factors" class="form-control" rows="2" placeholder="List risk factors separated by commas (e.g. previous C-section, anaemia, age >35)"></textarea>
                        </div>
                    </div>
                </div>
                <div class="p-3 text-end">
                    <button type="submit" class="btn btn-lg text-white" style="background: var(--maternity-pink);"><i class="mdi mdi-check"></i> Enroll Patient</button>
                </div>
            </form>
        </div>
    </div>`;
    $('#enrollment-content').html(html);

    // Auto-calculate EDD, GA display, countdown from LMP
    function updateLmpCalculations() {
        const lmpVal = $('#enroll-lmp').val();
        if (!lmpVal) {
            $('#enroll-edd').val('');
            $('#enroll-ga-display').html('— enter LMP —');
            $('#enroll-edd-countdown').html('—');
            return;
        }
        const lmp = new Date(lmpVal);
        if (isNaN(lmp)) return;

        // EDD = LMP + 280 days (Naegele's rule)
        const edd = new Date(lmp);
        edd.setDate(edd.getDate() + 280);
        $('#enroll-edd').val(edd.toISOString().split('T')[0]);

        // GA at booking (from LMP to today)
        const today = new Date();
        const diffDays = Math.floor((today - lmp) / (1000 * 60 * 60 * 24));
        if (diffDays >= 0) {
            const weeks = Math.floor(diffDays / 7);
            const days = diffDays % 7;
            const trimester = weeks < 13 ? '1st' : (weeks < 28 ? '2nd' : '3rd');
            $('#enroll-ga-display').html(`<span style="color:#333;">${weeks}w ${days}d</span> <span class="badge bg-secondary" style="font-size:0.65rem;">${trimester} trimester</span>`);
        } else {
            $('#enroll-ga-display').html('<span class="text-warning">Future date?</span>');
        }

        // Days to EDD countdown
        const daysToEdd = Math.floor((edd - today) / (1000 * 60 * 60 * 24));
        if (daysToEdd > 0) {
            const countdownColor = daysToEdd <= 14 ? '#ffc107' : (daysToEdd <= 42 ? '#17a2b8' : '#28a745');
            $('#enroll-edd-countdown').html(`<span style="color:${countdownColor};">${daysToEdd} days</span>`);
        } else if (daysToEdd === 0) {
            $('#enroll-edd-countdown').html('<span class="text-danger fw-bold">DUE TODAY</span>');
        } else {
            $('#enroll-edd-countdown').html(`<span class="text-danger fw-bold">${Math.abs(daysToEdd)} days overdue</span>`);
        }
    }
    $('#enroll-lmp').on('change', updateLmpCalculations);

    // Auto-calculate BMI from weight and height
    function updateBmiCalc() {
        const wt = parseFloat($('#enroll-weight').val());
        const ht = parseFloat($('#enroll-height').val());
        if (wt > 0 && ht > 0) {
            const bmi = (wt / ((ht / 100) ** 2)).toFixed(1);
            const category = bmi < 18.5 ? 'Underweight' : (bmi < 25 ? 'Normal' : (bmi < 30 ? 'Overweight' : 'Obese'));
            const color = bmi < 18.5 ? '#17a2b8' : (bmi < 25 ? '#28a745' : (bmi < 30 ? '#ffc107' : '#dc3545'));
            $('#enroll-bmi-display').html(`<span style="color:${color};">${bmi}</span> <span class="badge" style="background:${color}; font-size:0.65rem;">${category}</span>`);
        } else {
            $('#enroll-bmi-display').html('—');
        }
    }
    $('#enroll-weight, #enroll-height').on('input', updateBmiCalc);

    // Handle enrollment form submit
    $('#enrollment-form').on('submit', function(e) {
        e.preventDefault();
        const formData = {};
        $(this).serializeArray().forEach(f => formData[f.name] = f.value);

        $.ajax({
            url: '{{ route("maternity-workbench.enroll") }}',
            method: 'POST',
            data: formData,
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            success: function(resp) {
                if (resp.success) {
                    toastr.success(resp.message);
                    currentEnrollment = resp.enrollment;
                    currentEnrollmentId = resp.enrollment_id;
                    loadPatient(currentPatient); // Reload
                } else {
                    toastr.error(resp.message || 'Enrollment failed');
                }
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    Object.values(errors).flat().forEach(e => toastr.error(e));
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Enrollment failed');
                }
            }
        });
    });
}

function renderEnrollmentDetails() {
    const e = currentEnrollment;

    // Status transition bar
    const statuses = ['active', 'delivered', 'postnatal', 'discharged'];
    const statusLabels = ['Active (ANC)', 'Delivered', 'Postnatal', 'Discharged'];
    const statusColors = ['#e91e63', '#4caf50', '#2196f3', '#6c757d'];
    const currentIdx = statuses.indexOf(e.status);

    let statusBarHtml = '<div class="d-flex align-items-center mb-3" style="gap:0;">';
    statuses.forEach((s, i) => {
        const isActive = i <= currentIdx;
        const isCurrent = i === currentIdx;
        const bg = isActive ? statusColors[i] : '#e0e0e0';
        const textColor = isActive ? '#fff' : '#999';
        statusBarHtml += `<div class="text-center px-2 py-1 flex-fill" style="background:${bg}; color:${textColor}; font-size:0.72rem; font-weight:${isCurrent ? '700' : '400'}; ${i === 0 ? 'border-radius:6px 0 0 6px;' : ''} ${i === 3 ? 'border-radius:0 6px 6px 0;' : ''}">
            ${isCurrent ? '<i class="mdi mdi-chevron-right"></i> ' : ''}${statusLabels[i]}
        </div>`;
    });
    statusBarHtml += '</div>';

    // BMI display
    const bmi = (e.booking_weight_kg && e.height_cm) ? (e.booking_weight_kg / ((e.height_cm / 100) ** 2)).toFixed(1) : null;
    const bmiCategory = bmi ? (bmi < 18.5 ? 'Underweight' : (bmi < 25 ? 'Normal' : (bmi < 30 ? 'Overweight' : 'Obese'))) : '';
    const bmiColor = bmi ? (bmi < 18.5 ? '#17a2b8' : (bmi < 25 ? '#28a745' : (bmi < 30 ? '#ffc107' : '#dc3545'))) : '#999';

    const html = `
    <div class="card-modern">
        <div class="card-header text-white d-flex justify-content-between align-items-center" style="background: var(--maternity-pink);">
            <h6 class="mb-0"><i class="mdi mdi-clipboard-check"></i> Enrollment Details</h6>
            <span class="enrollment-badge ${e.status}" style="background: rgba(255,255,255,0.2); color: white;">${e.status.toUpperCase()}</span>
        </div>
        <div class="card-body">
            ${statusBarHtml}
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td class="text-muted" style="width:40%;">Entry Point</td><td class="fw-bold">${(e.entry_point || '').toUpperCase()}</td></tr>
                        <tr><td class="text-muted">Booking Date</td><td>${e.booking_date || 'N/A'}</td></tr>
                        <tr><td class="text-muted">LMP</td><td>${e.lmp || 'N/A'}</td></tr>
                        <tr><td class="text-muted">EDD</td><td>${e.edd || 'N/A'} ${e.remaining_days !== null ? (e.remaining_days < 0 ? '<span class="badge bg-danger ms-1">' + Math.abs(e.remaining_days) + 'd overdue</span>' : '<span class="badge bg-secondary ms-1">' + e.remaining_days + 'd remaining</span>') : ''}</td></tr>
                        <tr><td class="text-muted">Gestational Age</td><td><span class="ga-pill">${e.gestational_age || 'N/A'}</span></td></tr>
                        <tr><td class="text-muted">Obstetric Formula</td><td class="fw-bold">G${e.gravida || '?'} P${e.parity || '?'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td class="text-muted" style="width:40%;">Blood Group</td><td>${e.blood_group || 'N/A'}</td></tr>
                        <tr><td class="text-muted">Genotype</td><td>${e.genotype || 'N/A'}</td></tr>
                        <tr><td class="text-muted">Weight</td><td>${e.booking_weight_kg ? e.booking_weight_kg + ' kg' : 'N/A'}</td></tr>
                        <tr><td class="text-muted">Height</td><td>${e.height_cm ? e.height_cm + ' cm' : 'N/A'}</td></tr>
                        <tr><td class="text-muted">BMI</td><td>${bmi ? '<span style="color:' + bmiColor + '; font-weight:600;">' + bmi + '</span> <span class="badge" style="background:' + bmiColor + '; font-size:0.65rem;">' + bmiCategory + '</span>' : 'N/A'}</td></tr>
                        <tr><td class="text-muted">BP</td><td>${e.booking_bp || 'N/A'}</td></tr>
                        <tr><td class="text-muted">Risk Level</td><td><span class="risk-indicator ${e.risk_level}">${(e.risk_level || 'low').replace('_', ' ')}</span></td></tr>
                        ${e.risk_factors ? '<tr><td class="text-muted">Risk Factors</td><td class="small">' + e.risk_factors + '</td></tr>' : ''}
                    </table>
                </div>
            </div>
        </div>
    </div>`;
    $('#enrollment-content').html(html);
}

// ═══════════════════════════════════════════════════════════════
// HISTORY TAB
// ═══════════════════════════════════════════════════════════════
function loadHistoryTab() {
    if (!currentEnrollmentId) { $('#history-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>'); return; }

    $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
        if (!resp.success) return;
        const enrollment = resp.enrollment;
        window._medicalHistoryCache = enrollment.medical_history || [];
        window._prevPregnanciesCache = enrollment.previous_pregnancies || [];
        let html = '';

        // Medical History
        html += '<div class="card-modern mb-3"><div class="card-header" style="background: #f8f9fa;"><h6 class="mb-0"><i class="mdi mdi-clipboard-text"></i> Medical / Surgical History</h6></div><div class="card-body">';
        if (enrollment.medical_history && enrollment.medical_history.length > 0) {
            html += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Category</th><th>Description</th><th>Year</th><th>Notes</th><th style="width:80px">Actions</th></tr></thead><tbody>';
            enrollment.medical_history.forEach(h => {
                html += `<tr><td><span class="badge bg-secondary">${h.category}</span></td><td>${h.description}</td><td>${h.year || '-'}</td><td>${h.notes || '-'}</td>
                <td><button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editMedicalHistory(${h.id})" title="Edit"><i class="mdi mdi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteMedicalHistory(${h.id})" title="Delete"><i class="mdi mdi-delete"></i></button></td></tr>`;
            });
            html += '</tbody></table></div>';
        } else {
            html += '<p class="text-muted mb-0">No medical history recorded</p>';
        }
        html += `<button class="btn btn-sm btn-outline-primary mt-2" onclick="showAddHistoryForm()"><i class="mdi mdi-plus"></i> Add History</button></div></div>`;

        // Previous Pregnancies
        html += '<div class="card-modern mb-3"><div class="card-header" style="background: #f8f9fa;"><h6 class="mb-0"><i class="mdi mdi-human-pregnant"></i> Previous Pregnancies</h6></div><div class="card-body">';
        if (enrollment.previous_pregnancies && enrollment.previous_pregnancies.length > 0) {
            html += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Year</th><th>Duration</th><th>Place</th><th>Outcome</th><th>Sex</th><th>Weight</th><th>Notes</th><th style="width:60px">Edit</th></tr></thead><tbody>';
            enrollment.previous_pregnancies.forEach(p => {
                const outcome = p.baby_alive ? '✅ Alive' : (p.baby_dead ? '❌ Dead' : (p.baby_stillbirth ? '💔 Stillbirth' : '-'));
                html += `<tr><td>${p.year || '-'}</td><td>${p.duration_weeks ? p.duration_weeks + 'w' : '-'}</td><td>${p.place_of_delivery || '-'}</td><td>${outcome}</td><td>${p.baby_sex || '-'}</td><td>${p.birth_weight_kg ? p.birth_weight_kg + 'kg' : '-'}</td><td>${p.notes || '-'}</td>
                <td><button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editPreviousPregnancy(${p.id})" title="Edit"><i class="mdi mdi-pencil"></i></button></td></tr>`;
            });
            html += '</tbody></table></div>';
        } else {
            html += '<p class="text-muted mb-0">No previous pregnancies recorded</p>';
        }
        html += `<button class="btn btn-sm btn-outline-primary mt-2" onclick="showAddPregnancyForm()"><i class="mdi mdi-plus"></i> Add Previous Pregnancy</button></div></div>`;

        $('#history-content').html(html);
    });
}

function showAddHistoryForm() {
    _editMode = null; _editId = null;
    const form = $('#addHistoryModal #add-history-form')[0];
    if (form) form.reset();
    $('#addHistoryModalLabel').html('<i class="mdi mdi-clipboard-text-clock"></i> Add Medical History');
    $('#btn-save-history').html('<i class="mdi mdi-check"></i> Save');
    $('#addHistoryModal input[name="year"]').attr('max', new Date().getFullYear());
    $('#addHistoryModal').modal('show');
}

function editMedicalHistory(id) {
    const h = (window._medicalHistoryCache || []).find(x => x.id === id);
    if (!h) { toastr.error('Record not found'); return; }
    _editMode = 'history'; _editId = id;
    const form = $('#addHistoryModal #add-history-form')[0];
    if (form) form.reset();
    $('#addHistoryModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Medical History');
    $('#btn-save-history').html('<i class="mdi mdi-check"></i> Update');
    $('#addHistoryModal select[name="category"]').val(h.category || 'medical');
    $('#addHistoryModal input[name="year"]').val(h.year || '').attr('max', new Date().getFullYear());
    $('#addHistoryModal input[name="description"]').val(h.description || '');
    $('#addHistoryModal input[name="notes"]').val(h.notes || '');
    $('#addHistoryModal').modal('show');
}

function deleteMedicalHistory(id) {
    if (!confirm('Delete this medical history entry?')) return;
    $.ajax({
        url: `/maternity-workbench/medical-history/${id}`,
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            if (r.success) { toastr.success(r.message || 'Deleted'); loadHistoryTab(); } else toastr.error(r.message);
        },
        error: function() { toastr.error('Failed to delete'); }
    });
}

// Medical History modal save handler
$(document).on('click', '#btn-save-history', function() {
    const form = $('#addHistoryModal #add-history-form');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    const isEdit = _editMode === 'history' && _editId;
    if (isEdit) {
        // Single record update via PUT
        const data = {};
        form.serializeArray().forEach(f => data[f.name] = f.value);
        $.ajax({
            url: `/maternity-workbench/medical-history/${_editId}`,
            method: 'PUT', data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            success: function(r) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                if (r.success) { _editMode = null; _editId = null; $('#addHistoryModal').modal('hide'); toastr.success(r.message); loadHistoryTab(); } else toastr.error(r.message);
            },
            error: function() { btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save'); toastr.error('Failed to update'); }
        });
    } else {
        // Create via POST (existing pattern)
        const data = { items: [{}] };
        form.serializeArray().forEach(f => data.items[0][f.name] = f.value);
        $.ajax({
            url: `/maternity-workbench/enrollment/${currentEnrollmentId}/medical-history`,
            method: 'POST', data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            success: function(r) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                if (r.success) { $('#addHistoryModal').modal('hide'); toastr.success(r.message); loadHistoryTab(); } else toastr.error(r.message);
            },
            error: function() { btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save'); toastr.error('Failed to save'); }
        });
    }
});

function showAddPregnancyForm() {
    _editMode = null; _editId = null;
    const form = $('#addPregnancyModal #add-pregnancy-form')[0];
    if (form) form.reset();
    $('#addPregnancyModalLabel').html('<i class="mdi mdi-baby-carriage"></i> Add Previous Pregnancy');
    $('#btn-save-pregnancy').html('<i class="mdi mdi-check"></i> Save');
    $('#addPregnancyModal input[name="year"]').attr('max', new Date().getFullYear());
    $('#addPregnancyModal').modal('show');
}

function editPreviousPregnancy(id) {
    const p = (window._prevPregnanciesCache || []).find(x => x.id === id);
    if (!p) { toastr.error('Record not found'); return; }
    _editMode = 'pregnancy'; _editId = id;
    const form = $('#addPregnancyModal #add-pregnancy-form')[0];
    if (form) form.reset();
    $('#addPregnancyModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Previous Pregnancy');
    $('#btn-save-pregnancy').html('<i class="mdi mdi-check"></i> Update');
    const m = $('#addPregnancyModal');
    m.find('input[name="year"]').val(p.year || '').attr('max', new Date().getFullYear());
    m.find('input[name="duration_weeks"]').val(p.duration_weeks || '');
    m.find('input[name="place_of_delivery"]').val(p.place_of_delivery || '');
    m.find('select[name="baby_sex"]').val(p.baby_sex || '');
    m.find('input[name="birth_weight_kg"]').val(p.birth_weight_kg || '');
    // Determine outcome from booleans
    const outcome = p.baby_alive ? 'alive' : (p.baby_dead ? 'dead' : (p.baby_stillbirth ? 'stillbirth' : 'alive'));
    m.find('select[name="outcome"]').val(outcome);
    m.find('input[name="complications"]').val(p.complications || '');
    m.find('input[name="notes"]').val(p.notes || '');
    m.modal('show');
}

// Previous Pregnancy modal save handler
$(document).on('click', '#btn-save-pregnancy', function() {
    const form = $('#addPregnancyModal #add-pregnancy-form');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const data = {};
    form.serializeArray().forEach(f => data[f.name] = f.value);
    data.baby_alive = data.outcome === 'alive';
    data.baby_dead = data.outcome === 'dead';
    data.baby_stillbirth = data.outcome === 'stillbirth';
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    const isEdit = _editMode === 'pregnancy' && _editId;
    const url = isEdit ? `/maternity-workbench/prev-pregnancy/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/prev-pregnancy`;
    const method = isEdit ? 'PUT' : 'POST';
    $.ajax({
        url: url, method: method, data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
            if (r.success) { _editMode = null; _editId = null; $('#addPregnancyModal').modal('hide'); toastr.success(r.message); loadHistoryTab(); } else toastr.error(r.message);
        },
        error: function() { btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save'); toastr.error('Failed to save'); }
    });
});

// ═══════════════════════════════════════════════════════════════
// ANC VISITS TAB
// ═══════════════════════════════════════════════════════════════
function loadAncTab() {
    if (!currentEnrollmentId) { $('#anc-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>'); return; }

    $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/anc-visits`, function(resp) {
        if (!resp.success) return;
        _ancVisitsCache = resp.visits; // cache for edit pre-fill
        let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-stethoscope"></i> ANC Visits (${resp.visits.length})</h5>
            <button class="btn text-white" style="background: var(--maternity-pink);" onclick="showAncVisitForm()"><i class="mdi mdi-plus"></i> New ANC Visit</button>
        </div>`;

        if (resp.visits.length === 0) {
            html += '<p class="text-muted text-center py-4">No ANC visits recorded yet</p>';
        } else {
            // Trend charts panel (show when ≥2 visits with data)
            html += `<div class="card-modern mb-3">
                <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;" onclick="$('#anc-trends-body').slideToggle(200); $(this).find('.mdi-chevron-down, .mdi-chevron-up').toggleClass('mdi-chevron-down mdi-chevron-up');">
                    <h6 class="mb-0"><i class="mdi mdi-chart-line"></i> ANC Trend Charts</h6>
                    <i class="mdi mdi-chevron-down"></i>
                </div>
                <div class="card-body" id="anc-trends-body" style="display:none;">
                    <div class="small text-muted mb-2"><i class="mdi mdi-information"></i> Trends are plotted from ANC visit data. Reference lines indicate clinically significant thresholds.</div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Blood Pressure Trend</div><div style="position:relative; height:200px;"><canvas id="anc-chart-bp"></canvas></div></div></div>
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Weight Gain Trend</div><div style="position:relative; height:200px;"><canvas id="anc-chart-weight"></canvas></div></div></div>
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Fundal Height vs Gestational Age</div><div style="position:relative; height:200px;"><canvas id="anc-chart-fundal"></canvas></div></div></div>
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Haemoglobin Trend</div><div style="position:relative; height:200px;"><canvas id="anc-chart-hb"></canvas></div></div></div>
                    </div>
                </div>
            </div>`;

            resp.visits.forEach(function(v) {
                html += `<div class="anc-visit-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><span class="visit-number">Visit #${v.visit_number}</span> <span class="badge bg-secondary ms-1">${v.visit_type || ''}</span></div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="visit-date">${v.visit_date || ''}</span>
                            <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editAncVisit(${v.id})" title="Edit visit"><i class="mdi mdi-pencil"></i></button>
                        </div>
                    </div>
                    <div class="visit-details">
                        <div><div class="visit-detail-label">GA</div><div class="visit-detail-value">${v.gestational_age || '-'}</div></div>
                        <div><div class="visit-detail-label">Weight</div><div class="visit-detail-value">${v.weight_kg ? v.weight_kg + ' kg' : '-'}</div></div>
                        <div><div class="visit-detail-label">BP</div><div class="visit-detail-value">${v.bp || '-'}</div></div>
                        <div><div class="visit-detail-label">Fundal Ht</div><div class="visit-detail-value">${v.fundal_height ? v.fundal_height + ' cm' : '-'}</div></div>
                        <div><div class="visit-detail-label">FHR</div><div class="visit-detail-value">${v.fhr || '-'}</div></div>
                        <div><div class="visit-detail-label">Presentation</div><div class="visit-detail-value">${v.presentation || '-'}</div></div>
                        <div><div class="visit-detail-label">Oedema</div><div class="visit-detail-value">${v.oedema || '-'}</div></div>
                        <div><div class="visit-detail-label">Foetal Mvt</div><div class="visit-detail-value">${v.foetal_movement || '-'}</div></div>
                        <div><div class="visit-detail-label">Hb</div><div class="visit-detail-value">${v.haemoglobin ? v.haemoglobin + ' g/dL' : '-'}</div></div>
                        <div><div class="visit-detail-label">Urine Protein</div><div class="visit-detail-value">${v.urine_protein || '-'}</div></div>
                        <div><div class="visit-detail-label">Urine Glucose</div><div class="visit-detail-value">${v.urine_glucose || '-'}</div></div>
                        <div><div class="visit-detail-label">Next Appt</div><div class="visit-detail-value">${v.next_appointment || '-'}</div></div>
                    </div>
                    ${v.clinical_notes ? '<div class="mt-2 small text-muted"><i class="mdi mdi-note"></i> ' + v.clinical_notes + '</div>' : ''}
                    <div class="mt-1 small text-muted">Seen by: ${v.seen_by}</div>
                </div>`;
            });
        }
        $('#anc-content').html(html);
        if (resp.visits.length >= 2) {
            renderAncTrendCharts(resp.visits);
        }
    });
}

function showAncVisitForm() {
    _editMode = null; _editId = null; // reset to create mode
    destroyMaternityEditor('anc_notes');
    const form = $('#ancVisitModal #anc-visit-form')[0];
    if (form) form.reset();
    $('#ancVisitModalLabel').html('<i class="mdi mdi-stethoscope"></i> Record ANC Visit');
    $('#btn-save-anc-visit').html('<i class="mdi mdi-check"></i> Save Visit');
    // Set dynamic defaults
    $('#ancVisitModal input[name="visit_date"]').val(new Date().toISOString().split('T')[0]);
    const gaWeeks = currentEnrollment && currentEnrollment.gestational_age ? parseInt(currentEnrollment.gestational_age) : '';
    $('#ancVisitModal input[name="gestational_age_weeks"]').val(gaWeeks);

    // Init CKEditor after modal is fully visible
    $('#ancVisitModal').off('shown.bs.modal.ancEditor').on('shown.bs.modal.ancEditor', function() {
        initMaternityEditor('#mat-anc-notes-editor-modal', 'anc_notes');
    });
    // Destroy CKEditor when modal hides
    $('#ancVisitModal').off('hidden.bs.modal.ancEditor').on('hidden.bs.modal.ancEditor', function() {
        destroyMaternityEditor('anc_notes');
    });

    $('#ancVisitModal').modal('show');
}

function editAncVisit(id) {
    const v = _ancVisitsCache.find(x => x.id === id);
    if (!v) { toastr.error('Visit data not found'); return; }
    _editMode = 'anc'; _editId = id;
    destroyMaternityEditor('anc_notes');
    const form = $('#ancVisitModal #anc-visit-form')[0];
    if (form) form.reset();
    $('#ancVisitModalLabel').html('<i class="mdi mdi-pencil"></i> Edit ANC Visit #' + v.visit_number);
    $('#btn-save-anc-visit').html('<i class="mdi mdi-check"></i> Update Visit');
    // Pre-fill form fields
    $('#ancVisitModal input[name="visit_date"]').val(v.visit_date_raw || '');
    $('#ancVisitModal input[name="gestational_age_weeks"]').val(v.gestational_age_weeks || '');
    $('#ancVisitModal select[name="visit_type"]').val(v.visit_type || '');
    $('#ancVisitModal input[name="next_appointment"]').val(v.next_appointment_raw || '');
    $('#ancVisitModal input[name="weight_kg"]').val(v.weight_kg || '');
    $('#ancVisitModal input[name="blood_pressure_systolic"]').val(v.blood_pressure_systolic || '');
    $('#ancVisitModal input[name="blood_pressure_diastolic"]').val(v.blood_pressure_diastolic || '');
    $('#ancVisitModal input[name="haemoglobin"]').val(v.haemoglobin || '');
    $('#ancVisitModal input[name="fundal_height_cm"]').val(v.fundal_height_cm || '');
    $('#ancVisitModal input[name="fetal_heart_rate"]').val(v.fetal_heart_rate || '');
    $('#ancVisitModal select[name="presentation"]').val(v.presentation || '');
    $('#ancVisitModal select[name="oedema"]').val(v.oedema || '');
    $('#ancVisitModal select[name="foetal_movement"]').val(v.foetal_movement || '');
    $('#ancVisitModal select[name="urine_protein"]').val(v.urine_protein || '');
    $('#ancVisitModal select[name="urine_glucose"]').val(v.urine_glucose || '');
    // CKEditor: init after modal is visible, then set data
    $('#ancVisitModal').off('shown.bs.modal.ancEditor').on('shown.bs.modal.ancEditor', function() {
        initMaternityEditor('#mat-anc-notes-editor-modal', 'anc_notes').then(function(editor) {
            if (editor && v.clinical_notes) editor.setData(v.clinical_notes);
        });
    });
    $('#ancVisitModal').off('hidden.bs.modal.ancEditor').on('hidden.bs.modal.ancEditor', function() {
        destroyMaternityEditor('anc_notes');
    });
    $('#ancVisitModal').modal('show');
}

// ANC Visit modal save handler
$(document).on('click', '#btn-save-anc-visit', function() {
    const form = $('#ancVisitModal #anc-visit-form');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const data = {};
    form.serializeArray().forEach(f => data[f.name] = f.value);
    data.clinical_notes = getEditorData('anc_notes', '#mat-anc-notes-editor-modal');
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    const isEdit = _editMode === 'anc' && _editId;
    const url = isEdit ? `/maternity-workbench/anc-visit/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/anc-visit`;
    const method = isEdit ? 'PUT' : 'POST';
    $.ajax({
        url: url, method: method, data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Visit');
            if (r.success) { _editMode = null; _editId = null; destroyMaternityEditor('anc_notes'); $('#ancVisitModal').modal('hide'); toastr.success(r.message); loadAncTab(); } else toastr.error(r.message);
        },
        error: function(xhr) {
            btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Visit');
            const e = xhr.responseJSON?.errors; if (e) Object.values(e).flat().forEach(m => toastr.error(m)); else toastr.error('Failed to save');
        }
    });
});

// ─── ANC Trend Charts (Phase 4) ───────────────────────────────
function renderAncTrendCharts(visits) {
    if (typeof Chart === 'undefined' || !visits || visits.length < 2) return;

    // Destroy previous chart instances
    ['_ancBpChart','_ancWeightChart','_ancFundalChart','_ancHbChart'].forEach(k => {
        if (window[k]) { window[k].destroy(); window[k] = null; }
    });

    const sorted = [...visits].sort((a, b) => {
        const da = a.visit_date_raw || a.visit_date || '';
        const db = b.visit_date_raw || b.visit_date || '';
        return da.localeCompare(db);
    });
    const labels = sorted.map(v => v.visit_date || `V#${v.visit_number}`);
    const toNum = v => (v === null || v === undefined || v === '') ? null : Number(v);

    // ── 1. Blood Pressure Chart ──
    const bpCanvas = document.getElementById('anc-chart-bp');
    if (bpCanvas) {
        const sys = sorted.map(v => toNum(v.blood_pressure_systolic));
        const dia = sorted.map(v => toNum(v.blood_pressure_diastolic));
        window._ancBpChart = new Chart(bpCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Systolic', data: sys, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', tension: 0.3, pointRadius: 4, spanGaps: true },
                    { label: 'Diastolic', data: dia, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', tension: 0.3, pointRadius: 4, spanGaps: true },
                    { label: 'Pre-eclampsia threshold (140/90)', data: new Array(labels.length).fill(140), borderColor: '#fd7e14', borderDash: [5,3], pointRadius: 0, borderWidth: 1.5, fill: false },
                    { label: '', data: new Array(labels.length).fill(90), borderColor: '#fd7e14', borderDash: [5,3], pointRadius: 0, borderWidth: 1.5, fill: false }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { usePointStyle: true, padding: 8, font: {size: 10} } } }, scales: { y: { title: { display: true, text: 'mmHg' }, min: 40, max: 200 }, x: { ticks: { font: {size: 9}, maxRotation: 45 } } } }
        });
    }

    // ── 2. Weight Gain Chart ──
    const wtCanvas = document.getElementById('anc-chart-weight');
    if (wtCanvas) {
        const wts = sorted.map(v => toNum(v.weight_kg));
        const ppw = currentEnrollment && currentEnrollment.pre_pregnancy_weight ? Number(currentEnrollment.pre_pregnancy_weight) : null;
        const datasets = [
            { label: 'Weight (kg)', data: wts, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', tension: 0.3, pointRadius: 4, spanGaps: true, fill: true }
        ];
        if (ppw) {
            datasets.push({ label: 'Pre-pregnancy weight', data: new Array(labels.length).fill(ppw), borderColor: '#6c757d', borderDash: [5,3], pointRadius: 0, borderWidth: 1.5, fill: false });
        }
        window._ancWeightChart = new Chart(wtCanvas.getContext('2d'), {
            type: 'line',
            data: { labels: labels, datasets: datasets },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { usePointStyle: true, padding: 8, font: {size: 10} } } }, scales: { y: { title: { display: true, text: 'kg' } }, x: { ticks: { font: {size: 9}, maxRotation: 45 } } } }
        });
    }

    // ── 3. Fundal Height vs Gestational Age Chart ──
    const fhCanvas = document.getElementById('anc-chart-fundal');
    if (fhCanvas) {
        const gaWeeks = sorted.map(v => toNum(v.gestational_age_weeks));
        const fhCm = sorted.map(v => toNum(v.fundal_height_cm));
        // McDonald's rule reference: fundal height ≈ gestational age ± 2cm
        const refGa = [];
        const refUpper = [];
        const refLower = [];
        for (let w = 12; w <= 42; w++) { refGa.push(w); refUpper.push(w + 2); refLower.push(Math.max(0, w - 2)); }

        const ptData = [];
        sorted.forEach(v => {
            const ga = toNum(v.gestational_age_weeks);
            const fh = toNum(v.fundal_height_cm);
            if (ga !== null && fh !== null) ptData.push({ x: ga, y: fh });
        });

        window._ancFundalChart = new Chart(fhCanvas.getContext('2d'), {
            type: 'scatter',
            data: {
                datasets: [
                    { label: 'Fundal Height', data: ptData, borderColor: '#6f42c1', backgroundColor: '#6f42c1', pointRadius: 5, showLine: true, tension: 0.2 },
                    { label: 'Expected (GA ± 2cm)', data: refGa.map((g,i) => ({x: g, y: g})), borderColor: '#198754', borderDash: [4,2], pointRadius: 0, showLine: true, fill: false, borderWidth: 1.5 },
                    { label: 'Upper limit (+2cm)', data: refGa.map((g,i) => ({x: g, y: refUpper[i]})), borderColor: 'rgba(25,135,84,0.3)', borderDash: [2,2], pointRadius: 0, showLine: true, fill: false, borderWidth: 1 },
                    { label: 'Lower limit (−2cm)', data: refGa.map((g,i) => ({x: g, y: refLower[i]})), borderColor: 'rgba(25,135,84,0.3)', borderDash: [2,2], pointRadius: 0, showLine: true, fill: false, borderWidth: 1 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { usePointStyle: true, padding: 8, font: {size: 10} } } }, scales: { x: { title: { display: true, text: 'Gestational Age (weeks)' }, min: 12, max: 42 }, y: { title: { display: true, text: 'Fundal Height (cm)' }, min: 10, max: 44 } } }
        });
    }

    // ── 4. Haemoglobin Trend Chart ──
    const hbCanvas = document.getElementById('anc-chart-hb');
    if (hbCanvas) {
        const hbs = sorted.map(v => toNum(v.haemoglobin));
        window._ancHbChart = new Chart(hbCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Hb (g/dL)', data: hbs, borderColor: '#d63384', backgroundColor: 'rgba(214,51,132,0.1)', tension: 0.3, pointRadius: 4, spanGaps: true, fill: true },
                    { label: 'Normal threshold (11 g/dL)', data: new Array(labels.length).fill(11), borderColor: '#198754', borderDash: [5,3], pointRadius: 0, borderWidth: 1.5, fill: false },
                    { label: 'Severe anaemia (7 g/dL)', data: new Array(labels.length).fill(7), borderColor: '#dc3545', borderDash: [5,3], pointRadius: 0, borderWidth: 1.5, fill: false }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { usePointStyle: true, padding: 8, font: {size: 10} } } }, scales: { y: { title: { display: true, text: 'g/dL' }, min: 4, max: 16 }, x: { ticks: { font: {size: 9}, maxRotation: 45 } } } }
        });
    }
}

// ═══════════════════════════════════════════════════════════════
// CLINICAL ORDERS TAB (Nursing-parity — auto-save per item)
// ═══════════════════════════════════════════════════════════════
function loadClinicalOrdersTab() {
    if (!currentEnrollmentId || !currentPatient) {
        $('#clinical-orders-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
        return;
    }

    const html = `
    <div class="clinical-requests-container p-3">
        <div class="mat-info-banner mb-3"><i class="mdi mdi-auto-fix"></i> <strong>Auto-save enabled:</strong> Items are saved automatically when selected from search results. Use the search boxes below to find and add prescriptions, lab tests, imaging, or procedures.</div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="mdi mdi-clipboard-pulse"></i> Clinical Orders</h4>
            <span class="badge bg-primary" id="mco-patient-badge">Patient #${currentPatient}</span>
        </div>

        <ul class="nav nav-tabs service-tabs mb-3" id="mco-sub-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-prescriptions" type="button" role="tab">
                    <i class="mdi mdi-pill"></i> Drug Prescription
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-lab" type="button" role="tab">
                    <i class="mdi mdi-flask"></i> Lab Requests
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-imaging" type="button" role="tab">
                    <i class="mdi mdi-radioactive"></i> Imaging
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-procedures" type="button" role="tab">
                    <i class="mdi mdi-medical-bag"></i> Procedures
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- ══ PRESCRIPTIONS ══ -->
            <div class="tab-pane fade show active" id="mco-prescriptions" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                    <i class="fa fa-clipboard-list"></i> Treatment Plans
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                            <div class="dropdown" id="mco-rp-encounter-dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <i class="fa fa-redo"></i> Re-prescribe from Encounter
                                </button>
                                <ul class="dropdown-menu rp-encounter-menu" style="min-width: 320px; max-height: 300px; overflow-y: auto;">
                                    <li class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                                </ul>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-presc-history" type="button"><i class="fa fa-history"></i> Drug History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-presc-new" type="button"><i class="fa fa-plus-circle"></i> Add Prescription</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-presc-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_presc_history_list">
                                        <thead class="table-light"><tr><th style="width:100%"><i class="mdi mdi-pill"></i> Prescriptions</th></tr></thead>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-presc-new" role="tabpanel">
                                <div id="mco_presc_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Prescription</h6>
                                <div id="mco_dose_mode_container"></div>
                                <div class="form-group">
                                    <label>Search drugs/products</label>
                                    <input type="text" class="form-control" id="mco_presc_search" placeholder="Type to search products..." autocomplete="off">
                                    <ul class="list-group" id="mco_presc_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Name</th><th>Price</th><th>Dose / Frequency</th><th>*</th></tr></thead>
                                        <tbody id="mco-selected-products"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ LAB REQUESTS ══ -->
            <div class="tab-pane fade" id="mco-lab" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                    <i class="fa fa-clipboard-list"></i> Treatment Plans
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-lab-history" type="button"><i class="fa fa-history"></i> Lab History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-lab-new" type="button"><i class="fa fa-plus-circle"></i> New Lab Request</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-lab-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_lab_history_list">
                                        <thead class="table-light"><tr><th style="width:100%"><i class="mdi mdi-flask"></i> Lab Requests</th></tr></thead>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-lab-new" role="tabpanel">
                                <div id="mco_lab_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Lab Request</h6>
                                <div class="form-group">
                                    <label>Search lab services</label>
                                    <input type="text" class="form-control" id="mco_lab_search" placeholder="Type to search lab services..." autocomplete="off">
                                    <ul class="list-group" id="mco_lab_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Name</th><th>Price</th><th>Notes</th><th>*</th></tr></thead>
                                        <tbody id="mco-selected-labs"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ IMAGING ══ -->
            <div class="tab-pane fade" id="mco-imaging" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                    <i class="fa fa-clipboard-list"></i> Treatment Plans
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-imaging-history" type="button"><i class="fa fa-history"></i> Imaging History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-imaging-new" type="button"><i class="fa fa-plus-circle"></i> New Imaging Request</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-imaging-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_imaging_history_list">
                                        <thead class="table-light"><tr><th style="width:100%"><i class="mdi mdi-radioactive"></i> Imaging Requests</th></tr></thead>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-imaging-new" role="tabpanel">
                                <div id="mco_imaging_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Imaging Request</h6>
                                <div class="form-group">
                                    <label>Search imaging services</label>
                                    <input type="text" class="form-control" id="mco_imaging_search" placeholder="Type to search imaging services..." autocomplete="off">
                                    <ul class="list-group" id="mco_imaging_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Name</th><th>Price</th><th>Notes</th><th>*</th></tr></thead>
                                        <tbody id="mco-selected-imaging"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ PROCEDURES ══ -->
            <div class="tab-pane fade" id="mco-procedures" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                    <i class="fa fa-clipboard-list"></i> Treatment Plans
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-proc-history" type="button"><i class="fa fa-history"></i> Procedure History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-proc-new" type="button"><i class="fa fa-plus-circle"></i> Request Procedure</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-proc-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_proc_history_list">
                                        <thead class="table-light">
                                            <tr>
                                                <th><i class="mdi mdi-medical-bag"></i> Procedure</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-proc-new" role="tabpanel">
                                <div id="mco_proc_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> Request New Procedure</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label><i class="fa fa-search"></i> Search Procedure</label>
                                            <input type="text" class="form-control" id="mco_proc_search" placeholder="Type procedure name or code..." autocomplete="off">
                                            <ul class="list-group" id="mco_proc_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-3">
                                            <label><i class="fa fa-exclamation-triangle"></i> Priority <span class="mat-tooltip-icon" title="Routine: scheduled normally. Urgent: needs attention soon. Emergency: immediate intervention required"><i class="mdi mdi-help-circle"></i></span></label>
                                            <select class="form-control" id="mco_proc_priority">
                                                <option value="routine">Routine</option>
                                                <option value="urgent">Urgent</option>
                                                <option value="emergency">Emergency</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-3">
                                            <label><i class="fa fa-calendar"></i> Scheduled Date</label>
                                            <input type="date" class="form-control" id="mco_proc_scheduled_date">
                                            <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Leave blank for today; set future date for elective procedures</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mb-3">
                                    <label><i class="fa fa-sticky-note"></i> Pre-op / Clinical Notes</label>
                                    <textarea class="form-control" id="mco_proc_notes" rows="2" placeholder="Clinical indications, relevant history, patient consent status..."></textarea>
                                    <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Document clinical indications, relevant history, and any special instructions for the procedure team</div>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Procedure</th><th>Price</th><th>Priority</th><th>*</th></tr></thead>
                                        <tbody id="mco-selected-procedures"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

    $('#clinical-orders-content').html(html);

    // Inject the dose-mode toggle from the hidden source into the dynamic container
    $('#mco_dose_mode_container').html($('#mco-dose-mode-toggle-source').html());

    MaternityClinicalOrders.init(currentPatient, currentEnrollmentId);
}

const MaternityClinicalOrders = (function() {
    let patientId = null;
    let enrollmentId = null;
    let mcoDoseStructuredMode = true;
    const investigationCategoryId = '{{ appsettings("investigation_category_id", "") }}';
    const procedureCategoryId = {{ appsettings('procedure_category_id', 0) }};

    function init(pid, eid) {
        patientId = pid;
        enrollmentId = eid;

        // Clear selection tables
        $('#mco-selected-products').empty();
        $('#mco-selected-labs').empty();
        $('#mco-selected-imaging').empty();
        $('#mco-selected-procedures').empty();

        // Init history DataTables
        initPrescHistory();
        initLabHistory();
        initImagingHistory();
        initProcHistory();

        // Clear ClinicalOrdersKit duplicate tracking
        if (typeof ClinicalOrdersKit !== 'undefined') {
            ClinicalOrdersKit.clearAddedIds();
        }

        // One-time initialization
        if (!MaternityClinicalOrders._advancedInit && typeof ClinicalOrdersKit !== 'undefined') {

            // Dose mode toggle
            var mcoDoseState = ClinicalOrdersKit.initDoseModeToggle({
                prefix: 'mco_',
                cssPrefix: 'mco-',
                tableSelector: '#mco-selected-products',
                idInputName: 'mco_presc_id[]',
                doseInputName: 'mco_presc_dose[]',
                onchange: 'ClinicalOrdersKit.updateDoseValue(this, "mco-")',
                onToggle: function(isStructured) { mcoDoseStructuredMode = isStructured; }
            });
            mcoDoseStructuredMode = mcoDoseState.isStructured;

            // Register debounced dose auto-save for medications
            ClinicalOrdersKit.onDoseUpdate('mco-', function(recordId, doseValue) {
                ClinicalOrdersKit.debouncedUpdate({
                    url: '/maternity-workbench/enrollment/' + enrollmentId + '/prescriptions/' + recordId + '/dose',
                    payload: { dose: doseValue },
                    csrfToken: CSRF_TOKEN
                });
            });

            // Treatment Plans
            ClinicalOrdersKit.initTreatmentPlans({
                applyUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/apply-treatment-plan',
                csrfToken: CSRF_TOKEN,
                extraPayload: { enrollment_id: enrollmentId },
                onApplySuccess: function() {
                    initPrescHistory();
                    initLabHistory();
                    initImagingHistory();
                    initProcHistory();
                },
                currentItemsGatherer: function() {
                    var items = [];
                    $('#mco-selected-labs tr[data-record-id]').each(function() {
                        items.push({
                            item_type: 'lab',
                            reference_id: parseInt($(this).data('service-id')),
                            display_name: $(this).find('td:first').text().trim(),
                            note: $(this).find('input[name="mco_lab_note[]"]').val() || ''
                        });
                    });
                    $('#mco-selected-imaging tr[data-record-id]').each(function() {
                        items.push({
                            item_type: 'imaging',
                            reference_id: parseInt($(this).data('service-id')),
                            display_name: $(this).find('td:first').text().trim(),
                            note: $(this).find('input[name="mco_imaging_note[]"]').val() || ''
                        });
                    });
                    $('#mco-selected-products tr[data-record-id]').each(function() {
                        items.push({
                            item_type: 'medication',
                            reference_id: parseInt($(this).data('service-id')),
                            display_name: $(this).find('td:first').text().trim(),
                            dose: $(this).find('input[name="mco_presc_dose[]"]').val() || ''
                        });
                    });
                    $('#mco-selected-procedures tr[data-record-id]').each(function() {
                        items.push({
                            item_type: 'procedure',
                            reference_id: parseInt($(this).data('service-id')),
                            display_name: $(this).find('td:first').text().trim(),
                            note: ''
                        });
                    });
                    return items;
                }
            });

            // Re-prescribe from Encounter
            ClinicalOrdersKit.initRePrescribeFromEncounter({
                recentUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/recent-encounters',
                encounterItemsUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/encounter-items/{id}',
                rePrescribeUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/re-prescribe',
                csrfToken: CSRF_TOKEN,
                extraPayload: { enrollment_id: enrollmentId },
                dropdownSelector: '#mco-rp-encounter-dropdown',
                onRePrescribed: function() {
                    initPrescHistory();
                    initLabHistory();
                    initImagingHistory();
                    initProcHistory();
                }
            });

            MaternityClinicalOrders._advancedInit = true;
        }

        // Update configs on every enrollment switch
        if (typeof ClinicalOrdersKit !== 'undefined') {
            ClinicalOrdersKit.updateTreatmentPlanConfig({
                applyUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/apply-treatment-plan',
                extraPayload: { enrollment_id: enrollmentId }
            });
            ClinicalOrdersKit.updateRePrescribeConfig({
                recentUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/recent-encounters',
                encounterItemsUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/encounter-items/{id}',
                rePrescribeUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/re-prescribe',
                extraPayload: { enrollment_id: enrollmentId }
            });
        }

        // Setup search + re-order handlers (only once)
        if (!MaternityClinicalOrders._searchBound) {
            bindSearchHandlers();
            MaternityClinicalOrders._searchBound = true;
        }
    }

    function bindSearchHandlers() {
        let searchTimeout;

        // Drug search
        $('#mco_presc_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#mco_presc_results').hide(); return; }
            searchTimeout = setTimeout(() => searchProducts(q), 300);
        });

        // Lab search
        $('#mco_lab_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#mco_lab_results').hide(); return; }
            searchTimeout = setTimeout(() => searchLabServices(q), 300);
        });

        // Imaging search
        $('#mco_imaging_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#mco_imaging_results').hide(); return; }
            searchTimeout = setTimeout(() => searchImagingServices(q), 300);
        });

        // Procedure search
        $('#mco_proc_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#mco_proc_results').hide(); return; }
            searchTimeout = setTimeout(() => searchProcedureServices(q), 300);
        });

        // Close dropdowns on click outside
        $(document).off('click.mco').on('click.mco', function(e) {
            if (!$(e.target).closest('#mco_presc_search, #mco_presc_results').length) $('#mco_presc_results').hide();
            if (!$(e.target).closest('#mco_lab_search, #mco_lab_results').length) $('#mco_lab_results').hide();
            if (!$(e.target).closest('#mco_imaging_search, #mco_imaging_results').length) $('#mco_imaging_results').hide();
            if (!$(e.target).closest('#mco_proc_search, #mco_proc_results').length) $('#mco_proc_results').hide();
        });

        // Re-order from history (nursing parity — Plan §5.2)
        $(document).off('click.mcoreorder').on('click.mcoreorder', '.re-order-btn', function() {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            var type         = $btn.data('type');
            var name         = $btn.data('name');
            var price        = $btn.data('price') || 0;
            var coverageMode = $btn.data('coverage-mode') || null;
            var claims       = $btn.data('claims') || null;
            var payable      = $btn.data('payable') || null;
            if (coverageMode === '') coverageMode = null;

            if (type === 'labs') {
                var serviceId = parseInt($btn.data('service-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('labs', serviceId)) {
                    toastr.warning(name + ' is already in your current lab requests');
                    return;
                }
                addLabService(name, serviceId, price, coverageMode, claims, payable);
            } else if (type === 'imaging') {
                var serviceId = parseInt($btn.data('service-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('imaging', serviceId)) {
                    toastr.warning(name + ' is already in your current imaging requests');
                    return;
                }
                addImagingService(name, serviceId, price, coverageMode, claims, payable);
            } else if (type === 'prescriptions') {
                var productId = parseInt($btn.data('product-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('meds', productId)) {
                    toastr.warning(name + ' is already in your current prescriptions');
                    return;
                }
                addProductService(name, productId, price, coverageMode, claims, payable);
            }

            $btn.prop('disabled', true).html('<i class="fa fa-check text-success"></i> Added');
        });
    }

    // ===== HISTORY DATATABLES =====
    function initPrescHistory() {
        if ($.fn.DataTable.isDataTable('#mco_presc_history_list')) {
            $('#mco_presc_history_list').DataTable().destroy();
        }
        $('#mco_presc_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '/prescHistoryList/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No prescription history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }

    function initLabHistory() {
        if ($.fn.DataTable.isDataTable('#mco_lab_history_list')) {
            $('#mco_lab_history_list').DataTable().destroy();
        }
        $('#mco_lab_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '/investigationHistoryList/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No lab history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }

    function initImagingHistory() {
        if ($.fn.DataTable.isDataTable('#mco_imaging_history_list')) {
            $('#mco_imaging_history_list').DataTable().destroy();
        }
        $('#mco_imaging_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '/imagingHistoryList/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No imaging history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }

    function initProcHistory() {
        if ($.fn.DataTable.isDataTable('#mco_proc_history_list')) {
            $('#mco_proc_history_list').DataTable().destroy();
        }
        $('#mco_proc_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '/procedureHistoryList/' + patientId, type: 'GET' },
            columns: [
                { data: 'procedure', name: 'procedure' },
                { data: 'priority', name: 'priority' },
                { data: 'status', name: 'procedure_status' },
                { data: 'date', name: 'requested_on' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            order: [[3, 'desc']], pageLength: 10,
            language: { emptyTable: 'No procedure history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }

    // ===== SEARCH FUNCTIONS =====
    function searchLabServices(q) {
        const data = { term: q, patient_id: patientId };
        if (investigationCategoryId) data.category_id = investigationCategoryId;

        $.get('/live-search-services', data, function(results) {
            const $res = $('#mco_lab_results').empty();
            if (!results.length) {
                $res.append('<li class="list-group-item text-muted">No lab services found</li>');
            } else {
                results.forEach(item => {
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const display = name + '[' + code + ']';
                    const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('labs', parseInt(item.id));
                    const mode = item.coverage_mode || null;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const coverageBadge = ClinicalOrdersKit.renderCoverageBadge(mode, payable, claims);

                    if (alreadyAdded) {
                        $res.append('<li class="list-group-item text-muted" style="background:#e9ecef; cursor:not-allowed;">[' + (item.category?.category_name || 'Lab') + '] <b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + ' <span class="badge bg-warning ms-2">Already Added</span></li>');
                    } else {
                        $res.append('<li class="list-group-item" style="background:#f0f0f0; cursor:pointer;" onclick="MaternityClinicalOrders.addLabService(\'' + display.replace(/'/g, "\\'") + '\', ' + item.id + ', ' + price + ', \'' + (mode||'') + '\', ' + claims + ', ' + payable + ')">[' + (item.category?.category_name || 'Lab') + '] <b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + '</li>');
                    }
                });
            }
            $res.show();
        });
    }

    function searchProducts(q) {
        $.get('/live-search-products', { term: q, patient_id: patientId }, function(results) {
            const $res = $('#mco_presc_results').empty();
            if (!results.length) {
                $res.append('<li class="list-group-item text-muted">No products found</li>');
            } else {
                results.forEach(item => {
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock?.current_quantity ?? 0;
                    const price = item.price?.initial_sale_price ?? 0;
                    const display = name + '[' + code + '](' + qty + ' avail.)';
                    const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('meds', parseInt(item.id));
                    const mode = item.coverage_mode || null;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const coverageBadge = ClinicalOrdersKit.renderCoverageBadge(mode, payable, claims);

                    if (alreadyAdded) {
                        $res.append('<li class="list-group-item text-muted" style="background:#e9ecef; cursor:not-allowed;"><b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + ' <span class="badge bg-warning ms-2">Already Added</span></li>');
                    } else {
                        $res.append('<li class="list-group-item" style="background:#f0f0f0; cursor:pointer;" onclick="MaternityClinicalOrders.addProductService(\'' + display.replace(/'/g, "\\'") + '\', ' + item.id + ', ' + price + ', \'' + (mode||'') + '\', ' + claims + ', ' + payable + ')"><b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + '</li>');
                    }
                });
            }
            $res.show();
        });
    }

    function searchImagingServices(q) {
        $.get('/live-search-services', { term: q, category_id: 6, patient_id: patientId }, function(results) {
            const $res = $('#mco_imaging_results').empty();
            if (!results.length) {
                $res.append('<li class="list-group-item text-muted">No imaging services found</li>');
            } else {
                results.forEach(item => {
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const display = name + '[' + code + ']';
                    const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('imaging', parseInt(item.id));
                    const mode = item.coverage_mode || null;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const coverageBadge = ClinicalOrdersKit.renderCoverageBadge(mode, payable, claims);

                    if (alreadyAdded) {
                        $res.append('<li class="list-group-item text-muted" style="background:#e9ecef; cursor:not-allowed;">[' + (item.category?.category_name || 'Imaging') + '] <b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + ' <span class="badge bg-warning ms-2">Already Added</span></li>');
                    } else {
                        $res.append('<li class="list-group-item" style="background:#f0f0f0; cursor:pointer;" onclick="MaternityClinicalOrders.addImagingService(\'' + display.replace(/'/g, "\\'") + '\', ' + item.id + ', ' + price + ', \'' + (mode||'') + '\', ' + claims + ', ' + payable + ')">[' + (item.category?.category_name || 'Imaging') + '] <b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + '</li>');
                    }
                });
            }
            $res.show();
        });
    }

    function searchProcedureServices(q) {
        $.get('/live-search-services', { term: q, category_id: procedureCategoryId, patient_id: patientId }, function(results) {
            const $res = $('#mco_proc_results').empty();
            if (!results.length) {
                $res.append('<li class="list-group-item text-muted">No procedures found</li>');
            } else {
                results.forEach(item => {
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const display = name + '[' + code + ']';
                    const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('procedures', parseInt(item.id));
                    const payable = item.payable_amount ?? price;
                    const coverageBadge = ClinicalOrdersKit.renderCoverageBadge(item.coverage_mode || null, payable, item.claims_amount ?? 0);

                    if (alreadyAdded) {
                        $res.append('<li class="list-group-item text-muted" style="background:#e9ecef; cursor:not-allowed;">[' + (item.category?.category_name || 'Procedure') + '] <b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + ' <span class="badge bg-warning ms-2">Already Added</span></li>');
                    } else {
                        $res.append('<li class="list-group-item" style="background:#f0f0f0; cursor:pointer;" onclick="MaternityClinicalOrders.addProcedureService(\'' + display.replace(/'/g, "\\'") + '\', ' + item.id + ', ' + payable + ')">[' + (item.category?.category_name || 'Procedure') + '] <b>' + display + '</b> NGN ' + payable + ' ' + coverageBadge + '</li>');
                    }
                });
            }
            $res.show();
        });
    }

    // ===== AUTO-SAVE ADD FUNCTIONS (via ClinicalOrdersKit.addItem) =====

    function addProductService(name, id, price, mode, claims, payable) {
        var rowId = 'mco_rx_' + Date.now() + '_' + id;
        var coverageBadge = ClinicalOrdersKit.renderCoverageBadge(
            mode && mode !== 'null' ? mode : null, payable ?? price, claims ?? 0
        );

        ClinicalOrdersKit.addItem({
            url: '/maternity-workbench/enrollment/' + enrollmentId + '/add-prescription',
            payload: { product_id: id, dose: '' },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#mco-selected-products',
            type: 'meds',
            referenceId: parseInt(id),
            buildRowHtml: function(resp) {
                var recordId = resp.id;
                var doseOnchange = "ClinicalOrdersKit.updateDoseValue(this, 'mco-'); " +
                    "ClinicalOrdersKit.debouncedUpdate({url:'/maternity-workbench/enrollment/" + enrollmentId + "/prescriptions/" + recordId + "/dose'," +
                    "payload:{dose: $(this).closest('.mco-structured-dose').find('.mco-structured-dose-value').val()}," +
                    "csrfToken:'" + CSRF_TOKEN + "'});";

                var doseCell;
                if (mcoDoseStructuredMode) {
                    doseCell = '<td>' + ClinicalOrdersKit.buildStructuredDoseHtml({
                        cssPrefix: 'mco-',
                        hiddenName: 'mco_presc_dose[]',
                        onchange: doseOnchange,
                        drugName: name,
                        rowId: rowId
                    }) + '<input type="hidden" name="mco_presc_id[]" value="' + id + '"></td>';
                } else {
                    doseCell = '<td><input type="text" class="form-control form-control-sm" name="mco_presc_dose[]" ' +
                        'placeholder="e.g. 500mg BD x 5days" ' +
                        'onchange="ClinicalOrdersKit.debouncedUpdate({url:\'/maternity-workbench/enrollment/' + enrollmentId + '/prescriptions/' + recordId + '/dose\',' +
                        'payload:{dose:this.value},csrfToken:\'' + CSRF_TOKEN + '\'})" required>' +
                        '<input type="hidden" name="mco_presc_id[]" value="' + id + '"></td>';
                }

                return '<tr data-record-id="' + recordId + '" data-record-type="prescription" data-service-id="' + id + '" data-drug-name="' + name.replace(/"/g, '&quot;') + '" data-row-id="' + rowId + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    doseCell +
                    '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'prescription\',' + recordId + ',' + id + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initPrescHistory();
            }
        });
        $('#mco_presc_search').val('');
        $('#mco_presc_results').hide();
    }

    function addLabService(name, id, price, mode, claims, payable) {
        ClinicalOrdersKit.addItem({
            url: '/maternity-workbench/enrollment/' + enrollmentId + '/add-lab',
            payload: { service_id: id, note: '' },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#mco-selected-labs',
            type: 'labs',
            referenceId: parseInt(id),
            buildRowHtml: function(response) {
                var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                return '<tr data-record-id="' + response.id + '" data-record-type="lab" data-service-id="' + id + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    '<td><input type="text" class="form-control form-control-sm" name="mco_lab_note[]" placeholder="Clinical notes..." onchange="ClinicalOrdersKit.debouncedUpdate({url:\'/maternity-workbench/enrollment/' + enrollmentId + '/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + CSRF_TOKEN + '\'})"><input type="hidden" name="mco_lab_id[]" value="' + id + '"></td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'lab\',' + response.id + ',' + id + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initLabHistory();
            }
        });
        $('#mco_lab_search').val('');
        $('#mco_lab_results').hide();
    }

    function addImagingService(name, id, price, mode, claims, payable) {
        ClinicalOrdersKit.addItem({
            url: '/maternity-workbench/enrollment/' + enrollmentId + '/add-imaging',
            payload: { service_id: id, note: '' },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#mco-selected-imaging',
            type: 'imaging',
            referenceId: parseInt(id),
            buildRowHtml: function(response) {
                var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                return '<tr data-record-id="' + response.id + '" data-record-type="imaging" data-service-id="' + id + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    '<td><input type="text" class="form-control form-control-sm" name="mco_imaging_note[]" placeholder="Clinical notes..." onchange="ClinicalOrdersKit.debouncedUpdate({url:\'/maternity-workbench/enrollment/' + enrollmentId + '/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + CSRF_TOKEN + '\'})"><input type="hidden" name="mco_imaging_id[]" value="' + id + '"></td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'imaging\',' + response.id + ',' + id + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initImagingHistory();
            }
        });
        $('#mco_imaging_search').val('');
        $('#mco_imaging_results').hide();
    }

    function addProcedureService(name, id, price) {
        if (ClinicalOrdersKit.isAlreadyAdded('procedures', parseInt(id))) {
            toastr.warning('Procedure already added');
            return;
        }
        var priority = $('#mco_proc_priority').val() || 'routine';
        var scheduledDate = $('#mco_proc_scheduled_date').val() || '';
        var preNotes = $('#mco_proc_notes').val() || '';
        var priorityClass = { routine: 'bg-success', urgent: 'bg-warning text-dark', emergency: 'bg-danger' }[priority] || 'bg-secondary';
        var priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);

        ClinicalOrdersKit.addItem({
            url: '/maternity-workbench/enrollment/' + enrollmentId + '/add-procedure',
            payload: {
                service_id: id,
                priority: priority,
                scheduled_date: scheduledDate,
                pre_notes: preNotes
            },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#mco-selected-procedures',
            type: 'procedures',
            referenceId: parseInt(id),
            buildRowHtml: function(resp) {
                return '<tr data-record-id="' + resp.id + '" data-record-type="procedure" data-service-id="' + id + '">' +
                    '<td><strong>' + name + '</strong>' +
                    (preNotes ? '<br><small class="text-info"><i class="fa fa-sticky-note"></i> ' + preNotes.substring(0, 60) + '</small>' : '') + '</td>' +
                    '<td>NGN ' + price + '</td>' +
                    '<td><span class="badge ' + priorityClass + '">' + priorityLabel + '</span>' +
                    (scheduledDate ? '<br><small>' + scheduledDate + '</small>' : '') + '</td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'procedure\',' + resp.id + ',' + id + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function() {
                initProcHistory();
            }
        });
        $('#mco_proc_search').val('');
        $('#mco_proc_results').hide();
    }

    // ===== AUTO-SAVE REMOVE (via ClinicalOrdersKit.removeItem) =====
    function removeAutoSavedRow(btn, type, recordId, serviceId) {
        var deleteUrl, tableSelector;

        if (type === 'lab') {
            deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/labs/' + recordId;
            tableSelector = '#mco-selected-labs';
        } else if (type === 'imaging') {
            deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/imaging/' + recordId;
            tableSelector = '#mco-selected-imaging';
        } else if (type === 'prescription') {
            deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/prescriptions/' + recordId;
            tableSelector = '#mco-selected-products';
        } else if (type === 'procedure') {
            deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/procedures/' + recordId;
            tableSelector = '#mco-selected-procedures';
        }

        var idsType = { lab: 'labs', imaging: 'imaging', prescription: 'meds', procedure: 'procedures' }[type] || type;

        ClinicalOrdersKit.removeItem({
            url: deleteUrl,
            csrfToken: CSRF_TOKEN,
            rowSelector: $(btn).closest('tr'),
            type: idsType,
            referenceId: serviceId ? parseInt(serviceId) : null,
            tableSelector: tableSelector
        });
    }

    function showMessage(containerId, msg, type) {
        var alertType = type === 'error' ? 'danger' : type;
        $('#' + containerId).html('<div class="alert alert-' + alertType + ' alert-dismissible fade show">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        document.getElementById(containerId).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(function() { $('#' + containerId + ' .alert').alert('close'); }, 5000);
    }

    return {
        init: init,
        addProductService: addProductService,
        addLabService: addLabService,
        addImagingService: addImagingService,
        addProcedureService: addProcedureService,
        removeAutoSavedRow: removeAutoSavedRow,
        _searchBound: false
    };
})();

// ═══════════════════════════════════════════════════════════════
// DELIVERY TAB
// ═══════════════════════════════════════════════════════════════
function loadDeliveryTab() {
    if (!currentEnrollmentId) { $('#delivery-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>'); return; }

    // Check if delivery record exists
    if (currentEnrollment && currentEnrollment.has_delivery) {
        // Load existing delivery
        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
            if (!resp.success || !resp.enrollment.delivery_record) { renderDeliveryForm(); return; }
            renderDeliveryDetails(resp.enrollment.delivery_record);
        });
    } else {
        renderDeliveryForm();
    }
}

function renderDeliveryForm() {
    destroyMaternityEditor('delivery_notes');
    destroyMaternityEditor('delivery_complications');
    const html = `<div class="card-modern"><div class="card-header text-white" style="background: var(--success);"><h6 class="mb-0"><i class="mdi mdi-baby-carriage"></i> Record Delivery</h6></div><div class="card-body">
        <div class="mat-info-banner"><i class="mdi mdi-information"></i><div>Record the delivery outcome. All fields contribute to the patient\'s permanent delivery record. Fields marked <span class="text-danger">*</span> are required. After saving, register each baby separately in the Baby Records tab.</div></div>
        <form id="delivery-form">
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-clock"></i> Timing & Method</div>
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="form-label">Delivery Date <span class="text-danger">*</span></label><input type="date" name="delivery_date" class="form-control" value="${new Date().toISOString().split('T')[0]}" required></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Delivery Time</label><input type="time" name="delivery_time" class="form-control"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Type of Delivery <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="SVD: Spontaneous Vaginal Delivery. CS: Caesarean Section. Vacuum/Forceps: Assisted vaginal delivery"><i class="mdi mdi-help-circle"></i></span></label><select name="type_of_delivery" class="form-select" required><option value="svd">SVD (Spontaneous Vaginal)</option><option value="cs">CS (Caesarean Section)</option><option value="vacuum">Vacuum Extraction</option><option value="forceps">Forceps Delivery</option><option value="breech">Breech Delivery</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Number of Babies <span class="text-danger">*</span></label><input type="number" name="number_of_babies" class="form-control" value="1" min="1" max="8" placeholder="1" required></div>
                </div>
            </div>
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-medical-bag"></i> Labour Details</div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label">Duration of Labour (hrs) <span class="mat-tooltip-icon" title="Total active labour duration in hours. Prolonged labour: >12hrs primigravida, >8hrs multigravida"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="duration_of_labour_hours" class="form-control" step="0.5" placeholder="e.g. 8.5"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Estimated Blood Loss (ml) <span class="mat-tooltip-icon" title="Normal: SVD \u2264500ml, CS \u22641000ml. >500ml SVD or >1000ml CS = postpartum haemorrhage"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="blood_loss_ml" class="form-control" placeholder="e.g. 300"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Oxytocin Given <span class="mat-tooltip-icon" title="Active management of third stage: Oxytocin 10 IU IM within 1 minute of delivery"><i class="mdi mdi-help-circle"></i></span></label><select name="oxytocin_given" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
                </div>
            </div>
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-clipboard-check"></i> Outcomes & Assessment</div>
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="form-label">Placenta <span class="mat-tooltip-icon" title="Complete: all cotyledons and membranes accounted for. Incomplete: retained products — requires manual removal"><i class="mdi mdi-help-circle"></i></span></label><select name="placenta_complete" class="form-select"><option value="1">Complete</option><option value="0">Incomplete</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Perineal Tear <span class="mat-tooltip-icon" title="1st: mucosa only. 2nd: perineal muscles. 3rd: anal sphincter involved. 4th: rectal mucosa torn"><i class="mdi mdi-help-circle"></i></span></label><select name="perineal_tear_degree" class="form-select"><option value="">None</option><option value="1st">1st degree</option><option value="2nd">2nd degree</option><option value="3rd">3rd degree</option><option value="4th">4th degree</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Episiotomy <span class="mat-tooltip-icon" title="Surgical incision of perineum to widen vaginal opening during delivery"><i class="mdi mdi-help-circle"></i></span></label><select name="episiotomy" class="form-select"><option value="">None</option><option>Yes, mediolateral</option><option>Yes, midline</option></select></div>
                </div>
            </div>
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-alert-circle"></i> Complications</div>
                <div id="mat-delivery-complications-editor"></div>
                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Document any complications: PPH, shoulder dystocia, cord prolapse, fetal distress, etc.</div>
            </div>
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-note-text"></i> Delivery Notes</div>
                <div id="mat-delivery-notes-editor"></div>
                <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Additional notes: birth narrative, personnel present, interventions performed</div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success btn-lg"><i class="mdi mdi-check"></i> Save Delivery Record</button>
            </div>
        </form></div></div>`;
    $('#delivery-content').html(html);
    initMaternityEditor('#mat-delivery-notes-editor', 'delivery_notes');
    initMaternityEditor('#mat-delivery-complications-editor', 'delivery_complications');

    $('#delivery-form').on('submit', function(e) {
        e.preventDefault();
        const data = {};
        $(this).serializeArray().forEach(f => data[f.name] = f.value);
        data.notes = getEditorData('delivery_notes', '#mat-delivery-notes-editor');
        data.complications = getEditorData('delivery_complications', '#mat-delivery-complications-editor');
        $.ajax({
            url: `/maternity-workbench/enrollment/${currentEnrollmentId}/delivery`,
            method: 'POST', data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            success: function(r) {
                if (r.success) {
                    destroyMaternityEditor('delivery_notes');
                    destroyMaternityEditor('delivery_complications');
                    toastr.success(r.message);
                    currentEnrollment.has_delivery = true;
                    currentEnrollment.status = 'delivered';
                    loadDeliveryTab();
                } else toastr.error(r.message);
            },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Failed to save'); }
        });
    });
}

function renderDeliveryDetails(d) {
    const html = `<div class="card-modern"><div class="card-header text-white d-flex justify-content-between" style="background: var(--success);"><h6 class="mb-0"><i class="mdi mdi-baby-carriage"></i> Delivery Record</h6><div><button class="btn btn-sm btn-outline-light me-1" onclick="editDeliveryRecord(${d.id})" title="Edit"><i class="mdi mdi-pencil"></i> Edit</button><span class="badge bg-light text-dark">${(d.type_of_delivery || '').toUpperCase()}</span></div></div><div class="card-body">
        <div class="row"><div class="col-md-6"><table class="table table-sm">
            <tr><td class="text-muted">Date</td><td>${d.delivery_date || 'N/A'}</td></tr>
            <tr><td class="text-muted">Time</td><td>${d.delivery_time || 'N/A'}</td></tr>
            <tr><td class="text-muted">Type</td><td class="fw-bold">${(d.type_of_delivery || '').toUpperCase()}</td></tr>
            <tr><td class="text-muted">Babies</td><td>${d.number_of_babies || 0}</td></tr>
            <tr><td class="text-muted">Duration</td><td>${d.duration_of_labour_hours ? d.duration_of_labour_hours + ' hrs' : 'N/A'}</td></tr>
        </table></div><div class="col-md-6"><table class="table table-sm">
            <tr><td class="text-muted">Blood Loss</td><td>${d.blood_loss_ml ? d.blood_loss_ml + ' ml' : 'N/A'}</td></tr>
            <tr><td class="text-muted">Placenta</td><td>${d.placenta_complete ? 'Complete' : 'Incomplete'}</td></tr>
            <tr><td class="text-muted">Perineal Tear</td><td>${d.perineal_tear_degree || 'None'}</td></tr>
            <tr><td class="text-muted">Episiotomy</td><td>${d.episiotomy || 'None'}</td></tr>
            <tr><td class="text-muted">Complications</td><td>${d.complications || 'None'}</td></tr>
            <tr><td class="text-muted">Delivered By</td><td>${d.delivered_by_name || 'N/A'}</td></tr>
        </table></div></div>
        ${d.notes ? '<div class="alert alert-info mt-2 mb-0"><strong>Notes:</strong> ' + d.notes + '</div>' : ''}
    </div></div>
    <div class="card-modern mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="mdi mdi-chart-timeline-variant"></i> Partograph</h6>
            <button type="button" class="btn btn-sm btn-success" onclick="showPartographForm(${d.id})"><i class="mdi mdi-plus"></i> Add Entry</button>
        </div>
        <div class="card-body" id="partograph-content">
            <div class="text-center text-muted py-2">Loading partograph entries...</div>
        </div>
    </div>`;
    window._deliveryRecordCache = d; // cache for edit
    $('#delivery-content').html(html);
    loadPartographEntries(d.id);
}

function showPartographForm(deliveryId) {
    const form = $('#partograph-form')[0];
    if (form) form.reset();
    $('#partograph-delivery-id').val(deliveryId);
    $('#partograph-entry-id').val('');
    window._partographEditMode = false;
    $('#addPartographModalLabel').html('<i class="mdi mdi-chart-timeline-variant"></i> Add Partograph Entry');
    $('#btn-save-partograph').html('<i class="mdi mdi-check"></i> Save Entry');
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $('#partograph-form input[name="recorded_at"]').val(now.toISOString().slice(0, 16));
    $('#addPartographModal').modal('show');
}

function editPartographEntry(entryId) {
    const entry = (window._partographEntriesCache || []).find(e => e.id === entryId);
    if (!entry) { toastr.error('Entry data not found'); return; }
    const form = $('#partograph-form')[0];
    if (form) form.reset();
    window._partographEditMode = true;
    $('#partograph-entry-id').val(entryId);
    $('#partograph-delivery-id').val(entry.delivery_record_id);
    $('#addPartographModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Partograph Entry');
    $('#btn-save-partograph').html('<i class="mdi mdi-check"></i> Update Entry');
    // Pre-fill fields
    if (entry.recorded_at) {
        const dt = new Date(entry.recorded_at);
        dt.setMinutes(dt.getMinutes() - dt.getTimezoneOffset());
        $('#partograph-form input[name="recorded_at"]').val(dt.toISOString().slice(0, 16));
    }
    $('#partograph-form input[name="cervical_dilation_cm"]').val(entry.cervical_dilation_cm ?? '');
    $('#partograph-form input[name="descent"]').val(entry.descent ?? '');
    $('#partograph-form input[name="contractions_per_10min"]').val(entry.contractions_per_10min ?? '');
    $('#partograph-form input[name="contraction_duration_sec"]').val(entry.contraction_duration_sec ?? '');
    $('#partograph-form input[name="fetal_heart_rate"]').val(entry.fetal_heart_rate ?? '');
    $('#partograph-form select[name="amniotic_fluid"]').val(entry.amniotic_fluid || '');
    $('#partograph-form select[name="moulding"]').val(entry.moulding || '');
    $('#partograph-form input[name="maternal_pulse"]').val(entry.maternal_pulse ?? '');
    $('#partograph-form input[name="maternal_bp_systolic"]').val(entry.maternal_bp_systolic ?? '');
    $('#partograph-form input[name="maternal_bp_diastolic"]').val(entry.maternal_bp_diastolic ?? '');
    $('#partograph-form input[name="maternal_temp_c"]').val(entry.maternal_temp_c ?? '');
    $('#partograph-form input[name="urine_output_ml"]').val(entry.urine_output_ml ?? '');
    $('#partograph-form select[name="urine_protein"]').val(entry.urine_protein || '');
    $('#partograph-form input[name="oxytocin_dose"]').val(entry.oxytocin_dose ?? '');
    $('#partograph-form input[name="iv_fluids"]').val(entry.iv_fluids ?? '');
    $('#partograph-form textarea[name="medications"]').val(entry.medications ?? '');
    $('#addPartographModal').modal('show');
}

function deletePartographEntry(entryId, deliveryId) {
    if (!confirm('Delete this partograph entry? This action cannot be undone.')) return;
    $.ajax({
        url: `/maternity-workbench/partograph/${entryId}`,
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            if (r.success) {
                toastr.success(r.message || 'Entry deleted');
                loadPartographEntries(deliveryId);
            } else {
                toastr.error(r.message || 'Failed to delete entry');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to delete entry');
        }
    });
}

$(document).on('click', '#btn-save-partograph', function() {
    const form = $('#partograph-form');
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }

    const deliveryId = $('#partograph-delivery-id').val();
    const entryId = $('#partograph-entry-id').val();
    const isEdit = window._partographEditMode && entryId;

    if (!deliveryId && !isEdit) {
        toastr.error('Delivery record not found for partograph entry');
        return;
    }

    const data = {};
    form.serializeArray().forEach(function(f) { data[f.name] = f.value; });

    const btn = $(this);
    const originalHtml = btn.html();
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

    const url = isEdit
        ? `/maternity-workbench/partograph/${entryId}`
        : `/maternity-workbench/delivery/${deliveryId}/partograph`;
    const method = isEdit ? 'PUT' : 'POST';

    $.ajax({
        url: url,
        method: method,
        data: data,
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            btn.prop('disabled', false).html(originalHtml);
            if (r.success) {
                $('#addPartographModal').modal('hide');
                toastr.success(r.message || 'Partograph entry saved');
                loadPartographEntries(deliveryId || r.entry?.delivery_record_id);
            } else {
                toastr.error(r.message || 'Failed to save partograph entry');
            }
        },
        error: function(xhr) {
            btn.prop('disabled', false).html(originalHtml);
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                const errs = xhr.responseJSON.errors;
                const msgs = Object.values(errs).flat().join('<br>');
                toastr.error(msgs, 'Validation Error');
            } else {
                toastr.error(xhr.responseJSON?.message || 'Failed to save partograph entry');
            }
        }
    });
});

function loadPartographEntries(deliveryId) {
    if (!deliveryId) return;

    $('#partograph-content').html('<div class="text-center text-muted py-2"><i class="mdi mdi-loading mdi-spin"></i> Loading partograph entries...</div>');

    $.get(`/maternity-workbench/delivery/${deliveryId}/partograph`, function(resp) {
        if (!resp.success) {
            $('#partograph-content').html('<div class="alert alert-danger mb-0">Failed to load partograph entries.</div>');
            return;
        }

        const entries = resp.entries || [];
        window._partographEntriesCache = entries; // cache for edit

        if (!entries.length) {
            $('#partograph-content').html('<div class="alert alert-info mb-0"><i class="mdi mdi-information"></i> No partograph entries yet. Click <strong>Add Entry</strong> to begin labour monitoring.</div>');
            return;
        }

        let rows = '';
        entries.forEach(function(e) {
            const bp = e.maternal_bp || ((e.maternal_bp_systolic || e.maternal_bp_diastolic) ? `${e.maternal_bp_systolic || ''}/${e.maternal_bp_diastolic || ''}` : '—');
            const fhrVal = e.fetal_heart_rate ?? '—';
            const fhrClass = fhrVal !== '—' && (fhrVal < 110 || fhrVal > 160) ? 'text-danger fw-bold' : '';
            rows += `<tr>
                <td class="small">${e.recorded_at || '—'}</td>
                <td>${e.cervical_dilation_cm ?? '—'}</td>
                <td>${e.descent || '—'}</td>
                <td class="${fhrClass}">${fhrVal}</td>
                <td>${e.contractions_per_10min ?? '—'}${e.contraction_duration_sec ? ' <small class="text-muted">(' + e.contraction_duration_sec + 's)</small>' : ''}</td>
                <td>${e.amniotic_fluid || '—'}</td>
                <td>${e.moulding || '—'}</td>
                <td>${bp}</td>
                <td>${e.maternal_pulse ?? '—'}</td>
                <td>${e.maternal_temp_c ?? '—'}</td>
                <td>${e.urine_protein || '—'}</td>
                <td>${e.oxytocin_dose || '—'}</td>
                <td>${e.iv_fluids || '—'}</td>
                <td class="small">${e.recorded_by_name || '—'}</td>
                <td class="text-nowrap">
                    <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editPartographEntry(${e.id})" title="Edit"><i class="mdi mdi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deletePartographEntry(${e.id}, ${e.delivery_record_id})" title="Delete"><i class="mdi mdi-delete"></i></button>
                </td>
            </tr>`;
        });

        $('#partograph-content').html(`
            <div class="mb-3">
                <div class="small text-muted mb-2"><i class="mdi mdi-information"></i> Chart shows cervical dilation progression with WHO-style alert/action guide lines (anchored at first dilation ≥ 4 cm), plus fetal heart rate trend.</div>
                <div style="position:relative; height:350px;"><canvas id="partograph-chart-canvas"></canvas></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0" style="font-size: 0.82rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Dilation</th>
                            <th>Descent</th>
                            <th>FHR</th>
                            <th>Contractions</th>
                            <th>Liquor</th>
                            <th>Moulding</th>
                            <th>BP</th>
                            <th>Pulse</th>
                            <th>Temp</th>
                            <th>Protein</th>
                            <th>Oxytocin</th>
                            <th>IV Fluids</th>
                            <th>By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `);

        renderPartographChart(entries);
    }).fail(function() {
        $('#partograph-content').html('<div class="alert alert-danger mb-0">Unable to load partograph entries.</div>');
    });
}

function renderPartographChart(entries) {
    if (typeof Chart === 'undefined') return;

    const canvas = document.getElementById('partograph-chart-canvas');
    if (!canvas || !entries || !entries.length) return;

    if (window._partographChartInstance) {
        window._partographChartInstance.destroy();
    }

    const sorted = [...entries].sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at));
    const startTime = new Date(sorted[0].recorded_at);

    const toNum = (v) => {
        if (v === null || v === undefined || v === '') return null;
        const n = Number(v);
        return Number.isNaN(n) ? null : n;
    };

    const toHours = (dateStr) => {
        const d = new Date(dateStr);
        return Math.max(0, (d - startTime) / 3600000);
    };

    const dilation = [];
    const fhr = [];

    sorted.forEach((e) => {
        const x = toHours(e.recorded_at);
        const d = toNum(e.cervical_dilation_cm);
        const hr = toNum(e.fetal_heart_rate);

        if (d !== null) dilation.push({ x, y: d });
        if (hr !== null) fhr.push({ x, y: hr });
    });

    // WHO partograph: alert line starts at the first entry with dilation >= 4 cm
    // and rises at 1 cm/hour. Action line is 4 hours to the right of alert line.
    const alertLine = [];
    const actionLine = [];
    const maxHour = Math.max(...sorted.map(e => toHours(e.recorded_at)), 12);

    // Find the first time dilation >= 4 cm (active labour onset)
    let alertStartHour = null;
    for (const pt of dilation) {
        if (pt.y >= 4) {
            alertStartHour = pt.x;
            break;
        }
    }

    if (alertStartHour !== null) {
        // Alert line: starts at (alertStartHour, 4) and rises 1cm per hour
        for (let h = 0; h <= maxHour + 2; h += 0.5) {
            const alertY = 4 + (h - alertStartHour);
            if (h >= alertStartHour && alertY <= 10) {
                alertLine.push({ x: h, y: alertY });
            }
            // Action line: 4 hours to the right of alert line
            const actionH = h - 4;
            const actionY = 4 + (actionH - alertStartHour);
            if (actionH >= alertStartHour && actionY >= 4 && actionY <= 10) {
                actionLine.push({ x: h, y: actionY });
            }
        }
    }

    const ctx = canvas.getContext('2d');
    window._partographChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Cervical Dilation (cm)',
                    data: dilation,
                    borderColor: '#d63384',
                    backgroundColor: 'rgba(214, 51, 132, 0.15)',
                    tension: 0.2,
                    pointRadius: 5,
                    pointBackgroundColor: '#d63384',
                    borderWidth: 2.5,
                    yAxisID: 'y',
                    spanGaps: true
                },
                {
                    label: 'FHR (bpm)',
                    data: fhr,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.12)',
                    tension: 0.2,
                    pointRadius: 3,
                    borderWidth: 1.5,
                    yAxisID: 'y1',
                    spanGaps: true
                },
                {
                    label: 'Alert Line (1 cm/hr from 4 cm)',
                    data: alertLine,
                    borderColor: '#fd7e14',
                    borderDash: [6, 4],
                    borderWidth: 2,
                    pointRadius: 0,
                    yAxisID: 'y',
                    fill: false
                },
                {
                    label: 'Action Line (+4 hrs)',
                    data: actionLine,
                    borderColor: '#dc3545',
                    borderDash: [6, 4],
                    borderWidth: 2,
                    pointRadius: 0,
                    yAxisID: 'y',
                    fill: false,
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, padding: 15 } },
                tooltip: { mode: 'nearest', intersect: false }
            },
            scales: {
                x: {
                    type: 'linear',
                    title: { display: true, text: 'Hours since first entry' },
                    min: 0,
                    ticks: { callback: function(value) { return value + 'h'; }, stepSize: 1 }
                },
                y: {
                    min: 0,
                    max: 10,
                    title: { display: true, text: 'Cervical Dilation (cm)' },
                    ticks: { stepSize: 1 }
                },
                y1: {
                    position: 'right',
                    min: 60,
                    max: 220,
                    title: { display: true, text: 'FHR (bpm)' },
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 20 }
                }
            }
        }
    });
}

function editDeliveryRecord(id) {
    const d = window._deliveryRecordCache;
    if (!d) { toastr.error('Delivery data not found'); return; }
    // Re-render the delivery form pre-filled
    renderDeliveryForm();
    // Wait a tick for the form to render and editors to init, then pre-fill
    setTimeout(function() {
        const f = $('#delivery-form');
        // Parse dates from Eloquent serialization (ISO string or Y-m-d)
        const delivDate = d.delivery_date ? d.delivery_date.substring(0, 10) : '';
        const delivTime = d.delivery_time ? (d.delivery_time.length > 10 ? d.delivery_time.substring(11, 16) : d.delivery_time) : '';
        f.find('input[name="delivery_date"]').val(delivDate);
        f.find('input[name="delivery_time"]').val(delivTime);
        f.find('select[name="type_of_delivery"]').val(d.type_of_delivery || 'svd');
        f.find('input[name="number_of_babies"]').val(d.number_of_babies || 1);
        f.find('input[name="duration_of_labour_hours"]').val(d.duration_of_labour_hours || '');
        f.find('input[name="blood_loss_ml"]').val(d.blood_loss_ml || '');
        f.find('select[name="oxytocin_given"]').val(d.oxytocin_given ? '1' : '0');
        f.find('select[name="placenta_complete"]').val(d.placenta_complete ? '1' : '0');
        f.find('select[name="perineal_tear_degree"]').val(d.perineal_tear_degree || '');
        f.find('select[name="episiotomy"]').val(d.episiotomy || '');
        // Set CKEditor data after init
        setTimeout(function() {
            if (MaternityEditors['delivery_complications'] && d.complications) MaternityEditors['delivery_complications'].setData(d.complications);
            if (MaternityEditors['delivery_notes'] && d.notes) MaternityEditors['delivery_notes'].setData(d.notes);
        }, 500);
        // Change submit button text
        f.find('button[type="submit"]').html('<i class="mdi mdi-check"></i> Update Delivery Record');
        // Override form submit to use PUT
        f.off('submit').on('submit', function(e) {
            e.preventDefault();
            const data = {};
            $(this).serializeArray().forEach(fd => data[fd.name] = fd.value);
            data.notes = getEditorData('delivery_notes', '#mat-delivery-notes-editor');
            data.complications = getEditorData('delivery_complications', '#mat-delivery-complications-editor');
            $.ajax({
                url: `/maternity-workbench/delivery/${id}`,
                method: 'PUT', data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                success: function(r) {
                    if (r.success) {
                        destroyMaternityEditor('delivery_notes');
                        destroyMaternityEditor('delivery_complications');
                        toastr.success(r.message || 'Delivery record updated');
                        loadDeliveryTab();
                    } else toastr.error(r.message);
                },
                error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Failed to update'); }
            });
        });
    }, 300);
}

// ═══════════════════════════════════════════════════════════════
// BABY RECORDS TAB
// ═══════════════════════════════════════════════════════════════
function loadBabyTab() {
    if (!currentEnrollmentId) { $('#baby-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>'); return; }
    if (!currentEnrollment || !currentEnrollment.has_delivery) { $('#baby-content').html('<p class="text-muted text-center py-3">Record delivery first</p>'); return; }

    $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
        if (!resp.success) return;
        const babies = resp.enrollment.babies || [];
        window._babiesCache = babies; // cache for edit
        let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-baby-face-outline"></i> Baby Records (${babies.length})</h5>
            <button class="btn text-white" style="background: var(--maternity-pink);" onclick="showRegisterBabyForm()"><i class="mdi mdi-plus"></i> Register Baby</button>
        </div>`;

        if (babies.length === 0) {
            html += '<p class="text-muted text-center py-4">No babies registered yet</p>';
        } else {
            babies.forEach(function(b) {
                const patientName = b.patient && b.patient.user ? (b.patient.user.surname + ' ' + b.patient.user.firstname) : 'Baby';
                html += `<div class="baby-card">
                    <div class="baby-header">
                        <div class="baby-name">${patientName}</div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editBaby(${b.id})" title="Edit baby record"><i class="mdi mdi-pencil"></i></button>
                            <span class="baby-sex ${b.sex}">${b.sex === 'male' ? '♂ Male' : (b.sex === 'female' ? '♀ Female' : '? Ambiguous')}</span>
                        </div>
                    </div>
                    <div class="baby-metrics">
                        <div><div class="baby-metric-label">Birth Weight</div><div class="baby-metric-value">${b.birth_weight_kg ? b.birth_weight_kg + ' kg' : '-'}</div></div>
                        <div><div class="baby-metric-label">APGAR 1/5/10</div><div class="baby-metric-value">${b.apgar_1_min ?? '-'}/${b.apgar_5_min ?? '-'}/${b.apgar_10_min ?? '-'}</div></div>
                        <div><div class="baby-metric-label">Length</div><div class="baby-metric-value">${b.length_cm ? b.length_cm + ' cm' : '-'}</div></div>
                        <div><div class="baby-metric-label">Head Circ</div><div class="baby-metric-value">${b.head_circumference_cm ? b.head_circumference_cm + ' cm' : '-'}</div></div>
                        <div><div class="baby-metric-label">Feeding</div><div class="baby-metric-value">${(b.feeding_method || '-').replace(/_/g, ' ')}</div></div>
                        <div><div class="baby-metric-label">Status</div><div class="baby-metric-value">${b.status || '-'}</div></div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="loadGrowthChart(${b.id})"><i class="mdi mdi-chart-line"></i> Growth Chart</button>
                        <button class="btn btn-sm btn-outline-success" onclick="showGrowthRecordForm(${b.id})"><i class="mdi mdi-plus"></i> Add Growth</button>
                    </div>
                    <div id="growth-chart-${b.id}" class="mt-2"></div>
                </div>`;
            });
        }
        $('#baby-content').html(html);
    });
}

function showRegisterBabyForm() {
    _editMode = null; _editId = null;
    const form = $('#registerBabyModal #register-baby-form')[0];
    if (form) form.reset();
    // Uncheck all checkboxes
    $('#registerBabyModal input[type="checkbox"]').prop('checked', false);
    $('#registerBabyModalLabel').html('<i class="mdi mdi-baby-face-outline"></i> Register Baby');
    $('#btn-save-baby').html('<i class="mdi mdi-check"></i> Register Baby');
    $('#registerBabyModal').modal('show');
}

function editBaby(id) {
    const b = (window._babiesCache || []).find(x => x.id === id);
    if (!b) { toastr.error('Baby data not found'); return; }
    _editMode = 'baby'; _editId = id;
    const form = $('#registerBabyModal #register-baby-form')[0];
    if (form) form.reset();
    $('#registerBabyModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Baby Record');
    $('#btn-save-baby').html('<i class="mdi mdi-check"></i> Update Baby');
    // Pre-fill
    const m = $('#registerBabyModal');
    const u = b.patient && b.patient.user ? b.patient.user : {};
    m.find('input[name="baby_surname"]').val(u.surname || '');
    m.find('input[name="baby_firstname"]').val(u.firstname || '');
    m.find('select[name="sex"]').val(b.sex || '');
    m.find('input[name="birth_weight_kg"]').val(b.birth_weight_kg || '');
    m.find('input[name="length_cm"]').val(b.length_cm || '');
    m.find('input[name="head_circumference_cm"]').val(b.head_circumference_cm || '');
    m.find('input[name="apgar_1_min"]').val(b.apgar_1_min ?? '');
    m.find('input[name="apgar_5_min"]').val(b.apgar_5_min ?? '');
    m.find('input[name="apgar_10_min"]').val(b.apgar_10_min ?? '');
    m.find('select[name="feeding_method"]').val(b.feeding_method || 'exclusive_breastfeeding');
    ['bcg_given','opv0_given','hbv0_given','vitamin_k_given','eye_prophylaxis'].forEach(cb => {
        m.find('input[name="' + cb + '"]').prop('checked', !!b[cb]);
    });
    m.modal('show');
}

// Register Baby modal save handler
$(document).on('click', '#btn-save-baby', function() {
    const form = $('#registerBabyModal #register-baby-form');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const data = {};
    form.serializeArray().forEach(f => data[f.name] = f.value);
    ['bcg_given','opv0_given','hbv0_given','vitamin_k_given','eye_prophylaxis'].forEach(cb => {
        data[cb] = $('#registerBabyModal input[name="' + cb + '"]').is(':checked') ? 1 : 0;
    });
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    const isEdit = _editMode === 'baby' && _editId;
    const url = isEdit ? `/maternity-workbench/baby/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/baby`;
    const method = isEdit ? 'PUT' : 'POST';
    $.ajax({
        url: url, method: method, data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            btn.prop('disabled', false).html(isEdit ? '<i class="mdi mdi-check"></i> Update Baby' : '<i class="mdi mdi-check"></i> Register Baby');
            if (r.success) { _editMode = null; _editId = null; $('#registerBabyModal').modal('hide'); toastr.success(r.message); loadBabyTab(); } else toastr.error(r.message);
        },
        error: function(xhr) { btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Register Baby'); toastr.error(xhr.responseJSON?.message || 'Failed to register'); }
    });
});

function showGrowthRecordForm(babyId) {
    const form = $('#addGrowthModal #growth-record-form')[0];
    if (form) form.reset();
    $('#growth-baby-id').val(babyId);
    $('#addGrowthModal input[name="record_date"]').val(new Date().toISOString().split('T')[0]);
    $('#addGrowthModal').modal('show');
}

// Growth Record modal save handler
$(document).on('click', '#btn-save-growth', function() {
    const form = $('#addGrowthModal #growth-record-form');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const bid = $('#growth-baby-id').val();
    const data = {};
    form.serializeArray().forEach(f => { if (f.name !== 'baby_id') data[f.name] = f.value; });
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    $.ajax({
        url: `/maternity-workbench/baby/${bid}/growth`,
        method: 'POST', data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
            if (r.success) { $('#addGrowthModal').modal('hide'); toastr.success(r.message); loadBabyTab(); } else toastr.error(r.message);
        },
        error: function() { btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save'); toastr.error('Failed to save'); }
    });
});

function loadGrowthChart(babyId) {
    const container = $(`#growth-chart-${babyId}`);
    container.html('<div class="text-center text-muted py-2"><i class="mdi mdi-loading mdi-spin"></i> Loading growth charts...</div>');

    $.get(`/maternity-workbench/baby/${babyId}/growth-chart`, function(resp) {
        if (!resp.success || !resp.data || resp.data.length === 0) {
            container.html('<p class="text-muted small py-2"><i class="mdi mdi-information"></i> No growth records yet. Add a growth record to see WHO growth curves.</p>');
            return;
        }

        const sex = resp.sex;
        const sexLabel = sex === 'F' ? 'Girls' : 'Boys';
        const sexColor = sex === 'F' ? '#d63384' : '#0d6efd';
        const data = resp.data;
        const who = resp.who_reference;

        // Build tabbed chart view
        container.html(`
            <ul class="nav nav-tabs nav-tabs-sm mt-2" id="growth-tabs-${babyId}">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#gc-weight-${babyId}" style="font-size:0.78rem; padding: 4px 10px;">Weight-for-Age</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#gc-length-${babyId}" style="font-size:0.78rem; padding: 4px 10px;">Length-for-Age</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#gc-hc-${babyId}" style="font-size:0.78rem; padding: 4px 10px;">Head Circumference</a></li>
            </ul>
            <div class="tab-content border border-top-0 rounded-bottom p-2">
                <div class="tab-pane active" id="gc-weight-${babyId}">
                    <div class="small text-muted mb-1"><i class="mdi mdi-information"></i> WHO Weight-for-Age (${sexLabel}) — Shaded zones: <span class="text-danger">severe</span>, <span class="text-warning">moderate</span>, <span class="text-success">normal</span></div>
                    <div style="position:relative; height:280px;"><canvas id="gc-wfa-canvas-${babyId}"></canvas></div>
                </div>
                <div class="tab-pane" id="gc-length-${babyId}">
                    <div class="small text-muted mb-1"><i class="mdi mdi-information"></i> WHO Length/Height-for-Age (${sexLabel})</div>
                    <div style="position:relative; height:280px;"><canvas id="gc-lfa-canvas-${babyId}"></canvas></div>
                </div>
                <div class="tab-pane" id="gc-hc-${babyId}">
                    <div class="small text-muted mb-1"><i class="mdi mdi-information"></i> WHO Head Circumference-for-Age (${sexLabel})</div>
                    <div style="position:relative; height:280px;"><canvas id="gc-hc-canvas-${babyId}"></canvas></div>
                </div>
            </div>
            <div class="table-responsive mt-2">
                <table class="table table-sm table-bordered" style="font-size:0.8rem;">
                    <thead class="table-light"><tr><th>Date</th><th>Age (mo)</th><th>Weight (kg)</th><th>WAZ</th><th>Length (cm)</th><th>LAZ</th><th>Head (cm)</th><th>Status</th></tr></thead>
                    <tbody>${data.map(r => {
                        const statusBadge = r.nutritional_status === 'normal' ? 'bg-success' :
                            (r.nutritional_status && r.nutritional_status.includes('severe') ? 'bg-danger' : 'bg-warning text-dark');
                        return '<tr>' +
                            '<td>' + (r.record_date || '-') + '</td>' +
                            '<td>' + (r.age_months ? parseFloat(r.age_months).toFixed(1) : '-') + '</td>' +
                            '<td>' + (r.weight_kg || '-') + '</td>' +
                            '<td>' + (r.weight_for_age_z ? parseFloat(r.weight_for_age_z).toFixed(2) : '-') + '</td>' +
                            '<td>' + (r.length_height_cm || '-') + '</td>' +
                            '<td>' + (r.length_for_age_z ? parseFloat(r.length_for_age_z).toFixed(2) : '-') + '</td>' +
                            '<td>' + (r.head_circumference_cm || '-') + '</td>' +
                            '<td><span class="badge ' + statusBadge + '">' + (r.nutritional_status || '-').replace(/_/g, ' ') + '</span></td>' +
                        '</tr>';
                    }).join('')}</tbody>
                </table>
            </div>
        `);

        // Render each chart
        if (typeof Chart !== 'undefined') {
            renderWhoGrowthChart(`gc-wfa-canvas-${babyId}`, who.weight_for_age, data, 'weight_kg', 'Weight (kg)', sexColor, babyId + '-wfa');
            renderWhoGrowthChart(`gc-lfa-canvas-${babyId}`, who.length_for_age, data, 'length_height_cm', 'Length/Height (cm)', sexColor, babyId + '-lfa');
            renderWhoGrowthChart(`gc-hc-canvas-${babyId}`, who.head_circumference, data, 'head_circumference_cm', 'Head Circ (cm)', sexColor, babyId + '-hc');

            // Lazy-render on tab switch (Chart.js needs visible canvas)
            $(`#growth-tabs-${babyId} a[data-bs-toggle="tab"]`).on('shown.bs.tab', function() {
                const target = $(this).attr('href');
                if (target.includes('length') && window['_gcChart_' + babyId + '-lfa']) window['_gcChart_' + babyId + '-lfa'].resize();
                if (target.includes('hc') && window['_gcChart_' + babyId + '-hc']) window['_gcChart_' + babyId + '-hc'].resize();
            });
        }
    }).fail(function() {
        container.html('<div class="alert alert-danger small mb-0">Failed to load growth chart data.</div>');
    });
}

function renderWhoGrowthChart(canvasId, whoData, childData, measureField, yLabel, childColor, cacheKey) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !whoData || whoData.length === 0) return;

    if (window['_gcChart_' + cacheKey]) window['_gcChart_' + cacheKey].destroy();

    // WHO reference bands
    const months = whoData.map(w => w.month);
    const sd3n = whoData.map(w => w.sd_neg3);
    const sd2n = whoData.map(w => w.sd_neg2);
    const sd1n = whoData.map(w => w.sd_neg1);
    const median = whoData.map(w => w.median);
    const sd1p = whoData.map(w => w.sd_pos1);
    const sd2p = whoData.map(w => w.sd_pos2);
    const sd3p = whoData.map(w => w.sd_pos3);

    // Child data points
    const childPts = [];
    childData.forEach(record => {
        const age = record.age_months ? parseFloat(record.age_months) : null;
        const val = record[measureField] ? parseFloat(record[measureField]) : null;
        if (age !== null && val !== null) childPts.push({ x: age, y: val });
    });

    const datasets = [
        // WHO reference bands (filled)
        { label: '-3 SD', data: sd3n, borderColor: 'rgba(220,53,69,0.4)', borderWidth: 1, pointRadius: 0, fill: false, borderDash: [2,2] },
        { label: '-2 SD', data: sd2n, borderColor: 'rgba(255,152,0,0.5)', borderWidth: 1, pointRadius: 0, fill: { target: 0, above: 'rgba(255,152,0,0.08)' } },
        { label: '-1 SD', data: sd1n, borderColor: 'rgba(76,175,80,0.4)', borderWidth: 1, pointRadius: 0, fill: { target: 1, above: 'rgba(255,193,7,0.06)' } },
        { label: 'Median', data: median, borderColor: '#198754', borderWidth: 2, pointRadius: 0, fill: { target: 2, above: 'rgba(76,175,80,0.08)' } },
        { label: '+1 SD', data: sd1p, borderColor: 'rgba(76,175,80,0.4)', borderWidth: 1, pointRadius: 0, fill: { target: 3, above: 'rgba(76,175,80,0.08)' } },
        { label: '+2 SD', data: sd2p, borderColor: 'rgba(255,152,0,0.5)', borderWidth: 1, pointRadius: 0, fill: { target: 4, above: 'rgba(255,193,7,0.06)' } },
        { label: '+3 SD', data: sd3p, borderColor: 'rgba(220,53,69,0.4)', borderWidth: 1, pointRadius: 0, fill: { target: 5, above: 'rgba(255,152,0,0.08)' } },
    ];

    // Child measurement line (scatter + line)
    if (childPts.length > 0) {
        datasets.push({
            label: 'Child',
            data: childPts,
            borderColor: childColor,
            backgroundColor: childColor,
            pointRadius: 6,
            pointBackgroundColor: childColor,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            borderWidth: 2.5,
            showLine: true,
            tension: 0.2,
            type: 'scatter',
            order: 0
        });
    }

    window['_gcChart_' + cacheKey] = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels: months, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { usePointStyle: true, padding: 6, font: { size: 9 }, filter: item => ['Child','Median','-2 SD','+2 SD','-3 SD','+3 SD'].includes(item.text) }
                },
                tooltip: { mode: 'nearest', intersect: true }
            },
            scales: {
                x: { title: { display: true, text: 'Age (months)' }, min: 0, max: 60, ticks: { stepSize: 6 } },
                y: { title: { display: true, text: yLabel } }
            }
        }
    });
}

// ═══════════════════════════════════════════════════════════════
// POSTNATAL TAB
// ═══════════════════════════════════════════════════════════════
function loadPostnatalTab() {
    if (!currentEnrollmentId) { $('#postnatal-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>'); return; }

    $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/postnatal`, function(resp) {
        if (!resp.success) return;
        _postnatalVisitsCache = resp.visits; // cache for edit
        let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-account-heart"></i> Postnatal Visits (${resp.visits.length})</h5>
            <button class="btn text-white" style="background: var(--maternity-pink);" onclick="showPostnatalForm()"><i class="mdi mdi-plus"></i> New Visit</button>
        </div>`;

        if (resp.visits.length === 0) {
            html += '<p class="text-muted text-center py-4">No postnatal visits recorded</p>';
        } else {
            resp.visits.forEach(function(v) {
                html += `<div class="anc-visit-card" style="border-left-color: var(--info);">
                    <div class="d-flex justify-content-between">
                        <div><span class="visit-number" style="color: var(--info);">${v.visit_type_label}</span></div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="visit-date">${v.visit_date} (${v.days_postpartum || '?'}d postpartum)</span>
                            <button class="btn btn-sm btn-outline-info py-0 px-1" onclick="editPostnatalVisit(${v.id})" title="Edit visit"><i class="mdi mdi-pencil"></i></button>
                        </div>
                    </div>
                    <div class="visit-details">
                        <div><div class="visit-detail-label">Condition</div><div class="visit-detail-value">${v.general_condition || '-'}</div></div>
                        <div><div class="visit-detail-label">BP</div><div class="visit-detail-value">${v.blood_pressure || '-'}</div></div>
                        <div><div class="visit-detail-label">Lochia</div><div class="visit-detail-value">${v.lochia || '-'}</div></div>
                        <div><div class="visit-detail-label">Baby Weight</div><div class="visit-detail-value">${v.baby_weight_kg ? v.baby_weight_kg + ' kg' : '-'}</div></div>
                        <div><div class="visit-detail-label">Baby Feeding</div><div class="visit-detail-value">${v.baby_feeding || '-'}</div></div>
                        <div><div class="visit-detail-label">FP Counselled</div><div class="visit-detail-value">${v.family_planning_counselled ? 'Yes' : 'No'}</div></div>
                    </div>
                    <div class="mt-1 small text-muted">Seen by: ${v.seen_by}</div>
                </div>`;
            });
        }
        $('#postnatal-content').html(html);
    });
}

function showPostnatalForm() {
    _editMode = null; _editId = null;
    destroyMaternityEditor('postnatal_notes');
    const form = $('#postnatalModal #postnatal-form')[0];
    if (form) form.reset();
    $('#postnatalModalLabel').html('<i class="mdi mdi-account-heart"></i> Record Postnatal Visit');
    $('#btn-save-postnatal').html('<i class="mdi mdi-check"></i> Save Visit');
    $('#postnatalModal input[name="visit_date"]').val(new Date().toISOString().split('T')[0]);

    // Init CKEditor after modal is fully visible
    $('#postnatalModal').off('shown.bs.modal.pnEditor').on('shown.bs.modal.pnEditor', function() {
        initMaternityEditor('#mat-postnatal-notes-editor-modal', 'postnatal_notes');
    });
    // Destroy CKEditor when modal hides
    $('#postnatalModal').off('hidden.bs.modal.pnEditor').on('hidden.bs.modal.pnEditor', function() {
        destroyMaternityEditor('postnatal_notes');
    });

    $('#postnatalModal').modal('show');
}

function editPostnatalVisit(id) {
    const v = _postnatalVisitsCache.find(x => x.id === id);
    if (!v) { toastr.error('Visit data not found'); return; }
    _editMode = 'postnatal'; _editId = id;
    destroyMaternityEditor('postnatal_notes');
    const form = $('#postnatalModal #postnatal-form')[0];
    if (form) form.reset();
    $('#postnatalModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Postnatal Visit');
    $('#btn-save-postnatal').html('<i class="mdi mdi-check"></i> Update Visit');
    // Pre-fill form fields
    const m = $('#postnatalModal');
    m.find('select[name="visit_type"]').val(v.visit_type || '');
    m.find('input[name="visit_date"]').val(v.visit_date_raw || '');
    m.find('select[name="general_condition"]').val(v.general_condition || '');
    m.find('input[name="blood_pressure"]').val(v.blood_pressure || '');
    m.find('select[name="lochia"]').val(v.lochia || '');
    m.find('select[name="family_planning_counselled"]').val(v.family_planning_counselled ? '1' : '0');
    m.find('input[name="baby_weight_kg"]').val(v.baby_weight_kg || '');
    m.find('select[name="baby_feeding"]').val(v.baby_feeding || '');
    // CKEditor
    m.off('shown.bs.modal.pnEditor').on('shown.bs.modal.pnEditor', function() {
        initMaternityEditor('#mat-postnatal-notes-editor-modal', 'postnatal_notes').then(function(editor) {
            if (editor && v.clinical_notes) editor.setData(v.clinical_notes);
        });
    });
    m.off('hidden.bs.modal.pnEditor').on('hidden.bs.modal.pnEditor', function() {
        destroyMaternityEditor('postnatal_notes');
    });
    m.modal('show');
}

// Postnatal modal save handler
$(document).on('click', '#btn-save-postnatal', function() {
    const form = $('#postnatalModal #postnatal-form');
    if (!form[0].checkValidity()) { form[0].reportValidity(); return; }
    const data = {};
    form.serializeArray().forEach(f => data[f.name] = f.value);
    data.clinical_notes = getEditorData('postnatal_notes', '#mat-postnatal-notes-editor-modal');
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    const isEdit = _editMode === 'postnatal' && _editId;
    const url = isEdit ? `/maternity-workbench/postnatal/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/postnatal`;
    const method = isEdit ? 'PUT' : 'POST';
    $.ajax({
        url: url, method: method, data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Visit');
            if (r.success) { _editMode = null; _editId = null; destroyMaternityEditor('postnatal_notes'); $('#postnatalModal').modal('hide'); toastr.success(r.message); loadPostnatalTab(); } else toastr.error(r.message);
        },
        error: function(xhr) { btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Visit'); toastr.error(xhr.responseJSON?.message || 'Failed to save'); }
    });
});

// ═══════════════════════════════════════════════════════════════
// IMMUNIZATION TAB
// ═══════════════════════════════════════════════════════════════
function loadImmunizationTab() {
    if (!currentEnrollmentId) { $('#immunization-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>'); return; }

    if (typeof ImmunizationModule === 'undefined') {
        $('#immunization-content').html('<div class="alert alert-danger">Shared immunization module not loaded.</div>');
        return;
    }

    $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
        if (!resp.success) return;

        const enrollment = resp.enrollment || {};
        const mother = enrollment.patient || null;
        const babies = enrollment.babies || [];

        if (!mother) {
            $('#immunization-content').html('<p class="text-muted text-center py-4">Mother patient record not found.</p>');
            return;
        }

        const people = [];
        people.push({
            key: 'mother',
            label: 'Mother',
            name: mother.user ? `${mother.user.surname || ''} ${mother.user.firstname || ''}`.trim() : 'Mother',
            scheduleUrl: `/maternity-workbench/enrollment/${currentEnrollmentId}/mother-schedule`,
            generateUrl: `/maternity-workbench/enrollment/${currentEnrollmentId}/generate-mother-schedule`,
            historyUrl: `/maternity-workbench/enrollment/${currentEnrollmentId}/mother-immunization-history`,
            patientId: mother.id
        });

        babies.forEach(function(baby, idx) {
            const babyName = baby.patient && baby.patient.user
                ? `${baby.patient.user.surname || ''} ${baby.patient.user.firstname || ''}`.trim()
                : `Baby ${idx + 1}`;
            people.push({
                key: `baby-${baby.id}`,
                label: `Baby ${idx + 1}`,
                name: babyName,
                scheduleUrl: `/maternity-workbench/baby/${baby.id}/schedule`,
                generateUrl: `/maternity-workbench/baby/${baby.id}/generate-schedule`,
                historyUrl: `/maternity-workbench/baby/${baby.id}/immunization-history`,
                patientId: baby.patient_id
            });
        });

        let tabsHtml = '<ul class="nav nav-tabs mb-3" id="imm-person-tabs" role="tablist">';
        let panesHtml = '<div class="tab-content" id="imm-person-content">';

        people.forEach(function(person, idx) {
            const activeClass = idx === 0 ? 'active' : '';
            const showClass = idx === 0 ? 'show active' : '';
            tabsHtml += `
                <li class="nav-item">
                    <a class="nav-link ${activeClass}" data-toggle="tab" href="#imm-person-${person.key}" role="tab">
                        <i class="mdi ${person.key === 'mother' ? 'mdi-mother-nurse' : 'mdi-baby-face'}"></i> ${person.label}
                    </a>
                </li>`;

            panesHtml += `
                <div class="tab-pane fade ${showClass}" id="imm-person-${person.key}" role="tabpanel">
                    <div class="card-modern mb-3">
                        <div class="card-header py-2"><h6 class="mb-0"><i class="mdi mdi-account-circle"></i> ${person.name}</h6></div>
                        <div class="card-body py-2">
                            <ul class="nav nav-pills mb-3" id="imm-subtabs-${person.key}" role="tablist">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#imm-schedule-pane-${person.key}" role="tab"><i class="mdi mdi-calendar-check"></i> Schedule & Administer</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#imm-history-pane-${person.key}" role="tab"><i class="mdi mdi-history"></i> History</a></li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="imm-schedule-pane-${person.key}" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <select class="form-control form-control-sm mr-2" id="imm-template-${person.key}" style="width: 230px;"><option value="">Select Schedule Template...</option></select>
                                            <button type="button" class="btn btn-sm btn-primary" id="imm-add-schedule-${person.key}"><i class="mdi mdi-plus"></i> Add Schedule</button>
                                        </div>
                                    </div>
                                    <div class="mb-3" id="imm-active-${person.key}"><div class="alert alert-info py-2 mb-2"><i class="mdi mdi-information"></i> Loading active schedules...</div></div>
                                    <div class="mb-3 d-flex flex-wrap align-items-center">
                                        <span class="mr-3 small text-muted">Status:</span>
                                        <span class="badge badge-secondary mr-2">Pending</span>
                                        <span class="badge badge-warning mr-2">Due</span>
                                        <span class="badge badge-danger mr-2">Overdue</span>
                                        <span class="badge badge-success mr-2">Administered</span>
                                        <span class="badge badge-info mr-2">Skipped</span>
                                        <span class="badge badge-dark">Contraindicated</span>
                                    </div>
                                    <div id="imm-schedule-${person.key}"><div class="text-center text-muted py-3">Loading schedule...</div></div>
                                </div>

                                <div class="tab-pane fade" id="imm-history-pane-${person.key}" role="tabpanel">
                                    <div class="d-flex justify-content-end mb-2">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary active" data-view="timeline" data-person="${person.key}" onclick="switchImmunizationHistoryView(this)"><i class="mdi mdi-chart-timeline-variant"></i> Timeline</button>
                                            <button type="button" class="btn btn-outline-primary" data-view="calendar" data-person="${person.key}" onclick="switchImmunizationHistoryView(this)"><i class="mdi mdi-calendar-month"></i> Calendar</button>
                                            <button type="button" class="btn btn-outline-primary" data-view="table" data-person="${person.key}" onclick="switchImmunizationHistoryView(this)"><i class="mdi mdi-table"></i> Table</button>
                                        </div>
                                    </div>
                                    <div id="imm-history-timeline-${person.key}" class="imm-history-view-pane-${person.key}"></div>
                                    <div id="imm-history-calendar-${person.key}" class="imm-history-view-pane-${person.key} d-none"></div>
                                    <div id="imm-history-table-wrap-${person.key}" class="imm-history-view-pane-${person.key} d-none">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover" id="imm-history-table-${person.key}" style="width:100%">
                                                <thead>
                                                    <tr><th>Date</th><th>Vaccine</th><th>Dose #</th><th>Dose</th><th>Batch</th><th>Site</th><th>Nurse</th></tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
        });

        tabsHtml += '</ul>';
        panesHtml += '</div>';
        $('#immunization-content').html(tabsHtml + panesHtml);

        ImmunizationModule.initModalEvents();

        people.forEach(function(person) {
            ImmunizationModule.configure({
                baseUrl: '/maternity-workbench',
                csrfToken: CSRF_TOKEN,
                currentPatientId: person.patientId,
                productBatchesUrl: '/maternity-workbench/product-batches'
            });
            ImmunizationModule.loadTemplates(`#imm-template-${person.key}`);

            const reloadSchedule = function() {
                ImmunizationModule.configure({
                    baseUrl: '/maternity-workbench',
                    csrfToken: CSRF_TOKEN,
                    currentPatientId: person.patientId,
                    productBatchesUrl: '/maternity-workbench/product-batches',
                    onScheduleReload: reloadSchedule,
                    onHistoryReload: reloadTimeline
                });
                ImmunizationModule.loadSchedule(person.patientId, `#imm-schedule-${person.key}`, person.scheduleUrl, {
                    activeSchedulesId: `#imm-active-${person.key}`
                });
            };

            const reloadTimeline = function() {
                ImmunizationModule.configure({
                    baseUrl: '/maternity-workbench',
                    csrfToken: CSRF_TOKEN,
                    currentPatientId: person.patientId,
                    productBatchesUrl: '/maternity-workbench/product-batches',
                    onScheduleReload: reloadSchedule,
                    onHistoryReload: reloadTimeline
                });
                ImmunizationModule.loadTimeline(person.patientId, `#imm-history-timeline-${person.key}`, person.historyUrl);
            };

            reloadSchedule();
            reloadTimeline();

            $(document).off(`click.immAdd${person.key}`, `#imm-add-schedule-${person.key}`)
                .on(`click.immAdd${person.key}`, `#imm-add-schedule-${person.key}`, function() {
                    const templateId = $(`#imm-template-${person.key}`).val() || null;
                    ImmunizationModule.configure({
                        baseUrl: '/maternity-workbench',
                        csrfToken: CSRF_TOKEN,
                        currentPatientId: person.patientId,
                        productBatchesUrl: '/maternity-workbench/product-batches',
                        onScheduleReload: reloadSchedule,
                        onHistoryReload: reloadTimeline
                    });
                    ImmunizationModule.generateSchedule(person.patientId, person.generateUrl, templateId, function() {
                        reloadSchedule();
                    });
                });
        });
    });
}

function switchImmunizationHistoryView(btn) {
    const person = $(btn).data('person');
    const view = $(btn).data('view');
    const paneClass = `.imm-history-view-pane-${person}`;

    $(`#imm-history-pane-${person} .btn`).removeClass('active');
    $(btn).addClass('active');
    $(paneClass).addClass('d-none');

    const historyUrl = person === 'mother'
        ? `/maternity-workbench/enrollment/${currentEnrollmentId}/mother-immunization-history`
        : `/maternity-workbench/baby/${person.replace('baby-', '')}/immunization-history`;

    if (view === 'timeline') {
        $(`#imm-history-timeline-${person}`).removeClass('d-none');
        ImmunizationModule.loadTimeline(null, `#imm-history-timeline-${person}`, historyUrl);
    } else if (view === 'calendar') {
        $(`#imm-history-calendar-${person}`).removeClass('d-none');
        ImmunizationModule.loadCalendar(null, `#imm-history-calendar-${person}`, historyUrl);
    } else {
        $(`#imm-history-table-wrap-${person}`).removeClass('d-none');
        ImmunizationModule.loadHistoryTable(null, `#imm-history-table-wrap-${person}`, `#imm-history-table-${person}`, historyUrl);
    }
}

// ═══════════════════════════════════════════════════════════════
// NOTES TAB (shares pattern with nursing notes)
// ═══════════════════════════════════════════════════════════════
function loadNotesTab() {
    if (!currentEnrollmentId) { $('#notes-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>'); return; }

    $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/notes`, function(resp) {
        if (!resp.success) return;
        _notesCache = resp.notes; // cache for edit
        let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-note-text"></i> Notes</h5>
            <button class="btn text-white" style="background: var(--maternity-pink);" onclick="showAddNoteForm()"><i class="mdi mdi-plus"></i> Add Note</button>
        </div>`;

        // Cache note types for the modal
        if (resp.note_types) {
            let opts = '';
            resp.note_types.forEach(t => opts += `<option value="${t.id}">${t.name}</option>`);
            window._matNoteTypeOptions = opts;
        }

        if (resp.notes.length === 0) {
            html += '<p class="text-muted text-center py-4">No notes yet</p>';
        } else {
            resp.notes.forEach(function(n) {
                const actions = n.can_edit ? `<div class="mt-1"><button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editNote(${n.id})" title="Edit note"><i class="mdi mdi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteNote(${n.id})" title="Delete note"><i class="mdi mdi-delete"></i></button></div>` : '';
                html += `<div class="note-card">
                    <div class="d-flex justify-content-between"><span class="note-author">${n.created_by} <span class="badge bg-secondary">${n.type}</span></span><div class="d-flex align-items-center gap-2"><span class="note-time">${n.time_ago}</span>${actions}</div></div>
                    <div class="note-body">${n.note}</div>
                </div>`;
            });
        }
        $('#notes-content').html(html);
    });
}

// ═══════════════════════════════════════════════════════════════
// AUDIT TAB
// ═══════════════════════════════════════════════════════════════
function loadAuditTab() {
    if (!currentEnrollmentId) {
        $('#audit-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
        return;
    }

    $('#audit-content').html('<div class="text-center p-4 text-muted"><i class="mdi mdi-loading mdi-spin mdi-36px"></i><br>Loading audit trail...</div>');

    $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/audit-trail`, function(resp) {
        if (!resp.success) {
            $('#audit-content').html('<p class="text-danger text-center py-3">Failed to load audit trail</p>');
            return;
        }

        if (!resp.audits || resp.audits.length === 0) {
            $('#audit-content').html('<p class="text-muted text-center py-3">No audit records yet</p>');
            return;
        }

        let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-shield-search"></i> Audit Trail (${resp.total})</h5>
            <span class="badge bg-secondary">Enrollment #${resp.enrollment_id}</span>
        </div>`;

        html += '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Date/Time</th><th>Module</th><th>Event</th><th>User</th><th>Changes</th></tr></thead><tbody>';
        resp.audits.forEach(function(a) {
            const oldVals = a.old_values ? JSON.stringify(a.old_values) : '';
            const newVals = a.new_values ? JSON.stringify(a.new_values) : '';
            const changes = `${oldVals ? '<div><strong>Old:</strong> ' + oldVals + '</div>' : ''}${newVals ? '<div><strong>New:</strong> ' + newVals + '</div>' : ''}` || '-';
            html += `<tr>
                <td>${a.created_at || '-'}</td>
                <td><span class="badge bg-light text-dark">${a.module}</span></td>
                <td><span class="badge bg-info text-dark">${(a.event || '').toUpperCase()}</span></td>
                <td>${a.user || 'System'}</td>
                <td class="small">${changes}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';

        $('#audit-content').html(html);
    }).fail(function() {
        $('#audit-content').html('<p class="text-danger text-center py-3">Failed to load audit trail</p>');
    });
}

function showAddNoteForm() {
    _editMode = null; _editId = null;
    destroyMaternityEditor('note');
    // Populate note types from cached options
    $('#modal-note-type-select').html(window._matNoteTypeOptions || '');
    $('#addNoteModalLabel').html('<i class="mdi mdi-note-plus"></i> Add Clinical Note');
    $('#btn-save-note').html('<i class="mdi mdi-check"></i> Save Note');
    const form = $('#addNoteModal #add-note-form')[0];
    if (form) form.reset();

    // Init CKEditor after modal is fully visible
    $('#addNoteModal').off('shown.bs.modal.noteEditor').on('shown.bs.modal.noteEditor', function() {
        initMaternityEditor('#mat-note-editor-modal', 'note');
    });
    // Destroy CKEditor when modal hides
    $('#addNoteModal').off('hidden.bs.modal.noteEditor').on('hidden.bs.modal.noteEditor', function() {
        destroyMaternityEditor('note');
    });

    $('#addNoteModal').modal('show');
}

function editNote(id) {
    const n = _notesCache.find(x => x.id === id);
    if (!n) { toastr.error('Note not found'); return; }
    if (!n.can_edit) { toastr.warning('This note can no longer be edited'); return; }
    _editMode = 'note'; _editId = id;
    destroyMaternityEditor('note');
    $('#modal-note-type-select').html(window._matNoteTypeOptions || '');
    const form = $('#addNoteModal #add-note-form')[0];
    if (form) form.reset();
    $('#addNoteModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Note');
    $('#btn-save-note').html('<i class="mdi mdi-check"></i> Update Note');
    // Pre-fill note type
    $('#addNoteModal select[name="note_type_id"]').val(n.note_type_id || '');
    // CKEditor: set content after init
    $('#addNoteModal').off('shown.bs.modal.noteEditor').on('shown.bs.modal.noteEditor', function() {
        initMaternityEditor('#mat-note-editor-modal', 'note').then(function(editor) {
            if (editor && n.note) editor.setData(n.note);
        });
    });
    $('#addNoteModal').off('hidden.bs.modal.noteEditor').on('hidden.bs.modal.noteEditor', function() {
        destroyMaternityEditor('note');
    });
    $('#addNoteModal').modal('show');
}

function deleteNote(id) {
    if (!confirm('Delete this note?')) return;
    $.ajax({
        url: `/maternity-workbench/note/${id}`,
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            if (r.success) { toastr.success(r.message || 'Note deleted'); loadNotesTab(); } else toastr.error(r.message);
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Failed to delete note'); }
    });
}

// Add/Edit Note modal save handler
$(document).on('click', '#btn-save-note', function() {
    const noteContent = getEditorData('note', '#mat-note-editor-modal');
    if (!noteContent || !noteContent.trim()) { toastr.warning('Please enter note content'); return; }
    const noteTypeId = $('#addNoteModal select[name="note_type_id"]').val();
    if (!noteTypeId) { toastr.warning('Please select a note type'); return; }
    const data = { note_type_id: noteTypeId, note: noteContent };
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    const isEdit = _editMode === 'note' && _editId;
    const url = isEdit ? `/maternity-workbench/note/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/note`;
    const method = isEdit ? 'PUT' : 'POST';
    $.ajax({
        url: url, method: method, data: data, headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        success: function(r) {
            btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Note');
            if (r.success) { _editMode = null; _editId = null; destroyMaternityEditor('note'); $('#addNoteModal').modal('hide'); toastr.success(r.message); loadNotesTab(); } else toastr.error(r.message);
        },
        error: function(xhr) { btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Note'); toastr.error(xhr.responseJSON?.message || 'Failed to save'); }
    });
});

// ═══════════════════════════════════════════════════════════════
// REPORTS
// ═══════════════════════════════════════════════════════════════
function showReports() {
    hideAllViews();
    $('#reports-view').addClass('active').css('display', 'flex');
    if (window.innerWidth < 768) { $('#left-panel').addClass('hidden'); $('#main-workspace').addClass('active'); }

    $.get('{{ route("maternity-workbench.reports.summary") }}', function(resp) {
        if (!resp.success) return;
        const d = resp.data;
        let html = '<div class="row mb-3">';
        const stats = [
            { label: 'Total Enrollments', value: d.total_enrollments, icon: 'mdi-clipboard-list', cls: 'mat-stat-pink' },
            { label: 'Active ANC', value: d.active_enrollments, icon: 'mdi-mother-nurse', cls: 'mat-stat-green' },
            { label: 'Deliveries (Month)', value: d.deliveries_this_month, icon: 'mdi-baby-carriage', cls: 'mat-stat-blue' },
            { label: 'Total Babies', value: d.total_babies, icon: 'mdi-baby-face', cls: 'mat-stat-orange' },
        ];
        stats.forEach(s => {
            html += `<div class="col-lg-3 col-md-6 mb-3"><div class="mat-stat-card ${s.cls}"><div class="mat-stat-icon"><i class="mdi ${s.icon}" style="font-size:1.5rem;"></i></div><div><div class="mat-stat-value">${s.value}</div><div class="mat-stat-label">${s.label}</div></div></div></div>`;
        });
        html += '</div>';

        html += '<div class="row">';
        html += '<div class="col-lg-6 mb-3"><div class="card-modern"><div class="card-header"><h6 class="mb-0">Delivery Stats (This Year)</h6></div><div class="card-body" id="delivery-stats-body"><p class="text-muted">Loading...</p></div></div></div>';
        html += '<div class="col-lg-6 mb-3"><div class="card-modern"><div class="card-header"><h6 class="mb-0">Immunization Coverage</h6></div><div class="card-body" id="imm-coverage-body"><p class="text-muted">Loading...</p></div></div></div>';
        html += '</div>';
        html += '<div class="row"><div class="col-lg-6 mb-3"><div class="card-modern"><div class="card-header"><h6 class="mb-0">ANC Defaulters</h6></div><div class="card-body" id="defaulters-body"><p class="text-muted">Loading...</p></div></div></div>';
        html += '<div class="col-lg-6 mb-3"><div class="card-modern"><div class="card-header"><h6 class="mb-0">High Risk Register</h6></div><div class="card-body" id="high-risk-body"><p class="text-muted">Loading...</p></div></div></div></div>';

        $('#reports-content').html(html);

        // Load sub-reports with charts
        $.get('{{ route("maternity-workbench.reports.delivery-stats") }}', function(r) {
            if (!r.success) return;
            const types = Object.keys(r.by_type);
            const counts = Object.values(r.by_type);
            const chartColors = ['#e91e63','#4caf50','#2196f3','#ff9800','#9c27b0','#00bcd4','#795548'];

            let tHtml = '<div class="row"><div class="col-md-6"><div style="position:relative; height:250px;"><canvas id="delivery-donut-chart"></canvas></div></div><div class="col-md-6">';
            tHtml += '<table class="table table-sm mb-0"><thead><tr><th>Type</th><th>Count</th><th>%</th></tr></thead><tbody>';
            const total = counts.reduce((a, b) => a + b, 0) || 1;
            types.forEach((type, i) => {
                tHtml += `<tr><td><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${chartColors[i % chartColors.length]};margin-right:6px;"></span>${type.toUpperCase()}</td><td class="fw-bold">${r.by_type[type]}</td><td>${((r.by_type[type] / total) * 100).toFixed(1)}%</td></tr>`;
            });
            tHtml += '</tbody></table>';
            tHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'delivery_stats')"><i class="mdi mdi-download"></i> Export CSV</button>`;
            tHtml += '</div></div>';
            $('#delivery-stats-body').html(tHtml);

            if (typeof Chart !== 'undefined' && types.length > 0) {
                new Chart(document.getElementById('delivery-donut-chart').getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: types.map(t => t.toUpperCase()), datasets: [{ data: counts, backgroundColor: chartColors.slice(0, types.length), borderWidth: 2, borderColor: '#fff' }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, usePointStyle: true, padding: 8 } } } }
                });
            }
        });

        $.get('{{ route("maternity-workbench.reports.immunization-coverage") }}', function(r) {
            if (!r.success || !Object.keys(r.coverage).length) { $('#imm-coverage-body').html('<p class="text-muted mb-0">No data</p>'); return; }
            const vaccines = Object.keys(r.coverage);
            const givenArr = vaccines.map(v => r.coverage[v].given);
            const pendingArr = vaccines.map(v => r.coverage[v].total - r.coverage[v].given);

            let cHtml = '<div style="position:relative; height:250px;"><canvas id="imm-bar-chart"></canvas></div>';
            cHtml += '<table class="table table-sm mt-2 mb-0"><thead><tr><th>Vaccine</th><th>Given</th><th>Total</th><th>%</th></tr></thead><tbody>';
            vaccines.forEach(vaccine => {
                const data = r.coverage[vaccine];
                const color = data.percentage >= 80 ? 'text-success' : (data.percentage >= 50 ? 'text-warning' : 'text-danger');
                cHtml += `<tr><td>${vaccine}</td><td>${data.given}</td><td>${data.total}</td><td class="fw-bold ${color}">${data.percentage}%</td></tr>`;
            });
            cHtml += '</tbody></table>';
            cHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'immunization_coverage')"><i class="mdi mdi-download"></i> Export CSV</button>`;
            $('#imm-coverage-body').html(cHtml);

            if (typeof Chart !== 'undefined') {
                new Chart(document.getElementById('imm-bar-chart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: vaccines,
                        datasets: [
                            { label: 'Given', data: givenArr, backgroundColor: 'rgba(76,175,80,0.7)', borderColor: '#4caf50', borderWidth: 1 },
                            { label: 'Pending', data: pendingArr, backgroundColor: 'rgba(255,152,0,0.5)', borderColor: '#ff9800', borderWidth: 1 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { font: { size: 10 }, usePointStyle: true } } }, scales: { x: { stacked: true, ticks: { font: { size: 9 } } }, y: { stacked: true, beginAtZero: true } } }
                });
            }
        });

        $.get('{{ route("maternity-workbench.reports.anc-defaulters") }}', function(r) {
            if (!r.success) return;
            if (r.defaulters.length === 0) { $('#defaulters-body').html('<p class="text-muted mb-0">No defaulters</p>'); return; }

            // Horizontal bar chart of days overdue
            let dHtml = '<div style="position:relative; height:' + Math.max(200, r.defaulters.length * 30) + 'px;"><canvas id="defaulters-bar-chart"></canvas></div>';
            dHtml += '<table class="table table-sm mt-2 mb-0"><thead><tr><th>Name</th><th>File No</th><th>Missed</th><th>Days Overdue</th></tr></thead><tbody>';
            r.defaulters.forEach(d => { dHtml += `<tr><td>${d.name}</td><td>${d.file_no}</td><td>${d.missed_date}</td><td class="text-danger fw-bold">${d.days_overdue}</td></tr>`; });
            dHtml += '</tbody></table>';
            dHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'anc_defaulters')"><i class="mdi mdi-download"></i> Export CSV</button>`;
            $('#defaulters-body').html(dHtml);

            if (typeof Chart !== 'undefined') {
                new Chart(document.getElementById('defaulters-bar-chart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: r.defaulters.map(d => d.name.length > 20 ? d.name.substr(0, 18) + '...' : d.name),
                        datasets: [{ label: 'Days Overdue', data: r.defaulters.map(d => d.days_overdue), backgroundColor: r.defaulters.map(d => d.days_overdue > 30 ? 'rgba(220,53,69,0.7)' : (d.days_overdue > 14 ? 'rgba(255,152,0,0.7)' : 'rgba(255,193,7,0.7)')), borderWidth: 1 }]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, title: { display: true, text: 'Days' } } } }
                });
            }
        });

        $.get('{{ route("maternity-workbench.reports.high-risk-register") }}', function(r) {
            if (!r.success) return;
            if (r.register.length === 0) { $('#high-risk-body').html('<p class="text-muted mb-0">No high-risk patients</p>'); return; }

            // Risk distribution gauge
            const riskDist = {};
            r.register.forEach(p => { const lvl = p.risk_level || 'high'; riskDist[lvl] = (riskDist[lvl] || 0) + 1; });

            let hHtml = '';
            if (Object.keys(riskDist).length > 0) {
                const riskLabels = Object.keys(riskDist).map(k => k.replace('_', ' ').toUpperCase());
                const riskCounts = Object.values(riskDist);
                const riskClrs = Object.keys(riskDist).map(k => k === 'very_high' ? '#dc3545' : (k === 'high' ? '#fd7e14' : '#ffc107'));
                hHtml += '<div class="d-flex justify-content-center mb-2">';
                riskLabels.forEach((label, i) => {
                    hHtml += `<span class="badge me-2" style="background:${riskClrs[i]}; font-size:0.75rem;">${label}: ${riskCounts[i]}</span>`;
                });
                hHtml += '</div>';
            }

            hHtml += '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>File No</th><th>Risk</th><th>Factors</th><th>Status</th></tr></thead><tbody>';
            r.register.forEach(p => {
                const risks = Array.isArray(p.risk_factors) ? p.risk_factors.join(', ') : (p.risk_factors || 'N/A');
                const riskClr = (p.risk_level === 'very_high') ? 'bg-danger' : 'bg-warning text-dark';
                hHtml += `<tr><td>${p.name}</td><td>${p.file_no}</td><td><span class="badge ${riskClr}">${(p.risk_level || 'high').replace('_', ' ')}</span></td><td class="small">${risks}</td><td><span class="enrollment-badge ${p.status}">${p.status}</span></td></tr>`;
            });
            hHtml += '</tbody></table></div>';
            hHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'high_risk_register')"><i class="mdi mdi-download"></i> Export CSV</button>`;
            $('#high-risk-body').html(hHtml);
        });
    });
}

// Export table to CSV
function exportReportTable(btn, filename) {
    const card = $(btn).closest('.card-body');
    const table = card.find('table');
    if (!table.length) { toastr.warning('No table to export'); return; }

    let csv = '';
    table.find('tr').each(function() {
        const row = [];
        $(this).find('th, td').each(function() {
            row.push('"' + $(this).text().replace(/"/g, '""').trim() + '"');
        });
        csv += row.join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `maternity_${filename}_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
    toastr.success('CSV exported');
}

// ═══════════════════════════════════════════════════════════════
// EVENT BINDINGS (SHARED pattern with nursing workbench)
// ═══════════════════════════════════════════════════════════════
$(document).ready(function() {
    // Initialize shared patient search module
    PatientSearch.init();

    // Load queue counts on page load
    loadQueueCounts();

    // Queue item clicks (SHARED)
    $('.queue-item').on('click', function() {
        const filter = $(this).data('filter');
        if (filter) showQueue(filter);
    });

    // Refresh queues
    $('#refresh-queues-btn').on('click', loadQueueCounts);

    // Close queue (SHARED)
    $('#btn-close-queue').on('click', hideQueue);

    // View queue from empty state
    $('#view-queue-btn').on('click', function() { showQueue('active-anc'); });

    // Workspace tab switching (SHARED)
    $(document).on('click', '.workspace-tab', function() {
        const tab = $(this).data('tab');
        switchWorkspaceTab(tab);
    });

    // Mobile back button (SHARED)
    $('#btn-back-to-search').on('click', function() {
        hideAllViews();
        $('#empty-state').show();
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    });

    // View work pane (SHARED)
    $('#btn-view-work-pane').on('click', function() {
        if (currentPatient) {
            hideAllViews();
            $('#patient-header').addClass('active');
            $('#workspace-content').show().addClass('active');
        }
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    });

    // Toggle search (SHARED)
    $('#btn-toggle-search').on('click', function() {
        $('#left-panel').toggleClass('hidden');
    });

    // Clinical Context (SHARED — same as nursing workbench)
    $('#btn-clinical-context').on('click', function() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }
        ClinicalContext.load(currentPatient);
    });

    // Quick action: Enroll patient
    $('#btn-enroll-patient').on('click', function() {
        if (currentPatient) switchWorkspaceTab('enrollment');
    });

    // Quick action: Quick vitals
    $('#btn-quick-vitals').on('click', function() {
        if (currentPatient) switchWorkspaceTab('vitals');
    });

    // Reports button
    $('#btn-maternity-reports').on('click', showReports);

    // Print buttons
    $('#btn-print-anc-card').on('click', function() {
        if (!currentEnrollmentId) { toastr.warning('No enrollment selected'); return; }
        window.open(`/maternity-workbench/enrollment/${currentEnrollmentId}/print-anc-card`, '_blank');
    });

    $('#btn-print-road-card').on('click', function() {
        if (!currentEnrollmentId) { toastr.warning('No enrollment selected'); return; }
        window.open(`/maternity-workbench/enrollment/${currentEnrollmentId}/print-road-health-card`, '_blank');
    });

    $('#btn-maternity-audit').on('click', function() {
        if (!currentEnrollmentId) { toastr.warning('No enrollment selected'); return; }
        switchWorkspaceTab('audit');
    });

    $('#btn-close-reports').on('click', function() {
        $('#reports-view').removeClass('active').hide();
        if (currentPatient) {
            $('#patient-header').addClass('active');
            $('#workspace-content').show().addClass('active');
        } else {
            $('#empty-state').show();
        }
    });

    // Auto-refresh queues every 5 minutes
    setInterval(loadQueueCounts, 300000);
});

// ═══════════════════════════════════════════════════════════════
// LAB & IMAGING RESULT ENTRY / EDIT  (shared InvestResultEntry)
// ═══════════════════════════════════════════════════════════════

// Lab result entry (called from investigation history DataTable "Enter Result" button)
function enterLabResult(requestId) {
    InvestResultEntry.enterResult(
        requestId,
        `/lab-workbench/lab-service-requests/${requestId}`,
        `/lab-workbench/lab-service-requests/${requestId}/attachments`,
        '{{ route("lab.saveResult") }}'
    );
}

// Lab result edit (called from investigation history DataTable "Edit" button)
function editLabResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(
        requestId,
        `/lab-workbench/lab-service-requests/${requestId}`,
        `/lab-workbench/lab-service-requests/${requestId}/attachments`,
        '{{ route("lab.saveResult") }}'
    );
}

// Imaging result entry (called from imaging history DataTable "Enter Result" button)
function enterImagingResult(requestId) {
    InvestResultEntry.enterResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        '{{ route("imaging.saveResult") }}'
    );
}

// Imaging result edit (called from imaging history DataTable "Edit" button)
function editImagingResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        '{{ route("imaging.saveResult") }}'
    );
}

// Initialize shared result entry module — refresh maternity DataTables on save
InvestResultEntry.bindFormSubmit(function() {
    if ($.fn.DataTable.isDataTable('#mco_lab_history_list')) {
        $('#mco_lab_history_list').DataTable().ajax.reload(null, false);
    }
    if ($.fn.DataTable.isDataTable('#mco_imaging_history_list')) {
        $('#mco_imaging_history_list').DataTable().ajax.reload(null, false);
    }
});

// ── View lab result in modal ──────────────────────────────────
function setResViewInModal(obj) {
    let res_obj = JSON.parse($(obj).attr('data-result-obj'));

    // Basic service info
    $('.invest_res_service_name_view').text($(obj).attr('data-service-name'));

    // Patient information
    let patientName = (res_obj.patient && res_obj.patient.user)
        ? res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname
        : 'N/A';
    $('#res_patient_name').html(patientName);
    $('#res_patient_id').html(res_obj.patient ? res_obj.patient.file_no : 'N/A');

    // Calculate age from date of birth
    let age = 'N/A';
    if (res_obj.patient && res_obj.patient.date_of_birth) {
        let dob = new Date(res_obj.patient.date_of_birth);
        let today = new Date();
        let ageYears = today.getFullYear() - dob.getFullYear();
        let monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            ageYears--;
        }
        age = ageYears + ' years';
    }
    $('#res_patient_age').html(age);

    // Gender
    let gender = (res_obj.patient && res_obj.patient.gender) ? res_obj.patient.gender.toUpperCase() : 'N/A';
    $('#res_patient_gender').html(gender);

    // Test information
    $('#res_test_id').html(res_obj.id);
    $('#res_sample_date').html(res_obj.sample_date || 'N/A');
    $('#res_result_date').html(res_obj.result_date || 'N/A');
    $('#res_result_by').html(res_obj.results_person
        ? res_obj.results_person.firstname + ' ' + res_obj.results_person.surname
        : 'N/A');

    // Signature date (use result date)
    $('#res_signature_date').html(res_obj.result_date || '');

    // Generated date (current date)
    let now = new Date();
    let generatedDate = now.toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
    $('#res_generated_date').html(generatedDate);

    // Handle V2 results (structured data)
    if (res_obj.result_data) {
        let resultData = res_obj.result_data;
        if (typeof resultData === 'string') {
            try { resultData = JSON.parse(resultData); } catch (e) { resultData = null; }
        }

        if (resultData && typeof resultData === 'object') {
            let paramsArray = Array.isArray(resultData) ? resultData : [];

            if (paramsArray.length > 0) {
                let resultsHtml = '<table class="result-table"><thead><tr>';
                resultsHtml += '<th style="width:40%;">Test Parameter</th>';
                resultsHtml += '<th style="width:25%;">Results</th>';
                resultsHtml += '<th style="width:25%;">Reference Range</th>';
                resultsHtml += '<th style="width:10%;">Status</th>';
                resultsHtml += '</tr></thead><tbody>';

                paramsArray.forEach(function(param) {
                    resultsHtml += '<tr>';
                    resultsHtml += '<td><strong>' + param.name + '</strong>';
                    if (param.code) resultsHtml += ' <span style="color:#999;">(' + param.code + ')</span>';
                    resultsHtml += '</td>';

                    let valueDisplay = param.value;
                    if (param.unit) valueDisplay += ' ' + param.unit;
                    resultsHtml += '<td>' + valueDisplay + '</td>';

                    let refRange = 'N/A';
                    if (param.reference_range) {
                        if (param.type === 'integer' || param.type === 'float') {
                            if (param.reference_range.min !== undefined && param.reference_range.max !== undefined) {
                                refRange = param.reference_range.min + ' - ' + param.reference_range.max;
                                if (param.unit) refRange += ' ' + param.unit;
                            }
                        } else if (param.type === 'boolean' || param.type === 'enum') {
                            refRange = param.reference_range.reference_value || 'N/A';
                        } else if (param.reference_range.text) {
                            refRange = param.reference_range.text;
                        }
                    }
                    resultsHtml += '<td>' + refRange + '</td>';

                    let statusHtml = '';
                    if (param.status) {
                        let statusClass = 'status-' + param.status.toLowerCase().replace(' ', '-');
                        statusHtml = '<span class="result-status-badge ' + statusClass + '">' + param.status + '</span>';
                    }
                    resultsHtml += '<td>' + statusHtml + '</td>';
                    resultsHtml += '</tr>';
                });

                resultsHtml += '</tbody></table>';
                $('#invest_res').html(resultsHtml);
            } else {
                $('#invest_res').html(res_obj.result);
            }
        } else {
            $('#invest_res').html(res_obj.result);
        }
    } else {
        // V1 results (HTML content)
        $('#invest_res').html(res_obj.result);
    }

    // Handle attachments
    $('#invest_attachments').html('');
    if (res_obj.attachments) {
        let attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
        if (attachments && attachments.length > 0) {
            let attachHtml = '<div class="result-attachments"><h6 style="margin-bottom:15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
            attachments.forEach(function(attachment) {
                let url = '{{ asset("storage") }}/' + attachment.path;
                let icon = getFileIcon(attachment.type);
                attachHtml += `<div class="col-md-4 mb-2">
                    <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm btn-block">
                        ${icon} ${attachment.name}
                    </a>
                </div>`;
            });
            attachHtml += '</div></div>';
            $('#invest_attachments').html(attachHtml);
        }
    }

    $('#investResViewModal').modal('show');
}

function PrintElem(elem) {
    var mywindow = window.open('', 'PRINT', 'height=400,width=600');
    mywindow.document.write('<html><head><title>' + document.title + '</title>');
    mywindow.document.write('<style>body{font-family:"Segoe UI",sans-serif;} .result-table{width:100%;border-collapse:collapse;} .result-table th,.result-table td{border:1px solid #ddd;padding:8px;text-align:left;} .result-header{display:flex;justify-content:space-between;border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:20px;} .result-title-section{background:#eee;text-align:center;padding:10px;font-weight:bold;margin:20px 0;} .result-patient-info{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;} .result-info-row{display:flex;margin-bottom:5px;} .result-info-label{font-weight:bold;width:120px;} .result-footer{margin-top:50px;border-top:1px solid #ccc;padding-top:10px;text-align:center;font-size:12px;}</style>');
    mywindow.document.write('</head><body>');
    mywindow.document.write(document.getElementById(elem).innerHTML);
    mywindow.document.write('</body></html>');
    mywindow.document.close();
    mywindow.focus();
    mywindow.print();
    mywindow.close();
    return true;
}

function getFileIcon(type) {
    if (type.includes('image')) return '<i class="fa fa-file-image-o"></i>';
    if (type.includes('pdf')) return '<i class="fa fa-file-pdf-o"></i>';
    return '<i class="fa fa-file-o"></i>';
}
</script>
@endsection
