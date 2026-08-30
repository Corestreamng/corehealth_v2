@extends('admin.layouts.app')

@section('title', 'Nursing Workbench')

@push('styles')
    <style>
        .vitals-dashboard-header .bh-stat-card {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }
        .vitals-dashboard-header .bh-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .vitals-dashboard-header .bh-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        .vitals-dashboard-header .bh-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        .vitals-dashboard-header .bh-stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            color: #6c757d;
            font-weight: 600;
        }
        .vitals-dashboard-header .bh-stat-blue { border-top: 4px solid #3b82f6; }
        .vitals-dashboard-header .bh-stat-blue .bh-stat-icon { background: #eff6ff; color: #3b82f6; }
        .vitals-dashboard-header .bh-stat-pink { border-top: 4px solid #ec4899; }
        .vitals-dashboard-header .bh-stat-pink .bh-stat-icon { background: #fdf2f8; color: #ec4899; }
        .vitals-dashboard-header .bh-stat-purple { border-top: 4px solid #8b5cf6; }
        .vitals-dashboard-header .bh-stat-purple .bh-stat-icon { background: #f5f3ff; color: #8b5cf6; }
        .vitals-dashboard-header .bh-stat-green { border-top: 4px solid #10b981; }
        .vitals-dashboard-header .bh-stat-green .bh-stat-icon { background: #ecfdf5; color: #10b981; }
    </style>
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/clinical-orders-shared.css') }}">
<link rel="stylesheet" href="{{ asset('css/queue-status.css') }}">
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
        --hospital-primary: {{ appsettings('hos_color', '#007bff') }};
        --hospital-primary-rgb: 0, 123, 255;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
    }

    /* Billing History Stat Cards */
    .bh-stat-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border-left: 4px solid transparent;
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .bh-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .bh-stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; }
    .bh-stat-value { font-size: 1.15rem; font-weight: 700; color: #2d3748; }
    .bh-stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: #718096; }

    .bh-stat-purple { border-left-color: #667eea; }
    .bh-stat-purple .bh-stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
    .bh-stat-green { border-left-color: #11998e; }
    .bh-stat-green .bh-stat-icon { background: linear-gradient(135deg, #11998e, #38ef7d); }
    .bh-stat-pink { border-left-color: #f093fb; }
    .bh-stat-pink .bh-stat-icon { background: linear-gradient(135deg, #f093fb, #f5576c); }
    .bh-stat-blue { border-left-color: #4facfe; }
    .bh-stat-blue .bh-stat-icon { background: linear-gradient(135deg, #4facfe, #00f2fe); }

    /* Billing/Delivery Badges */
    .billing-badge, .delivery-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .billing-badge.billing-pending { background: #fff3cd; color: #856404; }
    .billing-badge.billing-billed { background: #cce5ff; color: #004085; }
    .billing-badge.billing-paid { background: #d4edda; color: #155724; }
    .delivery-badge.delivery-pending { background: #fff3cd; color: #856404; }
    .delivery-badge.delivery-progress { background: #cce5ff; color: #004085; }
    .delivery-badge.delivery-completed { background: #d4edda; color: #155724; }

    /* Queue Source Badges */
    .queue-source-badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    .queue-source-badge.source-walkin { background: #e2e3e5; color: #495057; }
    .queue-source-badge.source-appointment { background: #cce5ff; color: #004085; }
    .queue-source-badge.source-emergency { background: #f8d7da; color: #721c24; }

    /* Main Layout */
    .nursing-workbench-container {
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

    .queue-count.billing {
        background: #fff3cd;
        color: #856404;
    }

    .queue-count.sample {
        background: #ffe5d4;
        color: #c65400;
    }

    .queue-count.results {
        background: #f8d7da;
        color: #721c24;
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

    .quick-action-btn:hover:not(:disabled) {
        border-color: var(--hospital-primary);
        background: #f8f9fa;
    }

    .quick-action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f5f5f5;
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

    .toggle-clinical-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.5);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .toggle-clinical-btn:hover {
        background: rgba(255, 255, 255, 0.3);
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

    /* Right Panel - Clinical Context */
    .right-panel {
        width: 25%;
        min-width: 300px;
        border-left: 2px solid #e9ecef;
        background: #f8f9fa;
        display: none;
        flex-direction: column;
        overflow: hidden;
    }

    .right-panel.active {
        display: flex;
    }

    .right-panel-header {
        padding: 1rem;
        background: white;
        border-bottom: 2px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .right-panel-header h5 {
        font-size: 1rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .close-panel-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close-panel-btn:hover {
        color: var(--danger);
    }

    .right-panel-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .clinical-panel {
        background: white;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        overflow: hidden;
        border: 1px solid #dee2e6;
    }

    .clinical-panel-header {
        padding: 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: background 0.2s;
    }

    .clinical-panel-header:hover {
        background: #e9ecef;
    }

    .clinical-panel-title {
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }

    .clinical-panel-actions {
        display: flex;
        gap: 0.5rem;
    }

    .clinical-panel-btn {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0.25rem;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
        transition: all 0.2s;
    }

    .clinical-panel-btn:hover {
        background: #dee2e6;
        color: var(--hospital-primary);
    }

    .clinical-panel-body {
        padding: 1rem;
        display: none;
    }

    .clinical-panel-body.active {
        display: block;
    }

    /* Vitals Display */
    .vital-entry {
        padding: 1rem;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
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
        font-size: 0.85rem;
        color: #6c757d;
        font-weight: 500;
    }

    .vital-bmi {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--hospital-primary);
        padding: 0.25rem 0.75rem;
        background: rgba(var(--hospital-primary-rgb), 0.1);
        border-radius: 1rem;
    }

    .vital-entry-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 1rem;
    }

    .vital-values {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }

    .vital-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 0.5rem;
        border-radius: 0.375rem;
        background: #f8f9fa;
    }

    .vital-item i {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
        color: #6c757d;
    }

    .vital-value {
        font-size: 1rem;
        font-weight: 700;
        color: #212529;
        display: block;
    }

    .vital-label {
        font-size: 0.75rem;
        color: #6c757d;
        text-transform: uppercase;
        margin-top: 0.25rem;
    }

    .vital-item.vital-normal {
        background: #d4edda;
    }

    .vital-item.vital-normal i,
    .vital-item.vital-normal .vital-value {
        color: #155724;
    }

    .vital-item.vital-warning {
        background: #fff3cd;
    }

    .vital-item.vital-warning i,
    .vital-item.vital-warning .vital-value {
        color: #856404;
    }

    .vital-item.warning {
        color: var(--warning);
        font-weight: 600;
    }

    .vital-item.danger {
        color: var(--danger);
        font-weight: 600;
    }

    /* Notes Display */
    .note-entry {
        padding: 1rem;
        background: white;
        border: 1px solid #e9ecef;
        border-left: 4px solid var(--hospital-primary);
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .note-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .note-date {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .note-doctor {
        font-weight: 600;
        color: #212529;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .note-doctor i {
        color: var(--hospital-primary);
        font-size: 1.1rem;
    }

    .specialty-tag {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .note-diagnosis {
        margin-bottom: 0.5rem;
    }

    .diagnosis-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: rgba(var(--hospital-primary-rgb), 0.1);
        color: var(--hospital-primary);
        border-radius: 0.25rem;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .note-content {
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: #495057;
        line-height: 1.5;
    }

    .note-text {
        font-size: 0.9rem;
        color: #495057;
        line-height: 1.6;
        margin-bottom: 0.5rem;
    }

    .note-text.truncated {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .read-more-link {
        color: var(--hospital-primary);
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
    }

    .read-more-link:hover {
        text-decoration: underline;
    }

    .read-more-btn {
        color: var(--hospital-primary);
        background: none;
        border: none;
        padding: 0;
        font-size: 0.85rem;
        cursor: pointer;
        margin-top: 0.5rem;
        text-decoration: underline;
    }

    /* Medications Display */
    .medication-filters {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .medication-filter-btn {
        padding: 0.5rem 1rem;
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.2s;
        color: #495057;
    }

    .medication-filter-btn:hover {
        border-color: var(--hospital-primary);
        color: var(--hospital-primary);
    }

    .medication-filter-btn.active {
        border-color: var(--hospital-primary);
        background: var(--hospital-primary);
        color: white;
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

    /* DataTables Custom Styling */
    .clinical-panel-body .dataTables_wrapper {
        width: 100%;
    }

    .clinical-panel-body table.dataTable {
        width: 100% !important;
        margin: 0 !important;
    }

    .clinical-panel-body table.dataTable thead {
        display: none;
    }

    .clinical-panel-body table.dataTable tbody tr {
        background: transparent;
        border: none;
    }

    .clinical-panel-body table.dataTable tbody td {
        padding: 0;
        border: none;
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

    /* Clinical panel refresh animation */
    .clinical-panel-btn.refreshing {
        animation: spin 0.6s linear infinite;
    }

    /* Modal overlay for small screens */
    .clinical-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }

    .clinical-modal-overlay.active {
        display: block;
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

    /* Billing search dropdowns - shared styles */
    #service-search-results,
    #consumable-search-results {
        border: 1px solid #dee2e6;
        border-top: none;
        background: white;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        border-radius: 0 0 8px 8px;
        padding: 0;
        list-style: none;
    }

    #service-search-results .list-group-item,
    #consumable-search-results .list-group-item {
        cursor: pointer;
        border-left: 3px solid transparent;
        transition: all 0.15s ease;
        padding: 10px 14px;
    }

    #service-search-results .list-group-item:hover,
    #consumable-search-results .list-group-item:hover {
        background: #f0f8ff;
        border-left-color: var(--hospital-primary);
    }

    #service-search-results .list-group-item:first-child,
    #consumable-search-results .list-group-item:first-child {
        border-top: none;
    }

    .billing-search-item-name {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.9rem;
    }

    .billing-search-item-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 3px;
    }

    .billing-search-item-price {
        font-weight: 600;
        color: #27ae60;
        font-size: 0.85rem;
    }

    .billing-search-item-badge {
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 3px;
        background: #eef2f7;
        color: #607d8b;
    }

    .billing-search-item-stock {
        font-size: 0.75rem;
        margin-left: auto;
    }

    .billing-search-hmo-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
        padding-top: 4px;
        border-top: 1px dashed #e0e0e0;
        font-size: 0.78rem;
    }

    .billing-search-hmo-payable {
        color: #e67e22;
        font-weight: 600;
    }

    .billing-search-hmo-claims {
        color: #27ae60;
        font-weight: 600;
    }

    .billing-search-hmo-mode {
        font-size: 0.68rem;
        padding: 1px 5px;
        border-radius: 3px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .billing-search-hmo-mode.mode-express {
        background: #d4edda;
        color: #155724;
    }

    .billing-search-hmo-mode.mode-primary {
        background: #cce5ff;
        color: #004085;
    }

    .billing-search-hmo-mode.mode-secondary {
        background: #fff3cd;
        color: #856404;
    }

    .billing-search-hmo-label {
        font-size: 0.7rem;
        color: #888;
    }

    .billing-search-no-results {
        padding: 16px;
        text-align: center;
        color: #999;
        font-style: italic;
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
    .btn-toggle-search,
    .btn-clinical-context {
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

    .btn-clinical-context {
        display: flex;
        background: var(--hospital-primary);
        color: white;
        border-color: var(--hospital-primary);
    }

    .btn-clinical-context:hover:not(:disabled) {
        background: #0056b3;
        border-color: #0056b3;
        color: white;
    }

    .btn-clinical-context:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #6c757d;
        border-color: #6c757d;
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

    /* ==========================================
       NURSING REPORTS STYLES
       ========================================== */

    /* Reports Filter Panel */
    .nursing-reports-filters {
        flex-shrink: 0;
    }

    /* Reports Tabs Wrapper */
    .nursing-reports-tabs-wrapper {
        background: white;
        border-bottom: 1px solid #dee2e6;
        flex-shrink: 0;
    }

    .nursing-reports-tabs {
        padding: 0 1rem;
        border-bottom: none;
        flex-wrap: nowrap;
        overflow-x: auto;
        white-space: nowrap;
    }

    .nursing-reports-tabs .nav-item {
        flex-shrink: 0;
    }

    .nursing-reports-tabs .nav-link {
        color: #6c757d;
        border: none;
        border-bottom: 3px solid transparent;
        border-radius: 0;
        padding: 0.75rem 1rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .nursing-reports-tabs .nav-link:hover {
        color: var(--hospital-primary);
        background: rgba(0, 123, 255, 0.05);
    }

    .nursing-reports-tabs .nav-link.active {
        color: var(--hospital-primary);
        border-bottom-color: var(--hospital-primary);
        background: transparent;
    }

    .nursing-reports-tabs .nav-link i {
        font-size: 1.1rem;
    }

    /* Reports Content Area */
    .nursing-reports-content {
        flex: 1;
        overflow-y: auto;
        background: #f8f9fa;
    }

    /* Stat Cards */
    .nr-stat-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.2s;
        height: 100%;
    }

    .nr-stat-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .nr-stat-card-sm {
        padding: 1rem;
    }

    .nr-stat-card-sm .nr-stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.25rem;
    }

    .nr-stat-card-sm .nr-stat-content h4 {
        font-size: 1.5rem;
    }

    .nr-stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        flex-shrink: 0;
    }

    .nr-stat-content {
        flex: 1;
        min-width: 0;
    }

    .nr-stat-content h3,
    .nr-stat-content h4 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        color: #1a1a2e;
        line-height: 1.2;
    }

    .nr-stat-content p {
        margin: 0;
        font-size: 0.85rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Reports Tables */
    #nursing-reports-view .table {
        margin-bottom: 0;
    }

    #nursing-reports-view .table thead th {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        font-size: 0.85rem;
        color: #495057;
        white-space: nowrap;
    }

    #nursing-reports-view .table tbody td {
        vertical-align: middle;
        font-size: 0.9rem;
    }

    /* Reports Cards */
    #nursing-reports-view .card {
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        border-radius: 0.75rem;
    }

    #nursing-reports-view .card-header {
        background: white;
        border-bottom: 1px solid #f1f3f5;
        padding: 1rem 1.25rem;
    }

    #nursing-reports-view .card-header h6 {
        margin: 0;
        font-weight: 600;
        color: #1a1a2e;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Status badges in tables */
    .nr-status-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .nr-status-normal { background: #d4edda; color: #155724; }
    .nr-status-warning { background: #fff3cd; color: #856404; }
    .nr-status-critical { background: #f8d7da; color: #721c24; }
    .nr-status-late { background: #fff3cd; color: #856404; }
    .nr-status-ontime { background: #d4edda; color: #155724; }
    .nr-status-missed { background: #f8d7da; color: #721c24; }

    /* Sub-tabs styling */
    #nr-inj-subtabs .nav-link {
        border-radius: 0.5rem;
        padding: 0.5rem 1rem;
        font-weight: 500;
    }

    #nr-inj-subtabs .nav-link.active {
        background: var(--hospital-primary);
        color: white;
    }

    /* Charts container */
    #nursing-reports-view canvas {
        max-height: 300px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .nursing-reports-tabs .nav-link {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }

        .nursing-reports-tabs .nav-link span {
            display: none;
        }

        .nr-stat-card {
            padding: 1rem;
        }

        .nr-stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
        }

        .nr-stat-content h3 {
            font-size: 1.25rem;
        }
    }

    /* Procedures Tab Styles */
    #procedures-tab {
        padding: 1rem;
    }

    .procedures-container h4 {
        margin-bottom: 0.5rem;
        color: #333;
    }

    .procedures-table-wrapper {
        max-width: 100%;
        position: relative;
    }

    #procedures-tab .dataTables_wrapper {
        max-width: 100%;
        position: relative !important;
    }

    #procedures-tab table.dataTable {
        width: 100% !important;
    }

    /* Procedure Card Styles */
    .procedure-card {
        background: white;
        border: 1px solid #e9ecef;
        border-left: 4px solid var(--hospital-primary, #007bff);
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }

    .procedure-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .procedure-card.status-completed { border-left-color: #28a745; }
    .procedure-card.status-in_progress { border-left-color: #ffc107; }
    .procedure-card.status-scheduled { border-left-color: #17a2b8; }
    .procedure-card.status-cancelled { border-left-color: #dc3545; opacity: 0.7; }

    .procedure-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
    }

    .procedure-name {
        font-weight: 600;
        color: #212529;
        font-size: 1rem;
    }

    .procedure-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .procedure-status.status-requested { background: #e9ecef; color: #495057; }
    .procedure-status.status-scheduled { background: #cce5ff; color: #004085; }
    .procedure-status.status-in_progress { background: #fff3cd; color: #856404; }
    .procedure-status.status-completed { background: #d4edda; color: #155724; }
    .procedure-status.status-cancelled { background: #f8d7da; color: #721c24; }

    .procedure-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 0.5rem;
    }

    .procedure-meta-item {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .procedure-meta-item i {
        color: var(--hospital-primary, #007bff);
    }

    .procedure-actions {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #e9ecef;
    }

    /* ========== BATCH SELECTION STYLES ========== */
    .batch-select-dropdown {
        min-width: 200px;
        font-size: 0.875rem;
    }

    .batch-select-dropdown option {
        padding: 8px;
    }

    .batch-select-dropdown option.batch-expiring-soon {
        background-color: #fff3cd;
        color: #856404;
    }

    .batch-select-dropdown option.batch-expired {
        background-color: #f8d7da;
        color: #721c24;
    }

    .batch-info-display {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border: 1px solid #90caf9;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }

    .batch-info-display .batch-number {
        font-weight: 600;
        color: #1565c0;
    }

    .batch-info-display .batch-expiry {
        color: #666;
    }

    .batch-info-display .batch-qty {
        background: white;
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-weight: 600;
    }

    .batch-fifo-badge {
        background: linear-gradient(135deg, #4fc3f7 0%, #03a9f4 100%);
        color: white;
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .batch-manual-select-btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        border: 1px dashed #6c757d;
        background: transparent;
        color: #6c757d;
        border-radius: 0.25rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .batch-manual-select-btn:hover {
        border-color: var(--hospital-primary);
        color: var(--hospital-primary);
        background: rgba(0, 123, 255, 0.05);
    }

    .batch-cell {
        min-width: 180px;
    }

    .batch-loading {
        color: #6c757d;
        font-size: 0.8rem;
    }

    .batch-insufficient {
        color: #dc3545;
        font-size: 0.8rem;
    }

    /* Batch dropdown in tables */
    #injection-selected-drugs .batch-cell select,
    #consumable-batch-select,
    #modal-vaccine-batch-select {
        font-size: 0.85rem;
        padding: 0.375rem 0.5rem;
        border-radius: 0.375rem;
        border: 2px solid #e9ecef;
        background-color: #f8f9fa;
        transition: all 0.2s;
    }

    #injection-selected-drugs .batch-cell select:focus,
    #consumable-batch-select:focus,
    #modal-vaccine-batch-select:focus {
        border-color: #03a9f4;
        box-shadow: 0 0 0 3px rgba(3, 169, 244, 0.15);
        outline: none;
    }

    /* Batch option styling */
    .batch-option-expiring {
        background: #fff3cd !important;
    }

    .batch-option-expired {
        background: #f8d7da !important;
        text-decoration: line-through;
    }

</style>

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
        <strong>No ward store resolved.</strong>
        <span class="ms-1">Administration from ward stock is blocked. Start a shift or contact admin.</span>
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
            <h6><i class="mdi mdi-clipboard-list"></i> PATIENT QUEUES</h6>
            <div class="queue-item" data-filter="admitted">
                <span class="queue-item-label"><i class="mdi mdi-bed"></i> Admitted Patients</span>
                <span class="queue-count billing" id="queue-admitted-count">0</span>
            </div>
            <div class="queue-item" data-filter="vitals">
                <span class="queue-item-label"><i class="mdi mdi-heart-pulse"></i> Vitals Queue</span>
                <span class="queue-count sample" id="queue-vitals-count">0</span>
            </div>
            <div class="queue-item" data-filter="bed-requests">
                <span class="queue-item-label"><i class="mdi mdi-bed-empty"></i> Bed Requests</span>
                <span class="queue-count info" id="queue-bed-count">0</span>
            </div>
            <div class="queue-item" data-filter="discharge-requests">
                <span class="queue-item-label"><i class="mdi mdi-account-minus"></i> Discharge Requests</span>
                <span class="queue-count results" id="queue-discharge-count">0</span>
            </div>
            <div class="queue-item" data-filter="medication-due">
                <span class="queue-item-label"><i class="mdi mdi-pill"></i> Medication Due</span>
                <span class="queue-count results" id="queue-medication-count">0</span>
            </div>
            <div class="queue-item" data-filter="emergency" style="border-left: 3px solid #dc3545;">
                <span class="queue-item-label"><i class="mdi mdi-ambulance"></i> Emergency Queue</span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="deceased" style="border-left: 3px solid #6c757d;">
                <span class="queue-item-label"><i class="mdi mdi-emoticon-dead-outline"></i> Deceased (Last Office)</span>
                <span class="queue-count" id="queue-deceased-count" style="background: #6c757d; color: #fff;">0</span>
            </div>
            <button class="btn-queue-all" id="refresh-queues-btn">
                <i class="mdi mdi-refresh"></i> Refresh Queues
            </button>
        </div>

        <div class="quick-actions">
            <h6><i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS</h6>

            <!-- Ward & Bed Management -->
            <button class="quick-action-btn" id="btn-ward-dashboard">
                <i class="mdi mdi-hospital-building text-primary"></i>
                <span>Ward Dashboard</span>
            </button>

            <!-- Quick Vitals (opens modal for fast entry) -->
            <button class="quick-action-btn" id="btn-quick-vitals" disabled title="Select a patient first">
                <i class="mdi mdi-heart-pulse text-danger"></i>
                <span>Quick Vitals</span>
            </button>

            <!-- Medication Rounds -->
            <button class="quick-action-btn" data-filter="medication-due">
                <i class="mdi mdi-pill text-warning"></i>
                <span>Medication Round</span>
                <span class="badge bg-danger ms-auto" id="med-round-badge" style="display: none;">0</span>
            </button>

            <!-- Shift Handover -->
            <button class="quick-action-btn" id="btn-shift-handover">
                <i class="mdi mdi-clipboard-text text-info"></i>
                <span>Shift Handover</span>
            </button>

            <!-- Nursing Reports -->
            <button class="quick-action-btn" id="btn-nursing-reports">
                <i class="mdi mdi-chart-box-outline text-success"></i>
                <span>Nursing Reports</span>
            </button>

            <!-- Admission/Discharge Summary -->
            <button class="quick-action-btn" id="btn-admission-summary">
                <i class="mdi mdi-account-switch text-secondary"></i>
                <span>Admissions Today</span>
            </button>
            @if(appsettings()->enable_ei_nursing)
<button class="quick-action-btn" onclick="showEmergencyIntakeModal()">
                <i class="mdi mdi-ambulance text-danger"></i>
                <span>Emergency Intake</span>
            </button>
@endif
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
                <button class="btn-clinical-context" id="btn-clinical-context" disabled title="Select a patient first">
                    <i class="fa fa-heartbeat"></i> Clinical Context
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state">
            <i class="fa fa-user-nurse"></i>
            <h3>Select a patient to begin</h3>
            <p>Use the search box or select from patient queues</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                📋 View All Pending Requests
            </button>
        </div>

        <!-- Queue View -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-format-list-bulleted"></i> Lab Queue</h4>
                <button class="btn-close-queue" id="btn-close-queue">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content">
                <table class="table" id="queue-datatable" style="width: 100%">
                    <thead>
                        <tr>
                            <th>Queue Items</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <!-- Reports View (Full Screen - Global Access) -->
        <div class="queue-view" id="reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box"></i> Laboratory Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 1.5rem;">
                <!-- Filter Panel -->
                <div class="reports-filter-panel card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-filter"></i> Filters</h6>
                    </div>
                    <div class="card-body">
                        <form id="reports-filter-form">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="report-date-from"><i class="mdi mdi-calendar"></i> Date From</label>
                                    <input type="date" class="form-control" id="report-date-from" name="date_from">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="report-date-to"><i class="mdi mdi-calendar"></i> Date To</label>
                                    <input type="date" class="form-control" id="report-date-to" name="date_to">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="report-status-filter"><i class="mdi mdi-filter-variant"></i> Status</label>
                                    <select class="form-control" id="report-status-filter" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="1">Awaiting Billing</option>
                                        <option value="2">Awaiting Sample</option>
                                        <option value="3">Awaiting Results</option>
                                        <option value="4">Completed</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="report-service-filter"><i class="mdi mdi-test-tube"></i> Service</label>
                                    <select class="form-control" id="report-service-filter" name="service_id">
                                        <option value="">All Services</option>
                                        <!-- Services will be populated via JS -->
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="report-doctor-filter"><i class="mdi mdi-doctor"></i> Requesting Doctor</label>
                                    <select class="form-control" id="report-doctor-filter" name="doctor_id">
                                        <option value="">All Doctors</option>
                                        <!-- Doctors will be populated via JS -->
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="report-hmo-filter"><i class="mdi mdi-hospital-building"></i> HMO</label>
                                    <select class="form-control" id="report-hmo-filter" name="hmo_id">
                                        <option value="">All HMOs</option>
                                        <!-- HMOs will be populated via JS -->
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="report-patient-search"><i class="mdi mdi-account-search"></i> Patient Search</label>
                                    <input type="text" class="form-control" id="report-patient-search" name="patient_search" placeholder="File no or name...">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-12 text-right">
                                    <button type="button" class="btn btn-secondary" id="clear-report-filters">
                                        <i class="mdi mdi-refresh"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-primary">
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
                        <a class="nav-link active" id="reports-overview-tab" data-toggle="tab" href="#overview-content" role="tab" aria-controls="overview-content" aria-selected="true">
                            <i class="mdi mdi-view-dashboard"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="detailed-report-tab" data-toggle="tab" href="#detailed-report-content" role="tab" aria-controls="detailed-report-content" aria-selected="false">
                            <i class="mdi mdi-table"></i> Detailed Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="analytics-tab" data-toggle="tab" href="#analytics-content" role="tab" aria-controls="analytics-content" aria-selected="false">
                            <i class="mdi mdi-chart-line"></i> Analytics
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="reports-tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview-content" role="tabpanel" aria-labelledby="reports-overview-tab">
                        <div class="reports-container">
                            <!-- Summary Statistics Cards -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            <i class="mdi mdi-clipboard-list"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-total-requests">0</h3>
                                            <p>Total Requests</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-completed">0</h3>
                                            <p>Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                            <i class="mdi mdi-clock"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-pending">0</h3>
                                            <p>Pending</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                            <i class="mdi mdi-timer"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-avg-tat">0</h3>
                                            <p>Avg TAT</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts and Top Services -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-bar"></i> Requests by Status</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="status-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-line"></i> Monthly Trends</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="trends-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Services -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card-modern" id="top-services-card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-test-tube"></i> Top 10 Lab Services</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="top-services-list">
                                                <p class="text-muted">Loading...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Report Tab -->
                    <div class="tab-pane fade" id="detailed-report-content" role="tabpanel" aria-labelledby="detailed-report-tab">
                        <div class="reports-container">
                            <!-- DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Detailed Report</h6>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="export-excel">
                                            <i class="mdi mdi-file-excel"></i> Excel
                                        </button>
                                        <button class="btn btn-sm btn-danger" id="export-pdf">
                                            <i class="mdi mdi-file-pdf"></i> PDF
                                        </button>
                                        <button class="btn btn-sm btn-info" id="print-report">
                                            <i class="mdi mdi-printer"></i> Print
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="reports-datatable" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>File No</th>
                                                    <th>Patient</th>
                                                    <th>Service</th>
                                                    <th>Doctor</th>
                                                    <th>HMO</th>
                                                    <th>Status</th>
                                                    <th>TAT</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics-content" role="tabpanel" aria-labelledby="analytics-tab">
                        <div class="reports-container">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-doctor"></i> Top Requesting Doctors</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="top-doctors-list">
                                                <p class="text-muted">Loading...</p>
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

        <!-- Nursing Reports View -->
        <div class="queue-view" id="nursing-reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box-outline"></i> Nursing Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-nursing-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 0; overflow: hidden;">
                <!-- Global Filters Panel -->
                <div class="nursing-reports-filters p-3 bg-light border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-calendar-range"></i> Date Range
                            </label>
                            <select class="form-control form-control-modern" id="nr-date-range">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="7days" selected>Last 7 Days</option>
                                <option value="30days">Last 30 Days</option>
                                <option value="thismonth">This Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="nr-custom-dates" style="display: none;">
                            <label class="form-label-modern">From - To</label>
                            <div class="d-flex gap-2">
                                <input type="date" class="form-control form-control-modern" id="nr-date-from" style="height: 40px !important;">
                                <input type="date" class="form-control form-control-modern" id="nr-date-to" style="height: 40px !important;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-hospital-building"></i> Ward
                            </label>
                            <select class="form-control form-control-modern" id="nr-ward-filter">
                                <option value="">All Wards</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-account-nurse"></i> Nurse
                            </label>
                            <select class="form-control form-control-modern" id="nr-nurse-filter">
                                <option value="">All Nurses</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-modern">
                                <i class="mdi mdi-clock-outline"></i> Shift
                            </label>
                            <select class="form-control form-control-modern" id="nr-shift-filter">
                                <option value="">All Shifts</option>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="night">Night</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary btn-modern flex-grow-1" id="nr-apply-filters">
                                <i class="mdi mdi-filter"></i> Apply
                            </button>
                            <button class="btn btn-outline-secondary btn-modern" id="nr-reset-filters" title="Reset Filters">
                                <i class="mdi mdi-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reports Tabs -->
                <div class="nursing-reports-tabs-wrapper">
                    <ul class="nav nav-tabs nursing-reports-tabs" id="nursingReportsTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="nr-activity-tab" data-toggle="tab" href="#nr-activity" role="tab">
                                <i class="mdi mdi-chart-timeline-variant"></i> Activity Summary
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-vitals-tab" data-toggle="tab" href="#nr-vitals" role="tab">
                                <i class="mdi mdi-heart-pulse"></i> Vitals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-medications-tab" data-toggle="tab" href="#nr-medications" role="tab">
                                <i class="mdi mdi-pill"></i> Medications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-injections-tab" data-toggle="tab" href="#nr-injections" role="tab">
                                <i class="mdi mdi-needle"></i> Injections
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-io-tab" data-toggle="tab" href="#nr-io" role="tab">
                                <i class="mdi mdi-water"></i> I/O Balance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-notes-tab" data-toggle="tab" href="#nr-notes" role="tab">
                                <i class="mdi mdi-note-text"></i> Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-shifts-tab" data-toggle="tab" href="#nr-shifts" role="tab">
                                <i class="mdi mdi-account-clock"></i> Shift Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="nr-occupancy-tab" data-toggle="tab" href="#nr-occupancy" role="tab">
                                <i class="mdi mdi-bed"></i> Ward Occupancy
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tab Content -->
                <div class="tab-content nursing-reports-content" id="nursingReportsContent">
                    <!-- Activity Summary Tab -->
                    <div class="tab-pane fade show active" id="nr-activity" role="tabpanel">
                        <div class="p-3">
                            <!-- Stats Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-account-group"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-patients">0</h3>
                                            <p>Patients Served</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-heart-pulse"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-vitals">0</h3>
                                            <p>Vitals Recorded</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-pill"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-medications">0</h3>
                                            <p>Medications Given</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-needle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-injections">0</h3>
                                            <p>Injections</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-shield-check"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-immunizations">0</h3>
                                            <p>Immunizations</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-secondary">
                                            <i class="mdi mdi-note-text"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-notes">0</h3>
                                            <p>Notes Written</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon" style="background: #6f42c1;">
                                            <i class="mdi mdi-swap-horizontal"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-handovers">0</h3>
                                            <p>Handovers</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon" style="background: #e83e8c;">
                                            <i class="mdi mdi-clock-check"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-shifts">0</h3>
                                            <p>Shifts Completed</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts Row -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <div class="card-modern h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-line"></i> Activity Trend</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="nr-activity-trend-chart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card-modern h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-pie"></i> Activity Distribution</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="nr-activity-distribution-chart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Performers & Peak Hours -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="mdi mdi-trophy"></i> Top Performers</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0" id="nr-top-performers-table">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Nurse</th>
                                                            <th>Actions</th>
                                                            <th>Patients</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-clock-outline"></i> Peak Activity Hours</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="nr-peak-hours-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vitals Tab -->
                    <div class="tab-pane fade" id="nr-vitals" role="tabpanel">
                        <div class="p-3">
                            <!-- Vitals Summary Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-heart-pulse"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-total">0</h4>
                                            <p>Total Records</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-alert-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-abnormal">0</h4>
                                            <p>Abnormal Readings</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-thermometer-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-fever">0</h4>
                                            <p>Fever Cases</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-blood-bag"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-hypertension">0</h4>
                                            <p>High BP Cases</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vitals DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Vitals Records</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-vitals">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-vitals-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Patient</th>
                                                    <th>Ward/Bed</th>
                                                    <th>BP</th>
                                                    <th>HR</th>
                                                    <th>Temp</th>
                                                    <th>RR</th>
                                                    <th>SpO2</th>
                                                    <th>Recorded By</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medications Tab -->
                    <div class="tab-pane fade" id="nr-medications" role="tabpanel">
                        <div class="p-3">
                            <!-- Medication Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-pill"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-total">0</h4>
                                            <p>Total Administered</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-ontime">0%</h4>
                                            <p>On-Time Rate</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-clock-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-late">0</h4>
                                            <p>Late Administrations</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-close-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-missed">0</h4>
                                            <p>Missed Doses</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Medications DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Medication Administration Log</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-meds">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-medications-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Patient</th>
                                                    <th>Medication</th>
                                                    <th>Dose</th>
                                                    <th>Route</th>
                                                    <th>Scheduled</th>
                                                    <th>Administered By</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Injections Tab -->
                    <div class="tab-pane fade" id="nr-injections" role="tabpanel">
                        <div class="p-3">
                            <!-- Sub-tabs for Injections and Immunizations -->
                            <ul class="nav nav-pills mb-3" id="nr-inj-subtabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="pill" href="#nr-inj-list">
                                        <i class="mdi mdi-needle"></i> Injections
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="pill" href="#nr-imm-list">
                                        <i class="mdi mdi-shield-check"></i> Immunizations
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <!-- Injections List -->
                                <div class="tab-pane fade show active" id="nr-inj-list">
                                    <div class="card-modern">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="mdi mdi-needle"></i> Injection Records</h6>
                                            <button class="btn btn-sm btn-success" id="nr-export-injections">
                                                <i class="mdi mdi-file-excel"></i> Export
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="nr-injections-table" style="width: 100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Date/Time</th>
                                                            <th>Patient</th>
                                                            <th>Drug</th>
                                                            <th>Dose</th>
                                                            <th>Route</th>
                                                            <th>Site</th>
                                                            <th>Batch No</th>
                                                            <th>Administered By</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Immunizations List -->
                                <div class="tab-pane fade" id="nr-imm-list">
                                    <div class="card-modern">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="mdi mdi-shield-check"></i> Immunization Records</h6>
                                            <button class="btn btn-sm btn-success" id="nr-export-immunizations">
                                                <i class="mdi mdi-file-excel"></i> Export
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="nr-immunizations-table" style="width: 100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Date/Time</th>
                                                            <th>Patient</th>
                                                            <th>Age</th>
                                                            <th>Vaccine</th>
                                                            <th>Dose #</th>
                                                            <th>Batch No</th>
                                                            <th>Manufacturer</th>
                                                            <th>Administered By</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- I/O Balance Tab -->
                    <div class="tab-pane fade" id="nr-io" role="tabpanel">
                        <div class="p-3">
                            <!-- I/O Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-water"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-records">0</h4>
                                            <p>Total Records</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-plus-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-positive">0</h4>
                                            <p>Positive Balance</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-minus-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-negative">0</h4>
                                            <p>Negative Balance</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-critical">0</h4>
                                            <p>Critical Imbalance</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- I/O DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Intake/Output Records</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-io">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-io-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Patient</th>
                                                    <th>Ward/Bed</th>
                                                    <th>Total Intake</th>
                                                    <th>Total Output</th>
                                                    <th>Balance</th>
                                                    <th>Status</th>
                                                    <th>Recorded By</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes Tab -->
                    <div class="tab-pane fade" id="nr-notes" role="tabpanel">
                        <div class="p-3">
                            <!-- Notes Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-note-text"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-notes-total">0</h4>
                                            <p>Total Notes</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-alert-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-notes-critical">0</h4>
                                            <p>Critical/Incident</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-account-group"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-notes-patients">0</h4>
                                            <p>Patients Documented</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Nursing Notes Log</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-notes">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-notes-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Patient</th>
                                                    <th>Note Type</th>
                                                    <th>Summary</th>
                                                    <th>Written By</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shift Performance Tab -->
                    <div class="tab-pane fade" id="nr-shifts" role="tabpanel">
                        <div class="p-3">
                            <!-- Shift Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-clock-check"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-total">0</h4>
                                            <p>Shifts Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-timer"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-avg-duration">0h</h4>
                                            <p>Avg Duration</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-swap-horizontal"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-handovers">0%</h4>
                                            <p>Handover Rate</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-clock-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-overdue">0</h4>
                                            <p>Overdue Shifts</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Shifts DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Shift History</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-shifts">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-shifts-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Nurse</th>
                                                    <th>Shift Type</th>
                                                    <th>Ward</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                    <th>Duration</th>
                                                    <th>Actions</th>
                                                    <th>Handover</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ward Occupancy Tab -->
                    <div class="tab-pane fade" id="nr-occupancy" role="tabpanel">
                        <div class="p-3">
                            <!-- Occupancy Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-bed"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-total">0</h4>
                                            <p>Total Beds</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-bed-empty"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-occupied">0</h4>
                                            <p>Occupied</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-available">0</h4>
                                            <p>Available</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-wrench"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-maintenance">0</h4>
                                            <p>Maintenance</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Admission/Discharge Stats -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-account-plus"></i> Admissions</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-around text-center">
                                                <div>
                                                    <h3 class="text-success" id="nr-admissions-today">0</h3>
                                                    <small>Today</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-primary" id="nr-admissions-period">0</h3>
                                                    <small>This Period</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-info" id="nr-avg-los">0d</h3>
                                                    <small>Avg LOS</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-account-minus"></i> Discharges</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-around text-center">
                                                <div>
                                                    <h3 class="text-success" id="nr-discharges-today">0</h3>
                                                    <small>Today</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-primary" id="nr-discharges-period">0</h3>
                                                    <small>This Period</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-warning" id="nr-pending-discharges">0</h3>
                                                    <small>Pending</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ward Breakdown Table -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-hospital-building"></i> Ward Breakdown</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-occupancy">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-occupancy-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Ward</th>
                                                    <th>Total Beds</th>
                                                    <th>Occupied</th>
                                                    <th>Available</th>
                                                    <th>Maintenance</th>
                                                    <th>Occupancy %</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                </div>
                <button class="btn-expand-patient mt-2" id="btn-expand-patient" title="Show more details">
                    <span class="btn-expand-text">more biodata</span>
                    <i class="mdi mdi-chevron-down"></i>
                </button>
            </div>
            <div class="patient-details-expanded" id="patient-details-expanded">
                <div class="patient-details-grid" id="patient-details-grid"></div>
            </div>
        </div>

        <!-- Workspace Content -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="overview">
                    <i class="mdi mdi-account-details"></i>
                    <span>Overview</span>
                </button>
                <button class="workspace-tab" data-tab="clinical-story">
                    <i class="fa fa-history"></i>
                    <span>Clinical Story</span>
                </button>
                <button class="workspace-tab" data-tab="notes">
                    <i class="mdi mdi-note-text"></i>
                    <span>Nursing Notes</span>
                </button>
                <button class="workspace-tab" data-tab="vitals">
                    <i class="mdi mdi-heart-pulse"></i>
                    <span>Vitals</span>
                </button>
                <button class="workspace-tab" data-tab="medication">
                    <i class="mdi mdi-pill"></i>
                    <span>Medication Chart</span>
                </button>
                <button class="workspace-tab" data-tab="intake-output">
                    <i class="mdi mdi-water"></i>
                    <span>I/O Chart</span>
                </button>
                <button class="workspace-tab" data-tab="injection">
                    <i class="mdi mdi-needle"></i>
                    <span>Injections</span>
                </button>
                <button class="workspace-tab" data-tab="immunization">
                    <i class="mdi mdi-shield-check"></i>
                    <span>Immunization</span>
                </button>
                <button class="workspace-tab" data-tab="procedures">
                    <i class="mdi mdi-medical-bag"></i>
                    <span>Procedures</span>
                </button>
                <button class="workspace-tab" data-tab="clinical-requests">
                    <i class="mdi mdi-clipboard-pulse"></i>
                    <span>Clinical Requests</span>
                </button>
                <button class="workspace-tab" data-tab="billing">
                    <i class="mdi mdi-cash-register"></i>
                    <span>Billing</span>
                </button>
            </div>

            <!-- Overview Tab -->
            <div class="workspace-tab-content active" id="overview-tab">
                <div class="overview-container p-3">
                    <div id="patient-overview-content">
                        <!-- Patient Summary Row -->
                        <div class="row">
                            <!-- Patient Demographics Card -->
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-primary text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-account"></i> Patient Information</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-patient-info">
                                            <p class="text-muted text-center py-3">Select a patient to view details</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Admission Status Card -->
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-info text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-bed"></i> Admission Status</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-admission-info">
                                            <p class="text-muted text-center py-3">No admission data</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vital Signs Card -->
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-success text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-heart-pulse"></i> Latest Vitals</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-vitals-info">
                                            <p class="text-muted text-center py-3">No vitals recorded</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions Row -->
                        <div class="row">
                            <!-- Pending Medications -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-warning py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="mdi mdi-pill"></i> Pending Medications</h6>
                                        <span class="badge badge-light" id="overview-pending-meds-count">0</span>
                                    </div>
                                    <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                        <div id="overview-pending-meds">
                                            <p class="text-muted text-center py-2">No pending medications</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Today's Tasks -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-secondary text-white py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="mdi mdi-clipboard-check"></i> Today's Tasks</h6>
                                        <span class="badge badge-light" id="overview-tasks-count">0</span>
                                    </div>
                                    <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                        <div id="overview-tasks">
                                            <p class="text-muted text-center py-2">No tasks pending</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Latest Notes Row -->
                        <div class="row">
                            <!-- Latest Nurse Note -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-purple text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-note-text"></i> Latest Nurse Note</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-nurse-note">
                                            <p class="text-muted text-center py-2">No nursing notes</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Latest Doctor Note -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-dark text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-stethoscope"></i> Latest Doctor Note</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-doctor-note">
                                            <p class="text-muted text-center py-2">No doctor notes</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Allergies & Alerts Row -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="card-modern border-danger">
                                    <div class="card-header bg-danger text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-alert-circle"></i> Allergies & Alerts</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-allergies">
                                            <p class="text-muted text-center py-2">No allergies or alerts recorded</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clinical Story Tab -->
            <div class="workspace-tab-content" id="clinical-story-tab">
                <div class="clinical-story-container p-3">
                    @include('admin.partials.clinical_story')
                </div>
            </div>

            <!-- Vitals Tab -->
            <div class="workspace-tab-content" id="vitals-tab">
                <div class="vitals-container p-3">
                    @include('admin.partials.unified_vitals', ['patient' => $currentPatient ?? null])
                </div>
            </div>

            <!-- Medication Chart Tab -->
            <div class="workspace-tab-content" id="medication-tab">
                <div class="medication-container p-3">
                    <div id="medication-chart-content">
                        @include('admin.patients.partials.nurse_chart_medication_enhanced', ['patient' => $currentPatient ?? null])
                    </div>
                </div>
            </div>

            <!-- I/O Chart Tab -->
            <div class="workspace-tab-content" id="intake-output-tab">
                <div class="io-container p-3">
                    <div id="io-chart-content">
                        @include('admin.patients.partials.nurse_chart_intake_output', ['patient' => $currentPatient ?? null])
                    </div>
                </div>
            </div>

            <!-- Injection Service Tab -->
            <div class="workspace-tab-content" id="injection-tab">
                <div class="injection-container p-3">
                    <!-- Sub-tabs for Injection -->
                    <ul class="nav nav-tabs mb-3" id="injection-sub-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="injection-administer-tab" data-toggle="tab" href="#injection-administer" role="tab">
                                <i class="mdi mdi-plus-circle"></i> Administer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="injection-history-tab" data-toggle="tab" href="#injection-history" role="tab">
                                <i class="mdi mdi-history"></i> History
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="injection-sub-content">
                        <!-- Administer Sub-tab -->
                        <div class="tab-pane fade show active" id="injection-administer" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-needle"></i> Administer Injection</h6>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" id="injection-drug-source" value="pharmacy_dispensed">

                                    <!-- Drug Source Selector -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Drug Source</label>
                                        <div class="btn-group w-100" role="group" aria-label="Injection drug source">
                                            <button type="button" class="btn btn-outline-primary active" data-inj-source="pharmacy_dispensed">Pharmacy Dispensed</button>
                                            <button type="button" class="btn btn-outline-secondary" data-inj-source="patient_own">Patient's Own</button>
                                            <button type="button" class="btn btn-outline-info" data-inj-source="ward_stock">Ward Stock</button>
                                        </div>
                                    </div>

                                    <!-- Pharmacy Dispensed Section -->
                                    <div class="mb-3 source-section" id="inj-source-pharmacy">
                                        <div class="alert alert-info py-2 mb-2"><i class="mdi mdi-pill"></i> Select dispensed prescriptions to chart.</div>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-8">
                                                <label for="injection-rx-select" class="form-label">Dispensed Prescriptions</label>
                                                <select class="form-control" id="injection-rx-select">
                                                    <option value="">-- Loading prescriptions --</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <button type="button" class="btn btn-success mt-4 w-100" id="injection-add-rx">
                                                    <i class="mdi mdi-plus"></i> Add to list
                                                </button>
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-1" id="injection-rx-summary" style="display:none;"></div>
                                    </div>

                                    <!-- Patient Own Section -->
                                    <div class="mb-3 source-section" id="inj-source-patient" style="display:none;">
                                        <div class="alert alert-warning py-2 mb-3"><i class="mdi mdi-account-alert"></i> Record patient-supplied drug details. No stock will be deducted.</div>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label">Drug Name</label>
                                                <input type="text" class="form-control" id="inj-external-name" placeholder="Patient supplied drug">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Quantity</label>
                                                <input type="number" step="0.01" class="form-control" id="inj-external-qty" placeholder="1">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Batch (optional)</label>
                                                <input type="text" class="form-control" id="inj-external-batch" placeholder="Batch #">
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Expiry (optional)</label>
                                                <input type="date" class="form-control" id="inj-external-expiry">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Source Note (optional)</label>
                                                <input type="text" class="form-control" id="inj-external-note" placeholder="Where obtained / remarks">
                                            </div>
                                        </div>
                                        {{-- §7.2: Add to List button for patient's own virtual row --}}
                                        <div class="row mt-3">
                                            <div class="col text-end">
                                                <button type="button" class="btn btn-warning btn-sm" id="btn-add-patient-own-injection" onclick="addPatientOwnInjectionRow()">
                                                    <i class="mdi mdi-plus"></i> Add Drug to List
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ward Stock Section -->
                                    <div class="source-section" id="inj-source-ward" style="display:none;">
                                        <div class="store-selection-panel mb-4 p-3 rounded" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 2px solid #90caf9;">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold mb-2" style="font-size: 1rem;">
                                                        <i class="mdi mdi-store text-primary"></i> Select Ward Store
                                                    </label>
                                                    <select id="injection-store" class="form-control form-control-lg" style="border: 2px solid #1976d2; font-weight: 500;">
                                                        @if($resolvedStore ?? null)
                                                            <option value="{{ $resolvedStore->id }}" selected>{{ $resolvedStore->store_name }}</option>
                                                        @else
                                                            <option value="">-- No store assigned --</option>
                                                        @endif
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <div id="injection-store-info" class="p-3 bg-white rounded shadow-sm" style="display: none;">
                                                        <h6 class="text-primary mb-2"><i class="mdi mdi-package-variant"></i> Selected Store Stock</h6>
                                                        <div id="injection-store-stock-summary" class="small">
                                                            <!-- Stock will show here when items are selected -->
                                                        </div>
                                                    </div>
                                                    <div id="injection-store-placeholder" class="p-3 text-muted text-center">
                                                        <i class="mdi mdi-arrow-left-bold mdi-24px"></i>
                                                        <p class="mb-0 small">Select store first, then add drugs</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- §5.3: Bill Patient checkbox — unchecked = hospital absorbs cost, checked = creates POSR via tariff pipeline --}}
                                        <div class="form-check mt-2 ms-1">
                                            <input class="form-check-input" type="checkbox" id="injection-bill-patient" value="1">
                                            <label class="form-check-label" for="injection-bill-patient">
                                                <i class="mdi mdi-receipt text-info"></i> <strong>Bill Patient</strong>
                                                <small class="text-muted d-block">Creates a billing entry for this item (applies HMO tariff if applicable)</small>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Step 2: Drug Search (for ward stock / patient-own) -->
                                    <div class="form-group mb-3 inj-non-pharmacy">
                                        <label for="injection-drug-search"><i class="mdi mdi-magnify"></i> Step 2: Search Drug/Product</label>
                                        <input type="text" class="form-control" id="injection-drug-search"
                                               placeholder="Type to search for any drug or product..." autocomplete="off">
                                        <ul class="list-group" id="injection-drug-results"
                                            style="display: none; position: absolute; z-index: 1000; max-height: 250px; overflow-y: auto; width: calc(100% - 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></ul>
                                        <small class="text-muted">Search hospital inventory. For dispensed prescriptions, use the list above.</small>
                                    </div>

                                    <!-- Selected Drugs Table with Stock & Batch Column -->
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered table-striped" id="injection-selected-drugs">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th width="4%">#</th>
                                                    <th width="20%">Drug/Product</th>
                                                    <th width="8%">Qty</th>
                                                    <th width="18%">
                                                        <i class="mdi mdi-package-variant"></i> Batch
                                                        <span class="badge badge-info badge-sm ml-1" title="FIFO Recommended">FIFO</span>
                                                    </th>
                                                    <th width="10%">Stock</th>
                                                    <th width="12%">Price</th>
                                                    <th width="13%">HMO</th>
                                                    <th width="10%">Dose</th>
                                                    <th width="5%">*</th>
                                                </tr>
                                            </thead>
                                            <tbody id="injection-selected-body">
                                                <!-- Selected drugs will be added here with batch dropdown -->
                                            </tbody>
                                            <tfoot>
                                                <tr class="bg-light">
                                                    <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                                    <td id="injection-total-price"><strong>₦0.00</strong></td>
                                                    <td id="injection-total-coverage">-</td>
                                                    <td colspan="2"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Step 3: Administration Details -->
                                    <form id="injection-form">
                                        <h6 class="text-muted mb-3"><i class="mdi mdi-clipboard-text"></i> Step 3: Administration Details</h6>
                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label for="injection-route"><i class="mdi mdi-routes"></i> Route *</label>
                                                <select class="form-control" id="injection-route" required>
                                                    <option value="">Select Route</option>
                                                    <option value="IM">Intramuscular (IM)</option>
                                                    <option value="IV">Intravenous (IV)</option>
                                                    <option value="SC">Subcutaneous (SC)</option>
                                                    <option value="ID">Intradermal (ID)</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="injection-site"><i class="mdi mdi-map-marker"></i> Site *</label>
                                                <select class="form-control" id="injection-site" required>
                                                    <option value="">Select Site</option>
                                                    <option value="Left Arm">Left Arm (Deltoid)</option>
                                                    <option value="Right Arm">Right Arm (Deltoid)</option>
                                                    <option value="Left Thigh">Left Thigh (Vastus Lateralis)</option>
                                                    <option value="Right Thigh">Right Thigh (Vastus Lateralis)</option>
                                                    <option value="Left Buttock">Left Buttock (Gluteus)</option>
                                                    <option value="Right Buttock">Right Buttock (Gluteus)</option>
                                                    <option value="Abdomen">Abdomen</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="injection-time"><i class="mdi mdi-clock"></i> Time *</label>
                                                <input type="datetime-local" class="form-control" id="injection-time" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="injection-notes"><i class="mdi mdi-note-text"></i> Notes</label>
                                            <textarea class="form-control" id="injection-notes" rows="2" placeholder="Any additional notes..."></textarea>
                                        </div>
                                        <div class="form-actions text-right">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="mdi mdi-check"></i> Administer Injection
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- History Sub-tab -->
                        <div class="tab-pane fade" id="injection-history" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-history"></i> Injection History</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover" id="injection-history-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Drug</th>
                                                    <th>Dose</th>
                                                    <th>Route</th>
                                                    <th>Site</th>
                                                    <th>Nurse</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Immunization Tab -->
            <div class="workspace-tab-content" id="immunization-tab">
                <div class="immunization-container p-3">
                    <!-- Sub-tabs for Immunization - Redesigned UX -->
                    <ul class="nav nav-tabs mb-3" id="immunization-sub-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="immunization-schedule-tab" data-toggle="tab" href="#immunization-schedule" role="tab">
                                <i class="mdi mdi-calendar-check"></i> Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="immunization-history-tab" data-toggle="tab" href="#immunization-history" role="tab">
                                <i class="mdi mdi-history"></i> History & Timeline
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="immunization-sub-content">
                        <!-- Schedule Sub-tab (Now Primary) -->
                        <div class="tab-pane fade show active" id="immunization-schedule" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <h6 class="mb-0"><i class="mdi mdi-calendar-check"></i> Immunization Schedules</h6>
                                        <div class="d-flex align-items-center gap-2">
                                            <select class="form-control form-control-sm mr-2" id="schedule-template-select" style="width: 200px;">
                                                <option value="">Select Schedule Template...</option>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-primary" id="btn-add-schedule" title="Add selected schedule to patient">
                                                <i class="mdi mdi-plus"></i> Add Schedule
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Active Schedules Summary -->
                                    <div class="mb-3" id="patient-active-schedules">
                                        <div class="alert alert-info py-2 mb-2">
                                            <i class="mdi mdi-information"></i> Select a patient to view their immunization schedules
                                        </div>
                                    </div>

                                    <!-- Schedule Legend -->
                                    <div class="mb-3 d-flex flex-wrap align-items-center">
                                        <span class="mr-3 small text-muted">Status:</span>
                                        <span class="badge badge-secondary mr-2"><i class="mdi mdi-clock-outline"></i> Pending</span>
                                        <span class="badge badge-warning mr-2"><i class="mdi mdi-alert"></i> Due Now</span>
                                        <span class="badge badge-danger mr-2"><i class="mdi mdi-alert-circle"></i> Overdue</span>
                                        <span class="badge badge-success mr-2"><i class="mdi mdi-check"></i> Administered</span>
                                        <span class="badge badge-info mr-2"><i class="mdi mdi-skip-next"></i> Skipped</span>
                                        <span class="badge badge-dark"><i class="mdi mdi-cancel"></i> Contraindicated</span>
                                    </div>

                                    <!-- Schedule Timeline Container -->
                                    <div id="immunization-schedule-container">
                                        <div class="text-center py-4">
                                            <i class="mdi mdi-calendar-clock mdi-48px text-muted"></i>
                                            <p class="text-muted mt-2">Select a patient to view their immunization schedules</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History & Timeline Sub-tab -->
                        <div class="tab-pane fade" id="immunization-history" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="mdi mdi-history"></i> Immunization History & Timeline</h6>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary active" id="view-timeline-btn" data-view="timeline">
                                                <i class="mdi mdi-chart-timeline-variant"></i> Timeline
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" id="view-calendar-btn" data-view="calendar">
                                                <i class="mdi mdi-calendar-month"></i> Calendar
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" id="view-table-btn" data-view="table">
                                                <i class="mdi mdi-table"></i> Table
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- History Views Container -->
                                    <div id="immunization-history-container">
                                        <!-- Timeline View (Default) -->
                                        <div class="history-view" id="history-timeline-view">
                                            <div class="text-center py-4">
                                                <i class="mdi mdi-chart-timeline-variant mdi-48px text-muted"></i>
                                                <p class="text-muted mt-2">Select a patient to view their immunization history</p>
                                            </div>
                                        </div>

                                        <!-- Calendar View -->
                                        <div class="history-view d-none" id="history-calendar-view">
                                            <div id="immunization-calendar"></div>
                                        </div>

                                        <!-- Table View -->
                                        <div class="history-view d-none" id="history-table-view">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover" id="immunization-history-table" style="width:100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Vaccine</th>
                                                            <th>Dose #</th>
                                                            <th>Dose Amount</th>
                                                            <th>Batch</th>
                                                            <th>Site</th>
                                                            <th>Nurse</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
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

            <!-- Administer Vaccine Modal -->
            @include('admin.partials.administer_vaccine_modal')

            <!-- Procedures Tab -->
            <div class="workspace-tab-content" id="procedures-tab">
                <div class="procedures-container p-3">
                    <h4><i class="mdi mdi-medical-bag"></i> Patient Procedures</h4>
                    <p class="text-muted mb-3">View all procedures for the selected patient</p>
                    <div class="procedures-table-wrapper">
                        <table class="table table-hover" style="width: 100%" id="procedures_history_list">
                            <thead class="table-light">
                                <th><i class="mdi mdi-medical-bag"></i> Procedures</th>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Clinical Requests Tab -->
            <div class="workspace-tab-content" id="clinical-requests-tab">
                <div class="clinical-requests-container p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0"><i class="mdi mdi-clipboard-pulse"></i> Clinical Requests</h4>
                        <span class="badge bg-info" id="cr-patient-badge">No patient selected</span>
                    </div>

                    <!-- Sub-tabs -->
                    <ul class="nav nav-tabs service-tabs mb-3" id="clinical-requests-sub-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cr-prescriptions-tab" data-bs-toggle="tab" data-bs-target="#cr-prescriptions" type="button" role="tab">
                                <i class="mdi mdi-pill"></i> Drug Prescription
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-non-pharm-tab" data-bs-toggle="tab" data-bs-target="#cr-non-pharm" type="button" role="tab">
                                <i class="fa fa-heartbeat"></i> Care Plan / Non-Pharm
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-lab-tab" data-bs-toggle="tab" data-bs-target="#cr-lab" type="button" role="tab">
                                <i class="mdi mdi-flask"></i> Lab Requests
                                <span class="badge bg-danger rounded-pill ms-1 lab-unviewed-badge" id="cr-lab-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-imaging-tab" data-bs-toggle="tab" data-bs-target="#cr-imaging" type="button" role="tab">
                                <i class="mdi mdi-radioactive"></i> Imaging
                                <span class="badge bg-danger rounded-pill ms-1 imaging-unviewed-badge" id="cr-imaging-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-procedures-tab" data-bs-toggle="tab" data-bs-target="#cr-procedures" type="button" role="tab">
                                <i class="mdi mdi-medical-bag"></i> Procedures
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="clinical-requests-sub-content">

                        <!-- ===== PRESCRIPTIONS SUB-TAB ===== -->
                        <div class="tab-pane fade show active" id="cr-prescriptions" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Re-prescribe buttons (Plan §6.4, §5.3) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                        {{-- Re-prescribe from previous encounter dropdown (Plan §5.3) --}}
                                        <div class="dropdown" id="cr-rp-encounter-dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                                <i class="fa fa-redo"></i> Re-prescribe from Encounter
                                            </button>
                                            <ul class="dropdown-menu rp-encounter-menu" style="min-width: 320px; max-height: 300px; overflow-y: auto;">
                                                <li class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-presc-history" type="button"><i class="fa fa-history"></i> Drug History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-presc-new" type="button"><i class="fa fa-plus-circle"></i> Add Prescription</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-presc-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_presc_history_list">
                                                    <thead class="table-light"><th style="width:100%"><i class="mdi mdi-pill"></i> Prescriptions</th></thead>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-presc-new" role="tabpanel">
                                            <div id="cr_presc_message" class="mb-2"></div>
                                            <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left: 4px solid #17a2b8;">
                                                <i class="mdi mdi-information-outline text-info"></i>
                                                <strong>Note:</strong> Prescriptions added here are sent to the <strong>Pharmacy</strong> for dispensing and billing.
                                                For quick/direct billing, use the <strong>Billing &rarr; Consumables</strong> tab instead.
                                            </div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Prescription</h6>

                                            {{-- Dose Mode Toggle — Segmented button group (Plan §2.2, structured default) --}}
                                            @include('admin.partials.dose-mode-toggle', ['prefix' => 'cr_'])

                                            <div class="form-group">
                                                <label>Search drugs/products</label>
                                                <input type="text" class="form-control" id="cr_presc_search"
                                                    placeholder="Type to search products..." autocomplete="off">
                                                <ul class="list-group co-search-dropdown" id="cr_presc_results"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Drug / Product</th><th>Price</th><th>Dose / Frequency</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></thead>
                                                    <tbody id="cr-selected-products"></tbody>
                                                </table>
                                            </div>
                                            {{-- Save button removed — prescriptions auto-save on add (Plan §4.5) --}}
                                            <div id="cr_presc_message" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== NON-PHARM / CARE PLAN SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-non-pharm" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    <div id="cr-non-pharm-container"></div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== LAB REQUESTS SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-lab" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Save as Template (Plan §6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-lab-history" type="button"><i class="fa fa-history"></i> Lab History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-lab-new" type="button"><i class="fa fa-plus-circle"></i> New Lab Request</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-lab-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_lab_history_list">
                                                    <thead class="table-light"><th style="width:100%"><i class="mdi mdi-flask"></i> Lab Requests</th></thead>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-lab-new" role="tabpanel">
                                            <div id="cr_lab_message" class="mb-2"></div>
                                            <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left: 4px solid #17a2b8;">
                                                <i class="mdi mdi-information-outline text-info"></i>
                                                <strong>Note:</strong> Lab requests added here are sent to the <strong>Laboratory</strong> for processing and billing.
                                                For quick/direct billing, use the <strong>Billing &rarr; Labs</strong> tab instead.
                                            </div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Lab Request</h6>
                                            <div class="form-group">
                                                <label>Search lab services</label>
                                                <input type="text" class="form-control" id="cr_lab_search"
                                                    placeholder="Type to search lab services..." autocomplete="off">
                                                <ul class="list-group co-search-dropdown" id="cr_lab_results"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Lab Test</th><th>Price</th><th>Clinical Notes</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></thead>
                                                    <tbody id="cr-selected-labs"></tbody>
                                                </table>
                                            </div>
                                            {{-- Phase 2d (Plan §4.5): Auto-save status — labs save on add --}}
                                            <div class="auto-save-status text-muted small mt-2" id="cr-labs-auto-save-status"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== IMAGING SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-imaging" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Save as Template (Plan §6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-imaging-history" type="button"><i class="fa fa-history"></i> Imaging History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-imaging-new" type="button"><i class="fa fa-plus-circle"></i> New Imaging Request</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-imaging-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_imaging_history_list">
                                                    <thead class="table-light"><th style="width:100%"><i class="mdi mdi-radioactive"></i> Imaging Requests</th></thead>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-imaging-new" role="tabpanel">
                                            <div id="cr_imaging_message" class="mb-2"></div>
                                            <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left: 4px solid #17a2b8;">
                                                <i class="mdi mdi-information-outline text-info"></i>
                                                <strong>Note:</strong> Imaging requests added here are sent to the <strong>Imaging</strong> department for processing and billing.
                                                For quick/direct billing, use the <strong>Billing &rarr; Imaging</strong> tab instead.
                                            </div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Imaging Request</h6>
                                            <div class="form-group">
                                                <label>Search imaging services</label>
                                                <input type="text" class="form-control" id="cr_imaging_search"
                                                    placeholder="Type to search imaging services..." autocomplete="off">
                                                <ul class="list-group co-search-dropdown" id="cr_imaging_results"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Imaging Study</th><th>Price</th><th>Clinical Notes</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></thead>
                                                    <tbody id="cr-selected-imaging"></tbody>
                                                </table>
                                            </div>
                                            {{-- Phase 2d (Plan §4.5): Auto-save status — imaging saves on add --}}
                                            <div class="auto-save-status text-muted small mt-2" id="cr-imaging-auto-save-status"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== PROCEDURES SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-procedures" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Save as Template (Plan §6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-proc-history" type="button"><i class="fa fa-history"></i> Procedure History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-proc-new" type="button"><i class="fa fa-plus-circle"></i> Request Procedure</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-proc-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_proc_history_list">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 100%;"><i class="mdi mdi-medical-bag"></i> Procedure Requests</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-proc-new" role="tabpanel">
                                            <div id="cr_proc_message" class="mb-2"></div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> Request New Procedure</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label><i class="fa fa-search"></i> Search Procedure</label>
                                                        <input type="text" class="form-control" id="cr_proc_search"
                                                            placeholder="Search procedures..." autocomplete="off">
                                                        <ul class="list-group co-search-dropdown" id="cr_proc_results"></ul>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-3">
                                                        <label><i class="fa fa-exclamation-triangle"></i> Priority</label>
                                                        <select class="form-control" id="cr_proc_priority">
                                                            <option value="routine">Routine</option>
                                                            <option value="urgent">Urgent</option>
                                                            <option value="emergency">Emergency</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-3">
                                                        <label><i class="fa fa-calendar"></i> Scheduled Date</label>
                                                        <input type="date" class="form-control" id="cr_proc_scheduled_date">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label><i class="fa fa-sticky-note"></i> Pre-Procedure Notes</label>
                                                <textarea class="form-control" id="cr_proc_notes" rows="2" placeholder="Clinical notes, indications..."></textarea>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><tr><th>Procedure</th><th>Price</th><th>Priority</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></tr></thead>
                                                    <tbody id="cr-selected-procedures"></tbody>
                                                </table>
                                            </div>
                                            {{-- Save button removed — procedures auto-save on add (Plan §4.5) --}}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Billing Tab -->
            <div class="workspace-tab-content" id="billing-tab">
                <div id="billing-kit-root">
                    <p class="text-muted text-center py-5"><i class="mdi mdi-account-arrow-left mdi-36px"></i><br>Select a patient to view billing</p>
                </div>
            </div>
            <!-- Nursing Notes Tab -->
            <div class="workspace-tab-content" id="notes-tab">
                @include('admin.partials.shared_workbench_nurse_notes', [
                    'prefix' => 'nursing',
                    'formId' => 'nursing-note-form',
                    'editorId' => 'nursing-note-editor',
                    'statusId' => 'note-autosave-status',
                    'tableId' => 'nursing-notes-table',
                    'refreshCallback' => 'loadNotesHistory(currentPatient)'
                ])
            </div>
        </div>
    </div>
</div>

<!-- Medication Logs Modal -->
@include('admin.nursing.partials._modals')
@endsection

@include('admin.nursing.partials._scripts')
