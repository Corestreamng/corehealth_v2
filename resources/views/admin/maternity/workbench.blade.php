@extends('admin.layouts.app')

@section('title', 'Maternity Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/clinical-orders-shared.css') }}">
<link rel="stylesheet" href="{{ asset('css/billing-shared.css') }}">
@endpush

@section('content')
    @include('admin.partials.procedure_outcome_modal')
@php
$hosColor = appsettings()->hos_color ?? '#0066cc';
$sett = appsettings();
@endphp
<style>
    :root {
        --hospital-primary: {{ appsettings("hos_color", "#007bff") }};
        --hospital-primary-rgb: 0,
        123,
        255;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
        --maternity-pink: #e91e8a;
        --maternity-pink-rgb: 233,
        30,
        138;
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

    .search-result-item:hover,
    .search-result-item.active {
        background: #f8f9fa;
    }

    .search-result-item img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .search-result-info {
        flex: 1;
    }

    .search-result-name {
        font-weight: 600;
        color: #212529;
        margin-bottom: 0.25rem;
    }

    .search-result-details {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .pending-badge {
        background: var(--maternity-pink);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* ═══ SHARED: Queue Widget ═══ */
    .queue-widget {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .queue-widget h6 {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: 1rem;
    }

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

    .queue-item:hover {
        transform: translateX(5px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .queue-item.active {
        border-left: 3px solid var(--maternity-pink);
        background: #fdf2f8;
    }

    .queue-item-label {
        font-size: 0.9rem;
        color: #495057;
    }

    .queue-count {
        font-size: 1.25rem;
        font-weight: 700;
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
    }

    .queue-count.anc {
        background: #fce4ec;
        color: #c2185b;
    }

    .queue-count.edd {
        background: #fff3e0;
        color: #e65100;
    }

    .queue-count.postnatal {
        background: #e3f2fd;
        color: #1565c0;
    }

    .queue-count.overdue {
        background: #ffebee;
        color: #c62828;
    }

    .queue-count.high-risk {
        background: #fbe9e7;
        color: #bf360c;
    }

    .queue-count.due-visit {
        background: #fff8e1;
        color: #f57f17;
    }

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

    .btn-queue-all:hover {
        opacity: 0.9;
        transform: translateY(-2px);
    }

    /* ═══ SHARED: Quick Actions ═══ */
    .quick-actions {
        padding: 1rem;
        flex: 1;
    }

    .quick-actions h6 {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: 1rem;
    }

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

    .quick-action-btn:hover:not(:disabled) {
        border-color: var(--maternity-pink);
        background: #fdf2f8;
    }

    .quick-action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f5f5f5;
    }

    .quick-action-btn i {
        font-size: 1.25rem;
    }

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

    .patient-header.active {
        display: block;
    }

    .patient-header-top {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0;
    }

    .patient-name {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .patient-meta {
        display: flex;
        gap: 1.5rem;
        font-size: 0.95rem;
        opacity: 0.95;
        flex-wrap: wrap;
    }

    .patient-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-expand-patient {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
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

    .btn-expand-patient:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    .btn-expand-patient.expanded i {
        transform: rotate(180deg);
    }

    .patient-details-expanded {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        margin-top: 0;
    }

    .patient-details-expanded.show {
        max-height: 1000px;
        margin-top: 1rem;
        padding-top: 1rem;
    }

    .patient-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        padding: 0.5rem 0;
    }

    .patient-detail-item {
        background: rgba(255, 255, 255, 0.15);
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .patient-detail-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.8;
        margin-bottom: 0.25rem;
        font-weight: 600;
    }

    .patient-detail-value {
        font-size: 0.95rem;
        font-weight: 500;
        word-break: break-word;
    }

    .patient-detail-item.full-width {
        grid-column: 1 / -1;
    }

    .patient-detail-value.text-content {
        max-height: 100px;
        overflow-y: auto;
        line-height: 1.5;
        font-size: 0.9rem;
    }

    .allergies-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .allergy-tag {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid rgba(220, 53, 69, 0.5);
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* ═══ Context Switch Buttons ═══ */
    .context-switch-container {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .context-switch-btn {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: white;
        padding: 4px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        line-height: 1.25;
        transition: all 0.2s ease;
        text-align: left;
        min-width: 140px;
    }

    .context-switch-btn:hover {
        background: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.4);
        transform: translateY(-2px);
        color: white;
        text-decoration: none;
    }

    .context-switch-btn .linked-label {
        font-size: 0.62rem;
        text-transform: uppercase;
        opacity: 0.8;
        font-weight: 800;
        letter-spacing: 0.4px;
        margin-bottom: 1px;
    }

    .context-switch-btn .linked-name {
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 180px;
    }

    .context-switch-btn .linked-file {
        font-size: 0.7rem;
        opacity: 0.9;
    }

    .header-action-group {
        display: flex;
        align-items: center;
        gap: 8px;
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

    .empty-state i {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        font-size: 1rem;
        margin-bottom: 1.5rem;
    }

    /* ═══ SHARED: Queue View ═══ */
    .queue-view {
        flex: 1;
        display: none;
        flex-direction: column;
        overflow: hidden;
    }

    .queue-view.active {
        display: flex;
    }

    .queue-view-header {
        padding: 1rem 1.5rem;
        background: white;
        border-bottom: 2px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .queue-view-header h4 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

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

    .btn-close-queue:hover {
        background: #5a6268;
    }

    .queue-view-content {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        background: #f8f9fa;
    }

    /* ═══ SHARED: Queue Cards ═══ */
    .queue-card {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
        cursor: pointer;
    }

    .queue-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }

    .queue-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
    }

    .queue-card-patient-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .queue-card-patient-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        font-size: 0.875rem;
        color: #6c757d;
    }

    .queue-card-patient-meta-item {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* ═══ SHARED: Workspace Content ═══ */
    .workspace-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        display: none;
        min-height: 0;
    }

    .workspace-content.active {
        display: flex;
    }

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

    .workspace-tabs::-webkit-scrollbar {
        height: 4px;
    }

    .workspace-tabs::-webkit-scrollbar-thumb {
        background: #dee2e6;
        border-radius: 4px;
    }

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

    .workspace-tab:hover {
        color: var(--maternity-pink);
        background: rgba(var(--maternity-pink-rgb), 0.05);
    }

    .workspace-tab.active {
        color: var(--maternity-pink);
        border-bottom-color: var(--maternity-pink);
        background: white;
    }

    .workspace-tab-badge {
        background: var(--danger);
        color: white;
        padding: 0.125rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .workspace-tab-content {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        padding-bottom: 4rem;
        display: none;
        min-height: 0;
    }

    .workspace-tab-content.active {
        display: block;
    }

    /* ═══ SHARED: Panel Header ═══ */
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: var(--maternity-pink);
        color: white;
    }

    .panel-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    .btn-view-work-pane {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
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

    .workspace-navbar-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-back-to-search,
    .btn-toggle-search,
    .btn-clinical-context {
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
    .ck-editor__editable_inline {
        min-height: 200px;
        max-height: 400px;
    }

    .ck-editor__editable_inline:focus {
        border-color: var(--maternity-pink) !important;
        box-shadow: 0 0 0 0.15rem rgba(233, 30, 99, 0.15) !important;
    }

    .ck.ck-toolbar {
        border-radius: 0.5rem 0.5rem 0 0 !important;
    }

    .ck.ck-editor__main>.ck-editor__editable {
        border-radius: 0 0 0.5rem 0.5rem !important;
    }

    /* ═══ MATERNITY: Form Section Grouping ═══ */
    .mat-form-section {
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #fafbfc;
    }

    .mat-form-section-title {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--maternity-pink);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        border-bottom: 1px solid #e9ecef;
        padding-bottom: 0.5rem;
    }

    .mat-form-help {
        font-size: 0.78rem;
        color: #6c757d;
        margin-top: 0.2rem;
    }

    .mat-form-help i {
        color: var(--maternity-pink);
        margin-right: 0.2rem;
    }

    .mat-tooltip-icon {
        color: var(--maternity-pink);
        cursor: help;
        margin-left: 0.3rem;
        font-size: 0.8rem;
    }

    .mat-info-banner {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        border: 1px solid #bbdefb;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        font-size: 0.82rem;
        color: #37474f;
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .mat-info-banner i {
        color: var(--maternity-pink);
        font-size: 1.1rem;
        margin-top: 0.1rem;
    }

    /* ═══ MATERNITY-SPECIFIC: Card Styles ═══ */
    .card-modern {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e9ecef;
        overflow: hidden;
    }

    .card-modern .card-header {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        border-bottom: 1px solid #e9ecef;
    }

    .card-modern .card-body {
        padding: 1rem;
    }

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

    .enrollment-badge.active {
        background: #d4edda;
        color: #155724;
    }

    .enrollment-badge.postnatal {
        background: #fff3cd;
        color: #856404;
    }

    .enrollment-badge.completed {
        background: #e2e3e5;
        color: #383d41;
    }

    .enrollment-badge.transferred {
        background: #d1ecf1;
        color: #0c5460;
    }

    .enrollment-badge.deceased {
        background: #f8d7da;
        color: #721c24;
    }

    .enrollment-badge.high-risk {
        background: #f8d7da;
        color: #721c24;
    }

    /* Risk Level Indicator */
    .risk-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.6rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .risk-indicator.low {
        background: #d4edda;
        color: #155724;
    }

    .risk-indicator.moderate {
        background: #fff3cd;
        color: #856404;
    }

    .risk-indicator.high {
        background: #f8d7da;
        color: #721c24;
    }

    /* Gestational Age Pill */
    .ga-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.3rem 0.75rem;
        background: rgba(233, 30, 138, 0.1);
        color: var(--maternity-pink);
        border-radius: 1rem;
        font-weight: 700;
        font-size: 0.85rem;
    }

    /* ANC Visit Card */
    .anc-visit-card {
        background: white;
        border-radius: 0.75rem;
        border-left: 4px solid var(--maternity-pink);
        padding: 1rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    }

    .anc-visit-card .visit-number {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--maternity-pink);
        text-transform: uppercase;
    }

    .anc-visit-card .visit-date {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .anc-visit-card .visit-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .anc-visit-card .visit-detail-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6c757d;
    }

    .anc-visit-card .visit-detail-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: #2c3e50;
    }

    /* Timeline */
    .timeline-container {
        position: relative;
        padding-left: 30px;
    }

    .timeline-container::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -24px;
        top: 4px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: var(--maternity-pink);
        border: 2px solid white;
        box-shadow: 0 0 0 2px #dee2e6;
    }

    .timeline-item.booking::before {
        background: var(--info);
    }

    .timeline-item.anc_visit::before {
        background: var(--maternity-pink);
    }

    .timeline-item.delivery::before {
        background: var(--success);
    }

    .timeline-item.postnatal::before {
        background: var(--warning);
    }

    .timeline-date {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .timeline-title {
        font-weight: 600;
        color: #2c3e50;
    }

    .timeline-detail {
        font-size: 0.85rem;
        color: #6c757d;
    }

    /* Partograph Grid */
    .partograph-chart {
        overflow-x: auto;
    }

    .partograph-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }

    .partograph-table th,
    .partograph-table td {
        padding: 0.5rem;
        border: 1px solid #dee2e6;
        text-align: center;
    }

    .partograph-table th {
        background: #f8f9fa;
        font-weight: 600;
        white-space: nowrap;
    }

    /* Baby Card */
    .baby-card {
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e9ecef;
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    }

    .baby-card .baby-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .baby-card .baby-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2c3e50;
    }

    .baby-card .baby-sex {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.6rem;
        border-radius: 1rem;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .baby-card .baby-sex.male {
        background: #e3f2fd;
        color: #1565c0;
    }

    .baby-card .baby-sex.female {
        background: #fce4ec;
        color: #c2185b;
    }

    .baby-card .baby-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.75rem;
    }

    .baby-card .baby-metric-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6c757d;
    }

    .baby-card .baby-metric-value {
        font-size: 0.95rem;
        font-weight: 600;
    }

    /* Immunization Schedule */
    .imm-schedule-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-bottom: 1px solid #f1f3f5;
    }

    .imm-schedule-item:last-child {
        border-bottom: none;
    }

    .imm-status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .imm-status-dot.given {
        background: #28a745;
    }

    .imm-status-dot.overdue {
        background: #dc3545;
    }

    .imm-status-dot.upcoming {
        background: #dee2e6;
    }

    .imm-vaccine-name {
        font-weight: 600;
        color: #2c3e50;
        flex: 1;
    }

    .imm-age-label {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .imm-due-date {
        font-size: 0.8rem;
        color: #6c757d;
    }

    /* ═══ SHARED: Responsive (identical to nursing workbench) ═══ */
    @media (max-width: 767px) {
        .nursing-workbench-container {
            flex-direction: column;
        }

        .left-panel {
            width: 100%;
            min-width: 100%;
            border-right: none;
            border-bottom: 2px solid #e9ecef;
        }

        .main-workspace {
            display: none;
        }

        .main-workspace.active {
            display: flex;
        }

        .left-panel.hidden {
            display: none;
        }

        .workspace-navbar {
            display: flex;
        }

        .btn-view-work-pane {
            display: flex;
        }

        .panel-header {
            display: flex;
        }
    }

    @media (min-width: 768px) {
        .left-panel {
            display: flex !important;
        }

        .left-panel.hidden {
            display: flex !important;
            width: 0;
            min-width: 0;
            overflow: hidden;
            border: none;
            padding: 0;
        }

        .main-workspace {
            display: flex !important;
        }

        .workspace-navbar {
            display: flex !important;
        }

        .btn-back-to-search {
            display: none !important;
        }

        .btn-toggle-search {
            display: flex !important;
        }
    }

    /* Form sections */
    .form-section {
        padding: 1.25rem;
        border-bottom: 1px solid #e9ecef;
    }

    .form-section-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--maternity-pink);
        text-transform: uppercase;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-section-title i {
        font-size: 1.1rem;
    }

    /* Stat cards */
    .mat-stat-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border-left: 4px solid transparent;
    }

    .mat-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }

    .mat-stat-value {
        font-size: 1.15rem;
        font-weight: 700;
        color: #2d3748;
    }

    .mat-stat-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #718096;
    }

    .mat-stat-pink {
        border-left-color: var(--maternity-pink);
    }

    .mat-stat-pink .mat-stat-icon {
        background: linear-gradient(135deg, #e91e8a, #ad1457);
    }

    .mat-stat-green {
        border-left-color: #28a745;
    }

    .mat-stat-green .mat-stat-icon {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    .mat-stat-blue {
        border-left-color: #17a2b8;
    }

    .mat-stat-blue .mat-stat-icon {
        background: linear-gradient(135deg, #17a2b8, #007bff);
    }

    .mat-stat-orange {
        border-left-color: #fd7e14;
    }

    .mat-stat-orange .mat-stat-icon {
        background: linear-gradient(135deg, #fd7e14, #e65100);
    }

    .mat-stat-red {
        border-left-color: #dc3545;
    }

    .mat-stat-red .mat-stat-icon {
        background: linear-gradient(135deg, #dc3545, #b71c1c);
    }

    /* Timeline icons */
    .timeline-item {
        position: relative;
        padding-left: 28px;
    }

    .timeline-item .timeline-icon {
        position: absolute;
        left: 0;
        top: 2px;
        font-size: 1.1rem;
    }

    .timeline-item[style*="cursor:pointer"]:hover {
        background: rgba(0, 0, 0, 0.02);
        border-radius: 4px;
    }

    /* Notes */
    .note-card {
        background: white;
        border-radius: 0.5rem;
        border-left: 3px solid var(--maternity-pink);
        padding: 0.75rem 1rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .note-time {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .note-author {
        font-weight: 600;
        font-size: 0.85rem;
        color: #2c3e50;
    }

    .note-body {
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

</style>

<!-- ═══════════════════════════════════════════════════════════════
     HTML STRUCTURE (shared layout with nursing workbench)
     ═══════════════════════════════════════════════════════════════ -->

{{-- Store Context Banner — outside flex container so it spans full width --}}
@if($resolvedStore)
<div class="px-3 pt-2 pb-0">
    <div class="alert alert-info py-1 px-3 d-flex align-items-center gap-2 mb-1" id="store-context-badge">
        <i class="fas fa-store-alt me-1"></i>
        <strong>Active Store:</strong>
        <span class="ms-1">{{ $resolvedStore->store_name }}</span>
        <span class="badge bg-secondary ms-1 text-uppercase" style="font-size:0.7rem;">
            {{ $resolvedStore->distributionRoleLabel() }}
        </span>
        @can('store-context.change-manual')
        <button class="btn btn-outline-secondary btn-sm ms-auto py-0 px-2" onclick="openStoreContextOverride()" title="Change active store context">
            <i class="fas fa-exchange-alt"></i> Change
        </button>
        @endcan
    </div>
</div>
@elseif($contextFallbackAction === 'block')
<div class="px-3 pt-2 pb-0">
    <div class="alert alert-danger py-2 px-3 d-flex align-items-center gap-2 mb-1" id="store-context-banner">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <strong>No maternity store resolved.</strong>
        <span class="ms-1">Ward stock actions are blocked. Start a shift or contact admin.</span>
    </div>
</div>
@elseif($contextFallbackAction === 'allow_manual')
<div class="px-3 pt-2 pb-0">
    <div class="alert alert-warning py-2 px-3 d-flex align-items-center gap-2 mb-1" id="store-context-banner">
        <i class="fas fa-question-circle me-1"></i>
        <strong>No store auto-resolved.</strong>
        <span class="ms-1">Select a store manually for ward stock actions.</span>
    </div>
</div>
@endif

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
            <div class="queue-item" data-filter="bed-requests" style="border-left: 3px solid #17a2b8;">
                <span class="queue-item-label"><i class="mdi mdi-bed"></i> Bed Requests</span>
                <span class="queue-count" id="queue-bed-requests-count">0</span>
            </div>
            <div class="queue-item" data-filter="discharge-requests" style="border-left: 3px solid #ffc107;">
                <span class="queue-item-label"><i class="mdi mdi-exit-to-app"></i> Discharge Requests</span>
                <span class="queue-count" id="queue-discharge-requests-count">0</span>
            </div>
            <div class="queue-item" data-filter="admitted-patients" style="border-left: 3px solid #6f42c1;">
                <span class="queue-item-label"><i class="mdi mdi-hospital-building"></i> Admitted Patients</span>
                <span class="queue-count" id="queue-admitted-patients-count">0</span>
            </div>
            <button class="btn-queue-all" id="refresh-queues-btn">
                <i class="mdi mdi-refresh"></i> Refresh Queues
            </button>
        </div>

        <div class="quick-actions">
            <h6><i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-enroll-patient">
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
            <button class="quick-action-btn" id="btn-discharge-patient" disabled title="Discharge maternity enrollment" style="display:none;">
                <i class="mdi mdi-exit-run text-danger"></i>
                <span>Discharge</span>
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
            <div class="queue-view-content" id="reports-content" style="padding: 1.5rem; overflow-y: auto;">
                
                <!-- Quick Date Presets & Filter -->
                <div class="date-presets-bar mb-3 d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted me-2">Global Date Filter:</span>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn active" data-preset="all">All Time</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="today">Today</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="week">This Week</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="month">This Month</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="year">This Year</button>
                    
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <input type="date" class="form-control form-control-sm w-auto" id="mat-report-date-from" title="Date From">
                        <span class="text-muted">-</span>
                        <input type="date" class="form-control form-control-sm w-auto" id="mat-report-date-to" title="Date To">
                        <button class="btn btn-sm btn-primary" id="btn-apply-mat-dates">Apply</button>
                    </div>
                </div>

                <!-- Report Tabs -->
                <ul class="nav nav-tabs nav-fill mb-3" id="maternity-report-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="mat-overview-tab" data-bs-toggle="tab" data-bs-target="#mat-overview-content" type="button" role="tab">
                            <i class="mdi mdi-view-dashboard"></i> Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mat-clinical-tab" data-bs-toggle="tab" data-bs-target="#mat-clinical-content" type="button" role="tab">
                            <i class="mdi mdi-stethoscope"></i> Clinical Quality
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mat-ward-tab" data-bs-toggle="tab" data-bs-target="#mat-ward-content" type="button" role="tab">
                            <i class="mdi mdi-hospital-building"></i> Ward & Admissions
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="maternity-report-tab-content">
                    
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="mat-overview-content" role="tabpanel">
                        <div class="row" id="reports-summary-cards">
                            <div class="col-12 text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-24px"></i> Loading Overview...</div>
                        </div>
                    </div>

                    <!-- Clinical Quality Tab -->
                    <div class="tab-pane fade" id="mat-clinical-content" role="tabpanel">
                        <div class="row" id="reports-clinical-cards">
                            <div class="col-12 text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-24px"></i> Loading Clinical Data...</div>
                        </div>
                    </div>

                    <!-- Ward & Admissions Tab -->
                    <div class="tab-pane fade" id="mat-ward-content" role="tabpanel">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card-modern shadow-sm border-info h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted text-uppercase mb-2">Total Admissions</h6>
                                        <h2 class="text-info fw-bold" id="admissions-total-kpi">0</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern shadow-sm border-primary h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted text-uppercase mb-2">Average Length of Stay (ALOS)</h6>
                                        <h2 class="text-primary fw-bold" id="admissions-alos-kpi">0 Days</h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mx-auto">
                                <div class="card-modern shadow-sm">
                                    <div class="card-header bg-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-chart-pie"></i> Admissions by Class (Entry Point)</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <canvas id="admissions-class-chart" width="400" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Patient Header (SHARED PATTERN) -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div style="flex: 1; min-width: 200px;">
                    <div class="patient-name" id="patient-name"></div>
                    <div class="patient-meta" id="patient-meta"></div>
                </div>

                {{-- Context Switching Buttons --}}
                <div id="context-switch-container" class="context-switch-container mx-3"></div>

                {{-- Header Actions --}}
                <div class="header-action-group">
                    <button class="btn btn-sm btn-info" id="btn-print-anc-card" title="Print ANC Card" style="display:none;">
                        <i class="mdi mdi-card-account-details"></i> ANC Card
                    </button>
                    <button class="btn btn-sm btn-success" id="btn-print-road-card" title="Print Road to Health Card" style="display:none;">
                        <i class="mdi mdi-baby-face-outline"></i> Road to Health
                    </button>
                    
                    <div id="admission-status-container" class="d-none align-items-center px-2 py-1 bg-light rounded border me-2" style="gap: 5px;">
                        <i class="fa fa-bed" style="color: var(--maternity-pink, #e91e63); font-size: 0.75rem;"></i>
                        <div class="d-flex flex-column lh-1 text-start" id="admission-status-text"></div>
                    </div>
                    <button class="btn btn-sm btn-primary" id="btn-admit-to-ward" title="Admit Patient" style="display:none;">
                        <i class="mdi mdi-bed"></i> Admit
                    </button>
                    <button class="btn btn-sm btn-warning" id="btn-discharge-from-ward" title="Request Ward Discharge" style="display:none;">
                        <i class="mdi mdi-exit-to-app"></i> Ward Discharge
                    </button>
                    <button class="btn btn-sm btn-danger" id="btn-discharge-patient" title="Discharge maternity enrollment" style="display:none;">
                        <i class="mdi mdi-exit-to-app"></i> Discharge
                    </button>
                    <button class="btn-expand-patient" id="btn-expand-patient" title="Show more details">
                        <span class="btn-expand-text">more biodata</span>
                        <i class="mdi mdi-chevron-down"></i>
                    </button>
                </div>
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
                <button class="workspace-tab" data-tab="clinical-story">
                    <i class="fa fa-history"></i>
                    <span>Clinical Story</span>
                </button>
                <button class="workspace-tab" data-tab="notes">
                    <i class="mdi mdi-note-text"></i>
                    <span>Notes</span>
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
                <button class="workspace-tab" data-tab="partograph">
                    <i class="mdi mdi-chart-timeline-variant"></i>
                    <span>Partograph</span>
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
                <button class="workspace-tab" data-tab="audit">
                    <i class="mdi mdi-shield-search"></i>
                    <span>Audit Trail</span>
                </button>
                <button class="workspace-tab" data-tab="billing">
                    <i class="mdi mdi-receipt"></i>
                    <span>Billing</span>
                </button>
            </div>

            <!-- ═══ OVERVIEW TAB ═══ -->
            <div class="workspace-tab-content active" id="overview-tab">
                <div class="p-3" id="overview-content">
                    <p class="text-muted text-center py-3">Select a patient to view overview</p>
                </div>
            </div>

            <!-- Clinical Story Tab -->
            <div class="workspace-tab-content" id="clinical-story-tab">
                <div class="clinical-story-container p-3">
                    @include('admin.partials.clinical_story')
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

            <!-- ═══ PARTOGRAPH TAB ═══ -->
            <div class="workspace-tab-content" id="partograph-tab">
                <div class="p-3">
                    <div id="partograph-tab-content">
                        <p class="text-muted text-center py-3">Select a patient to view partograph</p>
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
                @include('admin.partials.shared_workbench_nurse_notes', [
                    'prefix' => 'maternity',
                    'formId' => 'maternity-note-form',
                    'editorId' => 'maternity-note-editor',
                    'statusId' => 'maternity-note-autosave-status',
                    'refreshCallback' => 'loadNotesTab()'
                ])
            </div>

            <!-- ═══ AUDIT TAB ═══ -->
            <div class="workspace-tab-content" id="audit-tab">
                <div class="p-3">
                    <div id="audit-content">
                        <p class="text-muted text-center py-3">Enroll/select patient to view audit trail</p>
                    </div>
                </div>
            </div>

            <!-- ═══ BILLING TAB ═══ -->
            <div class="workspace-tab-content" id="billing-tab">
                <div id="billing-kit-root">
                    <p class="text-muted text-center py-5"><i class="mdi mdi-account-arrow-left mdi-36px"></i><br>Select a patient to view billing</p>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.administer_vaccine_modal')

{{-- Dose-mode toggle rendered as hidden HTML; moved into dynamic container via JS --}}
<div id="mco-dose-mode-toggle-source" style="display:none;">
    @include('admin.partials.dose-mode-toggle', ['prefix' => 'mco_'])
</div>

{{-- MATERNITY FORM MODALS --}}
@include('admin.maternity.partials._modals')

@endsection
@include('admin.maternity.partials._scripts')
