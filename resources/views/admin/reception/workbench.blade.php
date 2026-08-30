@extends('admin.layouts.app')

@section('title', 'Reception Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/fullcalendar/fullcalendar.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/queue-status.css') }}">
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
    }

    /* Main Layout */

    /* Appointment Context Menu */
    .appt-context-menu {
        position: fixed;
        z-index: 10000;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        min-width: 220px;
        padding: 6px 0;
    }
    .appt-context-menu .context-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.9rem;
        transition: background 0.15s;
    }
    .appt-context-menu .context-item:hover { background: #f8f9fa; }
    .appt-context-menu .context-item i { font-size: 1.1rem; width: 20px; text-align: center; }

    /* Appointment Legend */
    .appt-legend { font-size: 0.8rem; color: #555; }
    .appt-legend span { display: inline-flex; align-items: center; gap: 4px; margin-right: 8px; }
    .legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }

    /* FullCalendar overrides for appointment view */
    #appointments-fullcalendar .fc-event {
        border-radius: 4px;
        padding: 2px 4px;
        font-size: 0.78rem;
        cursor: pointer;
    }
    #appointments-fullcalendar .fc-event:hover { opacity: 0.9; }
    #appointments-fullcalendar .fc-toolbar { margin-bottom: 12px; }
    #appointments-fullcalendar .fc-toolbar h2 { font-size: 1.1rem; }

    .btn-outline-purple { color: #6f42c1; border-color: #6f42c1; }
    .btn-outline-purple:hover, .btn-outline-purple.active { background: #6f42c1; color: #fff; border-color: #6f42c1; }
    .bg-purple { background-color: #6f42c1 !important; }
    .reception-workbench-container {
        display: flex;
        min-height: calc(100vh - 100px);
        gap: 0;
    }

    /* Left Panel - Patient Search */
    .left-panel {
        width: 20%;
        min-width: 250px;
        border-right: 2px solid #e9ecef;
        display: flex;
        flex-direction: column;
        background: #f8f9fa;
    }

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
        border-color: var(--hospital-primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
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
        background: var(--danger);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Queue Widget */
    .queue-widget {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    /* Emergency pulse animation */
    @keyframes emergencyPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        50% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
    }
    .emergency-pulse {
        animation: emergencyPulse 1.5s infinite;
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

    .queue-count.all-unpaid {
        background: #cce5ff;
        color: #004085;
    }

    .queue-count.hmo-items {
        background: #d4edda;
        color: #155724;
    }

    .queue-count.credit-accounts {
        background: #ffe5d4;
        color: #c65400;
    }

    .btn-queue-all {
        width: 100%;
        margin-top: 0.5rem;
        background: var(--hospital-primary);
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

    /* Quick Actions */
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

    .quick-action-btn:hover {
        border-color: var(--hospital-primary);
        background: #f8f9fa;
    }

    .quick-action-btn i {
        font-size: 1.25rem;
    }

    /* Main Workspace */
    .main-workspace {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: white;
    }

    .patient-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, var(--hospital-primary), var(--hospital-primary));
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
        margin-bottom: 1rem;
    }

    .patient-account-balance {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, var(--hospital-primary), var(--hospital-secondary));
        border-radius: 0.5rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .patient-account-balance .balance-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .patient-account-balance .balance-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
    }

    .account-balance-info {
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        border-left: 4px solid var(--hospital-primary);
    }

    .account-balance-info .balance-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1rem;
    }

    .account-balance-info .balance-amount {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--hospital-primary);
    }

    #account-payment-note {
        display: block;
        margin-top: 0.5rem;
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
        justify-content: center;
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

    .patient-detail-value.text-content::-webkit-scrollbar {
        width: 6px;
    }

    .patient-detail-value.text-content::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }

    .patient-detail-value.text-content::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }

    .patient-detail-value.text-content::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
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

    .allergy-tag i {
        font-size: 0.75rem;
    }

    /* Empty State */
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

    /* Queue View */
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
        background: var(--hospital-primary);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .queue-view-header h4 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .btn-close-queue {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-close-queue:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .queue-view-content {
        flex: 1;
        overflow: auto;
        padding: 1rem;
        background: #f8f9fa;
    }

    /* Queue Card Styling */
    .queue-card {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }

    .queue-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }

    .queue-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e9ecef;
    }

    .queue-card-patient {
        flex: 1;
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

    .queue-card-service {
        padding: 0.375rem 0.75rem;
        background: var(--hospital-primary);
        color: white;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .queue-card-body {
        margin-bottom: 1rem;
    }

    .queue-card-status-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .queue-card-status-item {
        padding: 0.75rem;
        border-radius: 0.5rem;
        background: #f8f9fa;
    }

    .queue-card-status-item.completed {
        background: #d4edda;
        border-left: 4px solid #28a745;
    }

    .queue-card-status-item.pending {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
    }

    .queue-card-status-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .queue-card-status-value {
        font-size: 0.875rem;
        color: #2c3e50;
    }

    .queue-card-note {
        padding: 0.75rem;
        background: #e7f3ff;
        border-left: 4px solid #007bff;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }

    .queue-card-note-label {
        font-weight: 700;
        color: #007bff;
        margin-bottom: 0.25rem;
    }

    .queue-card-attachments {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .queue-card-attachment {
        padding: 0.375rem 0.75rem;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        text-decoration: none;
        color: #495057;
        transition: all 0.2s;
    }

    .queue-card-attachment:hover {
        background: #e9ecef;
        border-color: #adb5bd;
    }

    /* Queue View */
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

    .queue-card-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .queue-card-actions .btn {
        flex: 1;
        min-width: 150px;
    }

    /* Workspace Content */
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
        color: var(--hospital-primary);
        background: rgba(0, 123, 255, 0.05);
    }

    .workspace-tab.active {
        color: var(--hospital-primary);
        border-bottom-color: var(--hospital-primary);
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

    /* Pending Sub-Tabs */
    .pending-subtabs {
        display: flex;
        background: white;
        border-bottom: 1px solid #dee2e6;
        padding: 0.5rem 1rem;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .pending-subtab {
        padding: 0.5rem 1rem;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        color: #495057;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .pending-subtab:hover {
        background: #e9ecef;
        border-color: #adb5bd;
    }

    .pending-subtab.active {
        background: var(--hospital-primary);
        color: white;
        border-color: var(--hospital-primary);
    }

    .subtab-badge {
        background: rgba(0, 0, 0, 0.2);
        color: white;
        padding: 0.125rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.7rem;
        font-weight: 700;
        min-width: 1.5rem;
        text-align: center;
    }

    .pending-subtab:not(.active) .subtab-badge {
        background: #6c757d;
        color: white;
    }

    .pending-subtab-content {
        padding: 1.5rem;
        overflow-y: auto;
    }

    /* Pending Requests Display */
    .request-section {
        margin-bottom: 2rem;
    }

    .request-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        border-left: 4px solid var(--hospital-primary);
    }

    .request-section-header h5 {
        margin: 0;
        font-weight: 700;
        color: #212529;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .request-section-actions {
        display: flex;
        gap: 0.5rem;
    }

    .request-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: all 0.2s;
        display: flex;
        align-items: start;
        gap: 1rem;
    }

    .request-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-color: var(--hospital-primary);
    }

    .request-card-checkbox {
        flex-shrink: 0;
        margin-top: 0.25rem;
    }

    .request-card-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .request-card-content {
        flex: 1;
        min-width: 0;
    }

    .request-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
        gap: 1rem;
    }

    .request-service-name {
        font-weight: 700;
        font-size: 1rem;
        color: #212529;
        margin-bottom: 0.25rem;
    }

    .request-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        font-size: 0.9rem;
        color: #6c757d;
    }

    .request-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .request-meta-item i {
        color: var(--hospital-primary);
    }

    .request-note {
        margin-top: 0.75rem;
        padding: 0.75rem;
        background: #f8f9fa;
        border-left: 3px solid #dee2e6;
        border-radius: 0.25rem;
        font-size: 0.9rem;
        color: #495057;
    }

    .request-status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .request-status-badge.status-billing {
        background: #fff3cd;
        color: #856404;
    }

    .request-status-badge.status-sample {
        background: #ffeaa7;
        color: #d63031;
    }

    .request-status-badge.status-results {
        background: #ffeaa7;
        color: #d63031;
    }

    .section-actions-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        margin-top: 1rem;
    }

    .select-all-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }

    .select-all-container input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-action {
        padding: 0.5rem 1.25rem;
        border: none;
        border-radius: 0.375rem;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-action:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-action-billing {
        background: #28a745;
        color: white;
    }

    .btn-action-billing:hover:not(:disabled) {
        background: #218838;
    }

    .btn-action-sample {
        background: #17a2b8;
        color: white;
    }

    .btn-action-sample:hover:not(:disabled) {
        background: #138496;
    }

    .btn-action-result {
        background: #007bff;
        color: white;
    }

    .btn-action-result:hover:not(:disabled) {
        background: #0056b3;
    }

    .btn-action-dismiss {
        background: #dc3545;
        color: white;
    }

    .btn-action-dismiss:hover:not(:disabled) {
        background: #c82333;
    }

    .medications-list {
        margin-bottom: 1rem;
    }

    .filter-btn {
        padding: 0.5rem 1rem;
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.2s;
    }

    .filter-btn:hover,
    .filter-btn.active {
        border-color: var(--hospital-primary);
        background: var(--hospital-primary);
        color: white;
    }

    .medication-entry {
        padding: 1rem;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .medication-entry.active {
        border-left-color: var(--success);
    }

    .medication-entry.stopped {
        border-left-color: var(--danger);
        opacity: 0.7;
    }

    .medication-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    .medication-name {
        font-weight: 700;
        color: #212529;
        font-size: 1rem;
    }

    .medication-status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .medication-status-badge.status-active {
        background: #d4edda;
        color: #155724;
    }

    .medication-status-badge.status-stopped {
        background: #f8d7da;
        color: #721c24;
    }

    .medication-status-badge.status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .medication-details {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .medication-detail-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        color: #495057;
    }

    .medication-detail-row i {
        color: #6c757d;
        font-size: 1rem;
        width: 20px;
        text-align: center;
    }

    .medication-detail-row strong {
        margin-right: 0.25rem;
    }

    .medication-status {
        padding: 0.125rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .medication-status.active {
        background: #d4edda;
        color: #155724;
    }

    .medication-status.stopped {
        background: #f8d7da;
        color: #721c24;
    }

    .medication-dosage {
        font-size: 0.9rem;
        color: #495057;
        margin-bottom: 0.25rem;
    }

    .medication-meta {
        font-size: 0.85rem;
        color: #6c757d;
    }

    /* Show All Link */
    .show-all-link {
        display: block;
        text-align: center;
        padding: 0.75rem;
        background: white;
        border: 2px dashed #dee2e6;
        border-radius: 0.5rem;
        color: var(--hospital-primary);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.2s;
        margin-top: 1rem;
    }

    .show-all-link:hover {
        border-color: var(--hospital-primary);
        background: rgba(0, 123, 255, 0.05);
    }

    .medications-header {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .medication-filters-container {
        order: 1;
    }

    .allergy-banner {
        order: 0;
    }

    .dataTables_filter {
        order: 2;
        margin: 0;
    }

    .dataTables_filter label {
        width: 100%;
        margin: 0;
    }

    .dataTables_filter input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        font-size: 0.85rem;
    }

    .dataTables_filter input:focus {
        outline: none;
        border-color: var(--hospital-primary);
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    /* Footer */
    .lab-footer {
        padding: 0.75rem 1.5rem;
        background: #f8f9fa;
        border-top: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .sync-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .sync-indicator.syncing {
        color: var(--info);
    }

    .sync-indicator i {
        animation: rotate 1s linear infinite;
    }

    @keyframes rotate {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    /* Loading States */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }

    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Tooltips for vitals */
    .vital-tooltip {
        position: absolute;
        background: #2d3748;
        color: white;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s;
        min-width: 200px;
    }

    .vital-tooltip.active {
        opacity: 1;
    }

    .vital-item {
        position: relative;
        cursor: help;
    }

    /* Enhanced vital status colors */
    .vital-critical {
        background: linear-gradient(135deg, #fee 0%, #fcc 100%);
        border-left: 4px solid var(--danger);
    }

    .vital-warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-left: 4px solid var(--warning);
    }

    .vital-normal {
        background: linear-gradient(135deg, #d4edda 0%, #c3f7cf 100%);
        border-left: 4px solid var(--success);
    }

    /* Allergy alert banner */
    .allergy-alert {
        background: linear-gradient(135deg, #ffe5e5 0%, #ffcccc 100%);
        border: 2px solid #ff4444;
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: pulse 2s ease-in-out infinite;
    }

    .allergy-alert-icon {
        font-size: 1.5rem;
        color: #ff4444;
    }

    @keyframes pulse {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(255, 68, 68, 0.4);
        }
        50% {
            box-shadow: 0 0 0 10px rgba(255, 68, 68, 0);
        }
    }

    /* Medication search box */
    .panel-search-box {
        padding: 0.5rem;
        border-bottom: 1px solid #dee2e6;
        background: #f8f9fa;
    }

    .panel-search-box input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        font-size: 0.9rem;
    }

    /* Enhanced medication status badges */
    .medication-status-badge.status-active {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        font-weight: 600;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
    }

    .medication-status-badge.status-stopped {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        font-weight: 600;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
    }

    .medication-status-badge.status-long-term {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        color: #212529;
        font-weight: 600;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
    }

    /* ============================================
       NEW REQUEST FORM STYLES
       ============================================ */

    .new-request-container {
        padding: 1.5rem;
    }

    .new-request-header {
        margin-bottom: 2rem;
    }

    .new-request-header h4 {
        color: var(--hospital-primary);
        margin-bottom: 0.5rem;
    }

    .new-request-form {
        background: white;
        padding: 2rem;
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
    }

    #service-search-results {
        border: 1px solid #dee2e6;
        border-top: none;
        background: white;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    #service-search-results .list-group-item {
        cursor: pointer;
        border-left: 3px solid transparent;
        transition: all 0.2s;
    }

    #service-search-results .list-group-item:hover {
        background: #f0f8ff;
        border-left-color: var(--hospital-primary);
    }

    .selected-service-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        margin-bottom: 0.5rem;
    }

    .selected-service-info {
        flex: 1;
    }

    .selected-service-name {
        font-weight: 600;
        color: #212529;
    }

    .selected-service-code {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .selected-service-price {
        font-weight: 600;
        color: var(--hospital-primary);
        margin-right: 1rem;
    }

    .btn-remove-service {
        color: var(--danger);
        border: none;
        background: none;
        cursor: pointer;
        padding: 0.25rem 0.5rem;
    }

    .btn-remove-service:hover {
        color: darkred;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #dee2e6;
    }

    /* ============================================
       REPORTS STYLES
       ============================================ */

    .reports-container {
        padding: 1.5rem;
    }

    .reports-header {
        margin-bottom: 2rem;
    }

    .reports-header h4 {
        color: var(--hospital-primary);
    }

    .reports-filter-panel {
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .reports-filter-panel .card-header {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .stat-card {
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .stat-card .card-body {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
    }

    .stat-content h3 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
        color: #212529;
    }

    .stat-content p {
        margin: 0;
        color: #6c757d;
        font-size: 0.9rem;
    }

    #reports-datatable th {
        font-weight: 600;
        background: #f8f9fa;
    }

    /* Reports Sub-tabs */
    #reports-tabs .nav-link {
        color: #6c757d;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
    }

    #reports-tabs .nav-link:hover {
        border-color: #dee2e6;
        color: #495057;
    }

    #reports-tabs .nav-link.active {
        color: {{ $hosColor }};
        border-bottom-color: {{ $hosColor }};
        background: transparent;
    }

    /* DataTable mobile responsiveness */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        max-width: 100%;
    }

    @media (max-width: 767px) {
        #reports-datatable {
            font-size: 0.875rem;
        }

        #reports-datatable th,
        #reports-datatable td {
            white-space: nowrap;
            padding: 0.5rem;
        }
    }

    /* ============================================
       SIMPLE RESPONSIVE LAYOUT - MOBILE FIRST
       ============================================ */

    /* Mobile: Show only one pane at a time */
    @media (max-width: 767px) {
        .left-panel {
            display: block;
            width: 100%;
        }

        .main-workspace {
            display: none;
            width: 100%;
            height: calc(100vh - 100px);
        }

        .main-workspace.active {
            display: flex;
        }

        .left-panel.hidden {
            display: none;
        }

        .workspace-navbar {
            display: flex !important;
        }

        .btn-back-to-search {
            display: flex !important;
        }

        .btn-toggle-search {
            display: none !important;
        }

        .btn-view-work-pane {
            display: flex !important;
            align-items: center;
        }

        .vital-entry-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .workspace-tab-content {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }

        #history-tab .dataTables_wrapper {
            overflow-x: auto;
        }

        #history-tab table.dataTable {
            font-size: 0.85rem;
        }
    }

    /* Tablet & Desktop: Show both panes side by side */
    @media (min-width: 768px) {
        .left-panel {
            display: flex;
            width: 300px;
            min-width: 300px;
        }

        .left-panel.hidden {
            display: none;
        }

        .main-workspace {
            display: block;
            flex: 1;
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

    /* Mobile Responsive: Queue cards */
    @media (max-width: 768px) {
        .queue-card-status-row {
            grid-template-columns: 1fr;
        }

        .queue-card-patient-meta {
            flex-direction: column;
            gap: 0.5rem;
        }

        .queue-card-header {
            flex-direction: column;
            gap: 1rem;
        }

        .queue-card-service {
            align-self: flex-start;
        }

        .queue-card-actions {
            flex-direction: column;
        }

        .queue-card-actions .btn {
            min-width: 100%;
        }

        .queue-view-header h4 {
            font-size: 1rem;
        }

        .btn-close-queue {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
    }

    /* Workspace Navbar */
    .workspace-navbar {
        display: none;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: white;
        border-bottom: 1px solid #dee2e6;
        gap: 1rem;
    }

    .workspace-navbar-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-back-to-search,
    .btn-toggle-search {
        display: none;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        background: white;
        color: #495057;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-back-to-search:hover,
    .btn-toggle-search:hover {
        background: #f8f9fa;
        border-color: #adb5bd;
    }

    .panel-header {
        padding: 1rem;
        background: var(--hospital-primary);
        color: white;
        border-bottom: 2px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .panel-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    .btn-view-work-pane {
        display: none;
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.5);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-view-work-pane:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.8);
    }

    .btn-view-work-pane i {
        margin-right: 0.25rem;
    }

    /* Medications Cards - Prevent fullscreen breakout */
    #medications-list-container {
        position: relative;
        max-width: 100%;
        overflow: hidden;
    }

    #medications-list-container .medication-card,
    #medications-list-container .card {
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

    #medications-list-container * {
        position: relative !important;
        max-width: 100% !important;
    }

    /* History Tab DataTable Fix */
    #history-tab {
        padding: 1rem;
        padding-bottom: 2rem;
        position: relative;
    }

    .history-table-wrapper {
        max-width: 100%;
        position: relative;
    }

    #history-tab .dataTables_wrapper {
        max-width: 100%;
        position: relative !important;
    }

    #history-tab .dataTables_wrapper .bottom {
        margin-top: 1rem;
        padding: 0.5rem;
    }

    #history-tab .dataTables_info,
    #history-tab .dataTables_paginate {
        margin-top: 0.5rem;
    }

    #history-tab table.dataTable {
        width: 100% !important;
        position: relative !important;
        transform: none !important;
    }

    #history-tab table.dataTable tbody td {
        position: relative !important;
        max-width: 100%;
        transform: none !important;
    }

    #history-tab table.dataTable tbody tr {
        position: relative !important;
        transform: none !important;
    }

    /* Disable DataTables responsive expansion */
    #history-tab table.dataTable.dtr-inline.collapsed> tbody> tr> td.dtr-control:before,
    #history-tab table.dataTable.dtr-inline.collapsed> tbody> tr> th.dtr-control:before {
        display: none !important;
    }

    #history-tab table.dataTable.dtr-inline.collapsed> tbody> tr.parent> td.dtr-control:before,
    #history-tab table.dataTable.dtr-inline.collapsed> tbody> tr.parent> th.dtr-control:before {
        display: none !important;
    }

    #history-tab table.dataTable> tbody> tr.child {
        display: none !important;
    }

    /* Prevent any child elements from going full screen */
    #history-tab * {
        max-width: 100% !important;
    }

    /* Fix for cards going full screen on mobile - Applies to all report cards */
    .reports-container .card,
    #top-services-card {
        position: relative !important;
        width: auto !important;
        height: auto !important;
        transform: none !important;
        z-index: auto !important;
        top: auto !important;
        left: auto !important;
        right: auto !important;
        bottom: auto !important;
    }

    /* Ensure cards inside DataTable don't break out */
    #history-tab .card,
    #history-tab .modal,
    #history-tab [style*="position: fixed"],
    #history-tab [style*="position: absolute"] {
        position: relative !important;
        width: auto !important;
        height: auto !important;
        top: auto !important;
        left: auto !important;
        right: auto !important;
        bottom: auto !important;
        transform: none !important;
        z-index: auto !important;
    }

    /* ========== BILLING WORKBENCH SPECIFIC STYLES ========== */

    /* Billing Tab */
    .billing-tab-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .billing-toolbar {
        display: flex;
        gap: 0.5rem;
    }

    .billing-items-container {
        padding: 1rem;
        overflow-x: auto;
    }

    #billing-items-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
    }

    .hmo-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
        border-radius: 0.25rem;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .item-qty-input,
    .item-discount-input {
        width: 100%;
        min-width: 70px;
        max-width: 80px;
        padding: 0.375rem 0.5rem;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
    }

    /* Payment Summary Card */
    .payment-summary-card {
        margin: 1.5rem;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 0.5rem;
        border: 2px solid var(--hospital-primary);
    }

    .payment-summary-card h5 {
        margin-bottom: 1rem;
        color: var(--hospital-primary);
        font-weight: 700;
    }

    .summary-details {
        margin-bottom: 1.5rem;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid #dee2e6;
        font-size: 1rem;
    }

    .summary-row.total {
        border-bottom: none;
        padding-top: 1rem;
        margin-top: 0.5rem;
        border-top: 2px solid #495057;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--hospital-primary);
    }

    .payment-method-section,
    .payment-reference-section {
        margin-bottom: 1rem;
    }

    .payment-method-section label,
    .payment-reference-section label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
    }

    /* Receipt Display */
    .receipt-display {
        margin: 1.5rem;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
    }

    .receipt-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #dee2e6;
    }

    .receipt-tab {
        padding: 0.75rem 1.5rem;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-weight: 600;
        color: #6c757d;
        transition: all 0.2s;
    }

    .receipt-tab:hover {
        color: var(--hospital-primary);
    }

    .receipt-tab.active {
        color: var(--hospital-primary);
        border-bottom-color: var(--hospital-primary);
    }

    .receipt-content {
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        max-height: 600px;
        overflow-y: auto;
    }

    .receipt-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
    }

    /* Receipts Tab */
    .receipts-tab-header,
    .transactions-tab-header,
    .account-tab-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .receipts-toolbar {
        display: flex;
        gap: 0.5rem;
    }

    .receipts-container,
    .transactions-container,
    .my-transactions-container {
        padding: 1rem;
        overflow-x: auto;
    }

    /* Transactions Tab */
    .transactions-filter-panel {
        padding: 1.5rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    /* =============================================
       WALK-IN SALES CART STYLES
       ============================================= */
    #walkin-cart-table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
        padding: 0.75rem 0.5rem;
    }

    #walkin-cart-table tbody td {
        vertical-align: middle;
        padding: 0.75rem 0.5rem;
        border-bottom: 1px solid #f0f0f0;
    }

    #walkin-cart-table tbody tr:hover {
        background: #f8f9fa;
    }

    #walkin-summary-table td {
        padding: 0.75rem 1rem;
    }

    .bg-success-light {
        background: rgba(40, 167, 69, 0.1) !important;
    }

    /* Walk-in Cart Tabs */
    #walkin-cart-tabs {
        border-bottom: none;
    }

    #walkin-cart-tabs .nav-link {
        border: none;
        border-radius: 0;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: #6c757d;
        background: #f8f9fa;
        border-bottom: 2px solid transparent;
    }

    #walkin-cart-tabs .nav-link:hover {
        color: var(--primary-color);
        background: #e9ecef;
    }

    #walkin-cart-tabs .nav-link.active {
        color: var(--primary-color);
        background: #fff;
        border-bottom-color: var(--primary-color);
    }

    /* Recent Request Item */
    .recent-request-item {
        padding: 0.75rem;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }

    .recent-request-item:hover {
        background: #f8f9fa;
    }

    .recent-request-item:last-child {
        border-bottom: none;
    }

    .recent-request-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }

    .recent-request-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: #333;
    }

    .recent-request-type {
        font-size: 0.7rem;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
    }

    .recent-request-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
    }

    .recent-request-pricing {
        display: flex;
        gap: 0.75rem;
    }

    .recent-request-pricing span {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .recent-request-pricing .price-label {
        font-size: 0.65rem;
        color: #999;
        text-transform: uppercase;
    }

    .recent-request-pricing .price-value {
        font-weight: 600;
        font-size: 0.8rem;
    }

    .recent-request-status {
        display: flex;
        gap: 0.5rem;
    }

    /* Service Requests Tab Styles */
    .stat-card-modern {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .stat-card-modern:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .stat-card-modern .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .stat-card-modern .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        line-height: 1.2;
    }

    .stat-card-modern .stat-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Delivery Status Badges */
    .delivery-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-weight: 600;
    }

    .delivery-badge.pending {
        background: #fef3cd;
        color: #856404;
    }

    .delivery-badge.in-progress {
        background: #cce5ff;
        color: #004085;
    }

    .delivery-badge.completed {
        background: #d4edda;
        color: #155724;
    }

    .delivery-badge.cancelled {
        background: #f8d7da;
        color: #721c24;
    }

    /* Billing Status Badges */
    .billing-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-weight: 600;
    }

    .billing-badge.pending {
        background: #fff3cd;
        color: #856404;
    }

    .billing-badge.billed {
        background: #cce5ff;
        color: #004085;
    }

    .billing-badge.paid {
        background: #d4edda;
        color: #155724;
    }

    /* =============================================
       REPORTS VIEW STYLES
       ============================================= */
    #reports-view .stat-card {
        display: flex;
        align-items: center;
        padding: 1rem;
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 1rem;
    }

    #reports-view .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
    }

    #reports-view .stat-icon i {
        font-size: 1.5rem;
        color: white;
    }

    #reports-view .stat-content h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0;
        color: #333;
    }

    #reports-view .stat-content p {
        font-size: 0.75rem;
        color: #6c757d;
        margin-bottom: 0;
    }

    #reports-view .reports-filter-panel {
        background: #f8f9fa;
    }

    #reports-view .nav-tabs .nav-link {
        color: #495057;
        border: none;
        padding: 0.75rem 1.25rem;
        font-weight: 500;
    }

    #reports-view .nav-tabs .nav-link.active {
        color: #007bff;
        background: transparent;
        border-bottom: 3px solid #007bff;
    }

    #reports-view .nav-tabs .nav-link i {
        margin-right: 0.5rem;
    }

    #reports-view .card {
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        border-radius: 0.75rem;
    }

    #reports-view .card-header {
        background: white;
        border-bottom: 1px solid #f0f0f0;
    }

    #top-clinics-table tbody tr:hover {
        background: #f8f9fa;
    }

    .transactions-summary {
        display: flex;
        gap: 1rem;
        padding: 1.5rem;
        background: white;
    }

    .stat-card {
        flex: 1;
        padding: 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.5rem;
        text-align: center;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    /* =============================================
       ACCOUNT TAB - MODERN UI
       ============================================= */

    /* Hero Balance Section */
    .account-hero-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
    }

    .account-hero-balance {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        padding: 2rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        margin: 1.5rem;
        border-radius: 1rem;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }

    .account-hero-balance.credit {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        box-shadow: 0 10px 40px rgba(40, 167, 69, 0.3);
    }

    .account-hero-balance.debit {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        box-shadow: 0 10px 40px rgba(220, 53, 69, 0.3);
    }

    .hero-balance-icon {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
    }

    .hero-balance-content {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .hero-balance-label {
        font-size: 0.9rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .hero-balance-amount {
        font-size: 3rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .hero-balance-status {
        font-size: 1rem;
        opacity: 0.9;
        margin-top: 0.25rem;
    }

    .hero-balance-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .hero-balance-actions .btn {
        white-space: nowrap;
    }

    /* Account Stats Grid */
    .account-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        padding: 0 1.5rem 1.5rem;
    }

    @media (max-width: 1200px) {
        .account-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .account-stats-grid {
            grid-template-columns: 1fr;
        }
        .account-hero-balance {
            flex-direction: column;
            text-align: center;
        }
        .hero-balance-actions {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    .account-stat-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e9ecef;
        transition: all 0.2s ease;
    }

    .account-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .account-stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .account-stat-card.deposits .stat-icon {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .account-stat-card.withdrawals .stat-icon {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .account-stat-card.pending .stat-icon {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }

    .account-stat-card.transactions .stat-icon {
        background: rgba(23, 162, 184, 0.1);
        color: #17a2b8;
    }

    .account-stat-card .stat-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .account-stat-card .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #212529;
    }

    .account-stat-card .stat-label {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.125rem;
    }

    /* No Account State */
    .account-no-account-state {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 400px;
        padding: 2rem;
    }

    .no-account-content {
        text-align: center;
        max-width: 400px;
    }

    .no-account-icon {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 4rem;
        color: #adb5bd;
    }

    .no-account-content h4 {
        margin-bottom: 0.75rem;
        color: #495057;
    }

    .no-account-content p {
        color: #6c757d;
        margin-bottom: 1.5rem;
    }

    /* Action Button Group */
    .hero-balance-actions .action-btn-group {
        display: flex;
        gap: 0.5rem;
    }

    /* Account Transaction Panel (Deposit/Withdraw/Adjust) */
    .account-transaction-panel {
        margin: 0 1.5rem 1.5rem;
        background: white;
        border-radius: 0.75rem;
        border: 2px solid #28a745;
        overflow: hidden;
        animation: slideDown 0.3s ease;
    }

    .account-transaction-panel.withdraw {
        border-color: #dc3545;
    }

    .account-transaction-panel.adjust {
        border-color: #17a2b8;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .transaction-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        background: #28a745;
        color: white;
    }

    .account-transaction-panel.withdraw .transaction-panel-header {
        background: #dc3545;
    }

    .account-transaction-panel.adjust .transaction-panel-header {
        background: #17a2b8;
    }

    .transaction-panel-header h5 {
        margin: 0;
        font-size: 1rem;
    }

    .transaction-panel-header .btn-link {
        color: white;
        padding: 0;
        font-size: 1.25rem;
    }

    .transaction-panel-body {
        padding: 1.25rem;
    }

    .transaction-form-inline {
        display: grid;
        grid-template-columns: 1fr 2fr auto;
        gap: 1rem;
        align-items: end;
    }

    @media (max-width: 992px) {
        .transaction-form-inline {
            grid-template-columns: 1fr 1fr;
        }
        .transaction-actions {
            grid-column: span 2;
        }
    }

    @media (max-width: 576px) {
        .transaction-form-inline {
            grid-template-columns: 1fr;
        }
        .transaction-actions {
            grid-column: span 1;
        }
    }

    .transaction-form-inline .form-group {
        margin-bottom: 0;
    }

    .transaction-form-inline .form-group label {
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
        display: block;
    }

    .transaction-actions {
        min-width: 180px;
    }

    .transaction-actions .btn {
        background: #28a745;
        border-color: #28a745;
        color: white;
    }

    .account-transaction-panel.withdraw .transaction-actions .btn {
        background: #dc3545;
        border-color: #dc3545;
    }

    .account-transaction-panel.adjust .transaction-actions .btn {
        background: #17a2b8;
        border-color: #17a2b8;
    }

    /* Balance Preview */
    .balance-preview {
        margin-top: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        border: 1px dashed #dee2e6;
    }

    .balance-preview-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        font-size: 0.95rem;
    }

    .balance-preview-row:first-child {
        border-bottom: 1px solid #dee2e6;
        color: #6c757d;
    }

    .balance-preview-row:last-child {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .balance-preview-row:last-child span:last-child.positive {
        color: #28a745;
    }

    .balance-preview-row:last-child span:last-child.negative {
        color: #dc3545;
    }

    /* Transaction Section */
    .account-transactions-section {
        padding: 0 1.5rem 1.5rem;
    }

    .transactions-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .transactions-section-header h5 {
        margin: 0;
        color: #495057;
    }

    .transactions-filters {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .transactions-filters .filter-group {
        min-width: 120px;
    }

    /* Transaction Timeline */
    .transaction-timeline {
        background: white;
        border-radius: 0.75rem;
        border: 1px solid #e9ecef;
        max-height: 500px;
        overflow-y: auto;
    }

    .timeline-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        color: #adb5bd;
    }

    .timeline-empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
    }

    .timeline-empty-state p {
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
        color: #6c757d;
    }

    .timeline-empty-state small {
        color: #adb5bd;
    }

    /* Timeline Items */
    .timeline-item {
        display: flex;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f3f4;
        transition: background 0.2s ease;
    }

    .timeline-item:last-child {
        border-bottom: none;
    }

    .timeline-item:hover {
        background: #f8f9fa;
    }

    .timeline-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .timeline-icon.success {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .timeline-icon.danger {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .timeline-icon.info {
        background: rgba(23, 162, 184, 0.1);
        color: #17a2b8;
    }

    .timeline-content {
        flex: 1;
        min-width: 0;
    }

    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.25rem;
    }

    .timeline-type {
        font-weight: 600;
        color: #212529;
    }

    .timeline-amount {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .timeline-amount.positive {
        color: #28a745;
    }

    .timeline-amount.negative {
        color: #dc3545;
    }

    .timeline-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .timeline-description {
        font-size: 0.875rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .timeline-balance {
        font-size: 0.8rem;
        color: #adb5bd;
        background: #f8f9fa;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        display: inline-block;
    }

    /* My Transactions Modal */
    .my-transactions-filter {
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .my-transactions-summary {
        margin-bottom: 1.5rem;
    }

    .summary-stat-card {
        padding: 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.5rem;
        text-align: center;
    }

    .payment-type-breakdown {
        margin-top: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
    }

</style>

<div class="reception-workbench-container">
    <!-- Left Panel: Patient Search & Queue -->
    <div class="left-panel" id="left-panel">
        <div class="panel-header">
            <h5><i class="fa fa-search"></i> Patient Search</h5>
            <button class="btn-view-work-pane" id="btn-view-work-pane" title="View Work Pane">
                <i class="fa fa-arrow-right"></i> Work Pane
            </button>
        </div>

        @include('admin.partials.patient_search_html')

        <div class="queue-widget">
            <h6><i class="mdi mdi-format-list-bulleted"></i> DOCTOR QUEUE</h6>
            <div class="queue-item" data-filter="emergency" style="background: #fff5f5; border-left: 3px solid #dc3545;">
                <span class="queue-item-label"><i class="fa fa-bolt text-danger"></i> <strong class="text-danger">Emergency</strong></span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="waiting">
                <span class="queue-item-label"><i class="mdi mdi-clock-outline text-warning"></i> Waiting</span>
                <span class="queue-count all-unpaid" id="queue-waiting-count">0</span>
            </div>
            <div class="queue-item" data-filter="vitals">
                <span class="queue-item-label"><i class="mdi mdi-heart-pulse text-info"></i> Vitals Pending</span>
                <span class="queue-count hmo-items" id="queue-vitals-count">0</span>
            </div>
            <div class="queue-item" data-filter="consultation">
                <span class="queue-item-label"><i class="mdi mdi-doctor text-success"></i> In Consultation</span>
                <span class="queue-count credit-accounts" id="queue-consultation-count">0</span>
            </div>
            <div class="queue-item" data-filter="admitted">
                <span class="queue-item-label"><i class="mdi mdi-bed text-danger"></i> Admitted</span>
                <span class="queue-count all-unpaid" id="queue-admitted-count">0</span>
            </div>
            <div class="queue-item" data-filter="appointments-today" style="border-left: 3px solid #6f42c1;">
                <span class="queue-item-label"><i class="mdi mdi-calendar-check" style="color:#6f42c1;"></i> <strong>Today's Appointments</strong></span>
                <span class="queue-count" id="queue-appointments-count" style="background: #6f42c1; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="referrals" style="border-left: 3px solid #b45309;">
                <span class="queue-item-label"><i class="mdi mdi-account-arrow-right" style="color:#b45309;"></i> <strong>Pending Referrals</strong></span>
                <span class="queue-count" id="queue-referrals-count" style="background: #b45309; color: #fff;">0</span>
            </div>
            <div class="queue-item" id="queue-hmo-pending-item" data-filter="hmo-pending-validation" style="border-left: 3px solid #0d6efd;">
                <span class="queue-item-label"><i class="mdi mdi-shield-check" style="color:#0d6efd;"></i> <strong>HMO Pending Validation</strong></span>
                <span class="queue-count" id="queue-hmo-pending-count" style="background: #0d6efd; color: #fff;">0</span>
            </div>
            <button class="btn-queue-all" id="show-all-queue-btn"><i class="mdi mdi-format-list-bulleted"></i> View Full Queue</button>
        </div>

        <div class="quick-actions">
            <h6><i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-ward-dashboard">
                <i class="mdi mdi-hospital-building text-primary"></i>
                <span>Ward Dashboard</span>
            </button>
            <button class="quick-action-btn" id="btn-new-patient">
                <i class="mdi mdi-account-plus"></i>
                <span>New Patient</span>
            </button>
            <button class="quick-action-btn" id="btn-quick-register">
                <i class="mdi mdi-account-plus-outline text-success"></i>
                <span>Quick Register</span>
            </button>
            <button class="quick-action-btn" id="btn-today-stats">
                <i class="mdi mdi-chart-bar"></i>
                <span>Today's Stats</span>
            </button>
            <button class="quick-action-btn" id="btn-view-reports">
                <i class="mdi mdi-file-chart"></i>
                <span>Reports</span>
            </button>
            @if(appsettings()->enable_ei_reception)
<button class="quick-action-btn" onclick="showEmergencyIntakeModal()">
                <i class="mdi mdi-ambulance text-danger"></i>
                <span>Emergency Intake</span>
            </button>
@endif
            <button class="quick-action-btn" onclick="showMorgueAdmissionModal()">
                <i class="mdi mdi-emoticon-dead text-dark"></i>
                <span>Morgue Admission (BID)</span>
            </button>
            <button class="quick-action-btn" id="btn-hmo-validation" onclick="showHmoValidationPanel()">
                <i class="mdi mdi-shield-check text-primary"></i>
                <span>HMO Validation</span>
            </button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="main-workspace" id="main-workspace">
        <!-- Navigation Bar (Mobile Back Button + Actions) -->
        <div class="workspace-navbar" id="workspace-navbar">
            <button class="btn-back-to-search" id="btn-back-to-search">
                <i class="fa fa-arrow-left"></i> Back to Search
            </button>
            <div class="workspace-navbar-actions">
                <button class="btn-toggle-search" id="btn-toggle-search">
                    <i class="fa fa-bars"></i> Toggle Search
                </button>
                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#hospital-contacts-modal" title="Hospital Contacts">
                    <i class="mdi mdi-contacts"></i> Contacts
                </button>
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#workbench-price-list-modal" title="Price List">
                    <i class="mdi mdi-currency-usd"></i> Price List
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state">
            <i class="mdi mdi-account-search"></i>
            <h3>No Patient Selected</h3>
            <p>Search and select a patient to manage their visit</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                <i class="mdi mdi-format-list-bulleted"></i> View Today's Queue
            </button>
        </div>

        <!-- Queue View -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-format-list-bulleted"></i> Doctor Queue</h4>
                <button class="btn-close-queue" id="btn-close-queue">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content">
                <div class="mb-3">
                    <select class="form-control" id="queue-clinic-filter" style="max-width: 300px;">
                        <option value="">All Clinics</option>
                    </select>
                </div>
                <table class="table" id="queue-datatable" style="width: 100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>File No</th>
                            <th>HMO</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        {{-- ─── HMO Pending Validation Panel ───────────────────────────────── --}}
        <div class="queue-view" id="hmo-validation-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-shield-check" style="color:#0d6efd;"></i> HMO Pending Validation</h4>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-primary" id="btn-hmo-batch-validate" disabled>
                        <i class="mdi mdi-check-all"></i> Validate Selected (<span id="hmo-batch-count">0</span>)
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-hmo-refresh-validation">
                        <i class="mdi mdi-refresh"></i>
                    </button>
                    <button class="btn-close-queue" id="btn-close-hmo-validation">
                        <i class="mdi mdi-close"></i> Close
                    </button>
                </div>
            </div>
            <div class="queue-view-content">
                <div class="mb-2 d-flex align-items-center gap-2">
                    <input type="text" class="form-control form-control-sm" id="hmo-validation-search"
                           placeholder="Search patient, file no..." style="max-width: 280px;">
                    <small class="text-muted" id="hmo-validation-total">0 pending</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="hmo-validation-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="hmo-select-all" style="transform:scale(1.3);cursor:pointer;"></th>
                                <th>Patient</th>
                                <th>HMO</th>
                                <th>Item</th>
                                <th>Coverage</th>
                                <th>HMO Amt</th>
                                <th>Pending</th>
                                <th style="width:140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="hmo-validation-body">
                            <tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ─── HMO Validation Confirm Modal ──────────────────────────────── --}}
        <div class="modal fade" id="hmoValidateConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content" style="border-radius:12px;border:none;">
                    <div class="modal-header py-2" style="background:linear-gradient(135deg,#0d6efd 0%,#0a58ca 100%);color:#fff;border-radius:12px 12px 0 0;">
                        <h5 class="modal-title"><i class="mdi mdi-shield-check"></i> <span id="hvc-title">Confirm Validation</span></h5>
                        <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Single request details --}}
                        <div id="hvc-single-details">
                            <table class="table table-sm table-borderless mb-2" style="font-size:0.88rem;">
                                <tr><td class="text-muted" style="width:110px;">Patient</td><td><strong id="hvc-patient"></strong></td></tr>
                                <tr><td class="text-muted">File No</td><td id="hvc-file-no"></td></tr>
                                <tr><td class="text-muted">HMO</td><td id="hvc-hmo"></td></tr>
                                <tr><td class="text-muted">Item</td><td id="hvc-item"></td></tr>
                                <tr><td class="text-muted">Coverage</td><td id="hvc-coverage"></td></tr>
                                <tr><td class="text-muted">HMO Amount</td><td><strong id="hvc-amount" class="text-success"></strong></td></tr>
                            </table>
                            <div class="alert alert-info py-2 mb-2" style="font-size:0.82rem;border-radius:8px;" id="hvc-outcome-info"></div>
                        </div>
                        {{-- Batch summary --}}
                        <div id="hvc-batch-details" style="display:none;">
                            <div class="alert alert-primary py-2 mb-2" style="font-size:0.88rem;border-radius:8px;">
                                <i class="mdi mdi-check-all"></i> You are about to validate <strong id="hvc-batch-total">0</strong> HMO request(s).
                            </div>
                            <div class="mb-2" style="font-size:0.82rem;">
                                <span class="text-muted">Primary requests → </span><span class="badge badge-success">Approved</span><br>
                                <span class="text-muted">Secondary requests → </span><span class="badge" style="background:#7c4dff;color:#fff;">Awaiting Code</span>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-muted">Notes <span class="font-weight-normal">(optional)</span></label>
                            <textarea class="form-control form-control-sm" id="hvc-notes" rows="2" maxlength="500" placeholder="Add a note..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary" id="hvc-confirm-btn"><i class="mdi mdi-check"></i> Confirm Validation</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Appointments Calendar / Table View ─────────────────────────── --}}
        <div class="queue-view" id="appointments-calendar-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-calendar-clock" style="color:#6f42c1;"></i> Appointment Schedule</h4>
                <div class="d-flex align-items-center gap-2">
                    {{-- Calendar/Table toggle --}}
                    <div class="btn-group btn-group-sm mr-3" role="group" id="appt-view-toggle">
                        <button type="button" class="btn btn-outline-purple active" data-view="calendar"><i class="mdi mdi-calendar"></i> Calendar</button>
                        <button type="button" class="btn btn-outline-purple" data-view="table"><i class="mdi mdi-table"></i> Table</button>
                    </div>
                    <button class="btn-close-queue" id="btn-close-appointments-view"><i class="mdi mdi-close"></i> Close</button>
                </div>
            </div>
            <div class="queue-view-content" style="padding: 1rem; overflow-y: auto;">
                {{-- Status legend --}}
                <div class="d-flex flex-wrap gap-2 mb-3 appt-legend">
                    <span><span class="legend-dot" style="background:#6f42c1;"></span> Scheduled</span>
                    <span><span class="legend-dot" style="background:#17a2b8;"></span> Waiting</span>
                    <span><span class="legend-dot" style="background:#ffc107;"></span> Vitals Pending</span>
                    <span><span class="legend-dot" style="background:#28a745;"></span> Ready</span>
                    <span><span class="legend-dot" style="background:#0d6efd;"></span> In Consultation</span>
                    <span><span class="legend-dot" style="background:#198754;"></span> Completed</span>
                    <span><span class="legend-dot" style="background:#dc3545;"></span> Cancelled</span>
                    <span><span class="legend-dot" style="background:#6c757d;"></span> No-Show</span>
                </div>
                {{-- Filters --}}
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="appt-cal-clinic-filter">
                            <option value="">All Clinics</option>
                            @if(isset($clinics))
                                @foreach($clinics as $clinic)
                                    <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="appt-cal-doctor-filter">
                            <option value="">All Doctors</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="appt-cal-status-filter">
                            <option value="">All Statuses</option>
                            <option value="6">Scheduled</option>
                            <option value="1">Waiting</option>
                            <option value="2">Vitals Pending</option>
                            <option value="3">Ready</option>
                            <option value="4">In Consultation</option>
                            <option value="5">Completed</option>
                            <option value="0">Cancelled</option>
                            <option value="7">No-Show</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="appt-cal-search" placeholder="Search patient name / file no...">
                    </div>
                </div>

                {{-- Calendar container --}}
                <div id="appointments-calendar-container">
                    <div id="appointments-fullcalendar"></div>
                </div>

                {{-- Table container (hidden by default) --}}
                <div id="appointments-table-container" style="display: none;">
                    <table class="table table-hover table-sm" id="appointments-global-datatable" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Patient</th>
                                <th>File No</th>
                                <th>HMO</th>
                                <th>Clinic</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Delivery</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <!-- Ward Dashboard View -->
        <div class="queue-view" id="ward-dashboard-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-hospital-building"></i> Ward Dashboard</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-ward-dashboard">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 1rem; overflow-y: auto;">
                @include('admin.partials.ward_dashboard')
            </div>
        </div>

        <!-- Reports View (Full Screen - Global Access) -->
        <div class="queue-view" id="reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box"></i> Reception Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 1.5rem; overflow-y: auto;">
                <!-- Filter Panel -->
                <div class="reports-filter-panel card-modern mb-4">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="mdi mdi-filter"></i> Filters</h6>
                    </div>
                    <div class="card-body py-3">
                        <form id="reports-filter-form">
                            <div class="row">
                                <div class="form-group col-md-2">
                                    <label for="report-date-from" class="small mb-1">Date From</label>
                                    <input type="date" class="form-control form-control-sm" id="report-date-from" name="date_from">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-date-to" class="small mb-1">Date To</label>
                                    <input type="date" class="form-control form-control-sm" id="report-date-to" name="date_to">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-type-filter" class="small mb-1">Report Type</label>
                                    <select class="form-control form-control-sm" id="report-type-filter" name="report_type">
                                        <option value="">All Activity</option>
                                        <option value="registrations">New Registrations</option>
                                        <option value="queue">Queue Entries</option>
                                        <option value="visits">Completed Visits</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-clinic-filter" class="small mb-1">Clinic</label>
                                    <select class="form-control form-control-sm" id="report-clinic-filter" name="clinic_id">
                                        <option value="">All Clinics</option>
                                        @foreach(\App\Models\Clinic::where('status', 1)->orderBy('name')->get() as $clinic)
                                            <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-hmo-filter" class="small mb-1">HMO</label>
                                    <select class="form-control form-control-sm" id="report-hmo-filter" name="hmo_id">
                                        <option value="">All HMOs</option>
                                        @foreach(\App\Models\Hmo::where('status', 1)->orderBy('name')->get() as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-patient-search" class="small mb-1">Patient Search</label>
                                    <input type="text" class="form-control form-control-sm" id="report-patient-search" name="patient_search" placeholder="File no or name...">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12 text-right">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clear-report-filters">
                                        <i class="mdi mdi-refresh"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="mdi mdi-filter"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sub Tabs -->
                <ul class="nav nav-tabs mb-3" id="reports-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview-content" role="tab">
                            <i class="mdi mdi-view-dashboard"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="registrations-tab" data-toggle="tab" href="#registrations-content" role="tab">
                            <i class="mdi mdi-account-plus"></i> Registrations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="queue-report-tab" data-toggle="tab" href="#queue-report-content" role="tab">
                            <i class="mdi mdi-format-list-bulleted"></i> Queue Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="visits-tab" data-toggle="tab" href="#visits-content" role="tab">
                            <i class="mdi mdi-calendar-check"></i> Visits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="clinical-reports-tab" data-toggle="tab" href="#clinical-reports-content" role="tab">
                            <i class="mdi mdi-chart-timeline-variant"></i> Clinical Reports
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="reports-tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview-content" role="tabpanel">
                        <div class="reports-container">
                            <!-- Summary Statistics Cards -->
                            <div class="row mb-4">
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            <i class="mdi mdi-account-plus"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-new-registrations">0</h3>
                                            <p>New Registrations</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                            <i class="mdi mdi-format-list-bulleted"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-total-queued">0</h3>
                                            <p>Total Queued</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-completed-visits">0</h3>
                                            <p>Completed Visits</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                            <i class="mdi mdi-clock-outline"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-pending-queue">0</h3>
                                            <p>Pending in Queue</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                            <i class="mdi mdi-timer"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-avg-wait-time">0m</h3>
                                            <p>Avg Wait Time</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                            <i class="mdi mdi-refresh"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-return-rate">0%</h3>
                                            <p>Return Visits</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-bar"></i> Registrations Trend</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="registrations-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-pie"></i> HMO Distribution</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="hmo-distribution-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Clinics & Peak Hours -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-hospital-building"></i> Top Clinics</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0" id="top-clinics-table">
                                                    <thead class="bg-light">
                                                        <tr>
                                                            <th>Clinic</th>
                                                            <th class="text-center">Visits</th>
                                                            <th class="text-right">%</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="top-clinics-body">
                                                        <tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-clock"></i> Peak Hours</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="peak-hours-chart" height="180"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registrations Tab -->
                    <div class="tab-pane fade" id="registrations-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <h6 class="mb-0"><i class="mdi mdi-account-plus"></i> Patient Registrations</h6>
                                <div>
                                    <button class="btn btn-sm btn-success" id="export-registrations-excel">
                                        <i class="mdi mdi-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-sm btn-info" id="print-registrations">
                                        <i class="mdi mdi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="registrations-datatable" style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>File No</th>
                                                <th>Patient Name</th>
                                                <th>Gender</th>
                                                <th>Age</th>
                                                <th>Phone</th>
                                                <th>HMO</th>
                                                <th>Registered By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queue Report Tab -->
                    <div class="tab-pane fade" id="queue-report-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <h6 class="mb-0"><i class="mdi mdi-format-list-bulleted"></i> Queue Entries</h6>
                                <div>
                                    <button class="btn btn-sm btn-success" id="export-queue-excel">
                                        <i class="mdi mdi-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-sm btn-info" id="print-queue">
                                        <i class="mdi mdi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="queue-report-datatable" style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>File No</th>
                                                <th>Patient</th>
                                                <th>Clinic</th>
                                                <th>Doctor</th>
                                                <th>Service</th>
                                                <th>Status</th>
                                                <th>Wait Time</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visits Tab -->
                    <div class="tab-pane fade" id="visits-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <h6 class="mb-0"><i class="mdi mdi-calendar-check"></i> Visit History</h6>
                                <div>
                                    <button class="btn btn-sm btn-success" id="export-visits-excel">
                                        <i class="mdi mdi-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-sm btn-info" id="print-visits">
                                        <i class="mdi mdi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="visits-datatable" style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>File No</th>
                                                <th>Patient</th>
                                                <th>Clinic</th>
                                                <th>Doctor</th>
                                                <th>Reason</th>
                                                <th>HMO</th>
                                                <th>Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('admin.partials.clinical-reports-panel')

                </div>
            </div>
        </div>

        <!-- Patient Header -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div style="flex: 1;">
                    <div class="d-flex align-items-center mb-1">
                        <div class="patient-name mb-0 me-3" id="patient-name"></div>
                        <button class="btn btn-sm btn-danger btn-manage-alerts" id="btn-manage-alerts" style="display:none;">
                            <i class="mdi mdi-alert-octagon"></i> Alerts
                        </button>
                    </div>
                    <div class="patient-meta" id="patient-meta"></div>
                    <div class="sticky-header-alerts mt-2" style="max-height: 60px; overflow-y: auto;"></div>
                    <div class="patient-allergies" id="patient-allergies" style="display: none;">
                        <span class="allergy-alert-badge"><i class="mdi mdi-alert"></i> Allergies: <span id="allergy-list"></span></span>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-2">
                    <div class="patient-account-balance" id="patient-header-balance" style="display: none;">
                        <div class="balance-label">Account Balance</div>
                        <div class="balance-value" id="header-balance-amount">₦0.00</div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-light" id="btn-edit-patient" title="Edit Patient">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-info" id="btn-print-card" title="Print Hospital Card">
                            <i class="mdi mdi-card-account-details"></i> Print Card
                        </button>
                        <button class="btn btn-sm btn-outline-dark" id="btn-medical-reports" title="Medical Reports" style="display:none;">
                            <i class="mdi mdi-file-document-multiple"></i> Medical Reports
                        </button>
                        <button class="btn-expand-patient d-inline-flex" id="btn-expand-patient" title="Show more details">
                            <span class="btn-expand-text">more biodata</span>
                            <i class="mdi mdi-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="patient-details-expanded" id="patient-details-expanded">
                <div class="patient-details-grid" id="patient-details-grid"></div>
            </div>
        </div>

        <!-- Workspace Content -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="profile">
                    <i class="mdi mdi-account-card-details"></i>
                    <span>Profile</span>
                </button>
                <button class="workspace-tab" data-tab="booking">
                    <i class="mdi mdi-calendar-plus"></i>
                    <span>Book Service</span>
                </button>
                <button class="workspace-tab" data-tab="walkin">
                    <i class="mdi mdi-cart-plus"></i>
                    <span>Walk-in Sales</span>
                </button>
                <button class="workspace-tab" data-tab="history">
                    <i class="mdi mdi-history"></i>
                    <span>Visit History</span>
                </button>
                <button class="workspace-tab" data-tab="requests">
                    <i class="mdi mdi-clipboard-list"></i>
                    <span>Service Requests</span>
                </button>
                <button class="workspace-tab" data-tab="appointments">
                    <i class="mdi mdi-calendar-clock" style="color:#6f42c1;"></i>
                    <span>Appointments</span>
                </button>
                <button class="workspace-tab" data-tab="admissions">
                    <i class="mdi mdi-hospital-building"></i>
                    <span>Admissions</span>
                    <span class="badge badge-dark ml-1" id="reception-admissions-badge" style="display:none;">0</span>
                </button>
            </div>

            <!-- Profile Tab -->
            <div class="workspace-tab-content active" id="profile-tab">
                <div class="profile-tab-content p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card-modern">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-account"></i> Patient Information</h5>
                                </div>
                                <div class="card-body" id="profile-info-card">
                                    <table class="table table-sm table-borderless">
                                        <tbody id="profile-info-table"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-modern mb-3">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-hospital-building"></i> HMO / Insurance</h5>
                                </div>
                                <div class="card-body" id="profile-hmo-card">
                                    <table class="table table-sm table-borderless">
                                        <tbody id="profile-hmo-table"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-modern">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-calendar-clock"></i> Current Queue</h5>
                                </div>
                                <div class="card-body" id="profile-queue-card">
                                    <div id="current-queue-entries">
                                        <p class="text-muted">No active queue entries</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Book Service Tab -->
            <div class="workspace-tab-content" id="booking-tab">
                <div class="booking-tab-content p-4">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card-modern">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="mdi mdi-calendar-plus"></i> Book Consultation</h5>
                                </div>
                                <div class="card-body">
                                    <form id="booking-form">
                                        <div id="family-booking-container" class="mb-4 row">
                                            <!-- Dynamically populated family members grid will go here -->
                                        </div>

                                        <!-- Appointment Type Toggle -->
                                        <div class="form-group mb-3 mt-4 border-top pt-3">
                                            <label class="d-block mb-2"><i class="mdi mdi-calendar-clock"></i> Appointment Type (Applies to all selected)</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="booking_type" id="booking-type-walkin" value="walkin" checked autocomplete="off">
                                                <label class="btn btn-outline-primary" for="booking-type-walkin"><i class="mdi mdi-walk"></i> Walk-in (Now)</label>
                                                <input type="radio" class="btn-check" name="booking_type" id="booking-type-schedule" value="schedule" autocomplete="off">
                                                <label class="btn btn-outline-purple" for="booking-type-schedule"><i class="mdi mdi-calendar-plus"></i> Schedule Future</label>
                                            </div>
                                        </div>

                                        <!-- Schedule Fields (hidden by default) -->
                                        <div id="schedule-fields" style="display:none;">
                                            <div class="card-modern border-purple mb-3 p-3">
                                                <div class="row">
                                                    <div class="col-md-6 mb-2">
                                                        <label><i class="mdi mdi-calendar"></i> Date <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="booking-appointment-date" min="{{ date('Y-m-d') }}">
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label><i class="mdi mdi-clock-outline"></i> Time Slot <span class="text-danger">*</span></label>
                                                        <select class="form-control" id="booking-appointment-time" style="display:block;">
                                                            <option value="">-- Select date first --</option>
                                                        </select>
                                                        <input type="time" class="form-control mt-1" id="booking-appointment-time-manual" style="display:none;" placeholder="HH:MM">
                                                        <div class="form-check mt-1">
                                                            <input class="form-check-input" type="checkbox" id="booking-custom-time-toggle">
                                                            <label class="form-check-label text-muted small" for="booking-custom-time-toggle">Enter custom time</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group mb-2">
                                                    <label><i class="mdi mdi-priority-high"></i> Priority</label>
                                                    <select class="form-control" id="booking-priority">
                                                        <option value="routine">Routine</option>
                                                        <option value="urgent">Urgent</option>
                                                        <option value="emergency">Emergency</option>
                                                    </select>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label><i class="mdi mdi-note-text-outline"></i> Notes</label>
                                                    <textarea class="form-control" id="booking-appointment-notes" rows="2" placeholder="Optional notes for the doctor or scheduler"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="force_rebill">
                                            <label class="form-check-label" for="force_rebill">
                                                Force Re-bill Consultation (ignore cycle duration limits for all selected)
                                            </label>
                                        </div>
                                        @hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS|BILLER')
                                        <button type="submit" class="btn btn-success w-100" id="btn-book-consultation">
                                            <i class="mdi mdi-cart-check"></i> Checkout & Book
                                        </button>
                                        @else
                                        <button type="submit" class="btn btn-primary w-100" id="btn-book-consultation">
                                            <i class="mdi mdi-check-circle"></i> Book Service
                                        </button>
                                        @endhasanyrole
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card-modern" id="tariff-preview-card" style="display: none;">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-cart"></i> Booking Cart</h5>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Service</th>
                                                <th class="text-right">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cart-preview-body">
                                            <!-- Dynamically filled by JS -->
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-primary">
                                                <td colspan="2"><strong>Total Payable:</strong></td>
                                                <td class="text-right"><strong id="cart-preview-total">₦0</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="alert alert-info" id="tariff-validation-alert" style="display: none;">
                                <i class="mdi mdi-information"></i> <span id="tariff-validation-message"></span>
                            </div>
                            <div class="card-modern mt-3">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-queue-first-in-last-out"></i> Current Queue</h5>
                                </div>
                                <div class="card-body">
                                    <div id="booking-current-queue">
                                        <p class="text-muted">No active queue entries</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Walk-in Sales Tab -->
            <div class="workspace-tab-content" id="walkin-tab">
                <div class="walkin-tab-content p-4">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card-modern">
                                <div class="card-header">
                                    <ul class="nav nav-pills card-header-pills" id="walkin-subtabs">
                                        <li class="nav-item">
                                            <a class="nav-link active" href="#walkin-lab" data-toggle="pill" data-type="lab">
                                                <i class="mdi mdi-test-tube"></i> Lab
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#walkin-imaging" data-toggle="pill" data-type="imaging">
                                                <i class="mdi mdi-x-ray"></i> Imaging
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#walkin-product" data-toggle="pill" data-type="product">
                                                <i class="mdi mdi-pill"></i> Products
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="form-group mb-3">
                                        <input type="text" class="form-control" id="walkin-search"
                                            placeholder="🔍 Search services/products...">
                                    </div>
                                    <div class="walkin-search-results" id="walkin-search-results" style="max-height: 300px; overflow-y: auto;">
                                        <p class="text-muted text-center">Type to search...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card-modern">
                                <!-- Cart Sub-Tabs -->
                                <div class="card-header p-0">
                                    <ul class="nav nav-tabs nav-fill" id="walkin-cart-tabs">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="tab" href="#walkin-cart-pane">
                                                <i class="mdi mdi-cart"></i> Cart
                                                <span class="badge badge-primary ml-1" id="cart-count-badge">0</span>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#walkin-recent-pane">
                                                <i class="mdi mdi-clock-outline"></i> Recent (24h)
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="tab-content">
                                    <!-- Cart Tab Pane -->
                                    <div class="tab-pane fade show active" id="walkin-cart-pane">
                                        <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm mb-0" id="walkin-cart-table">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Item</th>
                                                        <th class="text-right">Price</th>
                                                        <th class="text-right text-success">HMO Covers</th>
                                                        <th class="text-right text-primary">You Pay</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="walkin-cart-body">
                                                    <tr id="walkin-cart-empty">
                                                        <td colspan="5" class="text-center text-muted py-4">
                                                            <i class="mdi mdi-cart-outline" style="font-size: 2rem;"></i>
                                                            <p class="mb-0 mt-2">No items selected</p>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Payment Summary -->
                                        <div class="border-top">
                                            <table class="table table-sm mb-0" id="walkin-summary-table">
                                                <tbody>
                                                    <tr class="bg-light">
                                                        <td colspan="2"><strong>Subtotal (Original Prices):</strong></td>
                                                        <td class="text-right" colspan="3"><strong id="walkin-subtotal">₦0</strong></td>
                                                    </tr>
                                                    <tr id="walkin-hmo-row" style="display: none;" class="bg-success-light">
                                                        <td colspan="2">
                                                            <span class="text-success">
                                                                <i class="mdi mdi-shield-check"></i> <strong>Total HMO Coverage</strong>
                                                            </span>
                                                            <small class="d-block" id="walkin-hmo-name"></small>
                                                        </td>
                                                        <td class="text-right text-success" colspan="3"><strong id="walkin-hmo-amount">-₦0</strong></td>
                                                    </tr>
                                                    <tr class="table-primary">
                                                        <td colspan="2"><strong style="font-size: 1.1rem;">Patient Pays:</strong></td>
                                                        <td class="text-right" colspan="3"><strong style="font-size: 1.1rem;" id="walkin-cart-total">₦0</strong></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div class="p-3">
                                                <button class="btn btn-success btn-lg w-100" id="btn-submit-walkin" disabled>
                                                    <i class="mdi mdi-send"></i> Create Request (Awaiting Billing)
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Recent Requests Tab Pane -->
                                    <div class="tab-pane fade" id="walkin-recent-pane">
                                        <div class="card-body p-2" style="max-height: 400px; overflow-y: auto;">
                                            <div class="alert alert-info py-2 px-3 mb-2">
                                                <small><i class="mdi mdi-information"></i> Requests created in the last 24 hours for this patient</small>
                                            </div>
                                            <div id="recent-requests-container">
                                                <div class="text-center text-muted py-4">
                                                    <i class="mdi mdi-clock-outline" style="font-size: 2rem;"></i>
                                                    <p class="mb-0 mt-2">No recent requests</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visit History Tab -->
            <div class="workspace-tab-content" id="history-tab">
                <div class="history-tab-content p-4">
                    <div class="card-modern">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-history"></i> Visit History</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover" id="visit-history-table" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Service</th>
                                        <th>Reason</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Requests Tab -->
            <div class="workspace-tab-content" id="requests-tab">
                <div class="requests-tab-content p-4">
                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <i class="mdi mdi-clipboard-list text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-total-requests">0</div>
                                        <div class="stat-label">Total Requests</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                        <i class="mdi mdi-shield-check text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-hmo-covered">₦0</div>
                                        <div class="stat-label">HMO Covered</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                        <i class="mdi mdi-cash text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-patient-payable">₦0</div>
                                        <div class="stat-label">Patient Payable</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                        <i class="mdi mdi-check-circle text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-completed-count">0</div>
                                        <div class="stat-label">Completed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card-modern mb-4">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="mdi mdi-filter"></i> Filters</h6>
                        </div>
                        <div class="card-body py-3">
                            <form id="service-requests-filter-form">
                                <div class="row">
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Date From</label>
                                        <input type="date" class="form-control form-control-sm" id="req-date-from">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Date To</label>
                                        <input type="date" class="form-control form-control-sm" id="req-date-to">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Request Type</label>
                                        <select class="form-control form-control-sm" id="req-type-filter">
                                            <option value="">All Types</option>
                                            <option value="consultation">Consultation</option>
                                            <option value="lab">Lab Test</option>
                                            <option value="imaging">Imaging</option>
                                            <option value="product">Product/Drug</option>
                                            <option value="procedure">Procedure</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Billing Status</label>
                                        <select class="form-control form-control-sm" id="req-billing-filter">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending Billing</option>
                                            <option value="billed">Billed</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Delivery Status</label>
                                        <select class="form-control form-control-sm" id="req-delivery-filter">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-secondary mr-2" id="clear-req-filters">
                                            <i class="mdi mdi-refresh"></i>
                                        </button>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="mdi mdi-filter"></i> Apply
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Requests DataTable -->
                    <div class="card-modern">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0"><i class="mdi mdi-clipboard-list"></i> Service Requests</h6>
                            <div>
                                <button class="btn btn-sm btn-success" id="export-requests-excel">
                                    <i class="mdi mdi-file-excel"></i> Excel
                                </button>
                                <button class="btn btn-sm btn-danger" id="export-requests-pdf">
                                    <i class="mdi mdi-file-pdf"></i> PDF
                                </button>
                                <button class="btn btn-sm btn-info" id="print-requests">
                                    <i class="mdi mdi-printer"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="service-requests-datatable" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Request #</th>
                                            <th>Type</th>
                                            <th>Service/Item</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-right">HMO Covers</th>
                                            <th class="text-right">Payable</th>
                                            <th>Billing</th>
                                            <th>Delivery</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointments Tab -->
            <div class="workspace-tab-content" id="appointments-tab">
                <div class="appointments-tab-content p-4">
                    <div class="card-modern">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="mdi mdi-calendar-clock" style="color:#6f42c1;"></i> Patient Appointments</h5>
                            <button class="btn btn-sm btn-purple" id="btn-new-appointment-from-tab">
                                <i class="mdi mdi-calendar-plus"></i> New Appointment
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="patient-appointments-datatable" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Clinic</th>
                                            <th>Doctor</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Delivery</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admissions Tab (Reusable Module) -->
            <div class="workspace-tab-content" id="admissions-tab">
                @include('admin.partials.admissions-module')
            </div>
        </div>
    </div>
</div>

@include("admin.reception.partials._modals")
@endsection

@include("admin.reception.partials._scripts")
