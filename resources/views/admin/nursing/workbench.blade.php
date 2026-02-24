@extends('admin.layouts.app')

@section('title', 'Nursing Workbench')

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
    #history-tab table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
    #history-tab table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
        display: none !important;
    }

    #history-tab table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control:before,
    #history-tab table.dataTable.dtr-inline.collapsed > tbody > tr.parent > th.dtr-control:before {
        display: none !important;
    }

    #history-tab table.dataTable > tbody > tr.child {
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
            <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#emergencyIntakeModal">
                <i class="mdi mdi-ambulance text-danger"></i>
                <span>Emergency Intake</span>
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
                â‰¡Æ’Ã´Ã¯ View All Pending Requests
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
                <div class="reports-filter-panel card mb-4">
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
                    <div class="patient-name" id="patient-name"></div>
                    <div class="patient-meta" id="patient-meta"></div>
                </div>
                <button class="btn-expand-patient" id="btn-expand-patient" title="Show more details">
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
                <button class="workspace-tab" data-tab="notes">
                    <i class="mdi mdi-note-text"></i>
                    <span>Nursing Notes</span>
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
                                        {{-- Â§7.2: Add to List button for patient's own virtual row --}}
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
                                                        <option value="">-- Choose Store --</option>
                                                        @foreach($stores ?? [] as $store)
                                                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                                        @endforeach
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
                                        {{-- Â§5.3: Bill Patient checkbox â€” unchecked = hospital absorbs cost, checked = creates POSR via tariff pipeline --}}
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
                                                    <td id="injection-total-price"><strong>â‚¦0.00</strong></td>
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
            <div class="modal fade" id="administerVaccineModal" tabindex="-1" role="dialog" aria-labelledby="administerVaccineModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="administerVaccineModalLabel">
                                <i class="mdi mdi-needle"></i> Administer Vaccine
                            </h5>
                            <button type="button" class="close text-white"  data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Schedule Info Banner -->
                            <div class="alert alert-info mb-3" id="modal-schedule-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong><i class="mdi mdi-needle"></i> Vaccine:</strong> <span id="modal-vaccine-name">-</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong><i class="mdi mdi-numeric"></i> Dose:</strong> <span id="modal-dose-label">-</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong><i class="mdi mdi-calendar"></i> Due:</strong> <span id="modal-due-date">-</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 1: Store Selection - Primary Action -->
                            <div class="store-selection-panel mb-4 p-3 rounded" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #81c784;">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold mb-2" style="font-size: 1rem;">
                                            <i class="mdi mdi-store text-success"></i> Step 1: Select Dispensing Store
                                        </label>
                                        <select id="modal-vaccine-store" class="form-control form-control-lg" style="border: 2px solid #388e3c; font-weight: 500;" required>
                                            <option value="">-- Choose Store --</option>
                                            @foreach($stores ?? [] as $store)
                                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <div id="modal-vaccine-store-info" class="p-3 bg-white rounded shadow-sm" style="display: none;">
                                            <h6 class="text-success mb-2"><i class="mdi mdi-package-variant"></i> Store Stock</h6>
                                            <div id="modal-vaccine-store-stock" class="small">
                                                <!-- Stock will show here when product is selected -->
                                            </div>
                                        </div>
                                        <div id="modal-vaccine-store-placeholder" class="p-3 text-muted text-center">
                                            <i class="mdi mdi-arrow-left-bold mdi-24px"></i>
                                            <p class="mb-0 small">Select store first</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Vaccine Product Selection -->
                            <div class="form-group mb-3">
                                <label for="modal-vaccine-search"><i class="mdi mdi-magnify"></i> Step 2: Search Vaccine Product *</label>
                                <input type="text" class="form-control" id="modal-vaccine-search"
                                       placeholder="Type to search for vaccine product from inventory..." autocomplete="off">
                                <ul class="list-group" id="modal-vaccine-results"
                                    style="display: none; position: absolute; z-index: 1050; max-height: 200px; overflow-y: auto; width: calc(100% - 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></ul>
                            </div>

                            <!-- Selected Product Display with Stock Info -->
                            <div class="card-modern mb-3 d-none" id="modal-selected-product-card">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong id="modal-selected-product-name">-</strong>
                                            <br><small class="text-muted" id="modal-selected-product-details">-</small>
                                            <br><small id="modal-selected-product-stock" class="text-success"></small>
                                        </div>
                                        <div class="text-right">
                                            <span class="badge badge-primary" id="modal-selected-product-price">Î“Ã©Âª0.00</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="modal-remove-product">
                                                <i class="mdi mdi-close"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="modal-schedule-id">
                            <input type="hidden" id="modal-product-id">

                            <!-- Step 3: Administration Details Form -->
                            <h6 class="text-muted mb-3"><i class="mdi mdi-clipboard-text"></i> Step 3: Administration Details</h6>
                            <form id="modal-immunization-form">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="modal-vaccine-site"><i class="mdi mdi-map-marker"></i> Administration Site *</label>
                                        <select class="form-control" id="modal-vaccine-site" required>
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
                                        <label for="modal-vaccine-route"><i class="mdi mdi-routes"></i> Route *</label>
                                        <select class="form-control" id="modal-vaccine-route" required>
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
                                        <label for="modal-vaccine-batch-select">
                                            <i class="mdi mdi-package-variant"></i> Select Batch
                                            <span class="badge badge-info badge-sm ml-1" title="Auto-selects FIFO">FIFO</span>
                                        </label>
                                        <select class="form-control" id="modal-vaccine-batch-select">
                                            <option value="">-- Select store first --</option>
                                        </select>
                                        <input type="hidden" id="modal-vaccine-batch-id">
                                        <small class="text-muted" id="modal-vaccine-batch-help">Select product to see available batches</small>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="modal-vaccine-expiry"><i class="mdi mdi-calendar-alert"></i> Expiry Date</label>
                                        <input type="date" class="form-control" id="modal-vaccine-expiry" readonly>
                                        <small class="text-muted">Auto-filled from selected batch</small>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="modal-vaccine-time"><i class="mdi mdi-clock"></i> Administration Time *</label>
                                        <input type="datetime-local" class="form-control" id="modal-vaccine-time" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="modal-vaccine-manufacturer"><i class="mdi mdi-factory"></i> Manufacturer</label>
                                        <input type="text" class="form-control" id="modal-vaccine-manufacturer" placeholder="Vaccine manufacturer">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="modal-vaccine-vis"><i class="mdi mdi-file-document"></i> VIS Date Given</label>
                                        <input type="date" class="form-control" id="modal-vaccine-vis" title="Vaccine Information Statement date">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="modal-vaccine-notes"><i class="mdi mdi-note-text"></i> Notes / Reactions</label>
                                    <textarea class="form-control" id="modal-vaccine-notes" rows="2" placeholder="Any observations, reactions, or notes..."></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">
                                <i class="mdi mdi-close"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-success btn-lg" id="modal-submit-immunization">
                                <i class="mdi mdi-check"></i> Record Immunization
                            </button>
                        </div>
                    </div>
                </div>
            </div>

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
                            <button class="nav-link active" id="cr-prescriptions-tab" data-bs-toggle="tab"
                                data-bs-target="#cr-prescriptions" type="button" role="tab">
                                <i class="mdi mdi-pill"></i> Drug Prescription
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-lab-tab" data-bs-toggle="tab"
                                data-bs-target="#cr-lab" type="button" role="tab">
                                <i class="mdi mdi-flask"></i> Lab Requests
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-imaging-tab" data-bs-toggle="tab"
                                data-bs-target="#cr-imaging" type="button" role="tab">
                                <i class="mdi mdi-radioactive"></i> Imaging
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-procedures-tab" data-bs-toggle="tab"
                                data-bs-target="#cr-procedures" type="button" role="tab">
                                <i class="mdi mdi-medical-bag"></i> Procedures
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="clinical-requests-sub-content">

                        <!-- ===== PRESCRIPTIONS SUB-TAB ===== -->
                        <div class="tab-pane fade show active" id="cr-prescriptions" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Re-prescribe buttons (Plan Â§6.4, Â§5.3) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                                <i class="fa fa-clipboard-list"></i> Treatment Plans
                                            </button>
                                            <button class="btn btn-sm btn-outline-success"
                                                    onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                        {{-- Re-prescribe from previous encounter dropdown (Plan Â§5.3) --}}
                                        <div class="dropdown" id="cr-rp-encounter-dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                                                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
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
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Prescription</h6>

                                            {{-- Dose Mode Toggle â€” Segmented button group (Plan Â§2.2, structured default) --}}
                                            @include('admin.partials.dose-mode-toggle', ['prefix' => 'cr_'])

                                            <div class="form-group">
                                                <label>Search drugs/products</label>
                                                <input type="text" class="form-control" id="cr_presc_search"
                                                    placeholder="Type to search products..." autocomplete="off">
                                                <ul class="list-group" id="cr_presc_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Name</th><th>Price</th><th>Dose / Frequency</th><th>*</th></thead>
                                                    <tbody id="cr-selected-products"></tbody>
                                                </table>
                                            </div>
                                            {{-- Save button removed â€” prescriptions auto-save on add (Plan Â§4.5) --}}
                                            <div id="cr_presc_message" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== LAB REQUESTS SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-lab" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Save as Template (Plan Â§6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                                <i class="fa fa-clipboard-list"></i> Treatment Plans
                                            </button>
                                            <button class="btn btn-sm btn-outline-success"
                                                    onclick="ClinicalOrdersKit.openSaveTemplateModal()">
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
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Lab Request</h6>
                                            <div class="form-group">
                                                <label>Search lab services</label>
                                                <input type="text" class="form-control" id="cr_lab_search"
                                                    placeholder="Type to search lab services..." autocomplete="off">
                                                <ul class="list-group" id="cr_lab_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Name</th><th>Price</th><th>Notes</th><th>*</th></thead>
                                                    <tbody id="cr-selected-labs"></tbody>
                                                </table>
                                            </div>
                                            {{-- Phase 2d (Plan Â§4.5): Auto-save status â€” labs save on add --}}
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
                                    {{-- Treatment Plans + Save as Template (Plan Â§6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                                <i class="fa fa-clipboard-list"></i> Treatment Plans
                                            </button>
                                            <button class="btn btn-sm btn-outline-success"
                                                    onclick="ClinicalOrdersKit.openSaveTemplateModal()">
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
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Imaging Request</h6>
                                            <div class="form-group">
                                                <label>Search imaging services</label>
                                                <input type="text" class="form-control" id="cr_imaging_search"
                                                    placeholder="Type to search imaging services..." autocomplete="off">
                                                <ul class="list-group" id="cr_imaging_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Name</th><th>Price</th><th>Notes</th><th>*</th></thead>
                                                    <tbody id="cr-selected-imaging"></tbody>
                                                </table>
                                            </div>
                                            {{-- Phase 2d (Plan Â§4.5): Auto-save status â€” imaging saves on add --}}
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
                                    {{-- Treatment Plans + Save as Template (Plan Â§6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
                                                <i class="fa fa-clipboard-list"></i> Treatment Plans
                                            </button>
                                            <button class="btn btn-sm btn-outline-success"
                                                    onclick="ClinicalOrdersKit.openSaveTemplateModal()">
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
                                        <div class="tab-pane fade" id="cr-proc-new" role="tabpanel">
                                            <div id="cr_proc_message" class="mb-2"></div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> Request New Procedure</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label><i class="fa fa-search"></i> Search Procedure</label>
                                                        <input type="text" class="form-control" id="cr_proc_search"
                                                            placeholder="Search procedures..." autocomplete="off">
                                                        <ul class="list-group" id="cr_proc_results" style="display:none; position:absolute; z-index:1050; width:calc(100% - 30px); max-height:250px; overflow-y:auto;"></ul>
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
                                                    <thead><tr><th>Procedure</th><th>Price</th><th>Priority</th><th>*</th></tr></thead>
                                                    <tbody id="cr-selected-procedures"></tbody>
                                                </table>
                                            </div>
                                            {{-- Save button removed â€” procedures auto-save on add (Plan Â§4.5) --}}
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
                <div class="billing-container p-3">
                    <!-- Sub-tabs for Billing -->
                    <ul class="nav nav-tabs mb-3" id="billing-sub-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="billing-services-tab" data-toggle="tab" href="#billing-services" role="tab">
                                <i class="mdi mdi-medical-bag"></i> Services
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="billing-consumables-tab" data-toggle="tab" href="#billing-consumables" role="tab">
                                <i class="mdi mdi-package-variant"></i> Consumables
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="billing-pending-tab" data-toggle="tab" href="#billing-pending" role="tab">
                                <i class="mdi mdi-clock-outline"></i> Pending Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="billing-history-tab" data-toggle="tab" href="#billing-history" role="tab">
                                <i class="mdi mdi-clipboard-list"></i> Billing History
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="billing-sub-content">
                        <!-- Services Sub-tab -->
                        <div class="tab-pane fade show active" id="billing-services" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header bg-warning py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-medical-bag"></i> Add Nursing Service</h6>
                                </div>
                                <div class="card-body">
                                    <form id="service-billing-form">
                                        <div class="form-row">
                                            <div class="form-group col-md-8">
                                                <label for="service-search"><i class="mdi mdi-magnify"></i> Search Nursing Service *</label>
                                                <input type="text" class="form-control" id="service-search" placeholder="Type to search for nursing services..." autocomplete="off">
                                                <input type="hidden" id="service-id">
                                                <ul class="list-group" id="service-search-results" style="display: none; position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto; width: calc(66% - 30px);"></ul>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="service-price"><i class="mdi mdi-currency-ngn"></i> Price</label>
                                                <input type="text" class="form-control" id="service-price" readonly placeholder="Auto-calculated">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="service-notes"><i class="mdi mdi-note-text"></i> Notes</label>
                                            <textarea class="form-control" id="service-notes" rows="2" placeholder="Any additional notes..."></textarea>
                                        </div>
                                        <div class="form-actions text-right">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="mdi mdi-plus"></i> Add Service
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Consumables Sub-tab -->
                        <div class="tab-pane fade" id="billing-consumables" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header bg-info text-white py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-package-variant"></i> Add Consumable</h6>
                                </div>
                                <div class="card-body">
                                    <!-- Step 1: Store Selection - Primary Action -->
                                    <div class="store-selection-panel mb-4 p-3 rounded" style="background: linear-gradient(135deg, #e1f5fe 0%, #b3e5fc 100%); border: 2px solid #4fc3f7;">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold mb-2" style="font-size: 1rem;">
                                                    <i class="mdi mdi-store text-info"></i> Step 1: Select Dispensing Store
                                                </label>
                                                <select id="consumable-store" class="form-control form-control-lg" style="border: 2px solid #0288d1; font-weight: 500;" required>
                                                    <option value="">-- Choose Store --</option>
                                                    @foreach($stores ?? [] as $store)
                                                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <div id="consumable-store-info" class="p-3 bg-white rounded shadow-sm" style="display: none;">
                                                    <h6 class="text-info mb-2"><i class="mdi mdi-package-variant"></i> Store Stock</h6>
                                                    <div id="consumable-store-stock-summary" class="small">
                                                        <!-- Stock summary will be loaded here -->
                                                    </div>
                                                </div>
                                                <div id="consumable-store-placeholder" class="p-3 text-muted text-center">
                                                    <i class="mdi mdi-arrow-left-bold mdi-24px"></i>
                                                    <p class="mb-0 small">Select store first, then search product</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <form id="consumable-billing-form">
                                        <!-- Step 2: Product Search & Selection -->
                                        <div class="form-row">
                                            <div class="form-group col-md-5">
                                                <label for="consumable-search"><i class="mdi mdi-magnify"></i> Step 2: Search Consumable *</label>
                                                <input type="text" class="form-control" id="consumable-search" placeholder="Type to search for products..." autocomplete="off">
                                                <input type="hidden" id="consumable-id">
                                                <ul class="list-group" id="consumable-search-results" style="display: none; position: absolute; z-index: 1000; max-height: 200px; overflow-y: auto; width: calc(42% - 30px);"></ul>
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label for="consumable-quantity"><i class="mdi mdi-numeric"></i> Qty *</label>
                                                <input type="number" class="form-control" id="consumable-quantity" min="1" value="1" required>
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label for="consumable-price"><i class="mdi mdi-currency-ngn"></i> Total</label>
                                                <input type="text" class="form-control" id="consumable-price" readonly placeholder="Auto">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label for="consumable-batch-select">
                                                    <i class="mdi mdi-package-variant"></i> Batch
                                                    <span class="badge badge-info badge-sm" title="FIFO">FIFO</span>
                                                </label>
                                                <select class="form-control" id="consumable-batch-select">
                                                    <option value="">-- Select product first --</option>
                                                </select>
                                                <input type="hidden" id="consumable-batch-id">
                                            </div>
                                        </div>

                                        <!-- Selected Product Stock Info with Batch Details -->
                                        <div id="consumable-selected-stock" class="alert alert-light mb-3" style="display: none;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong id="consumable-selected-name">-</strong>
                                                    <br><small class="text-muted" id="consumable-selected-code">-</small>
                                                </div>
                                                <div id="consumable-stock-info" class="text-right">
                                                    <!-- Stock info will show here -->
                                                </div>
                                            </div>
                                            <div id="consumable-batch-info" class="mt-2 pt-2 border-top small" style="display: none;">
                                                <i class="mdi mdi-information-outline text-info"></i>
                                                <span id="consumable-batch-detail">FIFO batch will be auto-selected</span>
                                            </div>
                                        </div>

                                        <div class="form-actions text-right">
                                            <button type="submit" class="btn btn-info btn-lg">
                                                <i class="mdi mdi-plus"></i> Add Consumable
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Bills Sub-tab -->
                        <div class="tab-pane fade" id="billing-pending" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-clock-outline"></i> Pending Bills</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover" id="pending-bills-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Type</th>
                                                    <th>Qty</th>
                                                    <th>Amount</th>
                                                    <th>Added By</th>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Billing History Sub-tab -->
                        <div class="tab-pane fade" id="billing-history" role="tabpanel">
                            <!-- Summary Stats -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <div class="bh-stat-card bh-stat-purple">
                                        <div class="bh-stat-icon"><i class="mdi mdi-clipboard-list mdi-24px"></i></div>
                                        <div>
                                            <div class="bh-stat-value" id="bh-total-requests">0</div>
                                            <div class="bh-stat-label">Total Requests</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="bh-stat-card bh-stat-green">
                                        <div class="bh-stat-icon"><i class="mdi mdi-shield-check mdi-24px"></i></div>
                                        <div>
                                            <div class="bh-stat-value" id="bh-hmo-covered">â‚¦0.00</div>
                                            <div class="bh-stat-label">HMO Covered</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="bh-stat-card bh-stat-pink">
                                        <div class="bh-stat-icon"><i class="mdi mdi-cash mdi-24px"></i></div>
                                        <div>
                                            <div class="bh-stat-value" id="bh-patient-payable">â‚¦0.00</div>
                                            <div class="bh-stat-label">Patient Payable</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="bh-stat-card bh-stat-blue">
                                        <div class="bh-stat-icon"><i class="mdi mdi-check-circle mdi-24px"></i></div>
                                        <div>
                                            <div class="bh-stat-value" id="bh-completed-count">0</div>
                                            <div class="bh-stat-label">Completed</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="card-modern mb-3">
                                <div class="card-body py-2">
                                    <form id="bh-filter-form" class="form-inline flex-wrap">
                                        <div class="form-group mr-2 mb-2">
                                            <label class="mr-1 small font-weight-bold">From</label>
                                            <input type="date" class="form-control form-control-sm" id="bh-date-from">
                                        </div>
                                        <div class="form-group mr-2 mb-2">
                                            <label class="mr-1 small font-weight-bold">To</label>
                                            <input type="date" class="form-control form-control-sm" id="bh-date-to">
                                        </div>
                                        <div class="form-group mr-2 mb-2">
                                            <select class="form-control form-control-sm" id="bh-type-filter">
                                                <option value="">All Types</option>
                                                <option value="lab">Lab Test</option>
                                                <option value="imaging">Imaging</option>
                                                <option value="product">Product/Drug</option>
                                            </select>
                                        </div>
                                        <div class="form-group mr-2 mb-2">
                                            <select class="form-control form-control-sm" id="bh-billing-filter">
                                                <option value="">All Billing</option>
                                                <option value="pending">Pending</option>
                                                <option value="billed">Billed</option>
                                                <option value="paid">Paid</option>
                                            </select>
                                        </div>
                                        <div class="form-group mr-2 mb-2">
                                            <select class="form-control form-control-sm" id="bh-delivery-filter">
                                                <option value="">All Delivery</option>
                                                <option value="pending">Pending</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="completed">Completed</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary mr-1" id="bh-clear-filters" title="Clear Filters">
                                                <i class="mdi mdi-refresh"></i>
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="mdi mdi-filter"></i> Apply
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- DataTable -->
                            <div class="card-modern">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover" id="billing-history-table" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Request #</th>
                                                    <th>Type</th>
                                                    <th>Service/Item</th>
                                                    <th>Price</th>
                                                    <th>HMO Covers</th>
                                                    <th>Payable</th>
                                                    <th>Billing</th>
                                                    <th>Delivery</th>
                                                    <th>Actions</th>
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

            <!-- Nursing Notes Tab -->
            <div class="workspace-tab-content" id="notes-tab">
                <div class="notes-container p-3">
                    <!-- Sub-tabs for Notes -->
                    <ul class="nav nav-tabs mb-3" id="notes-sub-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="notes-add-tab" data-toggle="tab" href="#notes-add" role="tab">
                                <i class="mdi mdi-plus-circle"></i> Add Note
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="notes-history-tab-link" data-toggle="tab" href="#notes-history" role="tab">
                                <i class="mdi mdi-history"></i> History
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="notes-sub-content">
                        <!-- Add Note Sub-tab -->
                        <div class="tab-pane fade show active" id="notes-add" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0"><i class="mdi mdi-note-text"></i> Add Nursing Note</h6>
                                </div>
                                <div class="card-body">
                                    <form id="nursing-note-form">
                                        <div class="form-group">
                                            <label for="nursing-note-editor"><i class="mdi mdi-text"></i> Note Content *</label>
                                            <div id="nursing-note-editor"></div>
                                        </div>
                                        <div class="form-actions text-right mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-content-save"></i> Save Note
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Notes History Sub-tab -->
                        <div class="tab-pane fade has-timeline" id="notes-history" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-history"></i> Notes History</h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="loadNotesHistory(currentPatient)">
                                        <i class="mdi mdi-refresh"></i> Refresh
                                    </button>
                                </div>
                                <div class="card-body bg-light p-3">
                                    <div class="table-responsive">
                                        <table class="table table-borderless w-100" id="nursing-notes-table">
                                            <thead class="d-none">
                                                <tr>
                                                    <th>Info</th>
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
</div>

<!-- Medication Logs Modal -->
<div class="modal fade" id="medicationLogsModal" tabindex="-1" aria-labelledby="medicationLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="medication-logs-title">Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
            </div>
            <div class="modal-body">
                <div id="medication-logs-content">
                    <!-- Logs will be populated via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editNoteModalLabel">Edit Nursing Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
            </div>
            <div class="modal-body">
                <form id="edit-note-form">
                    <input type="hidden" id="edit-note-id">
                    <div class="form-group">
                        <label for="edit-note-content">Note Content</label>
                        <div id="edit-note-editor"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updatedNote()">Update Note</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Vital Modal -->
<div class="modal fade" id="editVitalModal" tabindex="-1" aria-labelledby="editVitalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="editVitalModalLabel"><i class="mdi mdi-heart-pulse"></i> Edit Vitals</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-vital-form">
                    <input type="hidden" id="edit-vital-id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit-blood-pressure" class="form-label"><i class="mdi mdi-heart-pulse text-danger"></i> Blood Pressure</label>
                            <input type="text" class="form-control" id="edit-blood-pressure" placeholder="e.g., 120/80">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-temp" class="form-label"><i class="mdi mdi-thermometer text-warning"></i> Temperature (â”¬â–‘C)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-temp" placeholder="e.g., 36.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-heart-rate" class="form-label"><i class="mdi mdi-heart text-danger"></i> Heart Rate (bpm)</label>
                            <input type="number" class="form-control" id="edit-heart-rate" placeholder="e.g., 72">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-resp-rate" class="form-label"><i class="mdi mdi-lungs text-primary"></i> Resp. Rate (bpm)</label>
                            <input type="number" class="form-control" id="edit-resp-rate" placeholder="e.g., 16">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-weight" class="form-label"><i class="mdi mdi-weight text-success"></i> Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-weight" placeholder="e.g., 70">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-height" class="form-label"><i class="mdi mdi-human-male-height"></i> Height (cm)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-height" placeholder="e.g., 170">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-spo2" class="form-label"><i class="mdi mdi-percent"></i> SpO2 (%)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-spo2" placeholder="e.g., 98">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-blood-sugar" class="form-label"><i class="mdi mdi-water"></i> Blood Sugar (mg/dL)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-blood-sugar" placeholder="e.g., 100">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="edit-other-notes" class="form-label"><i class="mdi mdi-note-text"></i> Notes</label>
                            <textarea class="form-control" id="edit-other-notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateVital()"><i class="mdi mdi-check"></i> Update Vitals</button>
            </div>
        </div>
    </div>
</div>

<!-- Investigation Result View Modal -->
<div class="modal fade" id="investResViewModal" tabindex="-1" role="dialog" aria-labelledby="investResViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <style>
                .result-table td { padding: 10px 12px; border-bottom: 1px solid #ddd; }
                .result-table tbody tr:hover { background: #f8f9fa; }
                .result-table td, .result-table th { word-wrap: break-word; overflow-wrap: break-word; }
                .result-status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
                .status-normal { background: #d4edda; color: #155724; }
                .status-high { background: #f8d7da; color: #721c24; }
                .status-low { background: #fff3cd; color: #856404; }
                .status-abnormal { background: #f8d7da; color: #721c24; }
                .result-attachments { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
                .result-footer { padding: 20px; border-top: 2px solid #eee; font-size: 12px; color: #999; text-align: center; }
                @media print {
                    .modal-header, .modal-footer, .result-print-btn { display: none !important; }
                    .modal-dialog { max-width: 100% !important; margin: 0 !important; }
                    .modal-content { border: none !important; box-shadow: none !important; }
                    body { background: white !important; }
                }
            </style>

            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="investResViewModalLabel"><i class="mdi mdi-file-document-outline"></i> Test Results</h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-0">
                <div id="resultViewTable">
                    <div class="result-header">
                        <div class="result-header-left">
                            <img src="data:image/jpeg;base64,{{ $sett->logo ?? '' }}" alt="Hospital Logo" class="result-logo" />
                            <div class="result-hospital-name">{{ $sett->site_name ?? 'Hospital Name' }}</div>
                        </div>
                        <div class="result-header-right">
                            <div><strong>Address:</strong> {{ $sett->contact_address ?? 'N/A' }}</div>
                            <div><strong>Phone:</strong> {{ $sett->contact_phones ?? 'N/A' }}</div>
                            <div><strong>Email:</strong> {{ $sett->contact_emails ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="result-title-section">TEST RESULTS</div>

                    <div class="result-patient-info">
                        <div class="result-info-box">
                            <div class="result-info-row"><div class="result-info-label">Patient Name:</div><div class="result-info-value" id="res_patient_name"></div></div>
                            <div class="result-info-row"><div class="result-info-label">Patient ID:</div><div class="result-info-value" id="res_patient_id"></div></div>
                            <div class="result-info-row"><div class="result-info-label">Age:</div><div class="result-info-value" id="res_patient_age"></div></div>
                            <div class="result-info-row"><div class="result-info-label">Gender:</div><div class="result-info-value" id="res_patient_gender"></div></div>
                        </div>
                        <div class="result-info-box">
                            <div class="result-info-row"><div class="result-info-label">Test Name:</div><div class="result-info-value invest_res_service_name_view"></div></div>
                            <div class="result-info-row"><div class="result-info-label">Test ID:</div><div class="result-info-value" id="res_test_id"></div></div>
                            <div class="result-info-row"><div class="result-info-label">Sample Date:</div><div class="result-info-value" id="res_sample_date"></div></div>
                            <div class="result-info-row"><div class="result-info-label">Result Date:</div><div class="result-info-value" id="res_result_date"></div></div>
                        </div>
                    </div>

                    <div class="result-section">
                        <div class="result-section-title">TEST RESULTS</div>
                        <div id="invest_res"></div>
                    </div>

                    <div id="invest_attachments" style="margin: 0 20px;"></div>

                    <div class="result-section" style="padding-top: 40px;">
                        <div style="display: flex; justify-content: space-between; border-top: 2px solid #eee; padding-top: 20px;">
                            <div><div style="margin-bottom: 5px;"><strong>Results By:</strong></div><div id="res_result_by" style="color: #666;"></div></div>
                            <div style="text-align: right;"><div style="margin-bottom: 5px;"><strong>Authorized Signature:</strong></div><div style="border-top: 1px solid #333; min-width: 200px; padding-top: 5px;"><span id="res_signature_date"></span></div></div>
                        </div>
                    </div>

                    <div class="result-footer">
                        <div>{{ $sett->site_name ?? 'Hospital Name' }} | {{ $sett->contact_address ?? '' }}</div>
                        <div>{{ $sett->contact_phones ?? '' }} | {{ $sett->contact_emails ?? '' }}</div>
                        <div style="margin-top: 10px; font-size: 11px;">This is a computer-generated document. Report generated on <span id="res_generated_date"></span></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                <button type="button" onclick="PrintElem('resultViewTable')" class="btn btn-primary"><i class="mdi mdi-printer"></i> Print Results</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Reason Modal -->
<div class="modal fade" id="deleteReasonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-trash"></i> Delete Lab Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="deleteRequestForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will soft delete the lab request. It can be restored from the trash later.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="delete_service_name"></span></p>
                        <p><strong>Request ID:</strong> <span id="delete_request_id"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="delete_reason">Reason for Deletion <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delete_reason" name="reason" rows="4"
                                  placeholder="Please provide a detailed reason for deleting this lab request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-trash"></i> Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dismiss Reason Modal -->
<div class="modal fade" id="dismissReasonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fa fa-ban"></i> Dismiss Lab Request</h5>
                <button type="button" class="close"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="dismissRequestForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Info:</strong> Dismissed requests can be restored later from the trash panel.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="dismiss_service_name"></span></p>
                        <p><strong>Request ID:</strong> <span id="dismiss_request_id"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="dismiss_reason">Reason for Dismissal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="dismiss_reason" name="reason" rows="4"
                                  placeholder="Please provide a reason for dismissing this lab request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-ban"></i> Dismiss Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Audit Log Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-clipboard-list"></i> Audit Trail</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Action Type</label>
                        <select class="form-control" id="audit_action_filter">
                            <option value="">All Actions</option>
                            <option value="view">View</option>
                            <option value="edit">Edit</option>
                            <option value="delete">Delete</option>
                            <option value="restore">Restore</option>
                            <option value="dismiss">Dismiss</option>
                            <option value="undismiss">Undismiss</option>
                            <option value="billing">Billing</option>
                            <option value="sample_collection">Sample Collection</option>
                            <option value="result_entry">Result Entry</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" class="form-control" id="audit_from_date">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" class="form-control" id="audit_to_date">
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block" id="applyAuditFilter">
                            <i class="fa fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="audit-log-table" style="width: 100%">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportAuditLog">
                    <i class="fa fa-download"></i> Export to Excel
                </button>
            </div>
        </div>
    </div>
</div>




<!-- =============================================
     SHIFT MANAGEMENT UI COMPONENTS
     ============================================= -->

<!-- Workbench Lock Overlay (shown when no active shift) -->
<div id="shift-lock-overlay" class="shift-lock-overlay" style="display: none;">
    <div class="shift-lock-content">
        <div class="shift-lock-icon">
            <i class="mdi mdi-clock-alert-outline"></i>
        </div>
        <h3>Start Your Shift</h3>
        <p class="text-muted">Please start your shift to access the nursing workbench and begin documenting patient care.</p>
        <div id="pending-handovers-preview" class="pending-handovers-preview" style="display: none;">
            <div class="alert alert-warning mb-3">
                <i class="mdi mdi-alert-circle"></i>
                <span id="pending-handovers-count">0</span> handover(s) from the last 24 hours need your attention
            </div>
            <div id="pending-handovers-list" class="pending-handovers-list mb-3"></div>
        </div>
        <button class="btn btn-lg btn-success" id="start-shift-btn">
            <i class="mdi mdi-play-circle"></i> Start Shift
        </button>
        <div class="shift-lock-nav-buttons mt-4">
            <a href="{{ route('home') }}" class="btn btn-outline-primary btn-lg me-2">
                <i class="mdi mdi-home"></i> Home
            </a>
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('shift-overlay-logout-form').submit();"
               class="btn btn-outline-danger btn-lg">
                <i class="mdi mdi-logout"></i> Logout
            </a>
            <form id="shift-overlay-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </div>
    </div>
</div>

<!-- Floating Shift Control Button -->
<div id="shift-control-fab" class="shift-control-fab" style="display: none;">
    <div class="shift-fab-timer">
        <span id="shift-elapsed-time">00:00</span>
    </div>
    <div class="shift-fab-main">
        <button class="btn btn-shift-control" id="shift-fab-btn" title="Shift Controls">
            <i class="mdi mdi-account-clock"></i>
        </button>
    </div>
    <div class="shift-fab-actions" style="display: none;">
        <button class="btn btn-sm btn-info shift-action-btn" id="view-shift-summary" title="View Shift Summary">
            <i class="mdi mdi-chart-bar"></i>
        </button>
        <button class="btn btn-sm btn-warning shift-action-btn" id="view-handovers-btn" title="View Handovers">
            <i class="mdi mdi-file-document-multiple"></i>
        </button>
        <button class="btn btn-sm btn-danger shift-action-btn" id="end-shift-btn" title="End Shift">
            <i class="mdi mdi-stop-circle"></i>
        </button>
    </div>
</div>

<!-- Start Shift Modal -->
<div class="modal fade" id="startShiftModal" tabindex="-1" aria-labelledby="startShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="startShiftModalLabel">
                    <i class="mdi mdi-play-circle"></i> Start Your Shift
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Shift Configuration -->
                <div id="shift-config-step" class="shift-step">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="shift-ward-select">Ward Assignment</label>
                                <select class="form-control" id="shift-ward-select">
                                    <option value="">All Wards (Floating)</option>
                                </select>
                                <small class="text-muted">Select your assigned ward to see relevant handovers</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="shift-type-select">Shift Type</label>
                                <select class="form-control" id="shift-type-select">
                                    <option value="">Auto-detect</option>
                                    <option value="morning">â‰¡Æ’Ã®Ã  Morning (6AM - 2PM)</option>
                                    <option value="afternoon">Î“Ã¿Ã‡âˆ©â••Ã… Afternoon (2PM - 10PM)</option>
                                    <option value="night">â‰¡Æ’Ã®Ã– Night (10PM - 6AM)</option>
                                </select>
                                <small class="text-muted">Leave blank to auto-detect based on current time</small>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-outline-primary" id="load-ward-handovers-btn">
                            <i class="mdi mdi-magnify"></i> Check for Handovers
                        </button>
                    </div>
                </div>

                <!-- Step 2: Pending Handovers (if any) -->
                <div id="shift-handovers-step" class="shift-step" style="display: none;">
                    <hr>
                    <div class="alert alert-info">
                        <i class="mdi mdi-information-outline"></i>
                        <strong>Review Previous Handovers</strong><br>
                        Please review and acknowledge the following handovers before starting your shift.
                    </div>
                    <div id="start-shift-handovers-list" class="handovers-acknowledgment-list"></div>
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary btn-sm" id="load-more-handovers-btn">
                            <i class="mdi mdi-history"></i> Load Older Handovers
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-start-shift-btn">
                    <i class="mdi mdi-play-circle"></i> Start Shift
                </button>
            </div>
        </div>
    </div>
</div>

<!-- End Shift Modal -->
<div class="modal fade" id="endShiftModal" tabindex="-1" aria-labelledby="endShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="endShiftModalLabel">
                    <i class="mdi mdi-stop-circle"></i> End Your Shift
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Shift Summary -->
                <div class="shift-end-summary mb-4">
                    <h6 class="text-muted mb-3">Shift Summary</h6>
                    <div class="row text-center">
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-duration">--:--</div>
                                <div class="stat-label">Duration</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-vitals">0</div>
                                <div class="stat-label">Vitals</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-medications">0</div>
                                <div class="stat-label">Medications</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-notes">0</div>
                                <div class="stat-label">Notes</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-total">0</div>
                                <div class="stat-label">Total Actions</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audit-Based Activity Preview -->
                <div class="audit-activity-preview mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-muted mb-0">
                            <i class="mdi mdi-history"></i> Recorded Activities (Auto-tracked)
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="load-shift-preview-btn">
                            <i class="mdi mdi-refresh"></i> Load Preview
                        </button>
                    </div>
                    <div id="shift-activity-preview" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                        <div class="text-center text-muted py-3">
                            <i class="mdi mdi-information-outline"></i> Click "Load Preview" to see auto-tracked activities during your shift
                        </div>
                    </div>
                </div>

                <!-- Handover Form -->
                <div class="handover-form">
                    <h6 class="text-muted mb-3">Create Handover Document</h6>

                    <div class="form-group mb-3">
                        <label for="end-shift-critical-notes">
                            <i class="mdi mdi-alert text-danger"></i> Critical Notes
                            <small class="text-muted">(Urgent items for incoming nurse)</small>
                        </label>
                        <textarea class="form-control" id="end-shift-critical-notes" rows="3"
                            placeholder="Document any critical patient conditions, pending urgent tasks, or important alerts..."></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label for="end-shift-concluding-notes">
                            <i class="mdi mdi-note-text"></i> Concluding Notes
                        </label>
                        <textarea class="form-control" id="end-shift-concluding-notes" rows="3"
                            placeholder="General shift summary and observations..."></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-format-list-checks"></i> Pending Tasks</label>
                        <div id="pending-tasks-container">
                            <div class="pending-task-row mb-2">
                                <div class="input-group">
                                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                                        <option value="normal">Normal</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                                            <i class="mdi mdi-close"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary mt-2" id="add-pending-task-btn">
                            <i class="mdi mdi-plus"></i> Add Task
                        </button>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="create-handover-checkbox" checked>
                        <label class="form-check-label" for="create-handover-checkbox">
                            Create handover document for incoming nurse
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-end-shift-btn">
                    <i class="mdi mdi-stop-circle"></i> End Shift
                </button>
            </div>
        </div>
    </div>
</div>
                    <i class="mdi mdi-stop-circle"></i> End Shift
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Handovers List Modal (Cards-based with Backend Processing) -->
<div class="modal fade" id="handoversListModal" tabindex="-1" aria-labelledby="handoversListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="handoversListModalLabel">
                    <i class="mdi mdi-file-document-multiple"></i> Shift Handovers
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Filter Panel -->
                <div class="handover-filter-panel p-3 bg-light border-bottom">
                    <!-- Primary Filters Row -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-3">
                            <label class="form-label-modern">
                                <i class="mdi mdi-hospital-building"></i> Ward
                            </label>
                            <select class="form-control form-control-modern" id="handover-filter-ward">
                                <option value="">All Wards</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-modern">
                                <i class="mdi mdi-clock-outline"></i> Shift Type
                            </label>
                            <select class="form-control form-control-modern" id="handover-filter-shift">
                                <option value="">All Shifts</option>
                                <option value="morning">â‰¡Æ’Ã®Ã  Morning (6AM - 2PM)</option>
                                <option value="afternoon">Î“Ã¿Ã‡âˆ©â••Ã… Afternoon (2PM - 10PM)</option>
                                <option value="night">â‰¡Æ’Ã®Ã– Night (10PM - 6AM)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-modern">
                                <i class="mdi mdi-magnify"></i> Search
                            </label>
                            <input type="text" class="form-control form-control-modern" id="handover-filter-search"
                                   placeholder="Search by nurse, summary...">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button class="btn btn-primary flex-grow-1 btn-modern" id="apply-handover-filters">
                                <i class="mdi mdi-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Filters -->
                    <div id="advancedFiltersSection">
                        <div class="row g-2 pt-3 border-top mt-2">
                            <div class="col-md-2">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-check-circle-outline"></i> Status
                                </label>
                                <select class="form-control form-control-modern" id="handover-filter-status">
                                    <option value="">All Status</option>
                                    <option value="pending">Î“Ã…â”‚ Pending</option>
                                    <option value="acknowledged">Î“Â£Ã  Acknowledged</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-alert-circle-outline"></i> Priority
                                </label>
                                <select class="form-control form-control-modern" id="handover-filter-priority">
                                    <option value="">All Priority</option>
                                    <option value="critical">â‰¡Æ’Ã¶â”¤ Critical Only</option>
                                    <option value="has_tasks">â‰¡Æ’Ã´Ã¯ Has Pending Tasks</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-calendar-start"></i> Date From
                                </label>
                                <input type="date" class="form-control form-control-modern" id="handover-filter-from">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-calendar-end"></i> Date To
                                </label>
                                <input type="date" class="form-control form-control-modern" id="handover-filter-to">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-sort"></i> Sort By
                                </label>
                                <select class="form-control form-control-modern" id="handover-filter-sort">
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="priority">Priority First</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-outline-danger w-100 btn-modern" id="clear-handover-filters">
                                    <i class="mdi mdi-filter-remove"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary Bar -->
                <div class="handover-stats-bar px-3 py-2 bg-white border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex gap-3">
                        <span class="badge bg-secondary" id="handover-total-count">
                            <i class="mdi mdi-file-document-multiple"></i> Total: <span>0</span>
                        </span>
                        <span class="badge bg-warning text-dark" id="handover-pending-count">
                            <i class="mdi mdi-clock-alert"></i> Pending: <span>0</span>
                        </span>
                        <span class="badge bg-danger" id="handover-critical-count">
                            <i class="mdi mdi-alert-circle"></i> Critical: <span>0</span>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small" id="handover-page-info">Page 1 of 1</span>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" id="handover-view-cards" title="Cards View">
                                <i class="mdi mdi-view-grid"></i>
                            </button>
                            <button class="btn btn-outline-secondary" id="handover-view-list" title="List View">
                                <i class="mdi mdi-view-list"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cards Container -->
                <div class="handover-cards-container p-3" id="handover-cards-container">
                    <!-- Loading State -->
                    <div class="handover-loading text-center py-5" id="handover-loading">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading handovers...</p>
                    </div>

                    <!-- Empty State -->
                    <div class="handover-empty text-center py-5" id="handover-empty" style="display: none;">
                        <i class="mdi mdi-file-document-outline text-muted" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 text-muted">No Handovers Found</h5>
                        <p class="text-muted small">Try adjusting your filters or select a different ward/shift.</p>
                    </div>

                    <!-- Cards Grid (populated dynamically) -->
                    <div class="row g-3" id="handover-cards-grid"></div>
                </div>

                <!-- Pagination -->
                <div class="handover-pagination p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <label class="form-label-modern mb-0">Per page:</label>
                        <select class="form-control form-control-modern" id="handover-per-page" style="width: 100px; height: 40px !important;">
                            <option value="6">6</option>
                            <option value="12" selected>12</option>
                            <option value="24">24</option>
                            <option value="48">48</option>
                        </select>
                    </div>
                    <nav aria-label="Handover pagination">
                        <ul class="pagination pagination-sm mb-0" id="handover-pagination-list">
                            <!-- Pagination items populated dynamically -->
                        </ul>
                    </nav>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Handover Detail Modal -->
<div class="modal fade" id="handoverDetailModal" tabindex="-1" aria-labelledby="handoverDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="handoverDetailModalLabel">
                    <i class="mdi mdi-file-document"></i> Handover Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="handover-detail-content">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="acknowledge-handover-detail-btn" style="display: none;">
                    <i class="mdi mdi-check-circle"></i> Acknowledge
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Shift Summary Modal -->
<div class="modal fade" id="shiftSummaryModal" tabindex="-1" aria-labelledby="shiftSummaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="shiftSummaryModalLabel">
                    <i class="mdi mdi-chart-bar"></i> Current Shift Summary
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="shift-summary-content">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Shift Lock Overlay Styles -->
<style>
    /* Audit Details Styles */
    .audit-details-list {
        font-size: 0.9rem;
    }
    .audit-patient-group {
        border-bottom: 1px solid #e9ecef;
        padding-bottom: 1rem;
    }
    .audit-patient-group:last-child {
        border-bottom: none;
    }
    .audit-items {
        border-left: 3px solid var(--hospital-primary, #007bff);
    }
    .audit-item {
        border-left: 2px solid transparent;
    }
    .audit-item:hover {
        border-left-color: var(--hospital-primary, #007bff);
    }
    .badge-sm {
        font-size: 0.7rem;
        padding: 0.2em 0.5em;
    }
    .key-changes-list .patient-changes {
        border-left: 3px solid var(--hospital-primary, #007bff);
        padding-left: 10px;
        margin-bottom: 15px;
    }
    .key-changes-list ul {
        padding-left: 20px;
        margin: 0;
    }
    .key-changes-list li {
        margin-bottom: 3px;
    }

    /* Shift Lock Overlay */
    .shift-lock-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.97);
        z-index: 1040; /* Below Bootstrap modal */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .shift-lock-overlay.modal-open-hidden {
        opacity: 0;
        pointer-events: none;
    }

    .shift-lock-content {
        text-align: center;
        max-width: 500px;
        padding: 2rem;
    }

    .shift-lock-icon {
        font-size: 5rem;
        color: var(--hospital-primary);
        margin-bottom: 1.5rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); }
    }

    .shift-lock-content h3 {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .pending-handovers-preview {
        text-align: left;
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1rem;
        margin: 1.5rem 0;
    }

    .shift-lock-nav-buttons {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        border-top: 1px solid #dee2e6;
        padding-top: 1.5rem;
    }

    .pending-handovers-list {
        max-height: 200px;
        overflow-y: auto;
    }

    .pending-handover-item {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pending-handover-item.has-critical {
        border-left: 3px solid var(--danger);
    }

    /* Floating Shift Control Button */
    .shift-control-fab {
        position: fixed;
        bottom: 100px;
        right: 30px;
        z-index: 1050;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        cursor: move;
    }

    .shift-fab-timer {
        background: rgba(0, 0, 0, 0.8);
        color: #fff;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.85rem;
        font-weight: 600;
        font-family: monospace;
    }

    .shift-fab-timer.overdue {
        background: var(--danger);
        animation: blink 1s infinite;
    }

    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .shift-fab-main .btn-shift-control {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--hospital-primary);
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        font-size: 1.5rem;
        transition: all 0.3s;
    }

    .shift-fab-main .btn-shift-control:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
    }

    .shift-fab-main .btn-shift-control.active {
        background: #28a745;
    }

    .shift-fab-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .shift-fab-actions .shift-action-btn {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    /* Handover Acknowledgment List */
    .handovers-acknowledgment-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .handover-ack-item {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 0.75rem;
    }

    .handover-ack-item.critical {
        border-left: 4px solid var(--danger);
        background: #fff5f5;
    }

    .handover-ack-item.shake-highlight {
        animation: shake 0.5s ease-in-out;
        box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-5px); }
        40%, 80% { transform: translateX(5px); }
    }

    .handover-ack-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }

    .handover-ack-meta {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .handover-ack-content {
        font-size: 0.9rem;
        color: #495057;
    }

    .handover-ack-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #dee2e6;
    }

    /* =============================================
       HANDOVER CARDS MODAL STYLES
       ============================================= */
    .handover-filter-panel {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .handover-cards-container {
        min-height: 400px;
        max-height: 60vh;
        overflow-y: auto;
    }

    .handover-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 0.75rem;
        transition: all 0.2s ease;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .handover-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .handover-card.critical {
        border-left: 4px solid #dc3545;
        background: linear-gradient(to right, #fff5f5 0%, #fff 20%);
    }

    .handover-card.pending {
        border-top: 3px solid #ffc107;
    }

    .handover-card.acknowledged {
        opacity: 0.85;
    }

    .handover-card-header {
        padding: 0.75rem 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .handover-card-shift-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-weight: 600;
    }

    .handover-card-shift-badge.morning {
        background: #fff3cd;
        color: #856404;
    }

    .handover-card-shift-badge.afternoon {
        background: #ffe8cc;
        color: #cc5500;
    }

    .handover-card-shift-badge.night {
        background: #d4edda;
        color: #155724;
    }

    .handover-card-body {
        padding: 1rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .handover-card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .handover-card-nurse {
        font-weight: 600;
        color: #333;
        font-size: 0.95rem;
    }

    .handover-card-time {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .handover-card-ward {
        font-size: 0.85rem;
        color: #495057;
        margin-bottom: 0.5rem;
    }

    .handover-card-ward i {
        color: var(--hospital-primary, #007bff);
    }

    .handover-card-summary {
        font-size: 0.9rem;
        color: #495057;
        line-height: 1.5;
        flex: 1;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }

    .handover-card-critical-preview {
        background: #fff3f3;
        border: 1px solid #f5c6cb;
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        margin-top: 0.75rem;
        font-size: 0.85rem;
        color: #721c24;
    }

    .handover-card-critical-preview i {
        color: #dc3545;
    }

    .handover-card-stats {
        display: flex;
        gap: 1rem;
        padding-top: 0.75rem;
        margin-top: auto;
        border-top: 1px solid #e9ecef;
        font-size: 0.8rem;
        color: #6c757d;
    }

    .handover-card-stat {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .handover-card-stat.danger {
        color: #dc3545;
    }

    .handover-card-stat.warning {
        color: #ffc107;
    }

    .handover-card-footer {
        padding: 0.75rem 1rem;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .handover-card-status {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
    }

    .handover-card-status.acknowledged {
        background: #d4edda;
        color: #155724;
    }

    .handover-card-status.pending {
        background: #fff3cd;
        color: #856404;
    }

    .handover-card-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    /* List View Styles */
    .handover-list-item {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.2s ease;
    }

    .handover-list-item:hover {
        background: #f8f9fa;
    }

    .handover-list-item.critical {
        border-left: 4px solid #dc3545;
    }

    .handover-list-info {
        flex: 1;
        min-width: 0;
    }

    .handover-list-meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.25rem;
    }

    .handover-list-summary {
        font-size: 0.9rem;
        color: #495057;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Pagination Styles */
    .handover-pagination .pagination .page-link {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    /* Stats Bar */
    .handover-stats-bar .badge {
        font-size: 0.8rem;
    }

    /* View Toggle */
    #handover-view-cards.active,
    #handover-view-list.active {
        background: var(--hospital-primary, #007bff);
        color: white;
    }

    /* End Shift Stats */
    .stat-box {
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1rem;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--hospital-primary);
    }

    .stat-label {
        font-size: 0.8rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    /* Pending Tasks */
    .pending-task-row {
        animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .shift-control-fab {
            bottom: 80px;
            right: 15px;
        }

        .shift-fab-main .btn-shift-control {
            width: 50px;
            height: 50px;
            font-size: 1.25rem;
        }

        .shift-fab-actions .shift-action-btn {
            width: 40px;
            height: 40px;
        }
    }
</style>

<!-- Include Clinical Context Modal -->
@include('admin.partials.clinical_context_modal')
@include('admin.partials.treatment-plan-modal')
@include('admin.partials.re-prescribe-encounter-modal')
@include('admin.partials.invest_res_modal', ['save_route' => 'lab.saveResult'])
@include('admin.partials.invest_res_js')
@include('admin.partials.invest_res_view_imaging_modal')
@include('admin.partials.invest_res_view_imaging_js')

@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'nursing'])
<script>
// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let vitalTooltip = null;
let queueDataTable = null;
let currentQueueFilter = 'admitted';
var medicationChartPrescribedRoute = "{{ route('nurse.medication.prescribed_drugs', [':patient']) }}";
let injectionPrescriptions = [];
let injectionPrescriptionsLoaded = false;

// =============================================
// VIEW MANAGEMENT HELPERS
// =============================================

// Hide all overlapping views - call this before showing any new view
function hideAllViews() {
    // Hide empty state
    $('#empty-state').hide();

    // Hide queue view
    $('#queue-view').removeClass('active').css('display', 'none');
    $('.queue-item').removeClass('active');

    // Hide reports view
    $('#reports-view').removeClass('active');

    // Hide ward dashboard
    $('#ward-dashboard-view').removeClass('active');

    // Hide patient workspace
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active').hide();
}

// =============================================
// QUEUE FUNCTIONALITY (derived from billing workbench pattern)
// =============================================

// Show queue view with specific filter
function showQueue(filter) {
    // First hide all other views to prevent stacking
    hideAllViews();
    currentQueueFilter = filter;

    // Update queue title based on filter type
    const titles = {
        'admitted': '<i class="mdi mdi-bed"></i> Admitted Patients',
        'vitals': '<i class="mdi mdi-heart-pulse"></i> Vitals Queue',
        'bed-requests': '<i class="mdi mdi-bed-empty"></i> Bed Requests',
        'discharge-requests': '<i class="mdi mdi-account-minus"></i> Discharge Requests',
        'medication-due': '<i class="mdi mdi-pill"></i> Medication Due',
        'emergency': '<i class="mdi mdi-ambulance"></i> Emergency Queue',
        'all': '<i class="mdi mdi-format-list-bulleted"></i> All Patients'
    };
    $('#queue-view-title').html(titles[filter] || titles['admitted']);

    // Update active state on queue buttons
    $('.queue-item').removeClass('active');
    $(`.queue-item[data-filter="${filter}"]`).addClass('active');

    // Show queue view
    $('#queue-view').addClass('active').css('display', 'flex');

    // On mobile, hide search pane and show main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Load queue data based on filter
    loadQueueData(filter);
}

// Hide queue view
function hideQueue() {
    $('#queue-view').removeClass('active').css('display', 'none');
    $('.queue-item').removeClass('active');

    if (currentPatient) {
        // If patient was selected, show their workspace
        $('#patient-header').addClass('active');
        $('#workspace-content').show().addClass('active');
    } else {
        // Otherwise show empty state
        $('#empty-state').show();
    }

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

// Load queue data based on filter type
function loadQueueData(filter) {
    const $container = $('#queue-view .queue-view-content');
    $container.html('<div class="text-center p-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading...</div>');

    // Determine endpoint and handler based on filter
    let url = '';
    let handler = null;

    switch(filter) {
        case 'admitted':
            url = '{{ route("nursing-workbench.admitted-patients") }}';
            handler = displayAdmittedPatientsQueue;
            break;
        case 'vitals':
            url = '{{ route("nursing-workbench.vitals-queue") }}';
            handler = displayVitalsQueue;
            break;
        case 'bed-requests':
            url = '{{ route("nursing-workbench.bed-requests-queue") }}';
            handler = displayBedRequestsQueue;
            break;
        case 'discharge-requests':
            url = '{{ route("nursing-workbench.discharge-queue") }}';
            handler = displayDischargeRequestsQueue;
            break;
        case 'medication-due':
            url = '{{ route("nursing-workbench.medication-due") }}';
            handler = displayMedicationDueQueue;
            break;
        case 'emergency':
            url = '{{ route("emergency.queue") }}';
            handler = displayEmergencyQueue;
            break;
        default:
            url = '{{ route("nursing-workbench.admitted-patients") }}';
            handler = displayAdmittedPatientsQueue;
    }

    $.ajax({
        url: url,
        method: 'GET',
        success: function(response) {
            // Handle both array response and DataTables format
            const data = response.data || response;
            if (!data || (Array.isArray(data) && data.length === 0)) {
                $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-account-off mdi-48px"></i><br>No patients found in this queue</div>');
                return;
            }
            handler(data);
        },
        error: function(xhr) {
            console.error('Error loading queue:', xhr);
            $container.html('<div class="text-center p-4 text-danger"><i class="mdi mdi-alert-circle mdi-48px"></i><br>Failed to load patients</div>');
        }
    });
}

// Display admitted patients in queue (card-based)
// Display admitted patients in queue â€” ward-grouped with filters
let admittedPatientsData = [];
let admittedWardFilter = 'all';
let admittedStatusFilter = 'all';

function displayAdmittedPatientsQueue(patients) {
    admittedPatientsData = patients;
    const $container = $('#queue-view .queue-view-content');

    // Collect unique wards
    const wards = [...new Set(patients.map(p => p.ward).filter(w => w && w !== 'N/A'))];

    // Filter bar
    let filterHtml = `<div class="d-flex flex-wrap gap-2 mb-3 p-2 bg-light rounded align-items-center">
        <div class="d-flex align-items-center gap-2">
            <label class="mb-0 fw-bold small"><i class="mdi mdi-hospital-building"></i> Ward:</label>
            <select class="form-select form-select-sm" id="admitted-ward-filter" style="width: auto; min-width: 150px;">
                <option value="all">All Wards (${patients.length})</option>
                ${wards.map(w => {
                    const count = patients.filter(p => p.ward === w).length;
                    return `<option value="${w}">${w} (${count})</option>`;
                }).join('')}
            </select>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="mb-0 fw-bold small"><i class="mdi mdi-filter-variant"></i> Status:</label>
            <select class="form-select form-select-sm" id="admitted-status-filter" style="width: auto; min-width: 140px;">
                <option value="all">All Statuses</option>
                <option value="admitted">Admitted</option>
                <option value="discharge_requested">Discharge Requested</option>
                <option value="pending_checklist">Pending Checklist</option>
            </select>
        </div>
        <div class="ms-auto d-flex gap-2 small">
            <span class="badge bg-danger">${patients.filter(p => p.overdue_meds > 0).length} overdue meds</span>
            <span class="badge bg-warning text-dark">${patients.filter(p => p.vitals_due).length} vitals due</span>
            <span class="badge bg-info">${patients.filter(p => p.priority === 'emergency').length} emergency</span>
        </div>
    </div>`;

    let cardsHtml = renderAdmittedCards(patients);

    $container.html(filterHtml + '<div id="admitted-cards-container">' + cardsHtml + '</div>');

    // Attach filter handlers
    $('#admitted-ward-filter').on('change', function() {
        admittedWardFilter = $(this).val();
        applyAdmittedFilters();
    });
    $('#admitted-status-filter').on('change', function() {
        admittedStatusFilter = $(this).val();
        applyAdmittedFilters();
    });
}

function applyAdmittedFilters() {
    let filtered = admittedPatientsData;
    if (admittedWardFilter !== 'all') {
        filtered = filtered.filter(p => p.ward === admittedWardFilter);
    }
    if (admittedStatusFilter !== 'all') {
        filtered = filtered.filter(p => p.admission_status === admittedStatusFilter);
    }
    $('#admitted-cards-container').html(renderAdmittedCards(filtered));
}

function renderAdmittedCards(patients) {
    if (patients.length === 0) {
        return '<div class="text-center p-4 text-muted"><i class="mdi mdi-bed mdi-48px"></i><br>No patients match the selected filters</div>';
    }

    // Group by ward
    const wardGroups = {};
    patients.forEach(p => {
        const ward = p.ward || 'Unassigned';
        if (!wardGroups[ward]) wardGroups[ward] = [];
        wardGroups[ward].push(p);
    });

    let html = '';
    const wardTypeIcons = {
        'icu': 'mdi-heart-pulse',
        'emergency': 'mdi-ambulance',
        'pediatric': 'mdi-baby-carriage',
        'maternity': 'mdi-mother-nurse',
        'isolation': 'mdi-biohazard',
        'general': 'mdi-hospital-building',
    };

    Object.keys(wardGroups).sort().forEach(ward => {
        const wardPatients = wardGroups[ward];
        const wardType = wardPatients[0]?.ward_type || 'general';
        const wardIcon = wardTypeIcons[wardType] || 'mdi-hospital-building';

        html += `<div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2 px-2">
                <h6 class="mb-0 fw-bold text-primary"><i class="mdi ${wardIcon}"></i> ${ward}</h6>
                <span class="badge bg-primary rounded-pill">${wardPatients.length}</span>
            </div>
            <div class="row px-2">`;

        wardPatients.forEach(p => {
            const priorityBorder = p.priority === 'emergency' ? 'border-left: 4px solid #dc3545;'
                : (p.priority === 'urgent' ? 'border-left: 4px solid #fd7e14;' : '');
            const priorityBadge = p.priority === 'emergency'
                ? '<span class="badge bg-danger"><i class="mdi mdi-alert"></i> Emergency</span>'
                : (p.priority === 'urgent' ? '<span class="badge bg-warning text-dark">Urgent</span>' : '');

            html += `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card-modern queue-patient-card" style="cursor: pointer; ${priorityBorder}" onclick="loadPatient(${p.patient_id}); hideQueue();">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="mb-0 ${p.priority === 'emergency' ? 'text-danger fw-bold' : ''}">${p.name || 'N/A'}</h6>
                                ${priorityBadge}
                            </div>
                            <small class="text-muted d-block">${p.file_no || ''} | ${p.age || ''} ${p.gender || ''}</small>
                            ${p.hmo ? `<small class="text-info d-block"><i class="mdi mdi-shield-check"></i> ${p.hmo}</small>` : ''}
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small"><i class="mdi mdi-bed text-primary"></i> ${p.bed_name || 'No bed'}</span>
                                <span class="small text-muted"><i class="mdi mdi-calendar"></i> Day ${p.days_admitted || 0}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted"><i class="mdi mdi-doctor"></i> ${p.doctor || 'N/A'}</span>
                                <span class="small text-muted"><i class="mdi mdi-heart-pulse"></i> ${p.last_vitals || 'Never'}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                ${p.overdue_meds > 0 ? `<span class="badge bg-danger"><i class="mdi mdi-pill"></i> ${p.overdue_meds} overdue</span>` : ''}
                                ${p.pending_meds > 0 && p.overdue_meds === 0 ? `<span class="badge bg-warning text-dark"><i class="mdi mdi-pill"></i> ${p.pending_meds} due</span>` : ''}
                                ${p.vitals_due ? '<span class="badge bg-warning text-dark"><i class="mdi mdi-heart-pulse"></i> Vitals due</span>' : ''}
                                ${p.chief_complaint ? `<span class="badge bg-info" title="${p.chief_complaint}"><i class="mdi mdi-comment-medical"></i> CC</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>`;
        });

        html += '</div></div>';
    });

    return html;
}

// Display vitals queue with wait times and clinic filter
let vitalsQueueData = [];
let vitalsClinicFilter = 'all';
let vitalsClinicsLoaded = false;
let vitalsClinicsCache = [];

function displayVitalsQueue(patients) {
    vitalsQueueData = Array.isArray(patients) ? patients : (patients.data || []);
    const $container = $('#queue-view .queue-view-content');

    if (vitalsQueueData.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-heart-pulse mdi-48px"></i><br>No patients pending vitals</div>');
        return;
    }

    // Load clinics for filter if not already loaded
    if (!vitalsClinicsLoaded) {
        $.get('{{ route("nursing-workbench.clinics") }}', function(data) {
            vitalsClinicsCache = data.clinics || [];
            vitalsClinicsLoaded = true;
            renderVitalsQueueFull();
        });
    } else {
        renderVitalsQueueFull();
    }

    function renderVitalsQueueFull() {
        const patients = vitalsQueueData;
        const criticalWaits = patients.filter(p => p.wait_level === 'critical').length;
        const warningWaits = patients.filter(p => p.wait_level === 'warning').length;
        const emergencies = patients.filter(p => p.priority === 'emergency').length;

        let filterHtml = `<div class="d-flex flex-wrap gap-2 mb-3 p-2 bg-light rounded align-items-center">
            <div class="d-flex align-items-center gap-2">
                <label class="mb-0 fw-bold small"><i class="mdi mdi-hospital-building"></i> Clinic:</label>
                <select class="form-select form-select-sm" id="vitals-clinic-filter" style="width: auto; min-width: 170px;">
                    <option value="all">All Clinics (${patients.length})</option>
                    ${vitalsClinicsCache.map(c => `<option value="${c.id}" ${vitalsClinicFilter == c.id ? 'selected' : ''}>${c.name}</option>`).join('')}
                </select>
            </div>
            <div class="ms-auto d-flex gap-2 small">
                ${emergencies > 0 ? `<span class="badge bg-danger"><i class="mdi mdi-alert"></i> ${emergencies} emergency</span>` : ''}
                ${criticalWaits > 0 ? `<span class="badge bg-danger"><i class="mdi mdi-clock-alert"></i> ${criticalWaits} long wait</span>` : ''}
                ${warningWaits > 0 ? `<span class="badge bg-warning text-dark"><i class="mdi mdi-clock"></i> ${warningWaits} moderate wait</span>` : ''}
                <span class="badge bg-secondary">${patients.length} total in queue</span>
            </div>
        </div>`;

        $container.html(filterHtml + '<div id="vitals-cards-container">' + renderVitalsCards(patients) + '</div>');

        $('#vitals-clinic-filter').on('change', function() {
            vitalsClinicFilter = $(this).val();
            // Reload from server with clinic filter
            $.get('{{ route("nursing-workbench.vitals-queue") }}', { clinic_id: vitalsClinicFilter }, function(data) {
                vitalsQueueData = data;
                $('#vitals-cards-container').html(renderVitalsCards(data));
            });
        });
    }
}

function renderVitalsCards(patients) {
    if (patients.length === 0) {
        return '<div class="text-center p-4 text-muted"><i class="mdi mdi-heart-pulse mdi-48px"></i><br>No patients pending vitals</div>';
    }

    let html = '<div class="row p-2">';
    patients.forEach((p, index) => {
        const waitColor = p.wait_level === 'critical' ? '#dc3545'
            : (p.wait_level === 'warning' ? '#fd7e14' : '#28a745');
        const waitBg = p.wait_level === 'critical' ? 'bg-danger'
            : (p.wait_level === 'warning' ? 'bg-warning text-dark' : 'bg-success');
        const priorityBorder = p.priority === 'emergency' ? 'border-left: 4px solid #dc3545;'
            : (p.priority === 'urgent' ? 'border-left: 4px solid #fd7e14;' : `border-left: 4px solid ${waitColor};`);
        const priorityBadge = p.priority === 'emergency'
            ? '<span class="badge bg-danger"><i class="mdi mdi-alert"></i> Emergency</span>'
            : (p.priority === 'urgent' ? '<span class="badge bg-warning text-dark">Urgent</span>' : '');
        const sourceBadge = p.source === 'emergency_intake'
            ? '<span class="badge bg-danger"><i class="mdi mdi-ambulance"></i> ER</span>' : '';

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="cursor: pointer; ${priorityBorder}" onclick="loadPatient(${p.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <span class="badge bg-light text-dark border me-1">#${index + 1}</span>
                                <strong class="${p.priority === 'emergency' ? 'text-danger' : ''}">${p.patient_name || 'N/A'}</strong>
                            </div>
                            <div class="d-flex gap-1">
                                ${priorityBadge}${sourceBadge}
                            </div>
                        </div>
                        <small class="text-muted d-block">${p.file_no || ''} | ${p.age || ''} ${p.gender || ''}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small"><i class="mdi mdi-hospital-building text-primary"></i> ${p.clinic || 'N/A'}</span>
                            <span class="small"><i class="mdi mdi-doctor"></i> ${p.doctor || 'N/A'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><i class="mdi mdi-clock-outline"></i> Queued ${p.queued_at || ''}</span>
                            <span class="badge ${waitBg}"><i class="mdi mdi-timer-sand"></i> ${p.wait_display || '0min'}</span>
                        </div>
                        ${p.triage_note ? `<div class="mt-2 p-2 bg-light rounded small"><i class="mdi mdi-note-text text-info"></i> ${p.triage_note.substring(0, 100)}${p.triage_note.length > 100 ? '...' : ''}</div>` : ''}
                    </div>
                </div>
            </div>`;
    });
    html += '</div>';
    return html;
}

// Display bed requests queue (card-based)
function displayBedRequestsQueue(requests) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(requests)) {
        requests = requests.data || [];
    }

    if (requests.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-bed-empty mdi-48px"></i><br>No bed requests at this time</div>');
        return;
    }

    let html = '<div class="row p-2">';
    requests.forEach(r => {
        const priorityLower = (r.priority || 'routine').toLowerCase();
        const statusClass = priorityLower === 'urgent' || priorityLower === 'emergency' ? 'border-danger' : 'border-info';
        const badgeClass = priorityLower === 'urgent' || priorityLower === 'emergency' ? 'badge-danger' : 'badge-secondary';

        // Escape quotes for use in onclick attributes
        const patientName = (r.patient_name || r.name || 'N/A').replace(/'/g, "\\'");
        const fileNo = (r.file_no || '').replace(/'/g, "\\'");

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern ${statusClass} queue-patient-card" style="cursor: pointer;" onclick="loadPatient(${r.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <h6 class="mb-1">${r.patient_name || r.name || 'N/A'}</h6>
                        <small class="text-muted d-block">${r.file_no || ''}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span><i class="mdi mdi-bed"></i> ${r.requested_ward || 'Any ward'}</span>
                            <span class="badge ${badgeClass}">${(r.priority || 'routine').toUpperCase()}</span>
                        </div>
                        <small class="text-muted mt-2 d-block">${r.reason || ''}</small>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); WardDashboard.openBedAssignment(${r.admission_id || r.id}, '${patientName}', '${fileNo}');">
                                <i class="mdi mdi-clipboard-check"></i> Process Admission
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(html);
}

// Display discharge requests queue â€” detailed cards with billing warnings
function displayDischargeRequestsQueue(requests) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(requests)) {
        requests = requests.data || [];
    }

    if (requests.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-account-minus mdi-48px"></i><br>No discharge requests at this time</div>');
        return;
    }

    // Summary bar
    const withUnpaid = requests.filter(r => r.unpaid_bills > 0).length;
    const checklistPhase = requests.filter(r => r.admission_status === 'discharge_checklist').length;

    let summaryHtml = `<div class="d-flex flex-wrap gap-2 mb-3 p-2 bg-light rounded align-items-center">
        <span class="fw-bold small"><i class="mdi mdi-account-minus"></i> ${requests.length} discharge requests</span>
        <div class="ms-auto d-flex gap-2 small">
            ${withUnpaid > 0 ? `<span class="badge bg-danger"><i class="mdi mdi-cash-remove"></i> ${withUnpaid} unpaid bills</span>` : ''}
            ${checklistPhase > 0 ? `<span class="badge bg-info"><i class="mdi mdi-clipboard-check"></i> ${checklistPhase} in checklist</span>` : ''}
        </div>
    </div>`;

    let html = '<div class="row p-2">';
    requests.forEach(r => {
        const patientName = (r.patient_name || r.name || 'N/A').replace(/'/g, "\\'");
        const fileNo = (r.file_no || '').replace(/'/g, "\\'");
        const bedName = (r.bed_name || 'No bed').replace(/'/g, "\\'");
        const hasUnpaid = r.unpaid_bills > 0;
        const borderStyle = hasUnpaid ? 'border-left: 4px solid #dc3545;' : 'border-left: 4px solid #ffc107;';
        const statusBadge = r.admission_status === 'discharge_checklist'
            ? '<span class="badge bg-info">Checklist In Progress</span>'
            : '<span class="badge bg-warning text-dark">Discharge Requested</span>';

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="cursor: pointer; ${borderStyle}" onclick="loadPatient(${r.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0">${r.patient_name || r.name || 'N/A'}</h6>
                            ${statusBadge}
                        </div>
                        <small class="text-muted d-block">${r.file_no || ''}</small>
                        ${r.hmo ? `<small class="text-info d-block"><i class="mdi mdi-shield-check"></i> ${r.hmo}</small>` : ''}
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small"><i class="mdi mdi-bed text-primary"></i> ${r.bed_name || 'No bed'}</span>
                            <span class="small text-muted"><i class="mdi mdi-hospital-building"></i> ${r.ward || 'N/A'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted"><i class="mdi mdi-doctor"></i> ${r.doctor || 'N/A'}</span>
                            <span class="small text-muted"><i class="mdi mdi-calendar"></i> ${r.days_admitted || 0} days admitted</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><i class="mdi mdi-clock"></i> Requested: ${r.wait_display || r.discharge_requested_at || 'N/A'}</small>
                        </div>
                        ${r.discharge_reason ? `<small class="d-block text-muted mb-2"><i class="mdi mdi-comment-text"></i> ${r.discharge_reason}</small>` : ''}
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            ${hasUnpaid ? `<span class="badge bg-danger"><i class="mdi mdi-cash-remove"></i> ${r.unpaid_bills} unpaid bills</span>` : '<span class="badge bg-success"><i class="mdi mdi-check-circle"></i> Bills clear</span>'}
                        </div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-warning w-100" onclick="event.stopPropagation(); WardDashboard.openDischarge(${r.admission_id}, '${patientName}', '${fileNo}', '${bedName}');">
                                <i class="mdi mdi-clipboard-check"></i> Process Discharge
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(summaryHtml + html);
}

// Display medication due queue â€” detailed with overdue timing and ward grouping
function displayMedicationDueQueue(patients) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(patients)) {
        patients = patients.data || [];
    }

    if (patients.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-pill mdi-48px"></i><br>No medications due at this time</div>');
        return;
    }

    // Sort by overdue_minutes desc (most overdue first)
    patients.sort((a, b) => (b.overdue_minutes || 0) - (a.overdue_minutes || 0));

    const overdueCount = patients.filter(p => p.overdue).length;
    const dueCount = patients.filter(p => !p.overdue).length;

    // Summary bar
    let summaryHtml = `<div class="d-flex flex-wrap gap-2 mb-3 p-2 bg-light rounded align-items-center">
        <span class="fw-bold small"><i class="mdi mdi-pill"></i> Medication Round</span>
        <div class="ms-auto d-flex gap-2 small">
            ${overdueCount > 0 ? `<span class="badge bg-danger"><i class="mdi mdi-clock-alert"></i> ${overdueCount} overdue</span>` : ''}
            ${dueCount > 0 ? `<span class="badge bg-warning text-dark"><i class="mdi mdi-clock"></i> ${dueCount} due now</span>` : ''}
            <span class="badge bg-secondary">${patients.length} patients total</span>
        </div>
    </div>`;

    let html = '<div class="row p-2">';
    patients.forEach(p => {
        const isOverdue = p.overdue;
        const borderStyle = isOverdue
            ? 'border-left: 4px solid #dc3545;'
            : 'border-left: 4px solid #ffc107;';
        const urgencyBadge = isOverdue
            ? `<span class="badge bg-danger"><i class="mdi mdi-clock-alert"></i> ${p.overdue_display || 'Overdue'}</span>`
            : '<span class="badge bg-warning text-dark"><i class="mdi mdi-clock"></i> Due now</span>';
        const priorityBadge = p.priority === 'emergency'
            ? ' <span class="badge bg-danger"><i class="mdi mdi-alert"></i> Emergency</span>'
            : '';

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="cursor: pointer; ${borderStyle}" onclick="loadPatient(${p.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0 ${isOverdue ? 'text-danger fw-bold' : ''}">${p.name || p.patient_name || 'N/A'}</h6>
                            <div class="d-flex gap-1">${urgencyBadge}${priorityBadge}</div>
                        </div>
                        <small class="text-muted d-block">${p.file_no || ''}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small"><i class="mdi mdi-bed text-primary"></i> ${p.bed_name || 'N/A'}</span>
                            <span class="small text-muted"><i class="mdi mdi-hospital-building"></i> ${p.ward || 'N/A'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small"><i class="mdi mdi-pill text-warning"></i> <strong>${p.medication_count || 0}</strong> medications${p.overdue_count > 0 ? ` (${p.overdue_count} overdue)` : ''}</span>
                            ${p.next_med_time ? `<span class="small text-muted"><i class="mdi mdi-clock-fast"></i> Next: ${p.next_med_time}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(summaryHtml + html);
}

// Display emergency queue patients
function displayEmergencyQueue(patients) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(patients)) {
        patients = patients.data || [];
    }

    if (patients.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-ambulance mdi-48px"></i><br>No emergency patients at this time</div>');
        return;
    }

    const esiColors = { 1: '#dc3545', 2: '#fd7e14', 3: '#ffc107', 4: '#28a745', 5: '#17a2b8' };

    let html = '<div class="row p-2">';
    patients.forEach(p => {
        const esiLevel = p.esi_level;
        const esiColor = esiColors[esiLevel] || '#dc3545';
        const esiLabel = esiLevel ? ('ESI-' + esiLevel + ' ' + (p.esi_label || '')) : 'Emergency';

        const bedInfo = p.bed && p.bed !== 'Unassigned'
            ? `<span class="badge badge-info"><i class="mdi mdi-bed"></i> ${p.bed}</span>`
            : '<span class="badge badge-secondary">No Bed</span>';
        const wardInfo = p.ward && p.ward !== 'N/A' ? `<small class="text-muted">${p.ward}</small>` : '';

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="border-left: 4px solid ${esiColor};">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start" style="cursor:pointer;" onclick="loadPatient(${p.patient_id}); hideQueue();">
                            <h6 class="mb-1">${p.patient_name || 'N/A'}</h6>
                            <span class="badge" style="background: ${esiColor}; color: #fff; font-size: 0.7rem;">${esiLabel}</span>
                        </div>
                        <small class="text-muted d-block">${p.file_no || ''} | ${p.hmo || 'Private'}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            ${bedInfo} ${wardInfo}
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            ${p.status_badge || ''}
                            <small class="text-muted"><i class="mdi mdi-clock"></i> ${p.admitted_at || ''} (${p.duration || ''})</small>
                        </div>
                        <div class="mt-2 d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary flex-fill" onclick="openTransferWardModal(${p.admission_id}, '${(p.patient_name || '').replace(/'/g, "\\'")}')">
                                <i class="mdi mdi-swap-horizontal"></i> Transfer
                            </button>
                            <button class="btn btn-sm btn-outline-warning flex-fill" onclick="event.stopPropagation(); WardDashboard.openDischarge(${p.admission_id}, '${(p.patient_name || '').replace(/'/g, "\\'")}', '${(p.file_no || '').replace(/'/g, "\\'")}', '${(p.bed || '').replace(/'/g, "\\'")}')">
                                <i class="mdi mdi-account-minus"></i> Discharge
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="loadPatient(${p.patient_id}); hideQueue();">
                                <i class="mdi mdi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(html);
}

// ===== EMERGENCY WARD TRANSFER =====
function openTransferWardModal(admissionId, patientName) {
    $('#transfer-ward-admission-id').val(admissionId);
    $('#transfer-ward-patient-name').text(patientName);
    $('#transfer-ward-bed-select').html('<option value="">Loading beds...</option>');

    // Load available non-emergency wards/beds
    $.get('{{ route("nursing-workbench.ward-dashboard.available-beds") }}', function(beds) {
        const $sel = $('#transfer-ward-bed-select').empty().append('<option value="">-- Select target bed --</option>');
        beds.forEach(function(b) {
            $sel.append(`<option value="${b.id}">${b.name} â€” ${b.ward_name}</option>`);
        });
    });

    $('#transferWardModal').modal('show');
}

function submitWardTransfer() {
    const admissionId = $('#transfer-ward-admission-id').val();
    const bedId = $('#transfer-ward-bed-select').val();

    if (!bedId) {
        toastr.warning('Please select a target bed.');
        return;
    }

    const $btn = $('#transfer-ward-submit-btn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Transferring...');

    $.ajax({
        url: '{{ url("nursing-workbench") }}/admission/' + admissionId + '/transfer-ward',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            bed_id: bedId
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#transferWardModal').modal('hide');
                loadQueueCounts();
                // Refresh emergency queue view
                if (currentQueueFilter === 'emergency') {
                    loadQueueData('emergency');
                }
            } else {
                toastr.error(response.message || 'Transfer failed.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Server error during transfer.');
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Transfer Patient');
        }
    });
}

// Create vital tooltip element (defined early to avoid hoisting issues)
function createVitalTooltip() {
    vitalTooltip = $('<div class="vital-tooltip"></div>').appendTo('body');

    // Hide on mouse leave
    $(document).on('mouseleave', '.vital-item', function() {
        vitalTooltip.removeClass('active');
    });
}

$(document).ready(function() {
    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadUserPreferences();
    createVitalTooltip();
    updateQuickActions(); // Set initial state for patient-dependent buttons

    // Auto-select patient from URL query parameter (e.g., from Patient list workbench button)
    const urlParams = new URLSearchParams(window.location.search);
    const patientId = urlParams.get('patient_id');
    if (patientId) {
        loadPatient(patientId);
    }

    // Auto-open queue from URL parameter (e.g., from dashboard queue widget click)
    const queueFilter = urlParams.get('queue_filter');
    if (queueFilter && ['admitted', 'vitals', 'bed-requests', 'discharge-requests', 'medication-due', 'emergency'].includes(queueFilter)) {
        setTimeout(function() { showQueue(queueFilter); }, 500);
    }
});

function initializeEventListeners() {
    // Patient search (shared module)
    PatientSearch.init();

    // Workspace tabs
    $('.workspace-tab').on('click', function() {
        const tab = $(this).data('tab');
        switchWorkspaceTab(tab);
    });

    // Pending sub-tabs
    $('.pending-subtab').on('click', function() {
        const status = $(this).data('status');
        $('.pending-subtab').removeClass('active');
        $(this).addClass('active');
        renderPendingSubtabContent(status);
    });

    // Navigation buttons
    $('#btn-back-to-search').on('click', function() {
        // Mobile: go back to search pane
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    });

    $('#btn-view-work-pane').on('click', function() {
        // Mobile: switch to work pane without selecting a patient
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    });

    $('#btn-toggle-search').on('click', function() {
        // Desktop/Tablet: toggle search pane visibility
        $('#left-panel').toggleClass('hidden');
    });

    $('#btn-clinical-context').on('click', function() {
        // Check if patient is selected
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }
        // Open clinical context modal with shared module
        ClinicalContext.load(currentPatient);
    });

    // Clinical modal refresh buttons
    $('.refresh-clinical-btn').on('click', function() {
        const panel = $(this).data('panel');
        refreshClinicalPanel(panel);
    });

    // Clinical panel collapse (legacy - keeping for compatibility)
    $('.clinical-panel-header').on('click', function(e) {
        if (!$(e.target).closest('.clinical-panel-actions').length) {
            $(this).next('.clinical-panel-body').slideToggle(200);
            $(this).find('.collapse-btn i').toggleClass('fa-chevron-up fa-chevron-down');
        }
    });

    // Queue filter buttons - use data-filter and showQueue
    $('.queue-item').on('click', function() {
        const filter = $(this).data('filter');
        showQueue(filter);
    });

    // Show all queue button
    $('#show-all-queue-btn, #view-queue-btn').on('click', function() {
        showQueue('all');
    });

    // Close queue button
    $('#btn-close-queue').on('click', function() {
        hideQueue();
    });
}

function loadPatient(patientId) {
    currentPatient = patientId;

    // Set PATIENT_ID for medication and I/O charts
    PATIENT_ID = patientId;

    // CRITICAL: Hide all other views first to prevent stacking
    hideAllViews();

    // Show patient workspace
    $('#workspace-content').show().addClass('active');
    $('#patient-header').addClass('active');

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Update quick actions visibility
    updateQuickActions();

    // Load patient details
    $.ajax({
        url: `/nursing-workbench/patient/${patientId}/details`,
        method: 'GET',
        success: function(data) {
            console.log('Patient details loaded:', data); // Debug log
            currentPatientData = data; // Store patient data including allergies

            // Store patient weight for dose calculators (last recorded weight from vitals)
            window.patientWeight = data.last_weight || null;

            displayPatientInfo(data);

            // Load overview content using the already fetched data
            populateOverviewTab(data);

            // Initialize medication and I/O charts for this patient
            initMedicationChart(patientId);
            initIntakeOutputChart(patientId);

            // Initialize Unified Vitals
            if(typeof window.initUnifiedVitals === 'function') {
                window.initUnifiedVitals(patientId);
            }

            // Load other tab data
            loadInjectionHistory(patientId);
            loadImmunizationSchedule(patientId);
            loadImmunizationHistory(patientId);
            loadPendingBills(patientId);
            loadNotesHistory(patientId);
            billingHistoryLoaded = false; // Reset so billing history lazy-loads fresh for new patient

            // Initialize procedures DataTable
            initializeProceduresDataTable(patientId);

            // Initialize Clinical Requests module
            ClinicalRequests.init(patientId);

            // Switch to overview tab
            switchWorkspaceTab('overview');
        },
        error: function(xhr) {
            console.error('Failed to load patient data:', xhr);
            toastr.error('Failed to load patient data');
        }
    });
}

function initializeHistoryDataTable(patientId) {
    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
        $('#investigation_history_list').DataTable().destroy();
    }

    $('#investigation_history_list').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: `/investigationHistoryList/${patientId}`,
            type: 'GET'
        },
        columns: [
            {
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: "No investigation history found for this patient",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        },
        drawCallback: function() {
            // Add click handler for view result buttons
            $('.view-invest-result-btn').off('click').on('click', function() {
                const requestId = $(this).data('request-id');
                viewInvestigationResult(requestId);
            });
        }
    });
}

// =====================================
// CLINICAL REQUESTS MODULE
// =====================================
const ClinicalRequests = (function() {
    let patientId = null;
    let selectedProcedures = [];
    let crDoseStructuredMode = true; // Plan Â§2.2: structured is now the default
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    const procedureCategoryId = {{ appsettings('procedure_category_id', 0) }};
    const investigationCategoryId = '{{ appsettings("investigation_category_id", "") }}';

    function init(pid) {
        patientId = pid;
        $('#cr-patient-badge').text('Patient #' + pid).removeClass('bg-info').addClass('bg-primary');
        selectedProcedures = [];

        // Clear selection tables
        $('#cr-selected-products').empty();
        $('#cr-selected-labs').empty();
        $('#cr-selected-imaging').empty();
        $('#cr-selected-procedures').empty();

        // Init history DataTables
        initPrescHistory();
        initLabHistory();
        initImagingHistory();
        initProcHistory();

        // Clear duplicate tracking (Plan Â§4.4)
        ClinicalOrdersKit.clearAddedIds();

        // Initialize dose mode toggle â€” structured by default (Plan Â§2.2)
        if (!ClinicalRequests._doseToggleInit) {
            var nurseDoseState = ClinicalOrdersKit.initDoseModeToggle({
                prefix: 'cr_',
                cssPrefix: 'cr-',
                tableSelector: '#cr-selected-products',
                idInputName: 'cr_presc_id[]',
                doseInputName: 'cr_presc_dose[]',
                onchange: 'ClinicalOrdersKit.updateDoseValue(this, "cr-")',
                onToggle: function(isStructured) { crDoseStructuredMode = isStructured; }
            });
            crDoseStructuredMode = nurseDoseState.isStructured;

            // Phase 2b (Plan Â§4.3): Register debounced dose auto-save for medications
            ClinicalOrdersKit.onDoseUpdate('cr-', function(recordId, doseValue) {
                ClinicalOrdersKit.debouncedUpdate({
                    url: '/nursing-workbench/clinical-requests/prescriptions/' + recordId + '/dose',
                    payload: { dose: doseValue },
                    csrfToken: CSRF_TOKEN
                });
            });

            // Phase 4d (Plan Â§6.4): Initialize treatment plans module
            ClinicalOrdersKit.initTreatmentPlans({
                applyUrl: '/nursing-workbench/clinical-requests/apply-treatment-plan',
                csrfToken: CSRF_TOKEN,
                extraPayload: { patient_id: patientId },
                onApplySuccess: function(response) {
                    initLabHistory();
                    initImagingHistory();
                    initPrescHistory();
                    initProcHistory();
                },
                currentItemsGatherer: function() {
                    // Gather all auto-saved items from selection tables (all 4 types)
                    var items = [];
                    $('#cr-selected-labs tr[data-record-id]').each(function() {
                        items.push({
                            item_type: 'lab',
                            reference_id: parseInt($(this).data('service-id')),
                            display_name: $(this).find('td:first').text().trim(),
                            note: $(this).find('input[name="cr_lab_note[]"]').val() || ''
                        });
                    });
                    $('#cr-selected-imaging tr[data-record-id]').each(function() {
                        items.push({
                            item_type: 'imaging',
                            reference_id: parseInt($(this).data('service-id')),
                            display_name: $(this).find('td:first').text().trim(),
                            note: $(this).find('input[name="cr_imaging_note[]"]').val() || ''
                        });
                    });
                    $('#cr-selected-products tr[data-record-id]').each(function() {
                        items.push({
                            item_type: 'medication',
                            reference_id: parseInt($(this).data('service-id')),
                            display_name: $(this).find('td:first').text().trim(),
                            dose: $(this).find('input[name="cr_presc_dose[]"]').val() || ''
                        });
                    });
                    $('#cr-selected-procedures tr[data-record-id]').each(function() {
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

            // Phase 3c (Plan Â§5.3): Initialize re-prescribe from encounter dropdown
            ClinicalOrdersKit.initRePrescribeFromEncounter({
                recentUrl: '/nursing-workbench/clinical-requests/recent-encounters',
                encounterItemsUrl: '/nursing-workbench/clinical-requests/encounter-items/{id}',
                rePrescribeUrl: '/nursing-workbench/clinical-requests/re-prescribe',
                csrfToken: CSRF_TOKEN,
                extraPayload: { patient_id: patientId },
                dropdownSelector: '#cr-rp-encounter-dropdown',
                onRePrescribed: function() {
                    initLabHistory();
                    initImagingHistory();
                    initPrescHistory();
                    initProcHistory();
                }
            });

            ClinicalRequests._doseToggleInit = true;
        }

        // A5 fix: Update treatment plan & re-prescribe config on EVERY patient switch
        // (Plan Â§6.4 + Â§5.3) â€” keeps extraPayload.patient_id current
        ClinicalOrdersKit.updateTreatmentPlanConfig({ extraPayload: { patient_id: patientId } });
        ClinicalOrdersKit.updateRePrescribeConfig({ extraPayload: { patient_id: patientId } });

        // Setup search handlers (only once)
        if (!ClinicalRequests._searchBound) {
            setupSearchHandlers();
            ClinicalRequests._searchBound = true;
        }
    }

    // ===== HISTORY DATATABLES =====
    function initPrescHistory() {
        if ($.fn.DataTable.isDataTable('#cr_presc_history_list')) {
            $('#cr_presc_history_list').DataTable().destroy();
        }
        $('#cr_presc_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '/prescHistoryList/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No prescription history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }
    function initLabHistory() {
        if ($.fn.DataTable.isDataTable('#cr_lab_history_list')) {
            $('#cr_lab_history_list').DataTable().destroy();
        }
        $('#cr_lab_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '/investigationHistoryList/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No lab history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }
    function initImagingHistory() {
        if ($.fn.DataTable.isDataTable('#cr_imaging_history_list')) {
            $('#cr_imaging_history_list').DataTable().destroy();
        }
        $('#cr_imaging_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '/imagingHistoryList/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No imaging history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }
    function initProcHistory() {
        if ($.fn.DataTable.isDataTable('#cr_proc_history_list')) {
            $('#cr_proc_history_list').DataTable().destroy();
        }
        $('#cr_proc_history_list').DataTable({
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

    // ===== SEARCH HANDLERS =====
    function setupSearchHandlers() {
        let searchTimeout;

        // Re-order / Re-prescribe from history (Plan Â§5.2)
        $(document).off('click.reorder').on('click.reorder', '.re-order-btn', function() {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            var type          = $btn.data('type');
            var name          = $btn.data('name');
            var price         = $btn.data('price') || 0;
            var coverageMode  = $btn.data('coverage-mode') || null;
            var claims        = $btn.data('claims') || null;
            var payable       = $btn.data('payable') || null;
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
                addProduct(name, productId, price, coverageMode, claims, payable);
            }

            $btn.prop('disabled', true).html('<i class="fa fa-check text-success"></i> Added');
        });

        // Drug search
        $('#cr_presc_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_presc_results').hide(); return; }
            searchTimeout = setTimeout(() => searchProducts(q), 300);
        });

        // Lab search
        $('#cr_lab_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_lab_results').hide(); return; }
            searchTimeout = setTimeout(() => searchLabServices(q), 300);
        });

        // Imaging search
        $('#cr_imaging_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_imaging_results').hide(); return; }
            searchTimeout = setTimeout(() => searchImagingServices(q), 300);
        });

        // Procedure search
        $('#cr_proc_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_proc_results').hide(); return; }
            searchTimeout = setTimeout(() => searchProcedureServices(q), 300);
        });

        // Close dropdowns on click outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#cr_presc_search, #cr_presc_results').length) $('#cr_presc_results').hide();
            if (!$(e.target).closest('#cr_lab_search, #cr_lab_results').length) $('#cr_lab_results').hide();
            if (!$(e.target).closest('#cr_imaging_search, #cr_imaging_results').length) $('#cr_imaging_results').hide();
            if (!$(e.target).closest('#cr_proc_search, #cr_proc_results').length) $('#cr_proc_results').hide();
        });
    }

    // ===== SEARCH FUNCTIONS =====
    function searchProducts(q) {
        $.get('/live-search-products', { term: q, patient_id: patientId }, function(data) {
            const $res = $('#cr_presc_results').empty();
            if (!data.length) { $res.append('<li class="list-group-item text-muted">No products found</li>'); }
            else {
                data.forEach(item => {
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock?.current_quantity ?? 0;
                    const price = item.price?.initial_sale_price ?? 0;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const mode = item.coverage_mode || null;
                    const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                    const displayName = `${name}[${code}](${qty} avail.)`;

                    // Phase 2c (Plan Â§4.4): Duplicate filtering for medications
                    const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('meds', parseInt(item.id));
                    const disabledStyle = alreadyAdded ? 'opacity:0.5; pointer-events:none;' : 'cursor:pointer;';
                    const alreadyBadge = alreadyAdded ? '<span class="badge bg-secondary ms-2">Already Added</span>' : '';

                    if (alreadyAdded) {
                        $res.append(`<li class='list-group-item' style="background:#f0f0f0; ${disabledStyle}"><b>${name}[${code}]</b> (${qty} avail.) NGN ${price} ${coverageBadge}${alreadyBadge}</li>`);
                    } else {
                        $res.append(`<li class='list-group-item' style="background:#f0f0f0; cursor:pointer;" onclick="ClinicalRequests.addProduct('${displayName.replace(/'/g,"\\'")}', ${item.id}, ${price}, '${mode}', ${claims}, ${payable})"><b>${name}[${code}]</b> (${qty} avail.) NGN ${price} ${coverageBadge}</li>`);
                    }
                });
            }
            $res.show();
        });
    }

    function searchLabServices(q) {
        const data = { term: q, patient_id: patientId };
        if (investigationCategoryId) data.category_id = investigationCategoryId;
        $.get('/live-search-services', data, function(data) {
            const $res = $('#cr_lab_results').empty();
            if (!data.length) { $res.append('<li class="list-group-item text-muted">No lab services found</li>'); }
            else {
                data.forEach(item => {
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const mode = item.coverage_mode || null;
                    const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                    const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('labs', parseInt(item.id));
                    if (alreadyAdded) {
                        $res.append(`<li class='list-group-item text-muted' style="background:#e9ecef; cursor:not-allowed;">[${item.category?.category_name || 'Lab'}] <b>${name}[${code}]</b> NGN ${price} ${coverageBadge} <span class='badge bg-warning ms-2'>Already Added</span></li>`);
                    } else {
                        $res.append(`<li class='list-group-item' style="background:#f0f0f0; cursor:pointer;" onclick="ClinicalRequests.addLabService('${(name+'['+code+']').replace(/'/g,"\\'")}', ${item.id}, ${price}, '${mode}', ${claims}, ${payable})">[${item.category?.category_name || 'Lab'}] <b>${name}[${code}]</b> NGN ${price} ${coverageBadge}</li>`);
                    }
                });
            }
            $res.show();
        });
    }

    function searchImagingServices(q) {
        $.get('/live-search-services', { term: q, category_id: 6, patient_id: patientId }, function(data) {
            const $res = $('#cr_imaging_results').empty();
            if (!data.length) { $res.append('<li class="list-group-item text-muted">No imaging services found</li>'); }
            else {
                data.forEach(item => {
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const mode = item.coverage_mode || null;
                    const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                    const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('imaging', parseInt(item.id));
                    if (alreadyAdded) {
                        $res.append(`<li class='list-group-item text-muted' style="background:#e9ecef; cursor:not-allowed;">[${item.category?.category_name || 'Imaging'}] <b>${name}[${code}]</b> NGN ${price} ${coverageBadge} <span class='badge bg-warning ms-2'>Already Added</span></li>`);
                    } else {
                        $res.append(`<li class='list-group-item' style="background:#f0f0f0; cursor:pointer;" onclick="ClinicalRequests.addImagingService('${(name+'['+code+']').replace(/'/g,"\\'")}', ${item.id}, ${price}, '${mode}', ${claims}, ${payable})">[${item.category?.category_name || 'Imaging'}] <b>${name}[${code}]</b> NGN ${price} ${coverageBadge}</li>`);
                    }
                });
            }
            $res.show();
        });
    }

    function searchProcedureServices(q) {
        $.get('/live-search-services', { term: q, category_id: procedureCategoryId, patient_id: patientId }, function(data) {
            const $res = $('#cr_proc_results').empty();
            if (!data.length) { $res.append('<li class="list-group-item text-muted">No procedures found</li>'); }
            else {
                data.forEach(item => {
                    const isSelected = ClinicalOrdersKit.isAlreadyAdded('procedures', item.id);
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const payable = item.payable_amount ?? price;
                    const disabledBadge = isSelected ? ' <span class="badge bg-warning">Already Added</span>' : '';
                    const cursor = isSelected ? 'not-allowed' : 'pointer';
                    const clickAttr = isSelected ? '' : `onclick="ClinicalRequests.addProcedure(${JSON.stringify(item).replace(/"/g, '&quot;')})"`;
                    $res.append(`<li class='list-group-item' style="background:#f0f0f0; cursor:${cursor};" ${clickAttr}>[${item.category?.category_name || 'Procedure'}] <b>${name}[${code}]</b> NGN ${payable}${disabledBadge}</li>`);
                });
            }
            $res.show();
        });
    }

    // ===== ADD TO SELECTION TABLE =====
    // Phase 2b (Plan Â§4.3): Two-phase medication auto-save
    // Phase 1 â€” instant POST with empty dose; Phase 2 â€” debounced PUT on dose field changes
    function addProduct(name, id, price, mode, claims, payable) {
        const rowId = 'crx_' + Date.now() + '_' + id;
        const coverageBadge = ClinicalOrdersKit.renderCoverageBadge(
            mode && mode !== 'null' ? mode : null, payable ?? price, claims ?? 0
        );

        ClinicalOrdersKit.addItem({
            url: '/nursing-workbench/clinical-requests/add-prescription',
            payload: { patient_id: patientId, product_id: id, dose: '' },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#cr-selected-products',
            type: 'meds',
            referenceId: parseInt(id),
            buildRowHtml: function(resp) {
                const recordId = resp.id;
                const doseOnchange = "ClinicalOrdersKit.updateDoseValue(this, 'cr-'); " +
                    "ClinicalOrdersKit.debouncedUpdate({url:'/nursing-workbench/clinical-requests/prescriptions/" + recordId + "/dose'," +
                    "payload:{dose: $(this).closest('.cr-structured-dose').find('.cr-structured-dose-value').val()}," +
                    "csrfToken:'" + CSRF_TOKEN + "'});";

                let doseCell;
                if (crDoseStructuredMode) {
                    doseCell = '<td>' + ClinicalOrdersKit.buildStructuredDoseHtml({
                        cssPrefix: 'cr-',
                        hiddenName: 'cr_presc_dose[]',
                        onchange: doseOnchange,
                        drugName: name,
                        rowId: rowId
                    }) + '<input type="hidden" name="cr_presc_id[]" value="' + id + '"></td>';
                } else {
                    doseCell = '<td><input type="text" class="form-control form-control-sm" name="cr_presc_dose[]" ' +
                        'placeholder="e.g. 500mg BD x 5days" ' +
                        'onchange="ClinicalOrdersKit.debouncedUpdate({url:\'/nursing-workbench/clinical-requests/prescriptions/' + recordId + '/dose\',' +
                        'payload:{dose:this.value},csrfToken:\'' + CSRF_TOKEN + '\'})" required>' +
                        '<input type="hidden" name="cr_presc_id[]" value="' + id + '"></td>';
                }

                return '<tr data-record-id="' + recordId + '" data-record-type="prescription" data-service-id="' + id + '" data-drug-name="' + name.replace(/"/g, '&quot;') + '" data-row-id="' + rowId + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    doseCell +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'prescription\',' + recordId + ',' + id + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initPrescHistory();
            }
        });
        $('#cr_presc_search').val('');
        $('#cr_presc_results').hide();
    }

    // Legacy dose functions (buildCrStructuredDoseHtml, crFreqMultiplierMap, crDurUnitMultiplierMap,
    // autoCalculateCrQty, updateDoseVal, toggleDoseMode) removed.
    // All dose logic now lives in ClinicalOrdersKit (clinical-orders-shared.js) per Plan Â§2.1â€“Â§2.3.

    // Phase 0d (Plan Â§2.3): Old global calculator functions removed.
    // Per-drug inline calculator now lives in ClinicalOrdersKit (clinical-orders-shared.js).

    function addLabService(name, id, price, mode, claims, payable) {
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        ClinicalOrdersKit.addItem({
            url: '/nursing-workbench/clinical-requests/add-lab',
            payload: { service_id: id, patient_id: patientId, note: '' },
            csrfToken: csrfToken,
            tableSelector: '#cr-selected-labs',
            type: 'labs',
            referenceId: parseInt(id),
            buildRowHtml: function(response) {
                var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                return '<tr data-record-id="' + response.id + '" data-record-type="lab" data-service-id="' + id + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    '<td><input type="text" class="form-control form-control-sm" name="cr_lab_note[]" placeholder="Clinical notes..." onchange="ClinicalOrdersKit.debouncedUpdate({url:\'/nursing-workbench/clinical-requests/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\'})"><input type="hidden" name="cr_lab_id[]" value="' + id + '"></td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'lab\',' + response.id + ',' + id + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initLabHistory();
            }
        });

        $('#cr_lab_search').val('');
        $('#cr_lab_results').hide();
    }

    function addImagingService(name, id, price, mode, claims, payable) {
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        ClinicalOrdersKit.addItem({
            url: '/nursing-workbench/clinical-requests/add-imaging',
            payload: { service_id: id, patient_id: patientId, note: '' },
            csrfToken: csrfToken,
            tableSelector: '#cr-selected-imaging',
            type: 'imaging',
            referenceId: parseInt(id),
            buildRowHtml: function(response) {
                var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                return '<tr data-record-id="' + response.id + '" data-record-type="imaging" data-service-id="' + id + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    '<td><input type="text" class="form-control form-control-sm" name="cr_imaging_note[]" placeholder="Clinical notes..." onchange="ClinicalOrdersKit.debouncedUpdate({url:\'/nursing-workbench/clinical-requests/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\'})"><input type="hidden" name="cr_imaging_id[]" value="' + id + '"></td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'imaging\',' + response.id + ',' + id + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initImagingHistory();
            }
        });

        $('#cr_imaging_search').val('');
        $('#cr_imaging_results').hide();
    }

    function addProcedure(item) {
        // Phase 2a (Plan Â§4.1): Auto-save procedure via ClinicalOrdersKit.addItem
        const procId = item.id;
        if (ClinicalOrdersKit.isAlreadyAdded('procedures', procId)) {
            toastr.warning('Procedure already added');
            return;
        }
        const priority = $('#cr_proc_priority').val();
        const scheduledDate = $('#cr_proc_scheduled_date').val();
        const preNotes = $('#cr_proc_notes').val();

        const payable = item.payable_amount ?? (item.price?.sale_price ?? 0);
        const priorityClass = { routine: 'bg-success', urgent: 'bg-warning text-dark', emergency: 'bg-danger' }[priority] || 'bg-secondary';
        const priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);

        ClinicalOrdersKit.addItem({
            url: '/nursing-workbench/clinical-requests/add-procedure',
            payload: {
                patient_id: patientId,
                service_id: procId,
                priority: priority,
                scheduled_date: scheduledDate,
                pre_notes: preNotes
            },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#cr-selected-procedures',
            type: 'procedures',
            referenceId: procId,
            buildRowHtml: function(resp) {
                return '<tr data-record-id="' + resp.id + '" data-record-type="procedure" data-service-id="' + procId + '">' +
                    '<td><strong>' + (item.service_name || 'N/A') + '</strong><br><small class="text-muted">' + (item.service_code || '') + '</small>' +
                    (preNotes ? '<br><small class="text-info"><i class="fa fa-sticky-note"></i> ' + preNotes.substring(0, 60) + '</small>' : '') + '</td>' +
                    '<td>NGN ' + payable + '</td>' +
                    '<td><span class="badge ' + priorityClass + '">' + priorityLabel + '</span>' +
                    (scheduledDate ? '<br><small>' + scheduledDate + '</small>' : '') + '</td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'procedure\',' + resp.id + ',' + procId + ')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function() {
                initProcHistory();
            }
        });
        $('#cr_proc_search').val('');
        $('#cr_proc_results').hide();
    }

    function removeProcedure(procId) {
        // Legacy fallback â€” for non-auto-saved rows only
        selectedProcedures = selectedProcedures.filter(p => p.id !== procId);
        renderSelectedProcedures();
    }

    function renderSelectedProcedures() {
        const $tb = $('#cr-selected-procedures').empty();
        if (selectedProcedures.length === 0) {
            $tb.append('<tr><td colspan="4" class="text-center text-muted"><i class="fa fa-info-circle"></i> No procedures selected</td></tr>');
            return;
        }
        selectedProcedures.forEach(p => {
            const payable = p.payable_amount ?? (p.price?.sale_price ?? 0);
            const priorityClass = { routine: 'bg-success', urgent: 'bg-warning text-dark', emergency: 'bg-danger' }[p.priority] || 'bg-secondary';
            $tb.append(`
                <tr>
                    <td><strong>${p.service_name || 'N/A'}</strong><br><small class="text-muted">${p.service_code || ''}</small>${p.pre_notes ? '<br><small class="text-info"><i class="fa fa-sticky-note"></i> ' + p.pre_notes.substring(0, 60) + '</small>' : ''}</td>
                    <td>NGN ${payable}</td>
                    <td><span class="badge ${priorityClass}">${p.priority}</span>${p.scheduled_date ? '<br><small>' + p.scheduled_date + '</small>' : ''}</td>
                    <td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeProcedure(${p.id})"><i class="fa fa-times"></i></button></td>
                </tr>
            `);
        });
    }

    // ===== SAVE FUNCTIONS =====
    function showMessage(containerId, msg, type) {
        const alertType = type === 'error' ? 'danger' : type;
        $(`#${containerId}`).html(`<div class="alert alert-${alertType} alert-dismissible fade show">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
        document.getElementById(containerId).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(() => $(`#${containerId} .alert`).alert('close'), 5000);
    }

    // Phase 2b (Plan Â§4.3): skip auto-saved prescription rows
    function savePrescriptions() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        const products = [], doses = [];
        let autoSavedCount = 0;

        $('#cr-selected-products tr').each(function() {
            // Skip rows already auto-saved (Phase 2b)
            if ($(this).data('record-id')) { autoSavedCount++; return; }
            const id = $(this).find('input[name="cr_presc_id[]"]').val();
            // Try structured hidden input first, fallback to text input
            let dose = $(this).find('.cr-structured-dose-value').val();
            if (dose === undefined || dose === null) {
                dose = $(this).find('input[name="cr_presc_dose[]"]').val();
            }
            if (id) { products.push(id); doses.push(dose || ''); }
        });

        // If ALL rows are auto-saved, show success and clear
        if (products.length === 0 && autoSavedCount > 0) {
            showMessage('cr_presc_message', autoSavedCount + ' prescription(s) already auto-saved', 'success');
            $('#cr-selected-products').empty();
            ClinicalOrdersKit.addedIds.meds.clear(); // A3 fix: only clear meds
            initPrescHistory();
            try { new bootstrap.Tab($('[data-bs-target="#cr-presc-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-presc-history"]').tab('show'); }
            return;
        }

        if (products.length === 0) { showMessage('cr_presc_message', 'No prescriptions selected.', 'error'); return; }

        const $btn = $('#cr-save-prescriptions-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '/nursing-workbench/clinical-requests/prescriptions',
            method: 'POST',
            data: { patient_id: patientId, product_ids: products, doses: doses, _token: CSRF_TOKEN },
            success: function(r) {
                if (r.success) {
                    const saved = autoSavedCount > 0 ? ` (${autoSavedCount} auto-saved earlier)` : '';
                    showMessage('cr_presc_message', r.message + saved, 'success');
                    $('#cr-selected-products').empty();
                    ClinicalOrdersKit.addedIds.meds.clear(); // A3 fix: only clear meds
                    initPrescHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-presc-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-presc-history"]').tab('show'); }
                } else showMessage('cr_presc_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_presc_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Prescriptions'); }
        });
    }

    function saveLabs() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        const services = [], notes = [];
        var autoSavedCount = 0;

        $('#cr-selected-labs tr').each(function() {
            if ($(this).data('record-id')) { autoSavedCount++; return; }
            const id = $(this).find('input[name="cr_lab_id[]"]').val();
            const note = $(this).find('input[name="cr_lab_note[]"]').val();
            if (id) { services.push(id); notes.push(note || ''); }
        });

        // If all items were auto-saved
        if (services.length === 0 && autoSavedCount > 0) {
            showMessage('cr_lab_message', autoSavedCount + ' lab(s) already saved', 'success');
            $('#cr-selected-labs').empty();
            ClinicalOrdersKit.addedIds.labs.clear(); // A3 fix: only clear labs
            initLabHistory();
            try { new bootstrap.Tab($('[data-bs-target="#cr-lab-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-lab-history"]').tab('show'); }
            return;
        }

        if (services.length === 0) { showMessage('cr_lab_message', 'No lab services selected.', 'error'); return; }

        const $btn = $('#cr-save-labs-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '/nursing-workbench/clinical-requests/labs',
            method: 'POST',
            data: { patient_id: patientId, service_ids: services, notes: notes, _token: CSRF_TOKEN },
            success: function(r) {
                if (r.success) {
                    showMessage('cr_lab_message', r.message, 'success');
                    $('#cr-selected-labs').empty();
                    initLabHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-lab-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-lab-history"]').tab('show'); }
                } else showMessage('cr_lab_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_lab_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Lab Requests'); }
        });
    }

    function saveImaging() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        const services = [], notes = [];
        var autoSavedCount = 0;

        $('#cr-selected-imaging tr').each(function() {
            if ($(this).data('record-id')) { autoSavedCount++; return; }
            const id = $(this).find('input[name="cr_imaging_id[]"]').val();
            const note = $(this).find('input[name="cr_imaging_note[]"]').val();
            if (id) { services.push(id); notes.push(note || ''); }
        });

        if (services.length === 0 && autoSavedCount > 0) {
            showMessage('cr_imaging_message', autoSavedCount + ' imaging request(s) already saved', 'success');
            $('#cr-selected-imaging').empty();
            ClinicalOrdersKit.addedIds.imaging.clear(); // A3 fix: only clear imaging
            initImagingHistory();
            try { new bootstrap.Tab($('[data-bs-target="#cr-imaging-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-imaging-history"]').tab('show'); }
            return;
        }

        if (services.length === 0) { showMessage('cr_imaging_message', 'No imaging services selected.', 'error'); return; }

        const $btn = $('#cr-save-imaging-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '/nursing-workbench/clinical-requests/imaging',
            method: 'POST',
            data: { patient_id: patientId, service_ids: services, notes: notes, _token: CSRF_TOKEN },
            success: function(r) {
                if (r.success) {
                    showMessage('cr_imaging_message', r.message, 'success');
                    $('#cr-selected-imaging').empty();
                    initImagingHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-imaging-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-imaging-history"]').tab('show'); }
                } else showMessage('cr_imaging_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_imaging_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Imaging Requests'); }
        });
    }

    function saveProcedures() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        if (selectedProcedures.length === 0) { showMessage('cr_proc_message', 'No procedures selected.', 'error'); return; }

        const $btn = $('#cr-save-procedures-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '/nursing-workbench/clinical-requests/procedures',
            method: 'POST',
            data: {
                patient_id: patientId,
                _token: CSRF_TOKEN,
                procedures: selectedProcedures.map(p => ({
                    service_id: p.id,
                    priority: p.priority,
                    scheduled_date: p.scheduled_date,
                    pre_notes: p.pre_notes
                }))
            },
            success: function(r) {
                if (r.success) {
                    showMessage('cr_proc_message', r.message, 'success');
                    selectedProcedures = [];
                    renderSelectedProcedures();
                    initProcHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-proc-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-proc-history"]').tab('show'); }
                } else showMessage('cr_proc_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_proc_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Procedures'); }
        });
    }

    /**
     * Remove an auto-saved row (lab/imaging) via DELETE.
     * Called from inline onclick in auto-saved rows.
     */
    function removeAutoSavedRow(btn, type, recordId, serviceId) {
        var deleteUrl;
        var tableSelector;
        if (type === 'lab') {
            deleteUrl = '/nursing-workbench/clinical-requests/labs/' + recordId;
            tableSelector = '#cr-selected-labs';
        } else if (type === 'imaging') {
            deleteUrl = '/nursing-workbench/clinical-requests/imaging/' + recordId;
            tableSelector = '#cr-selected-imaging';
        } else if (type === 'prescription') {
            deleteUrl = '/nursing-workbench/clinical-requests/prescriptions/' + recordId;
            tableSelector = '#cr-selected-products';
        } else if (type === 'procedure') {
            deleteUrl = '/nursing-workbench/clinical-requests/procedures/' + recordId;
            tableSelector = '#cr-selected-procedures';
        }

        // Map onclick type strings to addedIds keys
        var idsType = { lab: 'labs', imaging: 'imaging', prescription: 'meds', procedure: 'procedures' }[type] || type;

        ClinicalOrdersKit.removeItem({
            url: deleteUrl,
            csrfToken: $('meta[name="csrf-token"]').attr('content'),
            rowSelector: $(btn).closest('tr'),
            type: idsType,
            referenceId: serviceId ? parseInt(serviceId) : null,
            tableSelector: tableSelector
        });
    }

    return {
        init: init,
        addProduct: addProduct,
        addLabService: addLabService,
        addImagingService: addImagingService,
        addProcedure: addProcedure,
        removeProcedure: removeProcedure,
        removeAutoSavedRow: removeAutoSavedRow,
        savePrescriptions: savePrescriptions,
        saveLabs: saveLabs,
        saveImaging: saveImaging,
        saveProcedures: saveProcedures,
        toggleDoseMode: function() { /* removed â€” now handled by ClinicalOrdersKit.initDoseModeToggle (Plan Â§2.2) */ },
        toggleCalculator: function() { /* removed â€” global calculator replaced by per-drug calc (Plan Â§2.3) */ },
        calculate: function() { /* removed */ },
        applyToSelected: function() { /* removed */ },
        updateDoseVal: function() { /* removed â€” now handled by ClinicalOrdersKit.updateDoseValue (Plan Â§2.2) */ },
        _searchBound: false,
        _doseToggleInit: false
    };
})();

function initializeProceduresDataTable(patientId) {
    if ($.fn.DataTable.isDataTable('#procedures_history_list')) {
        $('#procedures_history_list').DataTable().destroy();
    }

    $('#procedures_history_list').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: `/patient-procedures/list-by-patient/${patientId}`,
            type: 'GET'
        },
        columns: [
            {
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: "No procedures found for this patient",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        }
    });
}

/* LAB-SPECIFIC - DISABLED
function viewInvestigationResult(requestId) {
    // Open modal to view completed result
    $.ajax({
        url: `/lab-workbench/lab-service-requests/${requestId}`,
        method: 'GET',
        success: function(request) {
            // Show result in a view-only modal or open in new tab
            if (request.result_document) {
                window.open(request.result_document, '_blank');
            } else {
                alert('No result document found');
            }
        },
        error: function(xhr) {
            alert('Error loading result: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}
*/

function displayPatientInfo(patient) {
    $('#patient-name').text(`${patient.name} (#${patient.file_no})`);
    $('#patient-meta').html(`
        <div class="patient-meta-item">
            <i class="mdi mdi-account"></i>
            <span>${patient.age} ${patient.gender}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-water"></i>
            <span>${patient.blood_group} ${patient.genotype !== 'N/A' ? '(' + patient.genotype + ')' : ''}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-phone"></i>
            <span>${patient.phone}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-hospital-building"></i>
            <span>${patient.hmo} ${patient.hmo_category !== 'N/A' ? '[' + patient.hmo_category + ']' : ''} ${patient.hmo_no !== 'N/A' ? '(' + patient.hmo_no + ')' : ''}</span>
        </div>
    `);

    // Build detailed information grid - show ALL fields
    let detailsHtml = '';

    // Age (detailed)
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-calendar-clock"></i> Age</div>
            <div class="patient-detail-value">${patient.age}</div>
        </div>
    `;

    // Gender
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-gender-male-female"></i> Gender</div>
            <div class="patient-detail-value">${patient.gender}</div>
        </div>
    `;

    // Blood Group
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-water"></i> Blood Group</div>
            <div class="patient-detail-value">${patient.blood_group}</div>
        </div>
    `;

    // Genotype
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-dna"></i> Genotype</div>
            <div class="patient-detail-value">${patient.genotype}</div>
        </div>
    `;

    // Phone
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-phone"></i> Phone Number</div>
            <div class="patient-detail-value">${patient.phone}</div>
        </div>
    `;

    // Address
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-map-marker"></i> Address</div>
            <div class="patient-detail-value">${patient.address}</div>
        </div>
    `;

    // Nationality
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-flag"></i> Nationality</div>
            <div class="patient-detail-value">${patient.nationality}</div>
        </div>
    `;

    // Ethnicity
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-account-group"></i> Ethnicity</div>
            <div class="patient-detail-value">${patient.ethnicity}</div>
        </div>
    `;

    // Disability Status
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-wheelchair-accessibility"></i> Disability</div>
            <div class="patient-detail-value">${patient.disability}</div>
        </div>
    `;

    // HMO
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-hospital-building"></i> HMO</div>
            <div class="patient-detail-value">${patient.hmo}</div>
        </div>
    `;

    // HMO Category
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-tag"></i> HMO Category</div>
            <div class="patient-detail-value">${patient.hmo_category}</div>
        </div>
    `;

    // HMO Number
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-card-account-details"></i> HMO Number</div>
            <div class="patient-detail-value">${patient.hmo_no}</div>
        </div>
    `;

    // Insurance Scheme
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-shield-account"></i> Insurance Scheme</div>
            <div class="patient-detail-value">${patient.insurance_scheme}</div>
        </div>
    `;

    // Allergies - handle array, comma-separated string, JSON string, object, or null
    let allergiesArray = [];
    if (patient.allergies) {
        if (Array.isArray(patient.allergies)) {
            // Already an array
            allergiesArray = patient.allergies;
        } else if (typeof patient.allergies === 'string') {
            // Try parsing as JSON first
            try {
                const parsed = JSON.parse(patient.allergies);
                allergiesArray = Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            } catch(e) {
                // Not JSON, treat as comma-separated string
                allergiesArray = patient.allergies.split(',').map(a => a.trim()).filter(a => a);
            }
        } else if (typeof patient.allergies === 'object') {
            // Object - extract values
            allergiesArray = Object.values(patient.allergies).filter(a => a);
        }
    }

    if (allergiesArray.length > 0) {
        const allergiesList = allergiesArray.map(allergy =>
            `<span class="allergy-tag"><i class="mdi mdi-alert"></i> ${allergy}</span>`
        ).join('');
        detailsHtml += `
            <div class="patient-detail-item full-width">
                <div class="patient-detail-label"><i class="mdi mdi-alert-circle"></i> Allergies</div>
                <div class="patient-detail-value">
                    <div class="allergies-list">${allergiesList}</div>
                </div>
            </div>
        `;
    } else {
        detailsHtml += `
            <div class="patient-detail-item full-width">
                <div class="patient-detail-label"><i class="mdi mdi-alert-circle"></i> Allergies</div>
                <div class="patient-detail-value">No known allergies</div>
            </div>
        `;
    }

    // Medical History
    detailsHtml += `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-clipboard-text"></i> Medical History</div>
            <div class="patient-detail-value text-content">${patient.medical_history}</div>
        </div>
    `;

    // Miscellaneous Notes
    detailsHtml += `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-note-text"></i> Additional Notes</div>
            <div class="patient-detail-value text-content">${patient.misc}</div>
        </div>
    `;

    $('#patient-details-grid').html(detailsHtml);

    // Toggle expand/collapse functionality
    $('#btn-expand-patient').off('click').on('click', function() {
        $(this).toggleClass('expanded');
        $('#patient-details-expanded').toggleClass('show');
    });
}

let currentPendingRequests = null;
let currentPendingFilter = 'all';

function displayPendingRequests(requests) {
    currentPendingRequests = requests;
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length;
    $('#pending-badge').text(totalPending);

    updatePendingSubtabBadges(requests);
    renderPendingSubtabContent(currentPendingFilter);
}

function updatePendingSubtabBadges(requests) {
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length;
    $('#all-pending-badge').text(totalPending);
    $('#billing-subtab-badge').text(requests.billing.length);
    $('#sample-subtab-badge').text(requests.sample.length);
    $('#results-subtab-badge').text(requests.results.length);
}

function renderPendingSubtabContent(filter) {
    if (!currentPendingRequests) return;

    currentPendingFilter = filter;
    const requests = currentPendingRequests;
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length;

    const $container = $('#pending-subtab-container');
    $container.empty();

    if (totalPending === 0) {
        $container.html('<div class="alert alert-info">No pending lab requests for this patient</div>');
        return;
    }

    // Billing Section (Status 1)
    if ((filter === 'all' || filter === 'billing') && requests.billing.length > 0) {
        const billingHtml = `
            <div class="request-section" data-section="billing">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-cash-register"></i>
                        Awaiting Billing (${requests.billing.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="billing-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-billing" class="select-all-checkbox">
                        <label for="select-all-billing">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-billing" id="btn-record-billing" disabled>
                            <i class="mdi mdi-check-circle"></i>
                            Record Billing
                        </button>
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-billing" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
        $container.append(billingHtml);

        requests.billing.forEach(request => {
            $('#billing-cards').append(createRequestCard(request, 'billing'));
        });
    }

    // Sample Section (Status 2)
    if ((filter === 'all' || filter === 'sample') && requests.sample.length > 0) {
        const sampleHtml = `
            <div class="request-section" data-section="sample">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-test-tube"></i>
                        Sample Collection (${requests.sample.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="sample-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-sample" class="select-all-checkbox">
                        <label for="select-all-sample">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-sample" id="btn-collect-sample" disabled>
                            <i class="mdi mdi-check-circle"></i>
                            Collect Sample
                        </button>
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-sample" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
        $container.append(sampleHtml);

        requests.sample.forEach(request => {
            $('#sample-cards').append(createRequestCard(request, 'sample'));
        });
    }

    // Results Section (Status 3)
    if ((filter === 'all' || filter === 'results') && requests.results.length > 0) {
        const resultsHtml = `
            <div class="request-section" data-section="results">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-file-document-edit"></i>
                        Result Entry (${requests.results.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="results-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <span class="text-muted"><i class="mdi mdi-information"></i> Results must be entered individually</span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-results" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss Selected
                        </button>
                    </div>
                </div>
            </div>
        `;
        $container.append(resultsHtml);

        requests.results.forEach(request => {
            $('#results-cards').append(createRequestCard(request, 'results'));
        });
    }

    // Initialize event handlers
    initializeRequestHandlers();
}

function createRequestCard(request, section) {
    const serviceName = request.service?.service_name || 'Unknown Service';
    const doctorName = request.doctor ? (request.doctor.firstname + ' ' + request.doctor.surname) : 'N/A';
    const requestDate = formatDateTime(request.created_at);
    const note = request.note || '';

    const hasNote = note && note.trim() !== '';
    const noteHtml = hasNote ? `<div class="request-note"><i class="mdi mdi-note-text"></i> ${note}</div>` : '';

    // Check delivery status
    const deliveryCheck = request.delivery_check;
    const canDeliver = deliveryCheck ? deliveryCheck.can_deliver : true;

    // Delivery warning message
    let deliveryWarningHtml = '';
    if (!canDeliver && deliveryCheck) {
        deliveryWarningHtml = `
            <div class="alert alert-warning py-2 px-2 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="fa fa-exclamation-triangle"></i> <strong>${deliveryCheck.reason}</strong><br>
                <small>${deliveryCheck.hint}</small>
            </div>
        `;
    }

    // Results section has individual action button instead of checkbox
    const checkboxOrAction = section === 'results' ? `
        <button class="btn btn-sm btn-primary enter-result-btn"
                data-request-id="${request.id}"
                ${!canDeliver ? 'disabled title="' + (deliveryCheck?.reason || 'Cannot deliver service') + '"' : ''}>
            <i class="mdi mdi-file-document-edit"></i>
            Enter Result
        </button>
    ` : `
        <div class="request-card-checkbox">
            <input type="checkbox" class="request-checkbox" data-request-id="${request.id}" data-section="${section}">
        </div>
    `;

    return `
        <div class="request-card">
            ${checkboxOrAction}
            <div class="request-card-content">
                <div class="request-card-header">
                    <div>
                        <div class="request-service-name">${serviceName}</div>
                        <div class="request-card-meta">
                            <div class="request-meta-item">
                                <i class="mdi mdi-doctor"></i>
                                <span>${doctorName}</span>
                            </div>
                            <div class="request-meta-item">
                                <i class="mdi mdi-clock-outline"></i>
                                <span>${requestDate}</span>
                            </div>
                        </div>
                    </div>
                </div>
                ${noteHtml}
                ${deliveryWarningHtml}
            </div>
        </div>
    `;
}

function initializeRequestHandlers() {
    // Select all checkboxes
    $('.select-all-checkbox').on('change', function() {
        const section = $(this).attr('id').replace('select-all-', '');
        const isChecked = $(this).is(':checked');
        $(`.request-checkbox[data-section="${section}"]`).prop('checked', isChecked).trigger('change');
    });

    // Individual checkboxes
    $('.request-checkbox').on('change', function() {
        const section = $(this).data('section');
        const checkedCount = $(`.request-checkbox[data-section="${section}"]:checked`).length;

        // Enable/disable action buttons
        $(`#btn-record-${section}, #btn-collect-${section}, #btn-dismiss-${section}`).prop('disabled', checkedCount === 0);

        // Update select all checkbox state
        const totalCount = $(`.request-checkbox[data-section="${section}"]`).length;
        $(`#select-all-${section}`).prop('checked', checkedCount === totalCount);
    });

    // Record Billing button
    $('#btn-record-billing').on('click', function() {
        const selectedIds = $('.request-checkbox[data-section="billing"]:checked').map(function() {
            return $(this).data('request-id');
        }).get();

        if (selectedIds.length > 0) {
            recordBilling(selectedIds);
        }
    });

    // Collect Sample button
    $('#btn-collect-sample').on('click', function() {
        const selectedIds = $('.request-checkbox[data-section="sample"]:checked').map(function() {
            return $(this).data('request-id');
        }).get();

        if (selectedIds.length > 0) {
            collectSample(selectedIds);
        }
    });

    // Dismiss buttons
    $('.btn-action-dismiss').on('click', function() {
        const btnId = $(this).attr('id');
        const section = btnId.replace('btn-dismiss-', '');
        const selectedIds = $(`.request-checkbox[data-section="${section}"]:checked`).map(function() {
            return $(this).data('request-id');
        }).get();

        if (selectedIds.length > 0) {
            dismissRequests(selectedIds, section);
        }
    });

    // Enter Result buttons (individual)
    $('.enter-result-btn').on('click', function() {
        const requestId = $(this).data('request-id');
        enterResult(requestId);
    });
}

// loadClinicalContext, displayVitals, displayNotes, displayMedications, and classifiers
// are now handled by ClinicalContext module (clinical-context.js)

function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// displayMedications is now handled by ClinicalContext.displayMedications() from clinical-context.js

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    const dateOptions = { month: 'short', day: 'numeric' };
    const timeOptions = { hour: '2-digit', minute: '2-digit' };
    return date.toLocaleDateString('en-US', dateOptions) + ', ' + date.toLocaleTimeString('en-US', timeOptions);
}

function loadQueueCounts() {
    $.get('{{ route("nursing-workbench.queue-counts") }}', function(counts) {
        $('#queue-admitted-count').text(counts.admitted || 0);
        $('#queue-vitals-count').text(counts.vitals || 0);
        $('#queue-bed-count').text(counts.bed_requests || 0);
        $('#queue-discharge-count').text(counts.discharge_requests || 0);
        $('#queue-medication-count').text(counts.medication_due || 0);
        $('#queue-emergency-count').text(counts.emergency || 0);
        updateSyncIndicator();
    });
}

function startQueueRefresh() {
    queueRefreshInterval = setInterval(function() {
        loadQueueCounts();

        // Also refresh current patient data if a patient is selected
        if (currentPatient) {
            refreshCurrentPatientData();
        }

        // Refresh queue DataTable if queue view is active
        if ($('#queue-view').hasClass('active') && queueDataTable) {
            queueDataTable.ajax.reload(null, false);
        }
    }, 30000); // 30 seconds
}

function refreshCurrentPatientData() {
    if (!currentPatient) return;

    // Silently reload patient data
    loadPendingBills(currentPatient);
    loadInjectionHistory(currentPatient);
    loadInjectionPrescriptions(true);
    loadImmunizationHistory(currentPatient);
    loadNotesHistory(currentPatient);
}

let lastSyncTimestamp = null;
let syncTimeUpdateInterval = null;

function updateSyncIndicator() {
    lastSyncTimestamp = Date.now();
    updateSyncTimeDisplay();

    // Start interval to update relative time every 10 seconds
    if (syncTimeUpdateInterval) {
        clearInterval(syncTimeUpdateInterval);
    }
    syncTimeUpdateInterval = setInterval(updateSyncTimeDisplay, 10000);
}

function updateSyncTimeDisplay() {
    if (!lastSyncTimestamp) {
        $('#last-sync-time').text('Just now');
        return;
    }

    const secondsAgo = Math.floor((Date.now() - lastSyncTimestamp) / 1000);

    if (secondsAgo < 10) {
        $('#last-sync-time').text('Just now');
    } else if (secondsAgo < 60) {
        $('#last-sync-time').text(secondsAgo + 's ago');
    } else {
        const minutesAgo = Math.floor(secondsAgo / 60);
        $('#last-sync-time').text(minutesAgo + 'm ago');
    }
}

function refreshClinicalPanel(panel) {
    if (!currentPatient) return;

    const $btn = $(`.refresh-clinical-btn[data-panel="${panel}"]`);
    $btn.find('i').addClass('fa-spin');

    // Vitals, medications, and allergies refresh is handled by clinical-context.js
    // Only notes refresh is handled locally (if needed)
    if (panel === 'medications') {
        // Reload medication chart (nursing-specific)
        loadMedicationsList();
        $btn.find('i').removeClass('fa-spin');
    } else {
        // For vitals/allergies â€” the shared module handles these via delegated click events
        setTimeout(function() { $btn.find('i').removeClass('fa-spin'); }, 1000);
    }
}



function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('clinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('â‰¡Æ’Ã´Ã¨ Clinical Context â”œÃ¹');
    }
}

// Removed lab-specific functions (recordBilling, collectSample, dismissRequests, enterResult)
// These were carried over from lab workbench and are not needed for nursing workbench

// ============================================
// INVESTIGATION RESULT ENTRY / VIEW FUNCTIONS
// Uses shared InvestResultEntry module for enter/edit
// ============================================

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

// Initialize shared result entry module
InvestResultEntry.bindFormSubmit(function() {
    // Refresh main history DataTables
    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
        $('#investigation_history_list').DataTable().ajax.reload(null, false);
    }
    if ($.fn.DataTable.isDataTable('#cr_lab_history_list')) {
        $('#cr_lab_history_list').DataTable().ajax.reload(null, false);
    }
    if ($.fn.DataTable.isDataTable('#cr_imaging_history_list')) {
        $('#cr_imaging_history_list').DataTable().ajax.reload(null, false);
    }
});

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
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    $('#res_generated_date').html(generatedDate);

    // Handle V2 results (structured data)
    if (res_obj.result_data) {
        let resultData = res_obj.result_data;
        if (typeof resultData === 'string') {
            try {
                resultData = JSON.parse(resultData);
            } catch (e) {
                console.error('Error parsing result data:', e);
                resultData = null;
            }
        }

        if (resultData && typeof resultData === 'object') {
            // If it's an object but not an array (e.g. key-value pairs), convert to array if needed
            // But based on previous code, it expects an array of parameters.
            // Let's ensure it is an array.
            let paramsArray = [];
            if (Array.isArray(resultData)) {
                paramsArray = resultData;
            } else {
                // If it's an object (key: value), we might need to map it back to the template structure
                // For now, let's assume if it's not an array, we can't iterate it easily without the template
                console.warn('Result data is not an array:', resultData);
            }

            if (paramsArray.length > 0) {
                let resultsHtml = '<table class="result-table"><thead><tr>';
                resultsHtml += '<th style="width: 40%;">Test Parameter</th>';
                resultsHtml += '<th style="width: 25%;">Results</th>';
                resultsHtml += '<th style="width: 25%;">Reference Range</th>';
                resultsHtml += '<th style="width: 10%;">Status</th>';
                resultsHtml += '</tr></thead><tbody>';

                paramsArray.forEach(function(param) {
                    resultsHtml += '<tr>';
                    resultsHtml += '<td><strong>' + param.name + '</strong>';
                    if (param.code) {
                        resultsHtml += ' <span style="color: #999;">(' + param.code + ')</span>';
                    }
                    resultsHtml += '</td>';

                    // Value with unit
                    let valueDisplay = param.value;
                    if (param.unit) {
                        valueDisplay += ' ' + param.unit;
                    }
                    resultsHtml += '<td>' + valueDisplay + '</td>';

                    // Reference range
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

                    // Status badge
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
                 // Fallback to V1 results (HTML content) if array is empty
                 $('#invest_res').html(res_obj.result);
            }
        } else {
             // Fallback to V1 results (HTML content)
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
            let attachHtml = '<div class="result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
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
    mywindow.document.write('<style>body{font-family: "Segoe UI", sans-serif;} .result-table {width: 100%; border-collapse: collapse;} .result-table th, .result-table td {border: 1px solid #ddd; padding: 8px; text-align: left;} .result-header {display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px;} .result-title-section {background: #eee; text-align: center; padding: 10px; font-weight: bold; margin: 20px 0;} .result-patient-info {display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;} .result-info-row {display: flex; margin-bottom: 5px;} .result-info-label {font-weight: bold; width: 120px;} .result-footer {margin-top: 50px; border-top: 1px solid #ccc; padding-top: 10px; text-align: center; font-size: 12px;}</style>');
    mywindow.document.write('</head><body >');
    mywindow.document.write(document.getElementById(elem).innerHTML);
    mywindow.document.write('</body></html>');

    mywindow.document.close(); // necessary for IE >= 10
    mywindow.focus(); // necessary for IE >= 10

    mywindow.print();
    mywindow.close();

    return true;
}

function getFileIcon(type) {
    if (type.includes('image')) return '<i class="fa fa-file-image-o"></i>';
    if (type.includes('pdf')) return '<i class="fa fa-file-pdf-o"></i>';
    return '<i class="fa fa-file-o"></i>';
}

// Delete Lab Request with Reason
let deleteRequestId = null;
let deleteEncounterId = null;

function deleteLabRequest(requestId, encounterId, serviceName) {
    deleteRequestId = requestId;
    deleteEncounterId = encounterId;
    $('#delete_service_name').text(serviceName);
    $('#delete_request_id').text(requestId);
    $('#delete_reason').val('');
    $('#deleteReasonModal').modal('show');
}

$('#deleteRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#delete_reason').val();

    if (reason.length < 10) {
        alert('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $.ajax({
        url: `/lab-workbench/lab-service-requests/${deleteRequestId}`,
        method: 'DELETE',
        data: {
            _token: '{{ csrf_token() }}',
            reason: reason
        },
        success: function(response) {
            $('#deleteReasonModal').modal('hide');
            alert(response.message);

            // Reload patient data if we're on a patient
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete request'));
        }
    });
});

// Dismiss Lab Request
let dismissRequestId = null;

function dismissSingleRequest(requestId, serviceName) {
    dismissRequestId = requestId;
    $('#dismiss_service_name').text(serviceName);
    $('#dismiss_request_id').text(requestId);
    $('#dismiss_reason').val('');
    $('#dismissReasonModal').modal('show');
}

$('#dismissRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#dismiss_reason').val();

    if (reason.length < 10) {
        alert('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $.ajax({
        url: `/lab-workbench/lab-service-requests/${dismissRequestId}/dismiss`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            reason: reason
        },
        success: function(response) {
            $('#dismissReasonModal').modal('hide');
            alert(response.message);

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to dismiss request'));
        }
    });
});













// ============================================
// ENHANCEMENT FUNCTIONS
// ============================================

// Show vital tooltip with details
function showVitalTooltip(event, vitalType, value, normalRange) {
    const tooltip = vitalTooltip;
    const $target = $(event.currentTarget);
    const offset = $target.offset();

    let deviation = '';
    let status = 'Normal';

    // Calculate deviation based on vital type
    if (vitalType === 'temperature' && value !== 'N/A') {
        const temp = parseFloat(value);
        const idealTemp = 37.0;
        const diff = Math.abs(temp - idealTemp);
        deviation = temp > idealTemp ? `+${diff.toFixed(1)}â”¬â–‘C above ideal` : `-${diff.toFixed(1)}â”¬â–‘C below ideal`;
        status = (temp >= 36.1 && temp <= 38.0) ? 'Normal' : 'Abnormal';
    } else if (vitalType === 'pulse' && value !== 'N/A') {
        const pulse = parseInt(value);
        const idealPulse = 80;
        const diff = Math.abs(pulse - idealPulse);
        deviation = pulse > idealPulse ? `+${diff} bpm above ideal` : `-${diff} bpm below ideal`;
        status = (pulse >= 60 && pulse <= 100) ? 'Normal' : 'Abnormal';
    } else if (vitalType === 'bp' && value !== 'N/A' && value.includes('/')) {
        const [sys, dia] = value.split('/').map(v => parseInt(v));
        status = (sys >= 90 && sys <= 140 && dia >= 60 && dia <= 90) ? 'Normal' : 'Abnormal';
        deviation = sys > 140 ? 'High BP' : sys < 90 ? 'Low BP' : 'Optimal';
    }

    const content = `
        <div style="font-weight: 600; margin-bottom: 0.5rem;">${vitalType.toUpperCase()}</div>
        <div><strong>Value:</strong> ${value}</div>
        <div><strong>Normal Range:</strong> ${normalRange}</div>
        <div><strong>Status:</strong> <span style="color: ${status === 'Normal' ? '#28a745' : '#dc3545'}">${status}</span></div>
        ${deviation ? `<div><strong>Deviation:</strong> ${deviation}</div>` : ''}
    `;

    tooltip.html(content);
    tooltip.css({
        top: offset.top - tooltip.outerHeight() - 10,
        left: offset.left + ($target.outerWidth() / 2) - (tooltip.outerWidth() / 2)
    });
    tooltip.addClass('active');
}

// Check for drug allergies
function checkForAllergies(medications, patientAllergies) {
    if (!patientAllergies) {
        return [];
    }

    // Normalize allergies to array format
    let allergiesArray = [];

    if (typeof patientAllergies === 'string') {
        // Handle comma-separated string
        allergiesArray = patientAllergies.split(',').map(a => a.trim()).filter(a => a.length > 0);
    } else if (Array.isArray(patientAllergies)) {
        // Handle array (could be array of strings or array of objects)
        allergiesArray = patientAllergies.map(a => {
            if (typeof a === 'string') return a.trim();
            if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
            return '';
        }).filter(a => a.length > 0);
    } else if (typeof patientAllergies === 'object' && patientAllergies !== null) {
        // Handle single object or object with values
        if (patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen) {
            allergiesArray = [(patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen).trim()];
        } else {
            // Try to extract values from object
            allergiesArray = Object.values(patientAllergies).map(a => {
                if (typeof a === 'string') return a.trim();
                if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
                return '';
            }).filter(a => a.length > 0);
        }
    }

    if (allergiesArray.length === 0) {
        return [];
    }

    const alerts = [];
    medications.forEach(med => {
        const drugName = (med.drug_name || med.product_name || '').toLowerCase();
        allergiesArray.forEach(allergy => {
            if (drugName.includes(allergy.toLowerCase())) {
                alerts.push({
                    medication: med.drug_name || med.product_name,
                    allergy: allergy
                });
            }
        });
    });

    return alerts;
}

// Display allergy alert banner
function displayAllergyAlert(alerts) {
    if (alerts.length === 0) return '';

    const allergyList = alerts.map(alert =>
        `<strong>${alert.medication}</strong> (Allergic to: ${alert.allergy})`
    ).join('<br>');

    return `
        <div class="allergy-alert">
            <div class="allergy-alert-icon">Î“ÃœÃ¡âˆ©â••Ã…</div>
            <div>
                <strong>ALLERGY WARNING!</strong><br>
                ${allergyList}
            </div>
        </div>
    `;
}

// Animate refresh button
function animateRefresh(buttonElement) {
    const $btn = $(buttonElement);
    $btn.addClass('refreshing');
    setTimeout(() => $btn.removeClass('refreshing'), 600);
}

// ==========================================
// REPORTS VIEW FUNCTIONS
// ==========================================
// NOTE: The lab-specific reports functionality has been disabled for nursing workbench.
// Nursing reports should use shift-summary and handover routes instead.

function showReports() {
    // Temporarily show message that reports are being redesigned for nursing
    toastr.info('Nursing reports feature is being configured. Please use Shift Summary and Handover reports from the left panel.');
    return;
}

function hideReports() {
    $('#reports-view').removeClass('active');

    // Show appropriate view based on patient selection state
    if (currentPatient) {
        $('#patient-header').addClass('active');
        $('#workspace-content').show().addClass('active');
    } else {
        $('#empty-state').show();
    }

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

function setDefaultDateFilters() {
    // Set date filters to current month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    // Format as YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    $('#report-date-from').val(formatDate(firstDay));
    $('#report-date-to').val(formatDate(lastDay));
}

/* LAB-SPECIFIC REPORT FILTER FUNCTIONS - DISABLED FOR NURSING WORKBENCH
function loadFilterOptions() {
    // Load doctors
    $.ajax({
        url: '{{ route("lab.filterDoctors") }}',
        method: 'GET',
        success: function(doctors) {
            let options = '<option value="">All Doctors</option>';
            doctors.forEach(function(doctor) {
                options += `<option value="${doctor.id}">${doctor.name}</option>`;
            });
            $('#report-doctor-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load doctors:', xhr);
        }
    });

    // Load HMOs with optgroups
    $.ajax({
        url: '{{ route("lab.filterHmos") }}',
        method: 'GET',
        success: function(hmoGroups) {
            let options = '<option value="">All HMOs</option>';
            Object.keys(hmoGroups).forEach(function(schemeName) {
                options += `<optgroup label="${schemeName}">`;
                hmoGroups[schemeName].forEach(function(hmo) {
                    options += `<option value="${hmo.id}">${hmo.name}</option>`;
                });
                options += '</optgroup>';
            });
            $('#report-hmo-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load HMOs:', xhr);
        }
    });

    // Load services
    $.ajax({
        url: '{{ route("lab.filterServices") }}',
        method: 'GET',
        success: function(services) {
            let options = '<option value="">All Services</option>';
            services.forEach(function(service) {
                options += `<option value="${service.id}">${service.name}</option>`;
            });
            $('#report-service-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load services:', xhr);
        }
    });
}
*/

/* REPLACED BY NEW IMPLEMENTATION
function loadReportsStatistics(filters = {}) {
    // If no filters provided, use current form values
    if (Object.keys(filters).length === 0) {
        filters = {
            date_from: $('#report-date-from').val(),
            date_to: $('#report-date-to').val(),
            status: $('#report-status-filter').val(),
            service_id: $('#report-service-filter').val(),
            doctor_id: $('#report-doctor-filter').val(),
            hmo_id: $('#report-hmo-filter').val(),
            patient_search: $('#report-patient-search').val()
        };
    }

    $.ajax({
        url: '{{ route("lab.statistics") }}',
        method: 'GET',
        data: filters,
        success: function(data) {
            // Update summary cards
            $('#stat-total-requests').text(data.summary.total_requests);
            $('#stat-completed').text(data.summary.completed);
            $('#stat-pending').text(data.summary.pending);
            $('#stat-avg-tat').text(data.summary.avg_tat + 'h');

            // Store data for charts
            window.reportsData = data;

            // Update charts if they exist
            if (window.statusChart) {
                updateStatusChart(data.by_status);
            }
            if (window.trendsChart) {
                updateTrendsChart(data.monthly_trends);
            }

            // Update top services
            if (data.top_services && data.top_services.length > 0) {
                let servicesHtml = '<ul class="list-group list-group-flush">';
                data.top_services.forEach(function(service, index) {
                    const percentage = data.summary.total_requests > 0 ? Math.round((service.count / data.summary.total_requests) * 100) : 0;
                    servicesHtml += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div style="flex: 1; min-width: 0; margin-right: 15px;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-truncate font-weight-bold" title="${service.service}">${index + 1}. ${service.service}</span>
                                <span class="badge badge-primary badge-pill">${service.count}</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
                    </li>`;
                });
                servicesHtml += '</ul>';
                $('#top-services-list').html(servicesHtml);
            } else {
                $('#top-services-list').html('<p class="text-muted">No data available</p>');
            }
        },
        error: function(xhr) {
            console.error('Failed to load statistics:', xhr);
            toastr.error('Failed to load statistics');
        }
    });
}
*/

/* LAB-SPECIFIC REPORTS DATATABLE - DISABLED FOR NURSING WORKBENCH
function initializeReportsDataTable() {
    if (window.reportsDataTable) {
        window.reportsDataTable.destroy();
    }

    window.reportsDataTable = $('#reports-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("lab.reports") }}',
            data: function(d) {
                // Add filter values to request
                d.date_from = $('#report-date-from').val();
                d.date_to = $('#report-date-to').val();
                d.status = $('#report-status-filter').val();
                d.service_id = $('#report-service-filter').val();
                d.doctor_id = $('#report-doctor-filter').val();
                d.hmo_id = $('#report-hmo-filter').val();
                d.patient_search = $('#report-patient-search').val();

                // Debug: Log what we're sending
                console.log('DataTable AJAX params:', {
                    date_from: d.date_from,
                    date_to: d.date_to,
                    status: d.status,
                    service_id: d.service_id,
                    doctor_id: d.doctor_id,
                    hmo_id: d.hmo_id,
                    patient_search: d.patient_search
                });
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'file_no', name: 'patient.file_no' },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'service_name', name: 'service.service_name' },
            { data: 'doctor_name', name: 'doctor_name', orderable: false },
            { data: 'hmo_name', name: 'hmo_name', orderable: false },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'tat', name: 'tat', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: false,
        scrollX: true
    });
}
*/

function initializeReportsCharts() {
    // TODO: Implement Chart.js charts
    // - Status breakdown (bar chart)
    // - Monthly trends (line chart)
    console.log('Charts will be implemented with Chart.js');
}

function updateStatusChart(data) {
    // TODO: Update status chart with new data
}

function updateTrendsChart(data) {
    // TODO: Update trends chart with new data
}

function renderQueueCard(data) {
    // Status badges
    const statusBadges = getStatusBadges(data);

    // Patient meta
    const patientMeta = `
        <div class="queue-card-patient-meta">
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-account"></i>
                <span>${data.age} Î“Ã‡Ã³ ${data.gender}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-card-account-details"></i>
                <span>${data.file_no}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-hospital-building"></i>
                <span>${data.hmo}</span>
            </div>
        </div>
    `;

    // Note section
    const noteSection = data.note ? `
        <div class="queue-card-note">
            <div class="queue-card-note-label"><i class="mdi mdi-note-text"></i> Request Note</div>
            <div>${data.note}</div>
        </div>
    ` : '';

    // Attachments
    let attachmentsSection = '';
    if (data.attachments && data.attachments.length > 0) {
        const attachmentLinks = data.attachments.map(att => {
            const icon = getFileIcon(att.type);
            return `<a href="/storage/${att.path}" target="_blank" class="queue-card-attachment">
                ${icon} ${att.name}
            </a>`;
        }).join('');
        attachmentsSection = `
            <div class="queue-card-attachments">
                <strong><i class="mdi mdi-paperclip"></i> Attachments:</strong>
                ${attachmentLinks}
            </div>
        `;
    }

    // Result section
    const resultSection = data.result ? `
        <div class="queue-card-note" style="background: #f0f9ff; border-color: #0ea5e9;">
            <div class="queue-card-note-label" style="color: #0ea5e9;"><i class="mdi mdi-flask"></i> Result</div>
            <div>${data.result}</div>
        </div>
    ` : '';

    return `
        <div class="queue-card" data-patient-id="${data.patient_id}">
            <div class="queue-card-header">
                <div class="queue-card-patient">
                    <div class="queue-card-patient-name">${data.patient_name}</div>
                    ${patientMeta}
                </div>
                <div class="queue-card-service">${data.service_name}</div>
            </div>
            <div class="queue-card-body">
                <div class="queue-card-status-row">
                    ${statusBadges}
                </div>
                ${noteSection}
                ${resultSection}
                ${attachmentsSection}
            </div>
            <div class="queue-card-actions">
                <button class="btn btn-primary btn-select-patient-from-queue" data-patient-id="${data.patient_id}">
                    <i class="mdi mdi-account-arrow-right"></i> Select Patient
                </button>
            </div>
        </div>
    `;
}

function getStatusBadges(data) {
    let badges = '';

    // Requested
    badges += `
        <div class="queue-card-status-item completed">
            <div class="queue-card-status-label"><i class="mdi mdi-calendar-check"></i> Requested</div>
            <div class="queue-card-status-value">${data.requested_by}<br><small>${data.requested_at}</small></div>
        </div>
    `;

    // Billing
    if (data.billed_by && data.billed_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billed</div>
                <div class="queue-card-status-value">${data.billed_by}<br><small>${data.billed_at}</small></div>
            </div>
        `;
    } else {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billing</div>
                <div class="queue-card-status-value">Awaiting billing</div>
            </div>
        `;
    }

    // Sample
    if (data.sample_taken_by && data.sample_taken_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample Taken</div>
                <div class="queue-card-status-value">${data.sample_taken_by}<br><small>${data.sample_taken_at}</small></div>
            </div>
        `;
    } else if (data.status >= 2) {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample</div>
                <div class="queue-card-status-value">Awaiting sample collection</div>
            </div>
        `;
    }

    // Result
    if (data.result_by && data.result_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">${data.result_by}<br><small>${data.result_at}</small></div>
            </div>
        `;
    } else if (data.status >= 3) {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">Awaiting result entry</div>
            </div>
        `;
    }

    return badges;
}

function getFileIcon(extension) {
    const icons = {
        'pdf': '<i class="mdi mdi-file-pdf"></i>',
        'doc': '<i class="mdi mdi-file-word"></i>',
        'docx': '<i class="mdi mdi-file-word"></i>',
        'jpg': '<i class="mdi mdi-file-image"></i>',
        'jpeg': '<i class="mdi mdi-file-image"></i>',
        'png': '<i class="mdi mdi-file-image"></i>',
    };
    return icons[extension] || '<i class="mdi mdi-file"></i>';
}

// Handle patient selection from queue
$(document).on('click', '.btn-select-patient-from-queue', function() {
    const patientId = $(this).data('patient-id');
    loadPatient(patientId);
    hideQueue();
});

// ==========================================
// WARD DASHBOARD EVENT HANDLERS
// ==========================================

// Open ward dashboard view
$('#btn-ward-dashboard').on('click', function() {
    showWardDashboard();
});

// Close ward dashboard view
$('#btn-close-ward-dashboard').on('click', function() {
    hideWardDashboard();
});

function showWardDashboard() {
    // Hide all other views first to prevent stacking
    hideAllViews();

    // Show ward dashboard
    $('#ward-dashboard-view').addClass('active');

    // Initialize ward dashboard
    if (typeof WardDashboard !== 'undefined') {
        WardDashboard.init();
    }

    // On mobile, show main workspace
    if (window.innerWidth < 768) {
        $('#main-workspace').addClass('active');
        $('#left-panel').addClass('hidden');
    }
}

function hideWardDashboard() {
    $('#ward-dashboard-view').removeClass('active');

    // Show appropriate view based on patient selection state
    if (currentPatient) {
        $('#patient-header').addClass('active');
        $('#workspace-content').show().addClass('active');
    } else {
        $('#empty-state').show();
    }

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

// ==========================================
// QUICK ACTION HANDLERS
// ==========================================

// Quick Vitals button - enabled when patient is selected
function updateQuickActionsState() {
    if (currentPatient) {
        $('#btn-quick-vitals').prop('disabled', false).attr('title', 'Record vitals for ' + (currentPatientData?.name || 'patient'));
    } else {
        $('#btn-quick-vitals').prop('disabled', true).attr('title', 'Select a patient first');
    }
}

// Quick Vitals button handler
$('#btn-quick-vitals').on('click', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    // Switch to vitals tab
    $('.workspace-tab[data-tab="vitals"]').click();
});

// Nursing Reports button handler
$('#btn-nursing-reports').on('click', function() {
    NursingReports.show();
});

// ==========================================
// NURSING REPORTS MODULE
// ==========================================
const NursingReports = (function() {
    // State
    let charts = {};
    let dataTables = {};
    let isInitialized = false;
    let currentFilters = {
        date_range: '7days',
        date_from: null,
        date_to: null,
        ward_id: null,
        nurse_id: null,
        shift_type: null
    };

    // Base URL
    const BASE_URL = '/nursing-workbench/reports';

    // Initialize
    function init() {
        if (isInitialized) return;

        bindEvents();
        loadNurseOptions();
        loadWardOptions();
        setDefaultDateRange();
        isInitialized = true;
    }

    // Bind events
    function bindEvents() {
        // Close button
        $('#btn-close-nursing-reports').on('click', hide);

        // Apply filters button
        $('#nr-apply-filters').on('click', applyFilters);

        // Reset filters button
        $('#nr-reset-filters').on('click', clearFilters);

        // Date range select
        $('#nr-date-range').on('change', function() {
            const val = $(this).val();
            if (val === 'custom') {
                $('#nr-custom-dates').show();
            } else {
                $('#nr-custom-dates').hide();
                currentFilters.date_range = val;
                currentFilters.date_from = null;
                currentFilters.date_to = null;
            }
        });

        // Tab change - load data for the tab
        $('#nursingReportsTabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            const target = $(e.target).attr('href');
            loadTabData(target);
        });
    }

    // Show reports view
    function show() {
        init();
        hideAllViews();
        $('#nursing-reports-view').show();

        // Load activity summary (first tab)
        loadTabData('#nr-activity');
    }

    // Hide reports view
    function hide() {
        $('#nursing-reports-view').hide();
    }

    // Set default date range (last 7 days)
    function setDefaultDateRange() {
        const today = new Date();
        const weekAgo = new Date(today);
        weekAgo.setDate(today.getDate() - 6);

        $('#nr-date-from').val(formatDateInput(weekAgo));
        $('#nr-date-to').val(formatDateInput(today));
    }

    // Format date for input
    function formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }

    // Load nurse options
    function loadNurseOptions() {
        $.get(BASE_URL + '/nurses', function(response) {
            if (response.success && response.nurses) {
                const $select = $('#nr-nurse-filter');
                $select.find('option:not(:first)').remove();
                response.nurses.forEach(nurse => {
                    $select.append(`<option value="${nurse.id}">${nurse.name}</option>`);
                });
            }
        });
    }

    // Load ward options
    function loadWardOptions() {
        $.get('/nursing-workbench/wards', function(response) {
            if (response.wards) {
                const $select = $('#nr-ward-filter');
                $select.find('option:not(:first)').remove();
                response.wards.forEach(ward => {
                    $select.append(`<option value="${ward.id}">${ward.name}</option>`);
                });
            }
        });
    }

    // Get current filters
    function getFilters() {
        return {
            date_range: $('#nr-date-range').val(),
            date_from: $('#nr-date-from').val(),
            date_to: $('#nr-date-to').val(),
            ward_id: $('#nr-ward-filter').val() || null,
            nurse_id: $('#nr-nurse-filter').val() || null,
            shift_type: $('#nr-shift-filter').val() || null
        };
    }

    // Apply filters
    function applyFilters() {
        currentFilters = getFilters();
        const activeTab = $('#nursingReportsTabs .active').attr('href');
        loadTabData(activeTab, true);
    }

    // Clear filters
    function clearFilters() {
        $('#nr-date-range').val('7days');
        $('#nr-custom-dates').hide();
        $('#nr-ward-filter').val('');
        $('#nr-nurse-filter').val('');
        $('#nr-shift-filter').val('');
        setDefaultDateRange();
        currentFilters = {
            date_range: '7days',
            date_from: null,
            date_to: null,
            ward_id: null,
            nurse_id: null,
            shift_type: null
        };
        applyFilters();
    }

    // Load data for specific tab
    function loadTabData(tabId, forceReload = false) {
        const filters = getFilters();

        switch (tabId) {
            case '#nr-activity':
                loadActivitySummary(filters);
                break;
            case '#nr-vitals':
                loadVitalsReport(filters, forceReload);
                break;
            case '#nr-medications':
                loadMedicationsReport(filters, forceReload);
                break;
            case '#nr-injections':
                loadInjectionsReport(filters, forceReload);
                break;
            case '#nr-io':
                loadIOReport(filters, forceReload);
                break;
            case '#nr-notes':
                loadNotesReport(filters, forceReload);
                break;
            case '#nr-shifts':
                loadShiftsReport(filters, forceReload);
                break;
            case '#nr-occupancy':
                loadOccupancyReport(filters);
                break;
        }
    }

    // Load Activity Summary
    function loadActivitySummary(filters) {
        showLoading('#nr-activity');

        $.get(BASE_URL + '/activity-summary', filters, function(response) {
            if (response.success) {
                // Update stats - matching HTML element IDs
                $('#nr-stat-patients').text(response.stats.patients_served || 0);
                $('#nr-stat-vitals').text(response.stats.vitals_recorded || 0);
                $('#nr-stat-medications').text(response.stats.medications_given || 0);
                $('#nr-stat-injections').text(response.stats.injections || 0);
                $('#nr-stat-immunizations').text(response.stats.immunizations || 0);
                $('#nr-stat-notes').text(response.stats.notes_written || 0);
                $('#nr-stat-handovers').text(response.stats.handovers || 0);
                $('#nr-stat-shifts').text(response.stats.shifts_completed || 0);

                // Render charts
                renderActivityTrendChart(response.trend);
                renderDistributionChart(response.distribution);
                renderPeakHoursChart(response.peak_hours);
                renderTopPerformersTable(response.top_performers);
            }
            hideLoading('#nr-activity');
        }).fail(function() {
            hideLoading('#nr-activity');
            toastr.error('Failed to load activity summary');
        });
    }

    // Render Activity Trend Chart
    function renderActivityTrendChart(data) {
        const ctx = document.getElementById('nr-activity-trend-chart');
        if (!ctx) return;

        if (charts.activityTrend) {
            charts.activityTrend.destroy();
        }

        charts.activityTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.date),
                datasets: [
                    {
                        label: 'Vitals',
                        data: data.map(d => d.vitals),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Medications',
                        data: data.map(d => d.medications),
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Injections',
                        data: data.map(d => d.injections),
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Notes',
                        data: data.map(d => d.notes),
                        borderColor: '#6c757d',
                        backgroundColor: 'rgba(108, 117, 125, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Render Distribution Chart
    function renderDistributionChart(data) {
        const ctx = document.getElementById('nr-activity-distribution-chart');
        if (!ctx) return;

        if (charts.distribution) {
            charts.distribution.destroy();
        }

        charts.distribution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    data: data.map(d => d.value),
                    backgroundColor: data.map(d => d.color)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Render Peak Hours Chart
    function renderPeakHoursChart(data) {
        const ctx = document.getElementById('nr-peak-hours-chart');
        if (!ctx) return;

        if (charts.peakHours) {
            charts.peakHours.destroy();
        }

        charts.peakHours = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.hour),
                datasets: [{
                    label: 'Activities',
                    data: data.map(d => d.count),
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }

    // Render Top Performers Table
    function renderTopPerformersTable(data) {
        const $tbody = $('#nr-top-performers-table tbody');
        $tbody.empty();

        if (!data || data.length === 0) {
            $tbody.append('<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>');
            return;
        }

        data.forEach((performer, index) => {
            $tbody.append(`
                <tr>
                    <td><span class="badge badge-${index < 3 ? 'primary' : 'secondary'}">#${index + 1}</span> ${performer.nurse}</td>
                    <td>${performer.actions}</td>
                    <td>${performer.patients}</td>
                    <td>${performer.shifts}</td>
                </tr>
            `);
        });
    }

    // Load Vitals Report
    function loadVitalsReport(filters, forceReload) {
        // Load stats
        $.get(BASE_URL + '/vitals', filters, function(response) {
            if (response.success) {
                $('#nr-vitals-total').text(response.stats.total || 0);
                $('#nr-vitals-abnormal').text(response.stats.abnormal || 0);
                $('#nr-vitals-fever').text(response.stats.fever || 0);
                $('#nr-vitals-hypertension').text(response.stats.hypertension || 0);
            }
        });

        // Initialize or reload DataTable
        if (!dataTables.vitals || forceReload) {
            if (dataTables.vitals) {
                dataTables.vitals.destroy();
            }
            dataTables.vitals = $('#nr-vitals-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/vitals',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'file_no', title: 'File No' },
                    { data: 'temp', title: 'Temp (â”¬â–‘C)' },
                    { data: 'blood_pressure', title: 'BP' },
                    { data: 'heart_rate', title: 'Pulse' },
                    { data: 'spo2', title: 'SpO2' },
                    { data: 'recorded_by', title: 'Recorded By' },
                    {
                        data: 'status',
                        title: 'Status',
                        render: function(data) {
                            const colors = { normal: 'success', warning: 'warning', critical: 'danger' };
                            return `<span class="badge badge-${colors[data] || 'secondary'}">${data}</span>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 15,
                dom: 'Bfrtip',
                buttons: ['excel', 'pdf', 'print']
            });
        } else {
            dataTables.vitals.ajax.reload();
        }
    }

    // Load Medications Report
    function loadMedicationsReport(filters, forceReload) {
        // Load stats
        $.get(BASE_URL + '/medications', filters, function(response) {
            if (response.success) {
                $('#nr-meds-total').text(response.stats.total || 0);
                $('#nr-meds-ontime').text(response.stats.ontime_rate || '0%');
                $('#nr-meds-late').text(response.stats.late || 0);
                $('#nr-meds-missed').text(response.stats.missed || 0);
            }
        });

        // Initialize or reload DataTable
        if (!dataTables.medications || forceReload) {
            if (dataTables.medications) {
                dataTables.medications.destroy();
            }
            dataTables.medications = $('#nr-medications-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/medications',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Administered At' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'medication', title: 'Medication' },
                    { data: 'dose', title: 'Dose' },
                    { data: 'route', title: 'Route' },
                    { data: 'scheduled_time', title: 'Scheduled' },
                    { data: 'administered_by_name', title: 'Given By' },
                    {
                        data: 'status',
                        title: 'Status',
                        render: function(data) {
                            return `<span class="badge badge-${data === 'ontime' ? 'success' : 'warning'}">${data === 'ontime' ? 'On Time' : 'Late'}</span>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.medications.ajax.reload();
        }
    }

    // Load Injections Report
    function loadInjectionsReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/injections', filters, function(response) {
            if (response.success) {
                $('#nr-injections-total').text(response.total || 0);
            }
        });

        $.get(BASE_URL + '/immunizations', filters, function(response) {
            if (response.success) {
                $('#nr-immunizations-total').text(response.total || 0);
            }
        });

        // Injections DataTable
        if (!dataTables.injections || forceReload) {
            if (dataTables.injections) {
                dataTables.injections.destroy();
            }
            dataTables.injections = $('#nr-injections-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/injections',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'drug_name', title: 'Drug' },
                    { data: 'dose', title: 'Dose' },
                    { data: 'route', title: 'Route' },
                    { data: 'site', title: 'Site' },
                    { data: 'administered_by_name', title: 'Given By' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.injections.ajax.reload();
        }

        // Immunizations DataTable
        if (!dataTables.immunizations || forceReload) {
            if (dataTables.immunizations) {
                dataTables.immunizations.destroy();
            }
            dataTables.immunizations = $('#nr-immunizations-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/immunizations',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'patient_age', title: 'Age' },
                    { data: 'vaccine', title: 'Vaccine' },
                    { data: 'dose_number', title: 'Dose #' },
                    { data: 'batch_no', title: 'Batch No' },
                    { data: 'administered_by_name', title: 'Given By' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.immunizations.ajax.reload();
        }
    }

    // Load I/O Report
    function loadIOReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/io', filters, function(response) {
            if (response.success) {
                $('#nr-io-records').text(response.stats.records || 0);
                $('#nr-io-positive').text(response.stats.positive || 0);
                $('#nr-io-negative').text(response.stats.negative || 0);
                $('#nr-io-critical').text(response.stats.critical || 0);
            }
        });

        // DataTable
        if (!dataTables.io || forceReload) {
            if (dataTables.io) {
                dataTables.io.destroy();
            }
            dataTables.io = $('#nr-io-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/io',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'date_formatted', title: 'Date' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'ward_bed', title: 'Ward / Bed' },
                    { data: 'total_intake', title: 'Total Intake' },
                    { data: 'total_output', title: 'Total Output' },
                    {
                        data: 'balance',
                        title: 'Balance',
                        render: function(data, type, row) {
                            const cls = row.status === 'critical' ? 'text-danger' : (row.status === 'warning' ? 'text-warning' : 'text-success');
                            return `<span class="${cls} font-weight-bold">${data}</span>`;
                        }
                    },
                    { data: 'recorded_by', title: 'Recorded By' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.io.ajax.reload();
        }
    }

    // Load Notes Report
    function loadNotesReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/notes', filters, function(response) {
            if (response.success) {
                $('#nr-notes-total').text(response.stats.total || 0);
                $('#nr-notes-critical').text(response.stats.critical || 0);
                $('#nr-notes-patients').text(response.stats.patients || 0);
            }
        });

        // DataTable
        if (!dataTables.notes || forceReload) {
            if (dataTables.notes) {
                dataTables.notes.destroy();
            }
            dataTables.notes = $('#nr-notes-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/notes',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'note_type', title: 'Type' },
                    { data: 'summary', title: 'Summary' },
                    { data: 'written_by', title: 'Written By' },
                    {
                        data: 'status',
                        title: 'Status',
                        render: function(data) {
                            return `<span class="badge badge-${data === 'completed' ? 'success' : 'warning'}">${data}</span>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.notes.ajax.reload();
        }
    }

    // Load Shifts Report
    function loadShiftsReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/shifts', filters, function(response) {
            if (response.success) {
                $('#nr-shifts-total').text(response.stats.total || 0);
                $('#nr-shifts-avg-duration').text(response.stats.avg_duration || '0h');
                $('#nr-shifts-handovers').text(response.stats.handover_rate || '0%');
                $('#nr-shifts-overdue').text(response.stats.overdue || 0);
            }
        });

        // DataTable
        if (!dataTables.shifts || forceReload) {
            if (dataTables.shifts) {
                dataTables.shifts.destroy();
            }
            dataTables.shifts = $('#nr-shifts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/shifts',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'date', title: 'Date' },
                    { data: 'nurse_name', title: 'Nurse' },
                    { data: 'shift_type_label', title: 'Shift' },
                    { data: 'ward_name', title: 'Ward' },
                    { data: 'start_time', title: 'Start' },
                    { data: 'end_time', title: 'End' },
                    { data: 'duration', title: 'Duration' },
                    { data: 'actions_count', title: 'Actions' },
                    {
                        data: 'handover_status',
                        title: 'Handover',
                        render: function(data) {
                            return `<span class="badge badge-${data === 'Yes' ? 'success' : 'secondary'}">${data}</span>`;
                        }
                    },
                    { data: 'status_label', title: 'Status' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.shifts.ajax.reload();
        }
    }

    // Load Occupancy Report
    function loadOccupancyReport(filters) {
        showLoading('#nr-occupancy');

        $.get(BASE_URL + '/occupancy', filters, function(response) {
            if (response.success) {
                // Bed stats
                $('#nr-beds-total').text(response.stats.total_beds || 0);
                $('#nr-beds-occupied').text(response.stats.occupied || 0);
                $('#nr-beds-available').text(response.stats.available || 0);
                $('#nr-beds-maintenance').text(response.stats.maintenance || 0);

                // Admission/Discharge stats
                $('#nr-admissions-today').text(response.admissions.today || 0);
                $('#nr-admissions-period').text(response.admissions.period || 0);
                $('#nr-avg-los').text(response.admissions.avg_los || '0d');
                $('#nr-discharges-today').text(response.discharges.today || 0);
                $('#nr-discharges-period').text(response.discharges.period || 0);
                $('#nr-pending-discharges').text(response.discharges.pending || 0);

                // Render ward table
                renderWardOccupancyTable(response.wards);

                // Render occupancy chart
                renderOccupancyChart(response.stats);
            }
            hideLoading('#nr-occupancy');
        }).fail(function() {
            hideLoading('#nr-occupancy');
            toastr.error('Failed to load occupancy data');
        });
    }

    // Render Ward Occupancy Table
    function renderWardOccupancyTable(data) {
        const $tbody = $('#nr-occupancy-table tbody');
        $tbody.empty();

        if (!data || data.length === 0) {
            $tbody.append('<tr><td colspan="6" class="text-center text-muted">No wards configured</td></tr>');
            return;
        }

        data.forEach(ward => {
            const occupancyPct = parseInt(ward.occupancy_rate);
            const barClass = occupancyPct > 90 ? 'bg-danger' : (occupancyPct > 70 ? 'bg-warning' : 'bg-success');
            $tbody.append(`
                <tr>
                    <td><strong>${ward.ward}</strong></td>
                    <td>${ward.total}</td>
                    <td><span class="text-danger">${ward.occupied}</span></td>
                    <td><span class="text-success">${ward.available}</span></td>
                    <td><span class="text-warning">${ward.maintenance}</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar ${barClass}" style="width: ${ward.occupancy_rate}"></div>
                            </div>
                            <span class="ml-2 font-weight-bold">${ward.occupancy_rate}</span>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    // Render Occupancy Chart
    function renderOccupancyChart(data) {
        const ctx = document.getElementById('bed-occupancy-chart');
        if (!ctx) return;

        if (charts.occupancy) {
            charts.occupancy.destroy();
        }

        charts.occupancy = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Available', 'Maintenance'],
                datasets: [{
                    data: [data.occupied, data.available, data.maintenance],
                    backgroundColor: ['#dc3545', '#28a745', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Show loading state
    function showLoading(container) {
        $(container).find('.card-body').each(function() {
            if (!$(this).find('.loading-overlay').length) {
                $(this).append('<div class="loading-overlay"><div class="spinner-border text-primary"></div></div>');
            }
        });
    }

    // Hide loading state
    function hideLoading(container) {
        $(container).find('.loading-overlay').remove();
    }

    // Public API
    return {
        init: init,
        show: show,
        hide: hide,
        applyFilters: applyFilters,
        clearFilters: clearFilters
    };
})();


// Admission Summary button handler
$('#btn-admission-summary').on('click', function() {
    // Show today's admissions and discharges summary
    showWardDashboard();
    // Focus on admission queue
    setTimeout(function() {
        $('.queue-tab[data-queue="admission"]').click();
    }, 100);
});

// Shift Handover button handler
$('#btn-shift-handover').on('click', function() {
    // Use ShiftManager to show the handovers modal
    if (typeof ShiftManager !== 'undefined' && ShiftManager.showHandoversList) {
        ShiftManager.showHandoversList();
    } else {
        // Fallback - show modal directly
        $('#handoversListModal').modal('show');
    }
});

// Medication Round quick action button handler
$('.quick-action-btn[data-filter="medication-due"]').on('click', function() {
    showQueue('medication-due');
});

// Update medication round badge
function updateMedicationRoundBadge() {
    $.get('/nursing-workbench/queue-counts', function(counts) {
        var medCount = counts.medication_due || 0;
        var $badge = $('#med-round-badge');
        if (medCount > 0) {
            $badge.text(medCount).show();
        } else {
            $badge.hide();
        }
    });
}

// Call on page load
updateMedicationRoundBadge();

// ==========================================
// REPORTS VIEW EVENT HANDLERS
// ==========================================

// Open reports view (btn-view-reports only - btn-nursing-reports has its own handler above)
$('#btn-view-reports').on('click', function() {
    showReports();
});

// Close reports view
$('#btn-close-reports').on('click', function() {
    hideReports();
});

// Reports filter form submission
$('#reports-filter-form').on('submit', function(e) {
    e.preventDefault();

    // Reload statistics with filters
    const filters = {
        date_from: $('#report-date-from').val(),
        date_to: $('#report-date-to').val(),
        status: $('#report-status-filter').val(),
        service_id: $('#report-service-filter').val(),
        doctor_id: $('#report-doctor-filter').val(),
        hmo_id: $('#report-hmo-filter').val(),
        patient_search: $('#report-patient-search').val()
    };

    loadReportsStatistics(filters);

    // Reload DataTable
    if (window.reportsDataTable) {
        window.reportsDataTable.ajax.reload();
    }
});

// Clear reports filters
$('#clear-report-filters').on('click', function() {
    $('#reports-filter-form')[0].reset();
    loadReportsStatistics();
    if (window.reportsDataTable) {
        window.reportsDataTable.ajax.reload();
    }
});

// Export buttons (TODO: Implement with DataTables buttons extension)
$('#export-excel').on('click', function() {
    toastr.info('Excel export will be implemented with DataTables buttons extension');
});

$('#export-pdf').on('click', function() {
    toastr.info('PDF export will be implemented with DataTables buttons extension');
});

$('#print-report').on('click', function() {
    toastr.info('Print functionality will be implemented with DataTables buttons extension');
});

// Show new request button and update quick actions when patient is selected
function updateQuickActions() {
    if (currentPatient) {
        $('#btn-new-request').show();
        // Enable patient-dependent buttons
        $('#btn-quick-vitals').prop('disabled', false).attr('title', 'Record vitals for ' + (currentPatientData?.name || 'patient'));
        $('#btn-clinical-context').prop('disabled', false).attr('title', 'View clinical context for ' + (currentPatientData?.name || 'patient'));
    } else {
        $('#btn-new-request').hide();
        // Disable patient-dependent buttons
        $('#btn-quick-vitals').prop('disabled', true).attr('title', 'Select a patient first');
        $('#btn-clinical-context').prop('disabled', true).attr('title', 'Select a patient first');
    }
}

// New request button handler
$('#btn-new-request').on('click', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    switchWorkspaceTab('new-request');
    $('#new-request-patient-name').text(currentPatient.name);
});

// ==========================================
// REPORTS & ANALYTICS HELPER FUNCTIONS
// NOTE: Lab-specific reports disabled for nursing workbench
// ==========================================

/* LAB-SPECIFIC STATISTICS - DISABLED FOR NURSING WORKBENCH
function loadReportsStatistics(filters = {}) {
    // Show loading state
    $('#stat-total-requests').text('Loading...');
    $('#stat-completed').text('Loading...');
    $('#stat-pending').text('Loading...');
    $('#stat-avg-tat').text('Loading...');

    $.ajax({
        url: '{{ route("lab.statistics") }}',
        method: 'GET',
        data: filters,
        success: function(response) {
            // Update Summary Cards
            updateSummaryCards(response.summary);

            // Render Top Services
            renderTopServices(response.top_services, response.summary ? response.summary.total_requests : 0);

            // Render Top Doctors
            renderTopDoctors(response.top_doctors);

            // Initialize/Update Charts
            initializeReportsCharts(response.by_status, response.monthly_trends);
        },
        error: function(xhr) {
            console.error('Error loading statistics:', xhr);
            toastr.error('Failed to load report statistics');
        }
    });
}
*/

function updateSummaryCards(summary) {
    if(!summary) return;

    $('#stat-total-requests').text(summary.total_requests || 0);
    $('#stat-completed').text(summary.completed_requests || 0);
    $('#stat-pending').text(summary.pending_requests || 0);

    // Update Avg TAT
    $('#stat-avg-tat').text((summary.avg_tat || 0) + ' hrs');
}

function renderTopServices(services, totalRequests = 0) {
    const container = $('#top-services-list');

    if (!services || services.length === 0) {
        container.html('<p class="text-muted text-center p-3">No data available for the selected period.</p>');
        return;
    }

    let html = '<ul class="list-group list-group-flush">';

    services.forEach((service, index) => {
        const percentage = totalRequests > 0 ? Math.round((service.count / totalRequests) * 100) : 0;

        html += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <div style="flex: 1; min-width: 0; margin-right: 15px;">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-truncate font-weight-bold" title="${service.name}">${index + 1}. ${service.name}</span>
                    <span class="badge badge-primary badge-pill">${service.count}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
        </li>`;
    });

    html += '</ul>';
    container.html(html);
}

function renderTopDoctors(doctors) {
    const container = $('#top-doctors-list');

    if (!doctors || doctors.length === 0) {
        container.html('<p class="text-muted text-center p-3">No data available.</p>');
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover table-sm"><thead><tr><th>Doctor Name</th><th class="text-center">Requests</th><th class="text-right">Total Revenue</th></tr></thead><tbody>';

    doctors.forEach(doc => {
        const docName = doc.doctor ? (doc.doctor.firstname + ' ' + doc.doctor.surname) : 'Unknown';
        html += `
            <tr>
                <td><i class="mdi mdi-doctor mr-1"></i> ${docName}</td>
                <td class="text-center"><span class="badge badge-pill badge-info">${doc.count}</span></td>
                <td class="text-right">Î“Ã©Âª${parseFloat(doc.revenue).toLocaleString()}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.html(html);
}

let statusChartInstance = null;
let trendsChartInstance = null;

function initializeReportsCharts(byStatus, monthlyTrends) {
    // 1. Status Chart (Doughnut)
    const statusCtx = document.getElementById('status-chart').getContext('2d');

    // Destroy existing chart if it exists
    if (statusChartInstance) {
        statusChartInstance.destroy();
    }

    const statusLabels = [];
    const statusData = [];
    const statusColors = [];

    // Map status IDs to names and colors
    const statusMap = {
        1: { name: 'Awaiting Billing', color: '#ffc107' },
        2: { name: 'Awaiting Sample', color: '#17a2b8' },
        3: { name: 'Awaiting Results', color: '#007bff' },
        4: { name: 'Completed', color: '#28a745' },
        5: { name: 'Pending Approval', color: '#6f42c1' },
        6: { name: 'Rejected', color: '#dc3545' }
    };

    if (byStatus && byStatus.length > 0) {
        byStatus.forEach(item => {
            const info = statusMap[item.status] || { name: 'Unknown', color: '#6c757d' };
            statusLabels.push(info.name);
            statusData.push(item.count);
            statusColors.push(info.color);
        });
    } else {
        // Empty state
        statusLabels.push('No Data');
        statusData.push(0);
        statusColors.push('#e9ecef');
    }

    statusChartInstance = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right'
            }
        }
    });

    // 2. Monthly Trends Chart (Line)
    const trendsCtx = document.getElementById('trends-chart').getContext('2d');

    if (trendsChartInstance) {
        trendsChartInstance.destroy();
    }

    const trendLabels = [];
    const trendData = [];

    if (monthlyTrends && monthlyTrends.length > 0) {
        monthlyTrends.forEach(item => {
            trendLabels.push(item.month);
            trendData.push(item.count);
        });
    }

    trendsChartInstance = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Requests',
                data: trendData,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        precision: 0
                    }
                }]
            }
        }
    });
}

// =====================================
// NURSING WORKBENCH SPECIFIC FUNCTIONS
// =====================================

// Populate Overview Tab with patient data (called directly with data object)
function populateOverviewTab(data) {
    console.log('Populating overview tab with:', data); // Debug log

    // Populate Patient Information Card
    let patientInfoHtml = `
        <table class="table table-sm mb-0">
            <tr><th width="40%">File No:</th><td>${data.file_no || 'N/A'}</td></tr>
            <tr><th>Name:</th><td>${data.name || 'N/A'}</td></tr>
            <tr><th>Age/Gender:</th><td>${data.age || 'N/A'} / ${data.gender || 'N/A'}</td></tr>
            <tr><th>Phone:</th><td>${data.phone || 'N/A'}</td></tr>
            <tr><th>HMO:</th><td>${data.hmo || 'Private'}</td></tr>
            <tr><th>Blood Group:</th><td>${data.blood_group || 'Unknown'}</td></tr>
        </table>
    `;
    $('#overview-patient-info').html(patientInfoHtml);

    // Populate Admission Status Card
    let admissionHtml = '';
    if (data.admission) {
        admissionHtml = `
            <table class="table table-sm mb-0">
                <tr><th width="40%">Bed/Ward:</th><td><span class="badge badge-info">${data.admission.bed || 'N/A'}</span></td></tr>
                <tr><th>Admitted:</th><td>${data.admission.admitted_date || 'N/A'}</td></tr>
                <tr><th>Duration:</th><td>${data.admission.days_admitted || '0'} day(s)</td></tr>
                <tr><th>Reason:</th><td>${data.admission.reason || 'N/A'}</td></tr>
            </table>
        `;
    } else {
        admissionHtml = '<p class="text-muted text-center py-3">Not currently admitted</p>';
    }
    $('#overview-admission-info').html(admissionHtml);

    // Populate Latest Vitals Card
    let vitalsHtml = '';
    if (data.last_vitals) {
        vitalsHtml = `
            <table class="table table-sm mb-0">
                <tr><th width="50%">BP:</th><td>${data.last_vitals.bp || 'N/A'}</td></tr>
                <tr><th>Heart Rate:</th><td>${data.last_vitals.heart_rate || 'N/A'} bpm</td></tr>
                <tr><th>Temp:</th><td>${data.last_vitals.temp || 'N/A'} â”¬â–‘C</td></tr>
                <tr><th>Resp Rate:</th><td>${data.last_vitals.resp_rate || 'N/A'} /min</td></tr>
                <tr><th>Recorded:</th><td><small class="text-muted">${data.last_vitals.time || 'N/A'}</small></td></tr>
            </table>
        `;
    } else {
        vitalsHtml = '<p class="text-muted text-center py-3">No vitals recorded</p>';
    }
    $('#overview-vitals-info').html(vitalsHtml);

    // Populate Latest Nurse Note
    let nurseNoteHtml = '';
    if (data.latest_nurse_note) {
        nurseNoteHtml = `
            <div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge badge-primary">${data.latest_nurse_note.type}</span>
                    <small class="text-muted">${data.latest_nurse_note.time_ago}</small>
                </div>
                <div class="note-preview mb-2" style="font-size: 0.9rem;">
                    ${data.latest_nurse_note.note}
                </div>
                <div class="text-right">
                    <small class="text-muted">By: <strong>${data.latest_nurse_note.created_by}</strong></small>
                </div>
            </div>
        `;
    } else {
        nurseNoteHtml = '<p class="text-muted text-center py-2">No nursing notes</p>';
    }
    $('#overview-nurse-note').html(nurseNoteHtml);

    // Populate Latest Doctor Note
    let doctorNoteHtml = '';
    if (data.latest_doctor_note) {
        doctorNoteHtml = `
            <div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge badge-info text-white">Doctor Note</span>
                    <small class="text-muted">${data.latest_doctor_note.time_ago}</small>
                </div>
                <div class="note-preview mb-2" style="font-size: 0.9rem;">
                    ${data.latest_doctor_note.note}
                </div>
                <div class="text-right">
                    <small class="text-muted">By: <strong>${data.latest_doctor_note.created_by}</strong></small>
                </div>
            </div>
        `;
    } else {
        doctorNoteHtml = '<p class="text-muted text-center py-2">No doctor notes</p>';
    }
    $('#overview-doctor-note').html(doctorNoteHtml);

    // Populate Allergies & Alerts Card - handle array, comma-separated string, JSON string, object, or null
    let allergiesHtml = '';
    let allergiesArray = [];
    if (data.allergies) {
        if (Array.isArray(data.allergies)) {
            allergiesArray = data.allergies;
        } else if (typeof data.allergies === 'string') {
            try {
                const parsed = JSON.parse(data.allergies);
                allergiesArray = Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            } catch(e) {
                allergiesArray = data.allergies.split(',').map(a => a.trim()).filter(a => a);
            }
        } else if (typeof data.allergies === 'object') {
            allergiesArray = Object.values(data.allergies).filter(a => a);
        }
    }

    if (allergiesArray.length > 0) {
        allergiesHtml = '<div class="d-flex flex-wrap">';
        allergiesArray.forEach(function(allergy) {
            allergiesHtml += `<span class="badge badge-danger m-1 p-2"><i class="mdi mdi-alert"></i> ${allergy}</span>`;
        });
        allergiesHtml += '</div>';
    } else {
        allergiesHtml = '<p class="text-success text-center py-2"><i class="mdi mdi-check-circle"></i> No known allergies</p>';
    }
    $('#overview-allergies').html(allergiesHtml);

    // Load pending medications for overview
    loadOverviewPendingMeds(data.id);

    // Load tasks for overview
    loadOverviewTasks(data.id);
}

// Load Patient Overview (fetches data first - used when switching tabs)
function loadPatientOverview(patientId) {
    if (currentPatientData && currentPatientData.id == patientId) {
        // Use cached data if available
        populateOverviewTab(currentPatientData);
        return;
    }

    $.ajax({
        url: `/nursing-workbench/patient/${patientId}/details`,
        method: 'GET',
        success: function(data) {
            currentPatientData = data;
            populateOverviewTab(data);
        },
        error: function() {
            $('#overview-patient-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-admission-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-vitals-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-nurse-note').html('<p class="text-danger">Failed to load</p>');
            $('#overview-doctor-note').html('<p class="text-danger">Failed to load</p>');
            $('#overview-allergies').html('<p class="text-danger">Failed to load</p>');
        }
    });
}

// Load pending medications for overview card (uses medication chart data)
function loadOverviewPendingMeds(patientId) {
    // Try to get medications from the existing medication chart API
    $.ajax({
        url: `/patients/${patientId}/nurse-chart/medication`,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            let html = '';
            let pendingMeds = [];

            // Filter for pending/due medications if data is an array
            if (Array.isArray(data)) {
                pendingMeds = data.filter(med => med.status === 'pending' || med.status === 'due');
            } else if (data.medications) {
                pendingMeds = data.medications.filter(med => !med.is_administered);
            }

            if (pendingMeds.length > 0) {
                $('#overview-pending-meds-count').text(pendingMeds.length);
                html = '<ul class="list-group list-group-flush">';
                pendingMeds.slice(0, 5).forEach(function(med) {
                    html += `
                        <li class="list-group-item p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${med.drug_name || med.name || 'Unknown'}</strong>
                                <small class="d-block text-muted">${med.dose || ''} ${med.route || ''}</small>
                            </div>
                            <span class="badge badge-warning">${med.due_time || med.scheduled_time || 'Pending'}</span>
                        </li>
                    `;
                });
                html += '</ul>';
                if (pendingMeds.length > 5) {
                    html += `<small class="text-muted d-block text-center mt-2">+${pendingMeds.length - 5} more</small>`;
                }
            } else {
                $('#overview-pending-meds-count').text('0');
                html = '<p class="text-muted text-center py-2">No pending medications</p>';
            }
            $('#overview-pending-meds').html(html);
        },
        error: function() {
            // Silently fail - medications can be viewed in medication tab
            $('#overview-pending-meds').html('<p class="text-muted text-center py-2">View in Medication Tab</p>');
            $('#overview-pending-meds-count').text('-');
        }
    });
}

// Load tasks for overview card (placeholder - can be enhanced later)
function loadOverviewTasks(patientId) {
    // For now, just show a placeholder since tasks may not have a dedicated API yet
    $('#overview-tasks').html('<p class="text-muted text-center py-2">No tasks pending</p>');
    $('#overview-tasks-count').text('0');
}

// Load Admitted Patients Queue
function loadAdmittedPatients() {
    $.ajax({
        url: '{{ route("nursing-workbench.admitted-patients") }}',
        method: 'GET',
        success: function(data) {
            if (data.length === 0) {
                showNotification('info', 'No admitted patients found');
                return;
            }
            // Display patients in a list/cards
            displayAdmittedPatientsQueue(data);
        },
        error: function() {
            showNotification('error', 'Failed to load admitted patients');
        }
    });
}

// Load Vitals Queue
function loadVitalsQueue() {
    $('#empty-state').hide();
    $('#workspace-content').removeClass('active');
    $('.patient-header').removeClass('active');
    $('#queue-view').addClass('active').css('display', 'flex');
    $('#queue-view-title').html('<i class="mdi mdi-heart-pulse"></i> Vitals Queue');

    if ($.fn.DataTable.isDataTable('#queue-datatable')) {
        $('#queue-datatable').DataTable().destroy();
    }

    $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("nursing-workbench.vitals-queue") }}',
        columns: [
            { data: 'info', name: 'info' }
        ],
        language: {
             emptyTable: "No patients pending vitals"
        },
        drawCallback: function() {
            // Attach click handlers to cards
        }
    });
}

// Load Bed Requests Queue
function loadBedRequestsQueue() {
    $('#empty-state').hide();
    $('#workspace-content').removeClass('active');
    $('.patient-header').removeClass('active');
    $('#queue-view').addClass('active').css('display', 'flex');
    $('#queue-view-title').html('<i class="mdi mdi-bed"></i> Bed Requests');

    if ($.fn.DataTable.isDataTable('#queue-datatable')) {
        $('#queue-datatable').DataTable().destroy();
    }

    $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("nursing-workbench.bed-requests-queue") }}',
        columns: [
            { data: 'info', name: 'info' }
        ],
        language: {
             emptyTable: "No pending bed requests"
        }
    });
}

// Load Medication Due
function loadMedicationDue() {
    showNotification('info', 'Medication due feature coming soon');
    // TODO: Implement medication due loading
}

// ========================================
// INJECTION MODULE - Drug Search & Administration
// ========================================

function setInjectionDrugSource(source) {
    $('#injection-drug-source').val(source);
    $('[data-inj-source]').removeClass('active');
    $(`[data-inj-source="${source}"]`).addClass('active');

    $('.source-section').hide();
    $('#inj-source-' + (source === 'ward_stock' ? 'ward' : source === 'patient_own' ? 'patient' : 'pharmacy')).show();

    // Â§7.1: Only ward_stock needs the hospital product search (Step 2)
    // Pharmacy uses prescription dropdown, Patient's Own uses free-text fields
    if (source === 'ward_stock') {
        $('.inj-non-pharmacy').show();
    } else {
        $('.inj-non-pharmacy').hide();
    }

    // Clear any previously selected items when switching source to avoid mixed payloads
    $('#injection-selected-body').empty();
    updateInjectionTotals();
    $('#injection-stock-error').remove();
}

function loadInjectionPrescriptions(force = false) {
    if (injectionPrescriptionsLoaded && !force) {
        return $.Deferred().resolve(injectionPrescriptions).promise();
    }
    if (!currentPatient) return $.Deferred().resolve([]).promise();

    const url = medicationChartPrescribedRoute.replace(':patient', currentPatient);
    return $.ajax({ url: url, type: 'GET' })
        .then(function(res) {
            if (res && res.success) {
                injectionPrescriptions = res.prescriptions || [];
                injectionPrescriptionsLoaded = true;
                populateInjectionRxSelect();
            }
            return injectionPrescriptions;
        })
        .catch(function(err) {
            console.error('Failed to load prescriptions for injections', err);
            return [];
        });
}

function populateInjectionRxSelect() {
    const select = $('#injection-rx-select');
    if (!select.length) return;

    select.empty();
    const dispensed = (injectionPrescriptions || []).filter(p => p.is_dispensed);

    if (dispensed.length === 0) {
        select.append('<option value="">No dispensed prescriptions</option>');
        return;
    }

    select.append('<option value="">-- Select dispensed prescription --</option>');
    dispensed.forEach(function(rx) {
        const label = `${rx.product_name || 'Drug'} (${rx.product_code || ''}) from ${rx.dispensed_from_store || 'Pharmacy'}`;
        select.append(`<option value="${rx.id}" data-product-id="${rx.product_id || ''}" data-remaining="${rx.remaining_doses ?? ''}">${label}</option>`);
    });
}

function addInjectionRxToTable() {
    const rxId = $('#injection-rx-select').val();
    if (!rxId) {
        showNotification('warning', 'Select a dispensed prescription first');
        return;
    }
    const rx = (injectionPrescriptions || []).find(p => p.id == rxId);
    if (!rx || !rx.is_dispensed) {
        showNotification('warning', 'Only dispensed prescriptions can be charted');
        return;
    }

    // Prevent duplicates
    if ($(`#injection-selected-body tr[data-product-request-id="${rx.id}"]`).length > 0) {
        showNotification('info', 'Prescription already added');
        return;
    }

    const price = parseFloat(rx.payable_amount || rx.claims_amount || 0) || 0;
    const coverage = rx.coverage_mode || 'cash';
    const coverageInfo = coverage && coverage !== 'cash'
        ? `<span class="badge bg-info">${coverage.toUpperCase()}</span>`
        : '<span class="badge bg-secondary">Cash</span>';

    const remaining = rx.remaining_doses ?? null;
    const remainText = remaining !== null ? `<div class="small text-muted">Remaining: ${remaining}</div>` : '';

    const row = `
        <tr data-product-id="${rx.product_id}" data-product-request-id="${rx.id}" data-price="${price}" data-source="pharmacy_dispensed">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${rx.product_name || 'Drug'}</strong><br>
                <small class="text-muted">[${rx.product_code || ''}]</small>
                ${remainText}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty" value="1" min="1" style="width: 60px;" readonly>
            </td>
            <td class="batch-cell text-muted">N/A</td>
            <td class="stock-cell text-muted">N/A</td>
            <td>â‚¦${price.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm" name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>`;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    $('#injection-rx-summary').text(`${rx.product_name || 'Drug'} added`).show();
}

// Drug source toggle
$(document).on('click', '[data-inj-source]', function() {
    const source = $(this).data('inj-source');
    setInjectionDrugSource(source);
});

$('#injection-add-rx').on('click', function() {
    addInjectionRxToTable();
});

// Default selection
setInjectionDrugSource('pharmacy_dispensed');

// Injection Drug Search (uses same endpoint as prescription form)
let injectionSearchTimeout;
$('#injection-drug-search').on('input', function() {
    const query = $(this).val();
    clearTimeout(injectionSearchTimeout);

    if (query.length < 2) {
        $('#injection-drug-results').hide();
        return;
    }

    injectionSearchTimeout = setTimeout(function() {
        $.ajax({
            url: "{{ url('live-search-products') }}",
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: currentPatient },
            success: function(data) {
                $('#injection-drug-results').html('');

                if (data.length === 0) {
                    $('#injection-drug-results').html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                    const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || 'cash';

                    const coverageBadge = mode && mode !== 'cash'
                        ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: Î“Ã©Âª${payable}</span> <span class='text-success ms-1'>Claim: Î“Ã©Âª${claims}</span>`
                        : '';

                    const qtyClass = qty > 0 ? 'text-success' : 'text-danger';

                    const mk = `<li class='list-group-item list-group-item-action' style="cursor: pointer;"
                               data-id="${item.id}"
                               data-name="${name}"
                               data-code="${code}"
                               data-qty="${qty}"
                               data-price="${price}"
                               data-payable="${payable}"
                               data-claims="${claims}"
                               data-mode="${mode}"
                               data-category="${category}"
                               onclick="addInjectionDrug(this)">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <strong>${name}</strong> <small class="text-muted">[${code}]</small>
                                       <div class="small text-muted">${category}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="${qtyClass}"><strong>${qty}</strong> avail.</div>
                                       <div>Î“Ã©Âª${price}</div>
                                   </div>
                               </div>
                               ${coverageBadge ? `<div class="small mt-1">${coverageBadge}</div>` : ''}
                           </li>`;
                    $('#injection-drug-results').append(mk);
                });
                $('#injection-drug-results').show();
            },
            error: function(xhr) {
                console.error('Product search failed', xhr);
                $('#injection-drug-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// Hide dropdown when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#injection-drug-search, #injection-drug-results').length) {
        $('#injection-drug-results').hide();
    }
    if (!$(e.target).closest('#vaccine-drug-search, #vaccine-drug-results').length) {
        $('#vaccine-drug-results').hide();
    }
});

// Add selected drug to injection table
function addInjectionDrug(element) {
    const $el = $(element);
    const id = $el.data('id');
    const name = $el.data('name');
    const code = $el.data('code');
    const qty = $el.data('qty');
    const price = parseFloat($el.data('price')) || 0;
    const payable = parseFloat($el.data('payable')) || price;
    const claims = parseFloat($el.data('claims')) || 0;
    const mode = $el.data('mode') || 'cash';

    const drugSource = $('#injection-drug-source').val();

    // Check if store is selected first
    const storeId = $('#injection-store').val();
    if (drugSource === 'ward_stock' && !storeId) {
        showNotification('warning', 'Please select a ward store first');
        $('#injection-store').focus();
        return;
    }

    // Check if already added
    if ($(`#injection-selected-body tr[data-product-id="${id}"]`).length > 0) {
        showNotification('warning', 'This drug is already in the list');
        $('#injection-drug-results').hide();
        $('#injection-drug-search').val('');
        return;
    }

    const coverageInfo = mode && mode !== 'cash'
        ? `<span class="badge bg-info">${mode.toUpperCase()}</span><br><small class="text-danger">â‚¦${payable}</small>`
        : '<span class="badge bg-secondary">Cash</span>';

    const row = `
        <tr data-product-id="${id}" data-price="${payable}" data-source="${drugSource}">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${name}</strong><br>
                <small class="text-muted">[${code}]</small>
                <input type="hidden" name="injection_products[]" value="${id}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty"
                       name="injection_qty[]" value="1" min="1" style="width: 60px;">
            </td>
            <td class="batch-cell">${drugSource === 'ward_stock' ? '<div class="batch-loading"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div><select class="form-control form-control-sm batch-select-dropdown d-none" name="injection_batch_id[]"><option value="">Auto (FIFO)</option></select><input type="hidden" name="injection_selected_batch_id[]" value="">' : '<span class="text-muted">N/A</span>'}</td>
            <td class="stock-cell">${drugSource === 'ward_stock' ? '<span class="text-muted"><i class="mdi mdi-loading mdi-spin"></i></span>' : '<span class="text-muted">N/A</span>'}</td>
            <td>â‚¦${payable.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    // Fetch and populate batch dropdown for this product if ward stock
    if (drugSource === 'ward_stock') {
        fetchAndPopulateBatchDropdown(id, storeId, `#injection-selected-body tr[data-product-id="${id}"]`);
    }

    $('#injection-drug-results').hide();
    $('#injection-drug-search').val('');
}

// Remove row from injection table
function removeInjectionRow(btn) {
    $(btn).closest('tr').remove();
    updateInjectionTotals();
}

// Â§7.2: Insert a virtual row for patient's own drug (no hospital product_id)
function addPatientOwnInjectionRow() {
    const drugName  = $('#inj-external-name').val()?.trim();
    const qty       = $('#inj-external-qty').val();
    const batch     = $('#inj-external-batch').val()?.trim() || '';
    const expiry    = $('#inj-external-expiry').val() || '';
    const note      = $('#inj-external-note').val()?.trim() || '';

    if (!drugName) {
        showNotification('warning', 'Enter the drug name');
        $('#inj-external-name').focus();
        return;
    }
    if (!qty || parseFloat(qty) <= 0) {
        showNotification('warning', 'Enter a valid quantity');
        $('#inj-external-qty').focus();
        return;
    }

    // Prevent duplicate virtual rows with same drug name
    const duplicate = $('#injection-selected-body tr[data-source="patient_own"]').filter(function() {
        return $(this).find('td:eq(1) strong').text().toLowerCase() === drugName.toLowerCase();
    });
    if (duplicate.length > 0) {
        showNotification('warning', 'This drug is already in the list');
        return;
    }

    const uid = 'po_' + Date.now(); // virtual row identifier

    const row = `
        <tr data-source="patient_own" data-virtual-id="${uid}" data-price="0"
            data-ext-name="${drugName}" data-ext-qty="${qty}"
            data-ext-batch="${batch}" data-ext-expiry="${expiry}" data-ext-note="${note}">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${drugName}</strong><br>
                <span class="badge bg-purple text-white" style="background:#9c27b0;">Patient's Own</span>
                ${batch ? `<small class="text-muted ms-1">Batch: ${batch}</small>` : ''}
                ${expiry ? `<small class="text-muted ms-1">Exp: ${expiry}</small>` : ''}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty"
                       name="injection_qty[]" value="${qty}" min="0.01" step="0.01" style="width: 60px;">
            </td>
            <td><span class="text-muted">N/A</span></td>
            <td><span class="text-muted">N/A</span></td>
            <td><span class="text-muted">â€”</span></td>
            <td><span class="badge bg-secondary">No Billing</span></td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    // Clear the input fields so nurse can add another if needed
    $('#inj-external-name').val('').focus();
    $('#inj-external-qty').val('');
    $('#inj-external-batch').val('');
    $('#inj-external-expiry').val('');
    $('#inj-external-note').val('');

    showNotification('success', `"${drugName}" added to list`);
}

// Update injection totals
function updateInjectionTotals() {
    let total = 0;
    $('#injection-selected-body tr').each(function() {
        const price = parseFloat($(this).data('price')) || 0;
        const qty = parseInt($(this).find('.injection-qty').val()) || 1;
        total += price * qty;
    });
    $('#injection-total-price').html(`<strong>â‚¦${total.toFixed(2)}</strong>`);
}

// ===========================================
// BATCH SELECTION HELPER FUNCTIONS
// ===========================================

/**
 * Fetch batches from server and populate dropdown
 * @param {int} productId - Product ID
 * @param {int} storeId - Store ID
 * @param {string} rowSelector - Selector for the table row
 */
function fetchAndPopulateBatchDropdown(productId, storeId, rowSelector) {
    const $row = $(rowSelector);
    const $batchCell = $row.find('.batch-cell');
    const $batchLoading = $batchCell.find('.batch-loading');
    const $batchSelect = $batchCell.find('.batch-select-dropdown');
    const $stockCell = $row.find('.stock-cell');

    $.ajax({
        url: '{{ route("nursing-workbench.product-batches") }}',
        method: 'GET',
        data: { product_id: productId, store_id: storeId },
        success: function(response) {
            $batchLoading.addClass('d-none');

            if (response.success && response.batches.length > 0) {
                // Build dropdown options
                let options = '<option value="">Auto (FIFO)</option>';
                response.batches.forEach((batch, index) => {
                    const isFirst = index === 0;
                    const expiryClass = batch.is_expired ? 'batch-option-expired' :
                                       batch.is_expiring_soon ? 'batch-option-expiring' : '';
                    const expiryText = batch.expiry_formatted ? ` | Exp: ${batch.expiry_formatted}` : '';
                    const fifoLabel = isFirst ? ' â˜… FIFO' : '';

                    options += `<option value="${batch.id}" class="${expiryClass}"
                                data-expiry="${batch.expiry_date || ''}"
                                data-qty="${batch.current_qty}">
                        ${batch.batch_number} (${batch.current_qty} avail)${expiryText}${fifoLabel}
                    </option>`;
                });

                $batchSelect.html(options).removeClass('d-none');

                // Update stock cell
                const totalQty = response.total_available;
                const reqQty = parseInt($row.find('.injection-qty').val()) || 1;
                const stockClass = totalQty >= reqQty ? 'text-success' : 'text-danger';
                const stockIcon = totalQty >= reqQty ? 'mdi-check-circle' : 'mdi-alert-circle';
                $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${totalQty}</span>`);

                // Store batches data for later reference
                $row.data('batches', response.batches);
            } else {
                // No batches available
                $batchSelect.html('<option value="">No batches available</option>').removeClass('d-none');
                $stockCell.html('<span class="text-danger"><i class="mdi mdi-alert-circle"></i> 0</span>');
            }
        },
        error: function() {
            $batchLoading.addClass('d-none');
            $batchSelect.html('<option value="">Error loading batches</option>').removeClass('d-none');
            $stockCell.html('<span class="text-warning"><i class="mdi mdi-help-circle"></i> ?</span>');
        }
    });
}

/**
 * Fetch batches for a single product and populate a standalone dropdown
 * @param {int} productId - Product ID
 * @param {int} storeId - Store ID
 * @param {string} selectId - ID of the select element to populate
 * @param {function} callback - Optional callback with batch data
 */
function fetchProductBatchesForSelect(productId, storeId, selectId, callback) {
    const $select = $(selectId);
    $select.html('<option value="">Loading batches...</option>').prop('disabled', true);

    $.ajax({
        url: '{{ route("nursing-workbench.product-batches") }}',
        method: 'GET',
        data: { product_id: productId, store_id: storeId },
        success: function(response) {
            $select.prop('disabled', false);

            if (response.success && response.batches.length > 0) {
                let options = '<option value="">Auto (FIFO) - Recommended</option>';

                response.batches.forEach((batch, index) => {
                    const isFirst = index === 0;
                    const expiryClass = batch.is_expired ? 'batch-option-expired' :
                                       batch.is_expiring_soon ? 'batch-option-expiring' : '';
                    const expiryText = batch.expiry_formatted ? ` | Exp: ${batch.expiry_formatted}` : '';
                    const fifoLabel = isFirst ? ' â˜…' : '';

                    options += `<option value="${batch.id}" class="${expiryClass}"
                                data-expiry="${batch.expiry_date || ''}"
                                data-qty="${batch.current_qty}"
                                data-batch-number="${batch.batch_number}">
                        ${batch.batch_number} (${batch.current_qty} avail)${expiryText}${fifoLabel}
                    </option>`;
                });

                $select.html(options);

                if (callback) callback(response);
            } else {
                $select.html('<option value="">No batches in this store</option>');
                if (callback) callback({ success: false, batches: [], total_available: 0 });
            }
        },
        error: function() {
            $select.prop('disabled', false);
            $select.html('<option value="">Error loading batches</option>');
            if (callback) callback({ success: false, error: true });
        }
    });
}

/**
 * Build batch info display for FIFO mode
 */
function buildBatchFifoDisplay(batch) {
    if (!batch) return '<span class="text-muted">No batch available</span>';

    const expiryText = batch.expiry_formatted ? `<span class="batch-expiry">Exp: ${batch.expiry_formatted}</span>` : '';

    return `
        <div class="batch-info-display">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="batch-fifo-badge"><i class="mdi mdi-sort-clock-ascending"></i> FIFO</span>
                    <span class="batch-number ml-2">${batch.batch_number}</span>
                </div>
                <span class="batch-qty">${batch.current_qty}</span>
            </div>
            ${expiryText ? `<div class="mt-1 small">${expiryText}</div>` : ''}
            <button type="button" class="batch-manual-select-btn mt-1" onclick="$(this).closest('.batch-cell').find('.batch-select-dropdown').removeClass('d-none'); $(this).closest('.batch-info-display').addClass('d-none');">
                <i class="mdi mdi-pencil"></i> Change Batch
            </button>
        </div>
    `;
}

/**
 * Handle batch select change - update expiry field if linked
 */
$(document).on('change', '.batch-select-dropdown, #consumable-batch-select, #modal-vaccine-batch-select', function() {
    const $selected = $(this).find(':selected');
    const expiryDate = $selected.data('expiry');
    const batchNumber = $selected.data('batch-number');

    // Update linked expiry field if exists
    const $form = $(this).closest('form, .modal-body, .card-body');
    const $expiryField = $form.find('input[type="date"][id*="expiry"]');
    if ($expiryField.length && expiryDate) {
        $expiryField.val(expiryDate);
    }

    // Store selected batch ID in hidden field if exists
    const $hiddenField = $(this).siblings('input[type="hidden"]');
    if ($hiddenField.length) {
        $hiddenField.val($(this).val());
    }
});

// Recalculate on qty change and clear validation
$(document).on('change', '.injection-qty', function() {
    updateInjectionTotals();

    // Re-fetch batches when quantity changes to show stock status
    const $row = $(this).closest('tr');
    const productId = $row.data('product-id');
    const storeId = $('#injection-store').val();
    const reqQty = parseInt($(this).val()) || 1;

    // Update stock display based on stored batches
    const batches = $row.data('batches');
    if (batches) {
        const totalQty = batches.reduce((sum, b) => sum + b.current_qty, 0);
        const $stockCell = $row.find('.stock-cell');
        const stockClass = totalQty >= reqQty ? 'text-success' : 'text-danger';
        const stockIcon = totalQty >= reqQty ? 'mdi-check-circle' : 'mdi-alert-circle';
        $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${totalQty}</span>`);
    }

    $(this).removeClass('is-invalid');
    $(this).siblings('.validation-error').remove();
});

// Clear validation on dose input change
$(document).on('input', 'input[name="injection_dose[]"]', function() {
    $(this).removeClass('is-invalid');
    $(this).siblings('.validation-error').remove();
});

// ===========================================
// STORE STOCK DISPLAY HELPERS
// ===========================================

// Fetch product stock by store
function fetchProductStockByStore(productId, callback) {
    $.ajax({
        url: `/pharmacy-workbench/product/${productId}/stock`,
        method: 'GET',
        success: function(response) {
            callback(response);
        },
        error: function() {
            callback({ global_stock: 0, stores: [] });
        }
    });
}

// Update injection stock display when store changes
$('#injection-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#injection-store-placeholder').hide();
        $('#injection-store-info').show();
        updateInjectionStockDisplay();
    } else {
        $('#injection-store-info').hide();
        $('#injection-store-placeholder').show();
    }
});

// Update injection table stock display
function updateInjectionStockDisplay() {
    const storeId = $('#injection-store').val();
    if (!storeId) return;

    let stockSummaryHtml = '';
    const rows = $('#injection-selected-body tr');

    if (rows.length === 0) {
        stockSummaryHtml = '<p class="text-muted mb-0">Add drugs to see stock</p>';
    } else {
        rows.each(function() {
            const productId = $(this).data('product-id');
            const productName = $(this).find('td:eq(1) strong').text();
            const qty = parseInt($(this).find('.injection-qty').val()) || 1;
            const $stockCell = $(this).find('.stock-cell');

            fetchProductStockByStore(productId, function(stockData) {
                const storeStock = stockData.stores.find(s => s.store_id == storeId);
                const availableQty = storeStock ? storeStock.quantity : 0;
                const stockClass = availableQty >= qty ? 'text-success' : 'text-danger';
                const stockIcon = availableQty >= qty ? 'mdi-check-circle' : 'mdi-alert-circle';

                $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${availableQty}</span>`);
            });
        });
    }

    $('#injection-store-stock-summary').html(stockSummaryHtml || '<p class="text-muted mb-0">Stock shown in table</p>');
}

// Consumable store change handler
$('#consumable-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#consumable-store-placeholder').hide();
        $('#consumable-store-info').show();
        updateConsumableStockDisplay();
    } else {
        $('#consumable-store-info').hide();
        $('#consumable-store-placeholder').show();
    }
});

// Update consumable stock display
function updateConsumableStockDisplay() {
    const storeId = $('#consumable-store').val();
    const productId = $('#consumable-id').val();

    if (!storeId || !productId) {
        $('#consumable-store-stock-summary').html('<p class="text-muted mb-0">Select a product to see stock</p>');
        return;
    }

    fetchProductStockByStore(productId, function(stockData) {
        const storeStock = stockData.stores.find(s => s.store_id == storeId);
        const availableQty = storeStock ? storeStock.quantity : 0;
        const qty = parseInt($('#consumable-quantity').val()) || 1;
        const stockClass = availableQty >= qty ? 'text-success' : 'text-danger';
        const stockIcon = availableQty >= qty ? 'mdi-check-circle' : 'mdi-alert-circle';

        let html = `<div class="${stockClass}"><i class="mdi ${stockIcon}"></i> Available: <strong>${availableQty}</strong></div>`;
        if (availableQty < qty) {
            html += `<div class="text-danger small"><i class="mdi mdi-alert"></i> Insufficient stock!</div>`;
        }

        $('#consumable-store-stock-summary').html(html);
        $('#consumable-stock-info').html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> Stock: ${availableQty}</span>`);
    });
}

// Immunization modal store change handler
$('#modal-vaccine-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#modal-vaccine-store-placeholder').hide();
        $('#modal-vaccine-store-info').show();
        updateImmunizationStockDisplay();
    } else {
        $('#modal-vaccine-store-info').hide();
        $('#modal-vaccine-store-placeholder').show();
    }
});

// Update immunization stock display
function updateImmunizationStockDisplay() {
    const storeId = $('#modal-vaccine-store').val();
    const productId = $('#modal-product-id').val();

    if (!storeId || !productId) {
        $('#modal-vaccine-store-stock').html('<p class="text-muted mb-0">Select a vaccine to see stock</p>');
        return;
    }

    fetchProductStockByStore(productId, function(stockData) {
        const storeStock = stockData.stores.find(s => s.store_id == storeId);
        const availableQty = storeStock ? storeStock.quantity : 0;
        const stockClass = availableQty > 0 ? 'text-success' : 'text-danger';
        const stockIcon = availableQty > 0 ? 'mdi-check-circle' : 'mdi-alert-circle';

        let html = `<div class="${stockClass}"><i class="mdi ${stockIcon}"></i> Available: <strong>${availableQty}</strong></div>`;
        if (availableQty <= 0) {
            html += `<div class="text-danger small"><i class="mdi mdi-alert"></i> Out of stock!</div>`;
        }

        $('#modal-vaccine-store-stock').html(html);
        $('#modal-selected-product-stock').html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> Stock in selected store: ${availableQty}</span>`);
    });
}

// Update consumable quantity change
$('#consumable-quantity').on('change', function() {
    updateConsumableStockDisplay();
});

// ===========================================
// STOCK VALIDATION HELPERS
// ===========================================

// Check stock availability before submission - returns Promise
function validateStockAvailability(storeId, products) {
    return new Promise((resolve, reject) => {
        const stockChecks = products.map(p => {
            return new Promise((res) => {
                fetchProductStockByStore(p.product_id, function(stockData) {
                    const storeStock = stockData.stores.find(s => s.store_id == storeId);
                    const availableQty = storeStock ? storeStock.quantity : 0;
                    res({
                        product_id: p.product_id,
                        product_name: p.product_name || 'Product',
                        requested_qty: parseInt(p.qty) || 1,
                        available_qty: availableQty,
                        sufficient: availableQty >= (parseInt(p.qty) || 1)
                    });
                });
            });
        });

        Promise.all(stockChecks).then(results => {
            const insufficientItems = results.filter(r => !r.sufficient);
            if (insufficientItems.length > 0) {
                reject({
                    type: 'insufficient_stock',
                    items: insufficientItems,
                    message: insufficientItems.map(i =>
                        `${i.product_name}: Need ${i.requested_qty}, only ${i.available_qty} available`
                    ).join('\n')
                });
            } else {
                resolve(results);
            }
        });
    });
}

// Show stock validation error with visual feedback
function showStockValidationError(items, tableSelector) {
    let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    errorHtml += '<strong><i class="mdi mdi-alert-circle"></i> Insufficient Stock!</strong><br>';
    errorHtml += '<ul class="mb-0 pl-3">';

    items.forEach(item => {
        errorHtml += `<li>${item.product_name || 'Item'}: Requested <strong>${item.requested_qty}</strong>, Available <strong>${item.available_qty}</strong></li>`;

        // Highlight the row in the table
        if (tableSelector) {
            const $row = $(`${tableSelector} tr[data-product-id="${item.product_id}"]`);
            $row.addClass('table-danger').find('.stock-cell').addClass('text-danger fw-bold');
            $row.find('.injection-qty, .vaccine-qty').addClass('is-invalid');
        }
    });

    errorHtml += '</ul>';
    errorHtml += '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    errorHtml += '</div>';

    return errorHtml;
}

// Injection Form Submit â€” Â§7.3 rewrite: 3-path source model
$('#injection-form').on('submit', function(e) {
    e.preventDefault();

    const drugSource = $('#injection-drug-source').val();
    const storeId = $('#injection-store').val();
    const billPatient = drugSource === 'ward_stock' ? ($('#injection-bill-patient').is(':checked') ? 1 : 0) : 0;

    // Ward stock requires store
    if (drugSource === 'ward_stock' && !storeId) {
        showNotification('error', 'Please select a store to dispense from');
        $('#injection-store').focus();
        return;
    }

    // Collect selected products/virtual rows
    const products = [];
    $('#injection-selected-body tr').each(function() {
        if (!$(this).find('.injection-row-check').is(':checked')) return;

        const rowSource = $(this).data('source') || drugSource;
        const batchId = $(this).find('.batch-select-dropdown').val() || null;
        const productRequestId = $(this).data('product-request-id') || null;

        if (rowSource === 'patient_own') {
            // Â§7.2: Virtual row â€” read external data from data attributes
            products.push({
                product_id: null, // no hospital product for patient's own
                product_name: $(this).find('td:eq(1) strong').text(),
                qty: $(this).find('.injection-qty').val(),
                dose: $(this).find('input[name="injection_dose[]"]').val(),
                batch_id: null,
                product_request_id: null,
                external_drug_name: $(this).data('ext-name') || $(this).find('td:eq(1) strong').text(),
                external_qty: $(this).data('ext-qty') || $(this).find('.injection-qty').val(),
                external_batch_number: $(this).data('ext-batch') || null,
                external_expiry_date: $(this).data('ext-expiry') || null,
                external_source_note: $(this).data('ext-note') || null,
            });
        } else {
            // pharmacy_dispensed or ward_stock â€” real hospital product
            products.push({
                product_id: $(this).data('product-id'),
                product_name: $(this).find('td:eq(1) strong').text(),
                qty: $(this).find('.injection-qty').val(),
                dose: $(this).find('input[name="injection_dose[]"]').val(),
                batch_id: batchId,
                product_request_id: productRequestId,
            });
        }
    });

    if (products.length === 0) {
        if (drugSource === 'patient_own') {
            showNotification('error', "Click 'Add Drug to List' first, then submit");
        } else {
            showNotification('error', 'Please select at least one drug');
        }
        return;
    }

    // Pharmacy dispensed must have product_request_id
    if (drugSource === 'pharmacy_dispensed') {
        const missingRx = products.some(p => !p.product_request_id);
        if (missingRx) {
            showNotification('error', 'Select from dispensed prescriptions before administering');
            return;
        }
    }

    // Dose is required for all rows
    const missingDose = products.some(p => !p.dose || !p.dose.trim());
    if (missingDose) {
        showNotification('warning', 'Enter a dose for every drug in the list');
        return;
    }

    // Clear previous validation errors
    $('#injection-selected-body .is-invalid').removeClass('is-invalid');
    $('#injection-selected-body .validation-error').remove();
    $('#injection-selected-body tr').removeClass('table-danger');
    $('#injection-stock-error').remove();

    // Validate stock before submission (ward_stock only)
    const $submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Checking Stock...');

    const stockPromise = drugSource === 'ward_stock'
        ? validateStockAvailability(storeId, products.filter(p => p.product_id))
        : Promise.resolve();

    stockPromise
        .then(() => {
            $submitBtn.html('<i class="mdi mdi-loading mdi-spin"></i> Administering...');

            const data = {
                patient_id: currentPatient,
                drug_source: drugSource,
                bill_patient: billPatient,
                products: products.map(p => ({
                    product_id: p.product_id || null,
                    qty: p.qty,
                    dose: p.dose,
                    batch_id: p.batch_id || null,
                    product_request_id: p.product_request_id || null,
                    external_drug_name: p.external_drug_name || null,
                    external_qty: p.external_qty || null,
                    external_batch_number: p.external_batch_number || null,
                    external_expiry_date: p.external_expiry_date || null,
                    external_source_note: p.external_source_note || null,
                })),
                route: $('#injection-route').val(),
                site: $('#injection-site').val(),
                administered_at: $('#injection-time').val(),
                notes: $('#injection-notes').val(),
                store_id: drugSource === 'ward_stock' ? storeId : null
            };

            $.ajax({
                url: '{{ route("nursing-workbench.injection.administer") }}',
                method: 'POST',
                data: data,
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                success: function(response) {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    showNotification('success', response.message || 'Injection administered successfully');
                    $('#injection-form')[0].reset();
                    $('#injection-selected-body').empty();
                    setInjectionDrugSource('pharmacy_dispensed');
                    updateInjectionTotals();
                    loadInjectionHistory(currentPatient);
                },
                error: function(xhr) {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    const response = xhr.responseJSON;
                    handleInjectionSubmitError(response, products);
                }
            });
        })
        .catch(stockError => {
            $submitBtn.prop('disabled', false).html(originalBtnHtml);

            const errorHtml = showStockValidationError(stockError.items, '#injection-selected-body');
            $('#injection-selected-drugs').before(`<div id="injection-stock-error">${errorHtml}</div>`);

            showNotification('error', 'Insufficient stock for one or more items');
        });
});

// Handle injection submit error (separated for cleaner code)
function handleInjectionSubmitError(response, products) {
    const checkedRows = $('#injection-selected-body tr').filter(function() {
        return $(this).find('.injection-row-check').is(':checked');
    });

    if (response?.errors) {
        let errorMessages = [];

        Object.keys(response.errors).forEach(function(field) {
            // Parse field like "products.0.dose" or "products.1.qty"
            const match = field.match(/^products\.(\d+)\.(\w+)$/);
            if (match) {
                const index = parseInt(match[1]);
                const fieldName = match[2];
                const row = checkedRows.eq(index);

                if (row.length) {
                    const productName = row.find('td:eq(1) strong').text();
                    let inputField;

                    if (fieldName === 'dose') {
                        inputField = row.find('input[name="injection_dose[]"]');
                    } else if (fieldName === 'qty') {
                        inputField = row.find('.injection-qty');
                    }

                    if (inputField && inputField.length) {
                        inputField.addClass('is-invalid');
                        const errorMsg = response.errors[field][0].replace(/products\.\d+\./, '');
                        inputField.after(`<div class="validation-error text-danger small">${errorMsg}</div>`);
                    }

                    errorMessages.push(`${productName}: ${response.errors[field][0].replace(/products\.\d+\./, '')}`);
                }
            } else {
                errorMessages.push(response.errors[field][0]);
            }
        });

        if (errorMessages.length > 0) {
            showNotification('error', 'Validation failed: ' + errorMessages.join(', '));
        } else {
            showNotification('error', response.message || 'Validation failed');
        }
    } else {
        showNotification('error', response?.message || 'Failed to administer injection');
    }
}

// Load Injection History with DataTable
function loadInjectionHistory(patientId) {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#injection-history-table')) {
        $('#injection-history-table').DataTable().destroy();
    }

    $('#injection-history-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: `/nursing-workbench/patient/${patientId}/injections`,
            dataSrc: ''
        },
        columns: [
            { data: 'administered_at' },
            {
                data: 'product_name',
                render: function(data, type, row) {
                    let name = data || 'N/A';
                    if (row.drug_source === 'patient_own') {
                        let tip = 'Patient\'s Own Drug';
                        if (row.external_qty) tip += ' | Qty: ' + row.external_qty;
                        if (row.external_batch_number) tip += ' | Batch: ' + row.external_batch_number;
                        if (row.external_expiry_date) tip += ' | Exp: ' + row.external_expiry_date;
                        if (row.external_source_note) tip += ' | Note: ' + row.external_source_note;
                        name = '<span title="' + tip + '">' + name + '</span> <span class="badge badge-warning badge-sm">Patient\'s Own</span>';
                    } else if (row.drug_source === 'ward_stock') {
                        name += ' <span class="badge badge-info badge-sm">Ward Stock</span>';
                    }
                    return name;
                }
            },
            { data: 'dose' },
            { data: 'route' },
            { data: 'site' },
            { data: 'administered_by' }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            emptyTable: "No injection history found"
        }
    });
}

// ========================================
// IMMUNIZATION MODULE - Vaccine Search & Administration
// ========================================

// Vaccine/Drug Search (uses same endpoint as prescription form)
let vaccineSearchTimeout;
$('#vaccine-drug-search').on('input', function() {
    const query = $(this).val();
    clearTimeout(vaccineSearchTimeout);

    if (query.length < 2) {
        $('#vaccine-drug-results').hide();
        return;
    }

    vaccineSearchTimeout = setTimeout(function() {
        $.ajax({
            url: "{{ url('live-search-products') }}",
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: currentPatient },
            success: function(data) {
                $('#vaccine-drug-results').html('');

                if (data.length === 0) {
                    $('#vaccine-drug-results').html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                    const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || 'cash';

                    const coverageBadge = mode && mode !== 'cash'
                        ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: Î“Ã©Âª${payable}</span> <span class='text-success ms-1'>Claim: Î“Ã©Âª${claims}</span>`
                        : '';

                    const qtyClass = qty > 0 ? 'text-success' : 'text-danger';

                    const mk = `<li class='list-group-item list-group-item-action' style="cursor: pointer;"
                               data-id="${item.id}"
                               data-name="${name}"
                               data-code="${code}"
                               data-qty="${qty}"
                               data-price="${price}"
                               data-payable="${payable}"
                               data-claims="${claims}"
                               data-mode="${mode}"
                               data-category="${category}"
                               onclick="addVaccineDrug(this)">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <strong>${name}</strong> <small class="text-muted">[${code}]</small>
                                       <div class="small text-muted">${category}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="${qtyClass}"><strong>${qty}</strong> avail.</div>
                                       <div>Î“Ã©Âª${price}</div>
                                   </div>
                               </div>
                               ${coverageBadge ? `<div class="small mt-1">${coverageBadge}</div>` : ''}
                           </li>`;
                    $('#vaccine-drug-results').append(mk);
                });
                $('#vaccine-drug-results').show();
            },
            error: function(xhr) {
                console.error('Product search failed', xhr);
                $('#vaccine-drug-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// Add selected vaccine to table
function addVaccineDrug(element) {
    const $el = $(element);
    const id = $el.data('id');
    const name = $el.data('name');
    const code = $el.data('code');
    const qty = $el.data('qty');
    const price = parseFloat($el.data('price')) || 0;
    const payable = parseFloat($el.data('payable')) || price;
    const claims = parseFloat($el.data('claims')) || 0;
    const mode = $el.data('mode') || 'cash';

    // Check if already added
    if ($(`#vaccine-selected-body tr[data-product-id="${id}"]`).length > 0) {
        showNotification('warning', 'This vaccine is already in the list');
        $('#vaccine-drug-results').hide();
        $('#vaccine-drug-search').val('');
        return;
    }

    const coverageInfo = mode && mode !== 'cash'
        ? `<span class="badge bg-info">${mode.toUpperCase()}</span><br><small class="text-danger">Pay: Î“Ã©Âª${payable}</small><br><small class="text-success">Claim: Î“Ã©Âª${claims}</small>`
        : '<span class="badge bg-secondary">Cash</span>';

    const row = `
        <tr data-product-id="${id}" data-price="${payable}">
            <td><input type="checkbox" class="form-check-input vaccine-row-check" checked></td>
            <td>
                <strong>${name}</strong><br>
                <small class="text-muted">[${code}]</small>
                <input type="hidden" name="vaccine_products[]" value="${id}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm vaccine-qty"
                       name="vaccine_qty[]" value="1" min="1" max="${qty}" style="width: 70px;">
                <small class="text-muted">${qty} avail.</small>
            </td>
            <td>Î“Ã©Âª${payable.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="vaccine_dose[]" placeholder="Dose amount">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeVaccineRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#vaccine-selected-body').append(row);
    updateVaccineTotals();
    $('#vaccine-drug-results').hide();
    $('#vaccine-drug-search').val('');
}

// Remove row from vaccine table
function removeVaccineRow(btn) {
    $(btn).closest('tr').remove();
    updateVaccineTotals();
}

// Update vaccine totals
function updateVaccineTotals() {
    let total = 0;
    $('#vaccine-selected-body tr').each(function() {
        const price = parseFloat($(this).data('price')) || 0;
        const qty = parseInt($(this).find('.vaccine-qty').val()) || 1;
        total += price * qty;
    });
    $('#vaccine-total-price').html(`<strong>Î“Ã©Âª${total.toFixed(2)}</strong>`);
}

// Recalculate on qty change
$(document).on('change', '.vaccine-qty', function() {
    updateVaccineTotals();
});

// Immunization Form Submit
$('#immunization-form').on('submit', function(e) {
    e.preventDefault();

    // Collect selected products
    const products = [];
    $('#vaccine-selected-body tr').each(function() {
        if ($(this).find('.vaccine-row-check').is(':checked')) {
            products.push({
                product_id: $(this).data('product-id'),
                qty: $(this).find('.vaccine-qty').val(),
                dose: $(this).find('input[name="vaccine_dose[]"]').val()
            });
        }
    });

    if (products.length === 0) {
        showNotification('error', 'Please select at least one vaccine');
        return;
    }

    const data = {
        patient_id: currentPatient,
        products: products,
        dose_number: $('#vaccine-dose-number').val(),
        routine: $('#vaccine-routine').val(),
        site: $('#vaccine-site').val(),
        administered_at: $('#vaccine-time').val(),
        batch_number: $('#vaccine-batch').val(),
        expiry_date: $('#vaccine-expiry').val(),
        notes: $('#vaccine-notes').val()
    };

    $.ajax({
        url: '{{ route("nursing-workbench.immunization.administer") }}',
        method: 'POST',
        data: data,
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        success: function(response) {
            showNotification('success', response.message || 'Vaccine administered successfully');
            $('#immunization-form')[0].reset();
            $('#vaccine-selected-body').empty();
            updateVaccineTotals();
            loadImmunizationHistory(currentPatient);
            loadImmunizationSchedule(currentPatient);
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to administer vaccine');
        }
    });
});

// Load Immunization Schedule (New Schedule System)
function loadImmunizationSchedule(patientId) {
    $('#immunization-schedule-container').html(`
        <div class="text-center py-4">
            <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
            <p class="text-muted mt-2">Loading immunization schedule...</p>
        </div>
    `);

    $.ajax({
        url: `/nursing-workbench/patient/${patientId}/schedule`,
        method: 'GET',
        success: function(response) {
            if (!response.success) {
                showScheduleError(response.message || 'Failed to load schedule');
                return;
            }

            if (!response.has_schedule) {
                showNoSchedule(patientId, response.patient);
                return;
            }

            renderScheduleTimeline(response);
        },
        error: function(xhr) {
            if (xhr.status === 404) {
                showNoSchedule(patientId, null);
            } else {
                showScheduleError('Failed to load immunization schedule');
            }
        }
    });
}

// Show error message in schedule container
function showScheduleError(message) {
    $('#immunization-schedule-container').html(`
        <div class="alert alert-danger">
            <i class="mdi mdi-alert-circle"></i> ${message}
        </div>
    `);
}

// Show no schedule message with option to generate
function showNoSchedule(patientId, patient) {
    let patientInfo = patient ? `<p class="mb-2">Patient: <strong>${patient.name}</strong> (Age: ${patient.age})</p>` : '';

    // Update active schedules display
    $('#patient-active-schedules').html(`
        <div class="mb-2">
            <strong>Active Schedules:</strong> <span class="text-muted">None - Add a schedule using the selector above</span>
        </div>
    `);

    $('#immunization-schedule-container').html(`
        <div class="text-center py-4">
            <i class="mdi mdi-calendar-plus mdi-48px text-muted"></i>
            <p class="text-muted mt-2">No immunization schedule found for this patient.</p>
            ${patientInfo}
            <p class="text-muted">Select a schedule template above and click "Add Schedule" to generate a vaccination schedule.</p>
        </div>
    `);
}

// Generate immunization schedule for patient
function generatePatientSchedule(patientId, templateId = null) {
    $('#immunization-schedule-container').html(`
        <div class="text-center py-4">
            <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
            <p class="text-muted mt-2">Generating schedule...</p>
        </div>
    `);

    const data = {};
    if (templateId) {
        data.template_id = templateId;
    }

    $.ajax({
        url: `/nursing-workbench/patient/${patientId}/generate-schedule`,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: data,
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                loadImmunizationSchedule(patientId);
                // Reset template selector
                $('#schedule-template-select').val('');
            } else {
                showScheduleError(response.message);
            }
        },
        error: function(xhr) {
            showScheduleError(xhr.responseJSON?.message || 'Failed to generate schedule');
        }
    });
}

// Handle generate schedule button click
$('#btn-generate-schedule').click(function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    generatePatientSchedule(currentPatient);
});

// Render schedule timeline
function renderScheduleTimeline(response) {
    const { patient, schedule, stats, active_templates } = response;

    // Update active schedules display
    let templateBadges = '';
    if (active_templates && active_templates.length > 0) {
        templateBadges = active_templates.map(t =>
            `<span class="badge badge-primary mr-1"><i class="mdi mdi-calendar-check"></i> ${t.name}</span>`
        ).join('');
    }
    $('#patient-active-schedules').html(`
        <div class="mb-2">
            <strong>Active Schedules:</strong> ${templateBadges || '<span class="text-muted">None</span>'}
        </div>
    `);

    let html = `
        <!-- Patient Info & Stats -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <i class="mdi mdi-account-circle mdi-36px text-primary"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">${patient.name}</h6>
                        <small class="text-muted">DOB: ${patient.dob || 'N/A'} | Age: ${patient.age}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end gap-2">
                    <span class="badge badge-success" title="Administered">
                        <i class="mdi mdi-check"></i> ${stats.administered}
                    </span>
                    <span class="badge badge-warning" title="Pending">
                        <i class="mdi mdi-clock"></i> ${stats.pending}
                    </span>
                    <span class="badge badge-danger" title="Overdue">
                        <i class="mdi mdi-alert"></i> ${stats.overdue}
                    </span>
                    <span class="badge badge-info" title="Skipped">
                        <i class="mdi mdi-skip-next"></i> ${stats.skipped}
                    </span>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="schedule-timeline">`;

    schedule.forEach((ageGroup, index) => {
        const isFirst = index === 0;
        const allAdministered = ageGroup.vaccines.every(v => v.status === 'administered');
        const hasOverdue = ageGroup.vaccines.some(v => v.status === 'overdue');
        const hasDue = ageGroup.vaccines.some(v => v.status === 'due');

        let ageHeaderClass = 'bg-light';
        let ageIcon = 'mdi-clock-outline';
        if (allAdministered) {
            ageHeaderClass = 'bg-success text-white';
            ageIcon = 'mdi-check-all';
        } else if (hasOverdue) {
            ageHeaderClass = 'bg-danger text-white';
            ageIcon = 'mdi-alert-circle';
        } else if (hasDue) {
            ageHeaderClass = 'bg-warning text-dark';
            ageIcon = 'mdi-bell';
        }

        html += `
            <div class="card-modern mb-2 schedule-age-group" data-age="${ageGroup.age_days}">
                <div class="card-header py-2 ${ageHeaderClass}" style="cursor: pointer;"
                     onclick="$(this).next('.card-body').slideToggle(200)">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <i class="mdi ${ageIcon} mr-1"></i>
                            <strong>${ageGroup.age_display}</strong>
                        </span>
                        <span>
                            ${ageGroup.vaccines.length} vaccine${ageGroup.vaccines.length > 1 ? 's' : ''}
                            <i class="mdi mdi-chevron-down ml-1"></i>
                        </span>
                    </div>
                </div>
                <div class="card-body py-2" ${!isFirst && allAdministered ? 'style="display:none;"' : ''}>
                    <div class="row">`;

        ageGroup.vaccines.forEach(vaccine => {
            let statusBadge = '';
            let actionButton = '';

            switch(vaccine.status) {
                case 'pending':
                    statusBadge = '<span class="badge badge-secondary"><i class="mdi mdi-clock-outline"></i> Pending</span>';
                    break;
                case 'due':
                    statusBadge = '<span class="badge badge-warning"><i class="mdi mdi-bell"></i> Due Now</span>';
                    actionButton = `<button class="btn btn-sm btn-success mt-1" onclick="openScheduleAdministerModal(${vaccine.id})">
                        <i class="mdi mdi-needle"></i> Administer
                    </button>`;
                    break;
                case 'overdue':
                    statusBadge = '<span class="badge badge-danger"><i class="mdi mdi-alert-circle"></i> Overdue</span>';
                    actionButton = `<button class="btn btn-sm btn-danger mt-1" onclick="openScheduleAdministerModal(${vaccine.id})">
                        <i class="mdi mdi-needle"></i> Administer Now
                    </button>`;
                    break;
                case 'administered':
                    statusBadge = '<span class="badge badge-success"><i class="mdi mdi-check"></i> Done</span>';
                    break;
                case 'skipped':
                    statusBadge = '<span class="badge badge-info"><i class="mdi mdi-skip-next"></i> Skipped</span>';
                    break;
                case 'contraindicated':
                    statusBadge = '<span class="badge badge-dark"><i class="mdi mdi-cancel"></i> Contraindicated</span>';
                    break;
            }

            // Add skip/contraindicate options for non-administered vaccines
            let optionsMenu = '';
            if (!['administered', 'skipped', 'contraindicated'].includes(vaccine.status)) {
                optionsMenu = `
                    <div class="dropdown d-inline">
                        <button class="btn btn-sm btn-link text-muted p-0 ml-1" type="button" data-toggle="dropdown">
                            <i class="mdi mdi-dots-vertical"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" onclick="skipScheduleVaccine(${vaccine.id}); return false;">
                                <i class="mdi mdi-skip-next"></i> Skip
                            </a>
                            <a class="dropdown-item" href="#" onclick="contraindicateScheduleVaccine(${vaccine.id}); return false;">
                                <i class="mdi mdi-cancel"></i> Contraindicated
                            </a>
                        </div>
                    </div>`;
            }

            html += `
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="card-modern h-100 ${vaccine.status === 'overdue' ? 'border-danger' : vaccine.status === 'due' ? 'border-warning' : ''}">
                        <div class="card-body py-2 px-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>${vaccine.dose_label || vaccine.vaccine_name}</strong>
                                    ${optionsMenu}
                                    <br>
                                    <small class="text-muted">${vaccine.vaccine_code || ''}</small>
                                </div>
                                ${statusBadge}
                            </div>
                            <hr class="my-1">
                            <small class="text-muted d-block">
                                <i class="mdi mdi-calendar"></i> Due: ${vaccine.due_date_formatted}
                            </small>
                            ${vaccine.route ? `<small class="text-muted d-block"><i class="mdi mdi-needle"></i> ${vaccine.route} - ${vaccine.site || 'N/A'}</small>` : ''}
                            ${vaccine.administered_date ? `<small class="text-success d-block"><i class="mdi mdi-check"></i> Given: ${vaccine.administered_date}</small>` : ''}
                            ${vaccine.skip_reason ? `<small class="text-info d-block"><i class="mdi mdi-information"></i> ${vaccine.skip_reason}</small>` : ''}
                            ${actionButton}
                        </div>
                    </div>
                </div>`;
        });

        html += `
                    </div>
                </div>
            </div>`;
    });

    html += `
        </div>`;

    $('#immunization-schedule-container').html(html);
}

// Skip a scheduled vaccine
function skipScheduleVaccine(scheduleId) {
    Swal.fire({
        title: 'Skip Vaccine',
        input: 'textarea',
        inputLabel: 'Please provide a reason for skipping this vaccine:',
        inputPlaceholder: 'Enter reason...',
        inputAttributes: {
            'aria-label': 'Reason'
        },
        showCancelButton: true,
        confirmButtonText: 'Skip',
        confirmButtonColor: '#17a2b8',
        inputValidator: (value) => {
            if (!value) {
                return 'Please provide a reason';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/nursing-workbench/schedule/${scheduleId}/status`,
                method: 'PUT',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { status: 'skipped', reason: result.value },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Vaccine marked as skipped');
                        loadImmunizationSchedule(currentPatient);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to update status');
                }
            });
        }
    });
}

// Mark vaccine as contraindicated
function contraindicateScheduleVaccine(scheduleId) {
    Swal.fire({
        title: 'Contraindication',
        input: 'textarea',
        inputLabel: 'Please provide the contraindication reason:',
        inputPlaceholder: 'e.g., Allergic reaction, Medical condition...',
        inputAttributes: {
            'aria-label': 'Reason'
        },
        showCancelButton: true,
        confirmButtonText: 'Mark Contraindicated',
        confirmButtonColor: '#343a40',
        inputValidator: (value) => {
            if (!value) {
                return 'Please provide a reason';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/nursing-workbench/schedule/${scheduleId}/status`,
                method: 'PUT',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { status: 'contraindicated', reason: result.value },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Vaccine marked as contraindicated');
                        loadImmunizationSchedule(currentPatient);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to update status');
                }
            });
        }
    });
}

// Store current schedule item for administration
let currentScheduleItem = null;
let modalSelectedProduct = null;

// Load vaccine schedule templates for selection
function loadScheduleTemplates() {
    $.ajax({
        url: '/nursing-workbench/schedule-templates',
        method: 'GET',
        success: function(response) {
            let options = '<option value="">Select Schedule Template...</option>';
            response.templates.forEach(template => {
                const defaultBadge = template.is_default ? ' (Default)' : '';
                options += `<option value="${template.id}">${template.name}${defaultBadge}</option>`;
            });
            $('#schedule-template-select').html(options);
        }
    });
}

// Initialize on page load
$(document).ready(function() {
    loadScheduleTemplates();

    // Load history timeline when history tab is shown
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if ($(e.target).attr('href') === '#immunization-history' && currentPatient) {
            loadImmunizationTimeline(currentPatient);
        }
    });
});

// Add schedule to patient
$('#btn-add-schedule').click(function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    const templateId = $('#schedule-template-select').val();
    if (!templateId) {
        toastr.warning('Please select a schedule template');
        return;
    }

    generatePatientSchedule(currentPatient, templateId);
});

// Open administer modal from schedule
function openScheduleAdministerModal(scheduleId) {
    // Reset modal state
    resetAdministerModal();

    // Fetch the schedule item details
    $.ajax({
        url: `/nursing-workbench/patient/${currentPatient}/schedule`,
        method: 'GET',
        success: function(response) {
            // Find the specific schedule item
            let found = null;
            response.schedule.forEach(ageGroup => {
                ageGroup.vaccines.forEach(vaccine => {
                    if (vaccine.id === scheduleId) {
                        found = vaccine;
                    }
                });
            });

            if (!found) {
                toastr.error('Schedule item not found');
                return;
            }

            currentScheduleItem = found;

            // Populate modal info
            $('#modal-vaccine-name').text(found.vaccine_name);
            $('#modal-dose-label').text(found.dose_label || 'Dose ' + found.dose_number);
            $('#modal-due-date').text(found.due_date_formatted);
            $('#modal-schedule-id').val(scheduleId);

            // Pre-fill route and site if available from schedule item
            if (found.route) {
                $('#modal-vaccine-route').val(found.route);
            }
            if (found.site) {
                $('#modal-vaccine-site').val(found.site);
            }

            // Set current date/time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            $('#modal-vaccine-time').val(now.toISOString().slice(0, 16));

            // If there's a mapped product, pre-fill it
            if (found.product) {
                selectModalProduct(found.product);
            }

            // Show the modal
            $('#administerVaccineModal').modal('show');
        },
        error: function(xhr) {
            toastr.error('Failed to load schedule item details');
        }
    });
}

// Reset administer modal
function resetAdministerModal() {
    currentScheduleItem = null;
    modalSelectedProduct = null;
    $('#modal-immunization-form')[0].reset();
    $('#modal-schedule-id').val('');
    $('#modal-product-id').val('');
    $('#modal-vaccine-name').text('-');
    $('#modal-dose-label').text('-');
    $('#modal-due-date').text('-');
    $('#modal-vaccine-search').val('');
    $('#modal-selected-product-card').addClass('d-none');
}

// Select product in modal
function selectModalProduct(element) {
    // Check if store is selected first
    const storeId = $('#modal-vaccine-store').val();
    if (!storeId) {
        showNotification('warning', 'Please select a store first');
        $('#modal-vaccine-store').focus();
        return;
    }

    const product = {
        id: $(element).data('id'),
        name: $(element).data('name'),
        code: $(element).data('code'),
        qty: $(element).data('qty'),
        price: $(element).data('price'),
        payable: $(element).data('payable'),
        claims: $(element).data('claims'),
        mode: $(element).data('mode'),
        category: $(element).data('category')
    };

    modalSelectedProduct = product;
    $('#modal-product-id').val(product.id);
    $('#modal-selected-product-name').text(product.name);
    $('#modal-selected-product-details').html(`
        <span class="mr-2">[${product.code}]</span>
        <span class="mr-2">${product.category}</span>
    `);

    // Show price with HMO info if applicable
    let priceHtml = `Î“Ã©Âª${parseFloat(product.price).toLocaleString()}`;
    if (product.mode && product.mode !== 'cash') {
        priceHtml = `
            <span class="badge badge-info mr-1">${product.mode.toUpperCase()}</span>
            <span class="text-danger">Pay: Î“Ã©Âª${parseFloat(product.payable).toLocaleString()}</span>
            <span class="text-success ml-1">Claim: Î“Ã©Âª${parseFloat(product.claims).toLocaleString()}</span>
        `;
    }
    $('#modal-selected-product-price').html(priceHtml);

    $('#modal-selected-product-card').removeClass('d-none');
    $('#modal-vaccine-search').val('');
    $('#modal-vaccine-results').hide();

    // Update stock display for selected product
    updateImmunizationStockDisplay();

    // Fetch and populate batch dropdown for this vaccine
    // storeId already declared above
    if (storeId) {
        fetchProductBatchesForSelect(product.id, storeId, '#modal-vaccine-batch-select', function(response) {
            if (response.success && response.batches && response.batches.length > 0) {
                // Show batch help text
                $('#modal-vaccine-batch-help').html(`
                    <span class="text-success"><i class="mdi mdi-check-circle"></i> ${response.total_available} available in ${response.batches.length} batch(es)</span>
                `);

                // Auto-select FIFO batch's expiry date
                const fifoBatch = response.batches[0];
                if (fifoBatch && fifoBatch.expiry_date) {
                    $('#modal-vaccine-expiry').val(fifoBatch.expiry_date);
                }
            } else {
                $('#modal-vaccine-batch-help').html('<span class="text-danger"><i class="mdi mdi-alert"></i> No batches available in this store</span>');
            }
        });
    } else {
        $('#modal-vaccine-batch-select').html('<option value="">-- Select store first --</option>');
        $('#modal-vaccine-batch-help').text('Select store first to see batches');
    }
}

// Remove selected product
$('#modal-remove-product').click(function() {
    modalSelectedProduct = null;
    $('#modal-product-id').val('');
    $('#modal-selected-product-card').addClass('d-none');
});

// Product search in modal - uses same endpoint as injections for HMO pricing
let modalSearchTimeout;
$('#modal-vaccine-search').on('input', function() {
    const term = $(this).val();
    clearTimeout(modalSearchTimeout);

    if (term.length < 2) {
        $('#modal-vaccine-results').hide();
        return;
    }

    modalSearchTimeout = setTimeout(function() {
        $.ajax({
            url: "{{ url('live-search-products') }}",
            method: 'GET',
            dataType: 'json',
            data: { term: term, patient_id: currentPatient },
            success: function(data) {
                $('#modal-vaccine-results').html('');

                if (data.length === 0) {
                    $('#modal-vaccine-results').html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                    const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || 'cash';

                    const coverageBadge = mode && mode !== 'cash'
                        ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: Î“Ã©Âª${payable}</span> <span class='text-success ms-1'>Claim: Î“Ã©Âª${claims}</span>`
                        : '';

                    const qtyClass = qty > 0 ? 'text-success' : 'text-danger';

                    const mk = `<li class='list-group-item list-group-item-action' style="cursor: pointer;"
                               data-id="${item.id}"
                               data-name="${name}"
                               data-code="${code}"
                               data-qty="${qty}"
                               data-price="${price}"
                               data-payable="${payable}"
                               data-claims="${claims}"
                               data-mode="${mode}"
                               data-category="${category}"
                               onclick="selectModalProduct(this)">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <strong>${name}</strong> <small class="text-muted">[${code}]</small>
                                       <div class="small text-muted">${category}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="${qtyClass}"><strong>${qty}</strong> avail.</div>
                                       <div>Î“Ã©Âª${price}</div>
                                   </div>
                               </div>
                               ${coverageBadge ? `<div class="small mt-1">${coverageBadge}</div>` : ''}
                           </li>`;
                    $('#modal-vaccine-results').append(mk);
                });
                $('#modal-vaccine-results').show();
            },
            error: function(xhr) {
                console.error('Product search failed', xhr);
                $('#modal-vaccine-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// Hide search results when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#modal-vaccine-search, #modal-vaccine-results').length) {
        $('#modal-vaccine-results').hide();
    }
});

// Submit immunization from modal
$('#modal-submit-immunization').click(function() {
    // Validate fields
    if (!$('#modal-product-id').val()) {
        toastr.error('Please select a vaccine product');
        return;
    }
    if (!$('#modal-vaccine-site').val()) {
        toastr.error('Please select administration site');
        return;
    }
    if (!$('#modal-vaccine-route').val()) {
        toastr.error('Please select administration route');
        return;
    }
    if (!$('#modal-vaccine-time').val()) {
        toastr.error('Please enter administration time');
        return;
    }
    if (!$('#modal-vaccine-store').val()) {
        toastr.error('Please select a store to dispense from');
        $('#modal-vaccine-store').focus();
        return;
    }

    const productId = $('#modal-product-id').val();
    const productName = modalSelectedProduct?.name || $('#modal-selected-product-name').text();
    const storeId = $('#modal-vaccine-store').val();

    // Clear previous errors
    $('#modal-immunization-stock-error').remove();

    const $btn = $(this);
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Checking Stock...');

    // Validate stock before submission
    validateStockAvailability(storeId, [{
        product_id: productId,
        product_name: productName,
        qty: 1
    }])
    .then(() => {
        // Stock OK - proceed
        $btn.html('<i class="mdi mdi-loading mdi-spin"></i> Administering...');

        // Get batch info from dropdown
        const $batchSelect = $('#modal-vaccine-batch-select');
        const selectedBatchId = $batchSelect.val();
        const selectedBatchOption = $batchSelect.find(':selected');
        const batchNumber = selectedBatchOption.data('batch-number') || '';

        const data = {
            schedule_id: $('#modal-schedule-id').val(),
            product_id: productId,
            site: $('#modal-vaccine-site').val(),
            route: $('#modal-vaccine-route').val(),
            batch_id: selectedBatchId, // Send batch ID for StockService
            batch_number: batchNumber || selectedBatchOption.text().split(' ')[0], // Fallback to text
            expiry_date: $('#modal-vaccine-expiry').val(),
            administered_at: $('#modal-vaccine-time').val(),
            manufacturer: $('#modal-vaccine-manufacturer').val(),
            vis_date: $('#modal-vaccine-vis').val(),
            notes: $('#modal-vaccine-notes').val(),
            store_id: storeId
        };

        $.ajax({
            url: '/nursing-workbench/administer-from-schedule',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: data,
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                if (response.success) {
                    toastr.success(response.message || 'Vaccine administered successfully');
                    $('#administerVaccineModal').modal('hide');
                    loadImmunizationSchedule(currentPatient);
                    loadImmunizationHistory(currentPatient);
                } else {
                    toastr.error(response.message || 'Failed to administer vaccine');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                toastr.error(xhr.responseJSON?.message || 'Failed to administer vaccine');
            }
        });
    })
    .catch(stockError => {
        $btn.prop('disabled', false).html(originalBtnHtml);

        const errorHtml = `<div id="modal-immunization-stock-error" class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><i class="mdi mdi-alert-circle"></i> Insufficient Stock!</strong><br>
            ${stockError.items[0].product_name}: Only <strong>${stockError.items[0].available_qty}</strong> available in selected store
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>`;

        $('#modal-immunization-form').prepend(errorHtml);
        toastr.error('Insufficient stock for selected vaccine');
    });
});

// History view toggle - using direct ID selectors for the buttons
$('#view-timeline-btn, #view-calendar-btn, #view-table-btn').on('click', function() {
    const view = $(this).data('view');

    // Update button states
    $('#view-timeline-btn, #view-calendar-btn, #view-table-btn').removeClass('active');
    $(this).addClass('active');

    // Show/hide views
    $('.history-view').addClass('d-none');
    $(`#history-${view}-view`).removeClass('d-none');

    // Load the appropriate view if patient is selected
    if (currentPatient) {
        if (view === 'timeline') {
            loadImmunizationTimeline(currentPatient);
        } else if (view === 'calendar') {
            loadImmunizationCalendar(currentPatient);
        } else if (view === 'table') {
            loadImmunizationHistoryTable(currentPatient);
        }
    }
});

// Load immunization timeline view
function loadImmunizationTimeline(patientId) {
    $('#history-timeline-view').html(`
        <div class="text-center py-4">
            <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
            <p class="text-muted mt-2">Loading timeline...</p>
        </div>
    `);

    $.ajax({
        url: `/nursing-workbench/patient/${patientId}/immunization-history`,
        method: 'GET',
        success: function(response) {
            if (!response.records || response.records.length === 0) {
                $('#history-timeline-view').html(`
                    <div class="text-center py-4">
                        <i class="mdi mdi-calendar-blank mdi-48px text-muted"></i>
                        <p class="text-muted mt-2">No immunization records found</p>
                    </div>
                `);
                return;
            }

            let html = '<div class="timeline-container" style="position: relative; padding-left: 30px; border-left: 3px solid #dee2e6;">';

            response.records.forEach((record, index) => {
                const statusClass = 'success';
                html += `
                    <div class="timeline-item mb-3" style="position: relative;">
                        <div class="timeline-marker" style="position: absolute; left: -40px; width: 20px; height: 20px; border-radius: 50%; background: var(--${statusClass}); border: 3px solid white; box-shadow: 0 0 0 3px var(--${statusClass});"></div>
                        <div class="card-modern">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${record.vaccine_name}</strong>
                                        <span class="badge badge-success ml-2">${record.dose_number || 'N/A'}</span>
                                        <br>
                                        <small class="text-muted">
                                            <i class="mdi mdi-calendar"></i> ${record.administered_date}
                                            ${record.site ? `| <i class="mdi mdi-map-marker"></i> ${record.site}` : ''}
                                            ${record.batch_number ? `| <i class="mdi mdi-barcode"></i> ${record.batch_number}` : ''}
                                        </small>
                                    </div>
                                    <small class="text-muted">${record.administered_by || ''}</small>
                                </div>
                                ${record.notes ? `<small class="text-muted d-block mt-1"><i class="mdi mdi-note"></i> ${record.notes}</small>` : ''}
                            </div>
                        </div>
                    </div>`;
            });

            html += '</div>';
            $('#history-timeline-view').html(html);
        },
        error: function() {
            $('#history-timeline-view').html(`
                <div class="alert alert-danger">
                    <i class="mdi mdi-alert-circle"></i> Failed to load timeline
                </div>
            `);
        }
    });
}

// Load immunization calendar view
function loadImmunizationCalendar(patientId) {
    // Simple calendar view - could be enhanced with a full calendar library
    $('#history-calendar-view').html(`
        <div class="text-center py-4">
            <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
            <p class="text-muted mt-2">Loading calendar...</p>
        </div>
    `);

    $.ajax({
        url: `/nursing-workbench/patient/${patientId}/immunization-history`,
        method: 'GET',
        success: function(response) {
            if (!response.records || response.records.length === 0) {
                $('#history-calendar-view').html(`
                    <div class="text-center py-4">
                        <i class="mdi mdi-calendar-blank mdi-48px text-muted"></i>
                        <p class="text-muted mt-2">No immunization records found</p>
                    </div>
                `);
                return;
            }

            // Group by month/year
            const grouped = {};
            response.records.forEach(record => {
                const date = new Date(record.administered_date);
                const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                if (!grouped[key]) {
                    grouped[key] = {
                        label: date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
                        records: []
                    };
                }
                grouped[key].records.push(record);
            });

            let html = '<div class="row">';
            Object.keys(grouped).sort().reverse().forEach(key => {
                const group = grouped[key];
                html += `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card-modern h-100">
                            <div class="card-header bg-primary text-white py-2">
                                <i class="mdi mdi-calendar-month"></i> ${group.label}
                            </div>
                            <div class="card-body py-2">
                                <ul class="list-unstyled mb-0">`;
                group.records.forEach(record => {
                    const day = new Date(record.administered_date).getDate();
                    html += `
                        <li class="mb-1">
                            <span class="badge badge-secondary mr-1">${day}</span>
                            <span>${record.vaccine_name}</span>
                            <small class="text-muted">(${record.dose_number || 'N/A'})</small>
                        </li>`;
                });
                html += `
                                </ul>
                            </div>
                        </div>
                    </div>`;
            });
            html += '</div>';

            $('#history-calendar-view').html(html);
        }
    });
}

// Load Immunization History Table View with DataTable
function loadImmunizationHistoryTable(patientId) {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#immunization-history-table')) {
        $('#immunization-history-table').DataTable().destroy();
    }

    $('#immunization-history-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: `/nursing-workbench/patient/${patientId}/immunization-history`,
            dataSrc: 'records'
        },
        columns: [
            {
                data: 'administered_date',
                render: function(data) {
                    return data || 'N/A';
                }
            },
            {
                data: 'vaccine_name',
                render: function(data) {
                    return data || 'N/A';
                }
            },
            {
                data: 'dose_number',
                render: function(data) {
                    return data ? `Dose ${data}` : 'N/A';
                }
            },
            {
                data: 'dose',
                defaultContent: 'N/A'
            },
            {
                data: 'batch_number',
                defaultContent: 'N/A'
            },
            {
                data: 'site',
                defaultContent: 'N/A'
            },
            {
                data: 'administered_by',
                defaultContent: 'N/A'
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            emptyTable: "No immunization records found",
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// Legacy function for backwards compatibility
function loadImmunizationHistory(patientId) {
    loadImmunizationHistoryTable(patientId);
}

// Service Billing Search
$('#service-search').on('input', function() {
    const query = $(this).val();
    if (query.length < 2) {
        $('#service-search-results').hide();
        return;
    }

    $.ajax({
        url: '{{ route("nursing-workbench.search-services") }}',
        method: 'GET',
        data: { term: query },
        success: function(results) {
            let html = '';
            results.forEach(service => {
                html += `<li class="list-group-item list-group-item-action" onclick="selectService(${service.id}, '${service.name.replace(/'/g, "\\'")}', '${service.price || 0}')">
                    <strong>${service.name}</strong><br>
                    <small class="text-muted">Î“Ã©Âª${service.price || 'N/A'}</small>
                </li>`;
            });
            $('#service-search-results').html(html).show();
        }
    });
});

function selectService(id, name, price) {
    $('#service-id').val(id);
    $('#service-search').val(name);
    $('#service-price').val('Î“Ã©Âª' + price);
    $('#service-search-results').hide();
}

// Consumable Search
$('#consumable-search').on('input', function() {
    const query = $(this).val();
    if (query.length < 2) {
        $('#consumable-search-results').hide();
        return;
    }

    $.ajax({
        url: '{{ route("nursing-workbench.search-products") }}',
        method: 'GET',
        data: { term: query },
        success: function(results) {
            let html = '';
            results.forEach(product => {
                const escapedName = (product.name || '').replace(/'/g, "\\'");
                const code = (product.code || '').replace(/'/g, "\\'");
                html += `<li class="list-group-item list-group-item-action" onclick="selectConsumable(${product.id}, '${escapedName}', ${product.price || 0}, '${code}')">
                    <strong>${product.name}</strong>
                    <span class="badge bg-secondary float-right">[${product.code || 'N/A'}]</span>
                    <br>
                    <small class="text-muted">Î“Ã©Âª${product.price || 'N/A'}/unit</small>
                </li>`;
            });
            $('#consumable-search-results').html(html).show();
        }
    });
});

function selectConsumable(id, name, unitPrice, code) {
    // Check if store is selected first
    const storeId = $('#consumable-store').val();
    if (!storeId) {
        showNotification('warning', 'Please select a store first');
        $('#consumable-store').focus();
        return;
    }

    $('#consumable-id').val(id);
    $('#consumable-search').val(name);
    updateConsumablePrice(unitPrice);
    $('#consumable-search-results').hide();

    // Show selected product info with stock
    $('#consumable-selected-name').text(name);
    $('#consumable-selected-code').text(`[${code || 'N/A'}]`);
    $('#consumable-selected-stock').show();
    updateConsumableStockDisplay();

    // Fetch and populate batch dropdown for this product
    fetchProductBatchesForSelect(id, storeId, '#consumable-batch-select', function(response) {
        if (response.success && response.total_available > 0) {
            $('#consumable-batch-info').show();
            const fifoBatch = response.fifo_recommended || response.batches[0];
            if (fifoBatch) {
                $('#consumable-batch-detail').html(`
                    <strong>FIFO Recommended:</strong> ${fifoBatch.batch_number}
                    (${fifoBatch.current_qty} avail)
                    ${fifoBatch.expiry_formatted ? `| Exp: ${fifoBatch.expiry_formatted}` : ''}
                `);
            }
        } else {
            $('#consumable-batch-info').hide();
        }
    });
}

// Update consumable price on quantity change
$('#consumable-quantity').on('input', function() {
    const id = $('#consumable-id').val();
    if (id) {
        // Recalculate based on stored unit price
        const quantity = $(this).val() || 1;
        // Get unit price from the consumable-price data attribute (set when selecting)
        const unitPrice = $(this).data('unit-price') || 0;
        updateConsumablePrice(unitPrice);
    }
});

function updateConsumablePrice(unitPrice) {
    const quantity = $('#consumable-quantity').val() || 1;
    const total = unitPrice * quantity;
    $('#consumable-price').val('Î“Ã©Âª' + total.toFixed(2));
    $('#consumable-quantity').data('unit-price', unitPrice);
}

// Service Billing Form Submit
$('#service-billing-form').on('submit', function(e) {
    e.preventDefault();

    const data = {
        patient_id: currentPatient,
        service_id: $('#service-id').val(),
        notes: $('#service-notes').val()
    };

    if (!data.service_id) {
        showNotification('error', 'Please select a service');
        return;
    }

    $.ajax({
        url: '{{ route("nursing-workbench.billing.add-service") }}',
        method: 'POST',
        data: data,
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        success: function(response) {
            showNotification('success', response.message || 'Service added successfully');
                if (billingHistoryLoaded) reloadBillingHistory();
            $('#service-billing-form')[0].reset();
            $('#service-id').val('');
            loadPendingBills(currentPatient);
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to add service');
        }
    });
});

// Consumable Billing Form Submit
$('#consumable-billing-form').on('submit', function(e) {
    e.preventDefault();

    const storeId = $('#consumable-store').val();
    if (!storeId) {
        showNotification('error', 'Please select a store to dispense from');
        $('#consumable-store').focus();
        return;
    }

    const productId = $('#consumable-id').val();
    const productName = $('#consumable-search').val();
    const quantity = parseInt($('#consumable-quantity').val()) || 1;

    if (!productId) {
        showNotification('error', 'Please select a consumable');
        return;
    }

    // Clear previous errors
    $('#consumable-stock-error').remove();
    $('#consumable-quantity').removeClass('is-invalid');

    const $submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Checking Stock...');

    // Validate stock before submission
    validateStockAvailability(storeId, [{
        product_id: productId,
        product_name: productName,
        qty: quantity
    }])
    .then(() => {
        // Stock OK - proceed
        $submitBtn.html('<i class="mdi mdi-loading mdi-spin"></i> Adding...');

        const data = {
            patient_id: currentPatient,
            product_id: productId,
            qty: quantity,
            store_id: storeId,
            batch_id: $('#consumable-batch-select').val() || null // Send selected batch ID
        };

        $.ajax({
            url: '{{ route("nursing-workbench.billing.add-consumable") }}',
            method: 'POST',
            data: data,
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(response) {
                $submitBtn.prop('disabled', false).html(originalBtnHtml);
                showNotification('success', response.message || 'Consumable added successfully');
                if (billingHistoryLoaded) reloadBillingHistory();
                $('#consumable-billing-form')[0].reset();
                $('#consumable-id').val('');
                $('#consumable-batch-select').html('<option value="">-- Select product first --</option>');
                $('#consumable-batch-info').hide();
                $('#consumable-store-stock-summary').html('<p class="text-muted mb-0">Select a product to see stock</p>');
                loadPendingBills(currentPatient);
            },
            error: function(xhr) {
                $submitBtn.prop('disabled', false).html(originalBtnHtml);
                showNotification('error', xhr.responseJSON?.message || 'Failed to add consumable');
            }
        });
    })
    .catch(stockError => {
        $submitBtn.prop('disabled', false).html(originalBtnHtml);
        $('#consumable-quantity').addClass('is-invalid');

        const errorHtml = `<div id="consumable-stock-error" class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
            <strong><i class="mdi mdi-alert-circle"></i> Insufficient Stock!</strong><br>
            ${stockError.items[0].product_name}: Need <strong>${stockError.items[0].requested_qty}</strong>, only <strong>${stockError.items[0].available_qty}</strong> available
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>`;

        $('#consumable-billing-form').prepend(errorHtml);
        showNotification('error', 'Insufficient stock');
    });
});

// Load Pending Bills with DataTable
function loadPendingBills(patientId) {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#pending-bills-table')) {
        $('#pending-bills-table').DataTable().destroy();
    }

    $('#pending-bills-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: `/nursing-workbench/patient/${patientId}/pending-bills`,
            dataSrc: ''
        },
        columns: [
            { data: 'item_name' },
            {
                data: 'type',
                render: function(data) {
                    const badgeClass = data === 'service' ? 'badge-primary' : 'badge-info';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            { data: 'qty' },
            {
                data: 'payable_amount',
                render: function(data, type, row) {
                    let html = `Î“Ã©Âª${parseFloat(data || 0).toFixed(2)}`;
                    if (row.claims_amount > 0) {
                        html += `<br><small class="text-success">Claims: Î“Ã©Âª${parseFloat(row.claims_amount).toFixed(2)}</small>`;
                    }
                    return html;
                }
            },
            { data: 'added_by' },
            { data: 'created_at' },
            {
                data: null,
                render: function(data) {
                    if (data.can_delete) {
                        return `<button class="btn btn-sm btn-danger" onclick="removeBillItem(${data.id})"><i class="fa fa-trash"></i></button>`;
                    }
                    return '<span class="text-muted">-</span>';
                }
            }
        ],
        order: [[5, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            emptyTable: "No pending bills"
        }
    });
}

function removeBillItem(id) {
    if (!confirm('Are you sure you want to remove this bill item?')) return;

    $.ajax({
        url: `/nursing-workbench/remove-bill/${id}`,
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        success: function(response) {
            showNotification('success', response.message || 'Item removed');
            loadPendingBills(currentPatient);
            // Refresh billing history if loaded
            if (billingHistoryLoaded) reloadBillingHistory();
        },
        error: function(xhr) {
            var msg = 'Failed to remove item';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            showNotification('error', msg);
        }
    });
}

// =====================================
// BILLING HISTORY (Service Requests)
// =====================================
let billingHistoryTable = null;
let billingHistoryLoaded = false;

function initBillingHistory(patientId) {
    if (!patientId) return;

    // Set default date range: current month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    $('#bh-date-from').val(firstDay.toISOString().split('T')[0]);
    $('#bh-date-to').val(lastDay.toISOString().split('T')[0]);

    loadBillingHistoryTable(patientId);
    loadBillingHistoryStats(patientId);
    billingHistoryLoaded = true;
}

function loadBillingHistoryTable(patientId) {
    if (billingHistoryTable) {
        billingHistoryTable.destroy();
        billingHistoryTable = null;
    }

    billingHistoryTable = $('#billing-history-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: `/nursing-workbench/patient/${patientId}/service-requests`,
            data: function(d) {
                d.date_from = $('#bh-date-from').val();
                d.date_to = $('#bh-date-to').val();
                d.type_filter = $('#bh-type-filter').val();
                d.billing_filter = $('#bh-billing-filter').val();
                d.delivery_filter = $('#bh-delivery-filter').val();
            }
        },
        columns: [
            { data: 'date_formatted', name: 'date_formatted' },
            { data: 'request_no', name: 'request_no' },
            { data: 'type_badge', name: 'type_badge' },
            { data: 'name', name: 'name' },
            { data: 'price_formatted', name: 'price_formatted', className: 'text-right' },
            { data: 'hmo_covers_formatted', name: 'hmo_covers_formatted', className: 'text-right text-success' },
            { data: 'payable_formatted', name: 'payable_formatted', className: 'text-right text-primary font-weight-bold' },
            { data: 'billing_badge', name: 'billing_badge' },
            { data: 'delivery_badge', name: 'delivery_badge' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        language: {
            emptyTable: 'No service requests found',
            processing: '<i class="mdi mdi-loading mdi-spin mdi-24px"></i>'
        },
        drawCallback: function() {
            loadBillingHistoryStats(currentPatient);
        }
    });
}

function loadBillingHistoryStats(patientId) {
    if (!patientId) return;
    $.ajax({
        url: `/nursing-workbench/patient/${patientId}/service-requests-stats`,
        data: {
            date_from: $('#bh-date-from').val(),
            date_to: $('#bh-date-to').val()
        },
        success: function(res) {
            if (res.success) {
                $('#bh-total-requests').text(res.stats.total_requests);
                $('#bh-hmo-covered').text(res.stats.hmo_covered);
                $('#bh-patient-payable').text(res.stats.patient_payable);
                $('#bh-completed-count').text(res.stats.completed);
            }
        }
    });
}

function reloadBillingHistory() {
    if (billingHistoryTable) {
        billingHistoryTable.ajax.reload();
    }
}

// Filter form submit
$(document).on('submit', '#bh-filter-form', function(e) {
    e.preventDefault();
    reloadBillingHistory();
});

// Clear filters
$(document).on('click', '#bh-clear-filters', function() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    $('#bh-date-from').val(firstDay.toISOString().split('T')[0]);
    $('#bh-date-to').val(lastDay.toISOString().split('T')[0]);
    $('#bh-type-filter').val('');
    $('#bh-billing-filter').val('');
    $('#bh-delivery-filter').val('');
    reloadBillingHistory();
});

// Lazy-load billing history when sub-tab is shown
$(document).on('shown.bs.tab', '#billing-history-tab', function() {
    if (!billingHistoryLoaded && currentPatient) {
        initBillingHistory(currentPatient);
    }
});

// View request details from billing history
$(document).on('click', '#billing-history .view-request-btn', function() {
    const type = $(this).data('type');
    const id = $(this).data('id');
    showBillingRequestDetails(type, id);
});

function showBillingRequestDetails(type, id) {
    // Build a simple detail view in a SweetAlert or Bootstrap modal
    const typeColors = { lab: '#17a2b8', imaging: '#ffc107', product: '#28a745' };
    const typeLabels = { lab: 'Lab Test', imaging: 'Imaging', product: 'Product/Drug' };

    Swal.fire({
        title: `<i class="mdi mdi-eye"></i> ${typeLabels[type] || 'Request'} Details`,
        html: '<div class="text-center"><i class="mdi mdi-loading mdi-spin mdi-36px"></i><br>Loading...</div>',
        showConfirmButton: true,
        confirmButtonText: 'Close',
        width: '500px',
        didOpen: () => {
            // Fetch details â€” use the same data from the table row if available
            const tableData = billingHistoryTable ? billingHistoryTable.rows().data().toArray() : [];
            const row = tableData.find(r => r.id == id && r.type === type);

            if (row) {
                const hmoCoverage = row.hmo_covers > 0
                    ? `<tr><td class="text-muted">HMO Covers</td><td class="text-success font-weight-bold">â‚¦${parseFloat(row.hmo_covers).toFixed(2)}</td></tr>`
                    : '';
                const coverageMode = row.coverage_mode
                    ? `<tr><td class="text-muted">Coverage</td><td><span class="badge badge-info">${row.coverage_mode}</span></td></tr>`
                    : '';

                Swal.update({
                    html: `
                        <div class="text-left">
                            <div class="mb-3 p-2 rounded" style="background: ${typeColors[type]}15; border-left: 4px solid ${typeColors[type]};">
                                <strong style="color: ${typeColors[type]};">${row.request_no}</strong>
                                <span class="badge ml-2" style="background: ${typeColors[type]}; color: #fff;">${typeLabels[type]}</span>
                            </div>
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" style="width:40%;">Service/Item</td><td class="font-weight-bold">${row.name}</td></tr>
                                <tr><td class="text-muted">Price</td><td>â‚¦${parseFloat(row.price).toFixed(2)}</td></tr>
                                ${hmoCoverage}
                                <tr><td class="text-muted">Payable</td><td class="text-primary font-weight-bold">â‚¦${parseFloat(row.payable).toFixed(2)}</td></tr>
                                ${coverageMode}
                                <tr><td class="text-muted">Billing Status</td><td>${row.billing_badge}</td></tr>
                                <tr><td class="text-muted">Delivery Status</td><td>${row.delivery_badge}</td></tr>
                                <tr><td class="text-muted">Requested By</td><td>${row.requested_by || '-'}</td></tr>
                                <tr><td class="text-muted">Date</td><td>${row.date_formatted}</td></tr>
                            </table>
                        </div>
                    `
                });
            } else {
                Swal.update({ html: '<p class="text-muted">Details not available. Try refreshing the table.</p>' });
            }
        }
    });
}

// Load Note Types
// Functions for Notes
// Note Types loading removed as requested
// function loadNoteTypes() { ... }

// Nursing Note Form Submit
// Initialize CKEditor for nursing note
let nursingNoteEditor;

function initNursingNoteCKEditor() {
    if (document.querySelector('#nursing-note-editor') && !nursingNoteEditor) {
        ClassicEditor
            .create(document.querySelector('#nursing-note-editor'), {
                toolbar: {
                    items: [
                        'heading',
                        '|',
                        'bold',
                        'italic',
                        'bulletedList',
                        'numberedList',
                        '|',
                        'outdent',
                        'indent',
                        '|',
                        'blockQuote',
                        'insertTable',
                        'undo',
                        'redo'
                    ]
                }
            })
            .then(editor => {
                nursingNoteEditor = editor;
            })
            .catch(error => {
                console.error(error);
            });
    }
}

// Ensure editor is initialized when tab shown
$('a[data-toggle="tab"][href="#notes-tab"]').on('shown.bs.tab', function (e) {
    initNursingNoteCKEditor();
});
$('a[data-toggle="tab"][href="#notes-add"]').on('shown.bs.tab', function (e) {
    initNursingNoteCKEditor();
});

// Also try to init on page load after a brief delay
setTimeout(initNursingNoteCKEditor, 1000);

$('#nursing-note-form').on('submit', function(e) {
    e.preventDefault();

    // Get data from CKEditor
    const noteContent = nursingNoteEditor ? nursingNoteEditor.getData() : '';

    if (!noteContent.trim()) {
        showNotification('error', 'Please enter note content');
        return;
    }

    const data = {
        patient_id: currentPatient,
        note_type_id: 5, // Hardcoded for "Others" as requested
        note: noteContent
    };

    $.ajax({
        url: '{{ route("nursing-workbench.notes.store") }}',
        method: 'POST',
        data: data,
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        success: function(response) {
            showNotification('success', response.message || 'Note saved successfully');
            if (nursingNoteEditor) {
                nursingNoteEditor.setData('');
            }
            // Switch to history tab to see the new note
            $('#notes-history-tab-link').tab('show');
            loadNotesHistory(currentPatient);
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to save note');
        }
    });
});

// Load Notes History with Cards (DataTable)
function loadNotesHistory(patientId) {
    if (!patientId) return;

    if ($.fn.DataTable.isDataTable('#nursing-notes-table')) {
        $('#nursing-notes-table').DataTable().ajax.url(`/nursing-workbench/patient/${patientId}/nursing-notes`).load();
    } else {
        $('#nursing-notes-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: `/nursing-workbench/patient/${patientId}/nursing-notes`,
            columns: [
                { data: 'info', name: 'info', orderable: false, searchable: false }
            ],
            ordering: false,
            lengthChange: false,
            pageLength: 10,
            searching: false,
            dom: "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-5'i><'col-sm-7'p>>",
            language: {
                emptyTable: `<div class="text-center py-5">
                                <i class="mdi mdi-note-outline mdi-48px text-muted"></i>
                                <p class="text-muted mt-2">No nursing notes found</p>
                            </div>`,
                processing: `<div class="text-center">
                                <i class="mdi mdi-loading mdi-spin mdi-24px text-primary"></i>
                             </div>`
            }
        });
    }
}

// Initialize nursing-specific features on tab switch
function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $('.workspace-tab-content').removeClass('active');

    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');
    $(`#${tab}-tab`).addClass('active');

    // Load tab-specific content
    if (!currentPatient) return;

    switch(tab) {
        case 'overview':
            loadPatientOverview(currentPatient);
            break;
        case 'medication':
            // Initialize medication chart with current patient
            if (typeof initMedicationChart === 'function') {
                initMedicationChart(currentPatient);
            }
            break;
        case 'intake-output':
            // Initialize I/O chart with current patient
            if (typeof initIntakeOutputChart === 'function') {
                initIntakeOutputChart(currentPatient);
            }
            break;
        case 'injection':
            loadInjectionHistory(currentPatient);
            // Set current time
            $('#injection-time').val(new Date().toISOString().slice(0, 16));
            break;
        case 'immunization':
            loadImmunizationSchedule(currentPatient);
            loadImmunizationHistory(currentPatient);
            $('#vaccine-time').val(new Date().toISOString().slice(0, 16));
            break;
        case 'billing':
            loadPendingBills(currentPatient);
            break;
        case 'notes':
            // Removed loadNoteTypes call as it is no longer needed
            loadNotesHistory(currentPatient);
            break;
    }
}

// Notification helper
function showNotification(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const html = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>`;

    // Create a notification container if it doesn't exist
    if ($('#notification-container').length === 0) {
        $('body').append('<div id="notification-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999; width: 350px;"></div>');
    }

    $('#notification-container').append(html);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        $('#notification-container .alert').first().alert('close');
    }, 5000);
}

</script>

{{-- ================================================================ --}}
{{-- MEDICATION & I/O CHART SCRIPTS (Adapted from nurse_chart_scripts_enhanced) --}}
{{-- ================================================================ --}}
<script>
// Workbench-specific wrapper variables for medication and I/O charts
// These will be set dynamically when a patient is selected
var PATIENT_ID = null;
var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Route templates - will be used with actual patient ID
var medicationChartIndexRoute = "{{ route('nurse.medication.index', ['patient' => ':patient']) }}";
var medicationChartScheduleRoute = "{{ route('nurse.medication.schedule') }}";
var medicationChartAdministerRoute = "{{ route('nurse.medication.administer') }}";
var medicationChartDiscontinueRoute = "{{ route('nurse.medication.discontinue') }}";
var medicationChartResumeRoute = "{{ route('nurse.medication.resume') }}";
var medicationChartDeleteRoute = "{{ route('nurse.medication.delete') }}";
var medicationChartEditRoute = "{{ route('nurse.medication.edit') }}";
var medicationChartRemoveScheduleRoute = "{{ route('nurse.medication.remove_schedule') }}";
var medicationChartCalendarRoute = "{{ route('nurse.medication.calendar', ['patient' => ':patient', 'medication' => ':medication', 'start_date' => ':start_date']) }}";
var medicationChartPrescribedRoute = "{{ route('nurse.medication.prescribed_drugs', ['patient' => ':patient']) }}";
var medicationChartDismissRoute = "{{ route('nurse.medication.dismiss_prescription', ['patient' => ':patient']) }}";
var medicationChartAdministerDirectRoute = "{{ route('nurse.medication.administer_direct', ['patient' => ':patient']) }}";
var medicationChartDirectCalendarRoute = "{{ route('nurse.medication.direct_calendar', ['patient' => ':patient']) }}";
var medicationChartOverviewRoute = "{{ route('nurse.medication.overview', ['patient' => ':patient']) }}";

var intakeOutputChartIndexRoute = "{{ route('nurse.intake_output.index', ['patient' => ':patient']) }}";
var intakeOutputChartLogsRoute = "{{ route('nurse.intake_output.logs', ['patient' => ':patient', 'period' => ':period']) }}";
var intakeOutputChartStartRoute = "{{ route('nurse.intake_output.start') }}";
var intakeOutputChartEndRoute = "{{ route('nurse.intake_output.end') }}";
var intakeOutputChartRecordRoute = "{{ route('nurse.intake_output.record') }}";
var intakeOutputChartDeleteRecordRoute = "{{ route('nurse.intake_output.delete_record', ['record' => ':record']) }}";

// Edit window from settings
var NOTE_EDIT_WINDOW = {{ appsettings('note_edit_window', 30) }};

// Global variables for medication chart
let selectedMedication = null;
let calendarStartDate = new Date();
calendarStartDate.setDate(calendarStartDate.getDate() - 15);
let medications = [];
let medicationStatus = {};
let currentSchedules = [];
let currentAdministrations = [];
let medicationHistory = {};
let patientPrescriptions = [];
let patientPrescriptionsLoaded = false;

// Â§4.6: Drug source badge helper
function getDrugSourceBadge(drugSource, productRequestId) {
    switch (drugSource) {
        case 'patient_own':
            return '<span class="badge" style="background:#7b1fa2;"><i class="mdi mdi-account-heart"></i> Patient\'s Own</span>';
        case 'ward_stock':
            if (productRequestId) {
                return '<span class="badge bg-primary"><i class="mdi mdi-hospital-building"></i> Ward Stock (Billed)</span>';
            }
            return '<span class="badge bg-info"><i class="mdi mdi-hospital-building"></i> Ward Stock</span>';
        case 'pharmacy_dispensed':
        default:
            return '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>';
    }
}

function setDrugSource(source) {
    $('#administer_drug_source').val(source || 'pharmacy_dispensed');
}

// Helper: set datetime-local input to current time
function setCurrentDateTime(inputId) {
    var now = new Date();
    var offset = now.getTimezoneOffset();
    var local = new Date(now.getTime() - offset * 60000);
    document.getElementById(inputId).value = local.toISOString().slice(0, 16);
}

// Â§6.1: Select2 template for dropdown results (rich format)
function formatRxOption(option) {
    if (!option.id) return option.text;

    var $opt = $(option.element);

    // Handle separator
    if ($opt.data('is-separator')) {
        return $('<div class="text-muted fw-bold small py-1 border-top mt-1">' + option.text + '</div>');
    }

    // Handle direct administration entries (ward stock / patient's own)
    var directEntry = $opt.data('direct-entry');
    if (directEntry) {
        var deIcon = $opt.data('status-icon') || '';
        var isPatientOwn = directEntry.drug_source === 'patient_own';
        var deLabel = isPatientOwn ? "Patient's Own" : 'Ward Stock';
        var deBadgeClass = isPatientOwn ? 'bg-purple' : 'bg-info';
        var deBadgeHtml = '<span class="badge ' + deBadgeClass + '">' + deLabel + '</span>';
        var deDrugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
        var deCodeStr = directEntry.product_code ? '(' + directEntry.product_code + ')' : '';
        var deSchedCount = directEntry.times_scheduled || 0;
        var deAdminCount = directEntry.times_administered || 0;

        return $(
            '<div class="d-flex flex-column py-1">' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<span style="font-size:1.1em;">' + deIcon + '</span>' +
                    '<strong>' + deDrugName + '</strong>' +
                    '<small class="text-muted">' + deCodeStr + '</small>' +
                    deBadgeHtml +
                '</div>' +
                '<div class="d-flex gap-3 ms-4">' +
                    '<small class="text-muted">Scheduled: ' + deSchedCount + '</small>' +
                    '<small class="text-info">Administered: ' + deAdminCount + '</small>' +
                    '<small class="text-muted">by ' + directEntry.nurse_name + '</small>' +
                '</div>' +
            '</div>'
        );
    }

    // Handle pharmacy prescriptions
    var rx = $opt.data('rx');
    if (!rx) return option.text;

    var icon = $opt.data('status-icon') || '';
    var badge = $opt.data('status-badge') || '';
    var adminText = $opt.data('admin-text') || '';
    var doctorText = $opt.data('doctor-text') || '';
    var isDisabled = option.disabled;

    return $(
        '<div class="d-flex flex-column py-1 ' + (isDisabled ? 'opacity-50' : '') + '">' +
            '<div class="d-flex align-items-center gap-2">' +
                '<span style="font-size:1.1em;">' + icon + '</span>' +
                '<strong>' + rx.product_name + '</strong>' +
                '<small class="text-muted">(' + rx.product_code + ')</small>' +
                badge +
            '</div>' +
            '<div class="d-flex gap-3 ms-4">' +
                '<small class="text-muted">Prescribed: ' + rx.qty_prescribed + '</small>' +
                '<small class="text-muted">Administered: ' + (rx.qty_administered || 0) + '</small>' +
                '<small class="text-muted">Remaining: ' + (rx.remaining_doses || 0) + '</small>' +
                '<small class="text-muted">Scheduled: ' + (rx.times_scheduled || 0) + '</small>' +
                (adminText ? '<small class="text-info">' + adminText + '</small>' : '') +
                (doctorText ? '<small class="text-muted">' + doctorText + '</small>' : '') +
                (rx.remaining_doses === 0 && rx.is_dispensed ? '<small class="text-success fw-bold">âœ“ Fully administered</small>' : '') +
            '</div>' +
            (isDisabled ? '<small class="text-danger ms-4"><i class="mdi mdi-lock"></i> ' + rx.status_label + ' â€” cannot chart</small>' : '') +
        '</div>'
    );
}

// Â§6.1: Select2 template for selected item (compact)
function formatRxSelection(option) {
    if (!option.id) return option.text;

    var $opt = $(option.element);

    // Handle direct entry selections
    var directEntry = $opt.data('direct-entry');
    if (directEntry) {
        var deIcon = $opt.data('status-icon') || '';
        var deDrugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
        var deCodeStr = directEntry.product_code ? '(' + directEntry.product_code + ')' : '';
        return deIcon + ' ' + deDrugName + ' ' + deCodeStr;
    }

    var rx = $opt.data('rx');
    if (!rx) return option.text;

    var icon = $opt.data('status-icon') || '';
    var remainStr = (rx.remaining_doses !== undefined) ? ' [' + (rx.qty_administered || 0) + '/' + rx.qty_prescribed + ' used]' : '';
    return icon + ' ' + rx.product_name + ' (' + rx.product_code + ')' + remainStr;
}

// =============================================
// OVERVIEW TAB FUNCTIONS
// =============================================
var overviewCurrentStart = null;
var overviewDataCache = null;

function loadMedOverview(startDate) {
    if (!PATIENT_ID) return;

    if (!startDate) {
        // Default to current week start (Monday)
        var d = new Date();
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); // Monday
        startDate = formatDateForApi(d);
    }
    overviewCurrentStart = startDate;

    var endDate = new Date(startDate);
    endDate.setDate(endDate.getDate() + 6);
    var endStr = formatDateForApi(endDate);

    $('#overview-loading').show();
    $('#unified-overview-container').html('');

    var url = medicationChartOverviewRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'GET',
        data: { start_date: startDate, end_date: endStr },
        success: function(data) {
            $('#overview-loading').hide();
            overviewDataCache = data;

            // Update stats
            var stats = data.stats || {};
            $('#stat-total-meds').text(stats.total_medications || 0);
            $('#stat-given').text(stats.total_given || 0);
            $('#stat-scheduled').text(stats.total_scheduled || 0);
            $('#stat-missed').text(stats.total_missed || 0);

            // Render the 7-day calendar
            renderOverviewCalendar(data, startDate, endStr);
        },
        error: function(xhr) {
            $('#overview-loading').hide();
            $('#unified-overview-container').html('<div class="alert alert-danger"><i class="mdi mdi-alert"></i> Failed to load overview.</div>');
        }
    });
}

function renderOverviewCalendar(data, startStr, endStr) {
    var container = $('#unified-overview-container');
    container.html('');

    var startDate = new Date(startStr);
    var today = new Date();
    today.setHours(0,0,0,0);

    var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    // Build day columns header
    var headerHtml = '<div class="calendar-weekday-header d-flex">';
    for (var i = 0; i < 7; i++) {
        var day = new Date(startDate);
        day.setDate(day.getDate() + i);
        var isToday = day.toDateString() === today.toDateString();
        var isWeekend = (day.getDay() === 0 || day.getDay() === 6);
        headerHtml += '<div class="weekday-name flex-fill text-center py-1 small fw-bold ' +
            (isToday ? 'bg-primary text-white rounded' : '') +
            (isWeekend ? ' text-muted' : '') + '">' +
            dayNames[day.getDay()] + ' ' + day.getDate() + '/' + (day.getMonth()+1) +
            '</div>';
    }
    headerHtml += '</div>';

    // Group schedules and admins by date
    var schedulesByDay = {};
    var adminsByDay = {};

    (data.schedules || []).forEach(function(s) {
        var dateKey = s.scheduled_time.substring(0, 10);
        if (!schedulesByDay[dateKey]) schedulesByDay[dateKey] = [];
        schedulesByDay[dateKey].push(s);
    });

    (data.unscheduled_admins || []).forEach(function(a) {
        var dateKey = a.administered_at.substring(0, 10);
        if (!adminsByDay[dateKey]) adminsByDay[dateKey] = [];
        adminsByDay[dateKey].push(a);
    });

    // Build day columns
    var gridHtml = '<div class="medication-calendar-grid d-flex" style="min-height:200px;">';
    for (var i = 0; i < 7; i++) {
        var day = new Date(startDate);
        day.setDate(day.getDate() + i);
        var dateKey = formatDateForApi(day);
        var isToday = day.toDateString() === today.toDateString();
        var isPast = day < today && !isToday;
        var isWeekend = (day.getDay() === 0 || day.getDay() === 6);

        var cellClass = 'calendar-day-cell flex-fill border-end p-1';
        if (isToday) cellClass += ' today';
        if (isPast) cellClass += ' past-date';
        if (isWeekend) cellClass += ' weekend';

        gridHtml += '<div class="' + cellClass + '" data-date="' + dateKey + '">';
        gridHtml += '<div class="schedule-items">';

        // Render schedules
        var daySchedules = (schedulesByDay[dateKey] || []).sort(function(a,b) {
            return a.scheduled_time.localeCompare(b.scheduled_time);
        });

        daySchedules.forEach(function(s) {
            var time = new Date(s.scheduled_time);
            var timeStr = time.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true});
            var statusClass = s.is_administered ? 'status-given' : (isPast ? 'status-missed' : 'status-pending');
            var sourceIcon = s.drug_source === 'ward_stock' ? 'ðŸ¥' : (s.drug_source === 'patient_own' ? 'ðŸ‘¤' : 'ðŸ’Š');
            var statusIcon = s.is_administered ? 'âœ…' : (isPast ? 'âŒ' : 'ðŸ•');

            gridHtml += '<div class="med-item ' + statusClass + '" title="' + s.drug_name + ' - ' + s.dose + ' ' + s.route + '">';
            gridHtml += '<span class="med-time">' + timeStr + '</span> ';
            gridHtml += '<span class="med-name">' + sourceIcon + ' ' + truncate(s.drug_name, 15) + '</span> ';
            gridHtml += '<span class="med-status">' + statusIcon + '</span>';
            gridHtml += '</div>';
        });

        // Render unscheduled administrations
        var dayAdmins = adminsByDay[dateKey] || [];
        dayAdmins.forEach(function(a) {
            var time = new Date(a.administered_at);
            var timeStr = time.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true});
            var sourceIcon = a.drug_source === 'ward_stock' ? 'ðŸ¥' : (a.drug_source === 'patient_own' ? 'ðŸ‘¤' : 'ðŸ’Š');

            gridHtml += '<div class="med-item status-given" title="' + a.drug_name + ' - ' + a.dose + ' (unscheduled)">';
            gridHtml += '<span class="med-time">' + timeStr + '</span> ';
            gridHtml += '<span class="med-name">' + sourceIcon + ' ' + truncate(a.drug_name, 15) + '</span> ';
            gridHtml += '<span class="med-status">âœ…</span>';
            gridHtml += '</div>';
        });

        if (daySchedules.length === 0 && dayAdmins.length === 0) {
            gridHtml += '<div class="text-muted text-center small py-3"><i class="mdi mdi-calendar-blank"></i><br>No items</div>';
        }

        gridHtml += '</div></div>';
    }
    gridHtml += '</div>';

    container.html(headerHtml + gridHtml);
}

function truncate(str, len) {
    if (!str) return '';
    return str.length > len ? str.substring(0, len) + 'â€¦' : str;
}

// Overview nav buttons
$(document).on('click', '#overview-prev-btn', function() {
    if (!overviewCurrentStart) return;
    var d = new Date(overviewCurrentStart);
    d.setDate(d.getDate() - 7);
    loadMedOverview(formatDateForApi(d));
});

$(document).on('click', '#overview-next-btn', function() {
    if (!overviewCurrentStart) return;
    var d = new Date(overviewCurrentStart);
    d.setDate(d.getDate() + 7);
    loadMedOverview(formatDateForApi(d));
});

$(document).on('click', '#overview-today-btn', function() {
    loadMedOverview(null); // null â†’ defaults to current week
});

// =============================================
// PRESCRIPTIONS TAB FUNCTIONS
// =============================================
var rxTabDataCache = null;
var rxCurrentFilter = 'all';

function loadPrescriptionsTab() {
    if (!PATIENT_ID) return;

    $('#rx-loading').show();
    $('#rx-table-wrap').hide();
    $('#rx-empty').hide();

    var url = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'GET',
        success: function(data) {
            $('#rx-loading').hide();
            rxTabDataCache = data;

            var rxList = data.prescriptions || [];
            var directList = data.direct_entries || [];

            // Update summary counts
            var dispensed = rxList.filter(function(r) { return r.status === 3; }).length;
            var billed = rxList.filter(function(r) { return r.status === 2; }).length;
            var requested = rxList.filter(function(r) { return r.status === 1; }).length;
            var total = rxList.length + directList.length;

            $('#rx-count-dispensed').text(dispensed);
            $('#rx-count-billed').text(billed);
            $('#rx-count-requested').text(requested);
            $('#rx-count-total').text(total);
            $('#rx-tab-badge').text(total).toggle(total > 0);

            // Render table
            renderPrescriptionsTable(rxList, directList, rxCurrentFilter);
        },
        error: function() {
            $('#rx-loading').hide();
            $('#rx-empty').show().find('p').text('Failed to load prescriptions.');
        }
    });
}

function renderPrescriptionsTable(rxList, directList, filter) {
    var $body = $('#rx-dashboard-body');
    $body.empty();

    var filtered = rxList;
    if (filter && filter !== 'all') {
        filtered = rxList.filter(function(r) { return r.status == filter; });
    }

    if (filtered.length === 0 && (filter !== 'all' || directList.length === 0)) {
        $('#rx-table-wrap').hide();
        $('#rx-empty').show();
        return;
    }

    $('#rx-empty').hide();
    $('#rx-table-wrap').show();

    // Pharmacy prescriptions
    filtered.forEach(function(rx) {
        var statusBadge, statusClass;
        switch (rx.status) {
            case 3:
                statusBadge = '<span class="badge bg-success">Dispensed</span>';
                statusClass = '';
                break;
            case 2:
                statusBadge = rx.is_paid
                    ? '<span class="badge bg-warning text-dark">Awaiting Pharmacy</span>'
                    : '<span class="badge bg-secondary">' + (rx.status_label || 'Awaiting Payment') + '</span>';
                statusClass = '';
                break;
            default:
                statusBadge = '<span class="badge bg-danger">Awaiting Billing</span>';
                statusClass = 'table-danger';
        }

        var qtyInfo = rx.qty_prescribed || 0;
        var adminInfo = (rx.qty_administered || 0) + ' / ' + qtyInfo;
        var remaining = rx.remaining_doses || 0;
        var adminBadge = rx.is_fully_administered
            ? '<span class="badge bg-success">Complete</span>'
            : '<span class="badge bg-' + (remaining <= 0 ? 'danger' : 'secondary') + '">' + adminInfo + '</span>';

        var prescDate = rx.prescribed_at ? formatDate(new Date(rx.prescribed_at)) : '-';

        var actionBtns = '';
        if (rx.can_chart) {
            actionBtns = '<button class="btn btn-sm btn-outline-primary rx-select-btn" data-posr-id="' + rx.posr_id + '" title="Select in chart"><i class="mdi mdi-pencil-plus"></i></button>';
        }
        if (rx.status !== 3 && rx.status !== 0) {
            actionBtns += ' <button class="btn btn-sm btn-outline-danger rx-dismiss-btn" data-product-request-id="' + rx.product_request_id + '" data-drug-name="' + (rx.product_name || '') + '" title="Dismiss"><i class="mdi mdi-close-circle"></i></button>';
        }

        $body.append(
            '<tr class="' + statusClass + '">' +
                '<td><strong>' + (rx.product_name || 'Unknown') + '</strong><br><small class="text-muted">' + (rx.product_code || '') + '</small></td>' +
                '<td>' + (rx.dose || '-') + '</td>' +
                '<td><small>' + (rx.doctor_name ? 'Dr. ' + rx.doctor_name : '-') + '</small></td>' +
                '<td><small>' + prescDate + '</small></td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + adminBadge + '<br><small class="text-muted">Remaining: ' + remaining + '</small></td>' +
                '<td class="text-center">' + actionBtns + '</td>' +
            '</tr>'
        );
    });

    // Direct entries (show if filter = 'all')
    if (filter === 'all' && directList.length > 0) {
        $body.append('<tr class="table-light"><td colspan="7" class="fw-bold small text-muted py-1"><i class="mdi mdi-arrow-right"></i> Direct Administrations</td></tr>');

        directList.forEach(function(entry) {
            var isPatientOwn = entry.drug_source === 'patient_own';
            var sourceBadge = isPatientOwn
                ? '<span class="badge" style="background:#7b1fa2;">Patient\'s Own</span>'
                : '<span class="badge bg-info">Ward Stock</span>';
            var drugName = entry.product_name || entry.external_drug_name || 'Unknown';

            $body.append(
                '<tr>' +
                    '<td><strong>' + drugName + '</strong><br><small class="text-muted">' + (entry.product_code || '') + '</small></td>' +
                    '<td>-</td>' +
                    '<td><small>' + (entry.nurse_name || '-') + '</small></td>' +
                    '<td><small>' + (entry.last_administered_at ? formatDate(new Date(entry.last_administered_at)) : '-') + '</small></td>' +
                    '<td>' + sourceBadge + '</td>' +
                    '<td><span class="badge bg-secondary">' + (entry.times_administered || 0) + ' given</span>' +
                        '<br><small class="text-muted">Scheduled: ' + (entry.times_scheduled || 0) + '</small></td>' +
                    '<td class="text-center">' +
                        '<button class="btn btn-sm btn-outline-primary rx-select-direct-btn" ' +
                            'data-drug-source="' + entry.drug_source + '" ' +
                            'data-product-id="' + (entry.product_id || '') + '" ' +
                            'data-external-name="' + (entry.external_drug_name || '') + '" ' +
                            'title="Select in chart"><i class="mdi mdi-pencil-plus"></i></button>' +
                    '</td>' +
                '</tr>'
            );
        });
    }
}

// Filter buttons for prescriptions tab
$(document).on('click', '#rx-filter-group .btn', function() {
    $('#rx-filter-group .btn').removeClass('active');
    $(this).addClass('active');
    rxCurrentFilter = $(this).data('rx-filter') || 'all';

    if (rxTabDataCache) {
        renderPrescriptionsTable(
            rxTabDataCache.prescriptions || [],
            rxTabDataCache.direct_entries || [],
            rxCurrentFilter
        );
    }
});

// Refresh button
$(document).on('click', '#rx-refresh-btn', function() {
    loadPrescriptionsTab();
});

// Select prescription in chart (switch to Entry tab and pick the drug)
$(document).on('click', '.rx-select-btn', function() {
    var posrId = $(this).data('posr-id');
    if (posrId) {
        // Switch to Entry tab
        $('#med-entry-tab').tab('show');
        // Select the drug in the dropdown
        setTimeout(function() {
            $('#drug-select').val(posrId).trigger('change');
        }, 200);
    }
});

// Select direct entry in chart
$(document).on('click', '.rx-select-direct-btn', function() {
    var drugSource = $(this).data('drug-source');
    var productId = $(this).data('product-id');
    var externalName = $(this).data('external-name');

    // Switch to Entry tab
    $('#med-entry-tab').tab('show');

    // Find matching option in dropdown
    setTimeout(function() {
        var matchVal = null;
        $('#drug-select option').each(function() {
            var $opt = $(this);
            var de = $opt.data('direct-entry');
            if (de && de.drug_source === drugSource) {
                if (drugSource === 'ward_stock' && de.product_id == productId) {
                    matchVal = $opt.val();
                    return false;
                }
                if (drugSource === 'patient_own' && de.external_drug_name === externalName) {
                    matchVal = $opt.val();
                    return false;
                }
            }
        });
        if (matchVal) {
            $('#drug-select').val(matchVal).trigger('change');
        }
    }, 200);
});

// Dismiss prescription from prescriptions tab
$(document).on('click', '.rx-dismiss-btn', function() {
    var productRequestId = $(this).data('product-request-id');
    var drugName = $(this).data('drug-name');
    var reason = prompt('Dismiss "' + drugName + '"? Enter reason:');
    if (!reason) return;

    var url = medicationChartDismissRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            _token: CSRF_TOKEN,
            product_request_id: productRequestId,
            reason: reason
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Prescription dismissed.');
                loadPrescriptionsTab();
                loadMedicationsList(); // Refresh the dropdown too
            } else {
                toastr.error(response.error || 'Failed to dismiss.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.error || 'Failed to dismiss prescription.');
        }
    });
});

// =============================================
// TAB SWITCH TRIGGERS
// =============================================
$(document).on('shown.bs.tab', '#med-overview-tab', function() {
    loadMedOverview(overviewCurrentStart);
});

$(document).on('shown.bs.tab', '#med-rx-tab', function() {
    loadPrescriptionsTab();
});

// Global variables for I/O chart
let fluidPeriods = [];
let solidPeriods = [];
let currentFluidPeriodId = null;
let currentSolidPeriodId = null;

// Initialize medication chart for a specific patient
function initMedicationChart(patientId) {
    if (!patientId) {
        console.error('No patient ID provided for medication chart');
        return;
    }

    PATIENT_ID = patientId;

    // Update hidden input for patient ID in modals
    $('#schedule_patient_id').val(patientId);
    $('#discontinue_patient_id').val(patientId);
    $('#resume_patient_id').val(patientId);

    // Reset medication state
    selectedMedication = null;
    medications = [];
    medicationStatus = {};
    currentSchedules = [];
    currentAdministrations = [];
    medicationHistory = {};

    // Reset UI
    $('#drug-select').empty().append('<option value="">-- Select a medication --</option>');
    $('#medication-calendar').hide();
    $('#calendar-legend').hide();
    $('#medication-status').empty();
    $('#set-schedule-btn, #discontinue-btn, #resume-btn').prop('disabled', true);

    // Load medications list
    loadMedicationsList();
}

// Initialize I/O chart for a specific patient
function initIntakeOutputChart(patientId) {
    if (!patientId) {
        console.error('No patient ID provided for I/O chart');
        return;
    }

    PATIENT_ID = patientId;

    // Initialize date filters with today - 7 days to today
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);

    $('#fluid_start_date, #solid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#fluid_end_date, #solid_end_date').val(today.toISOString().split('T')[0]);

    // Load I/O data
    loadFluidPeriods();
    loadSolidPeriods();
}

// Helper function to get user name
function getUserName(obj) {
    return obj.user_fullname || obj.user_name || obj.administered_by_name ||
        (obj.administeredBy && obj.administeredBy.name) ||
        obj.nurse_name ||
        (obj.nurse && obj.nurse.name) || 'Unknown';
}

// Format date for API calls
function formatDateForApi(date) {
    if (date instanceof Date) {
        return date.toISOString().split('T')[0];
    }
    return date;
}

// Format date for display
function formatDate(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Format time for display
function formatTime(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

// Format datetime for display
function formatDateTime(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return formatDate(date) + ' ' + formatTime(date);
}

// Get day of week
function getDayOfWeek(date) {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    return days[date.getDay()];
}

// =============================================
// MEDICATION CHART FUNCTIONS
// =============================================

function loadMedicationsList() {
    if (!PATIENT_ID) {
        console.error('Patient ID is not set');
        return;
    }

    console.log('Loading medications list (enriched Â§6.1)...');
    $('#medication-loading').show();
    $('#medication-calendar').hide();

    // Â§6.1: Use prescribed-drugs API for enriched status data
    var prescribedUrl = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: prescribedUrl,
        type: 'GET',
        success: function(data) {
            console.log('Prescribed drugs loaded:', data);
            $('#medication-loading').hide();

            var prescriptions = data.prescriptions || [];

            // Store all prescriptions for reference
            window._rxLookup = {};
            prescriptions.forEach(function(rx) { window._rxLookup[rx.posr_id || rx.id] = rx; });

            // Â§6.1: Populate dropdown with rich, status-aware options
            var select = $('#drug-select');
            select.empty();
            select.append('<option value="">-- Select a medication --</option>');

            if (prescriptions.length === 0) {
                toastr.warning('No medications found for this patient.');
            } else {
                console.log('Found ' + prescriptions.length + ' prescriptions');

                prescriptions.forEach(function(rx) {
                    var posrId = rx.posr_id || '';
                    var canChart = rx.can_chart && posrId;

                    // Â§6.1: Status icon + color
                    var statusIcon, statusBadge;
                    switch (rx.status) {
                        case 3:
                            statusIcon = 'ðŸŸ¢';
                            statusBadge = '<span class="badge bg-success">Dispensed</span>';
                            break;
                        case 2:
                            if (rx.is_paid) {
                                statusIcon = 'ðŸŸ¡';
                                statusBadge = '<span class="badge bg-warning text-dark">Awaiting Pharmacy</span>';
                            } else {
                                statusIcon = 'ðŸŸ ';
                                statusBadge = '<span class="badge bg-secondary">' + rx.status_label + '</span>';
                            }
                            break;
                        default:
                            statusIcon = 'ðŸ”´';
                            statusBadge = '<span class="badge bg-danger">Awaiting Billing</span>';
                    }

                    // Administered progress
                    var adminText = rx.is_dispensed
                        ? 'Administered: ' + rx.times_administered + '/' + rx.qty_prescribed
                        : '';

                    // Doctor info
                    var doctorText = rx.doctor_name ? 'Dr. ' + rx.doctor_name : '';

                    // Build display text
                    var plainText = statusIcon + ' ' + rx.product_name + ' (' + rx.product_code + ') â€” ' + rx.status_label;

                    var opt = new Option(plainText, posrId || ('rx_' + rx.id), false, false);
                    opt.disabled = !canChart;

                    // Store rich data on the option for Select2 templateResult
                    $(opt).data('rx', rx);
                    $(opt).data('status-icon', statusIcon);
                    $(opt).data('status-badge', statusBadge);
                    $(opt).data('admin-text', adminText);
                    $(opt).data('doctor-text', doctorText);
                    $(opt).data('drug-source', 'pharmacy_dispensed');
                    $(opt).data('product-request-id', rx.product_request_id);
                    $(opt).data('product-id', rx.product_id);

                    select.append(opt);

                    // Store medication status for discontinue/resume tracking
                    if (posrId) {
                        medicationStatus[posrId] = {
                            discontinued: false,
                            resumed: false,
                        };
                    }
                });

                // Â§6.1: Merge direct administration entries (ward stock + patient's own)
                var directEntries = data.direct_entries || [];
                if (directEntries.length > 0) {
                    // Add separator
                    var separator = new Option('â”€â”€ Direct Administrations â”€â”€', '', false, false);
                    separator.disabled = true;
                    $(separator).data('is-separator', true);
                    select.append(separator);

                    directEntries.forEach(function(entry) {
                        var isPatientOwn = entry.drug_source === 'patient_own';
                        var icon = isPatientOwn ? 'ðŸŸ£' : 'ðŸ”µ';
                        var label = isPatientOwn ? "Patient's Own" : 'Ward Stock';
                        var drugName = entry.product_name || entry.external_drug_name || 'Unknown';
                        var codeStr = entry.product_code ? ' (' + entry.product_code + ')' : '';
                        var plainText = icon + ' ' + drugName + codeStr + ' â€” ' + label;

                        var optVal = 'direct_' + entry.drug_source + '_' + (entry.product_id || entry.external_drug_name || entry.id);
                        var opt = new Option(plainText, optVal, false, false);

                        // Store data for Select2 template and calendar loading
                        $(opt).data('direct-entry', entry);
                        $(opt).data('drug-source', entry.drug_source);
                        $(opt).data('product-id', entry.product_id || null);
                        $(opt).data('external-drug-name', entry.external_drug_name || null);
                        $(opt).data('status-icon', icon);
                        $(opt).data('is-direct', true);

                        select.append(opt);
                    });

                    console.log('Added ' + directEntries.length + ' direct administration entries to dropdown');
                }

                // Â§6.1: Initialize Select2 with rich formatting
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }
                select.select2({
                    width: '100%',
                    placeholder: '-- Select a medication --',
                    allowClear: true,
                    templateResult: formatRxOption,
                    templateSelection: formatRxSelection,
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load medications:', status, error);
            $('#medication-loading').hide();
            toastr.error('Failed to load medications: ' + error);
        }
    });
}

// Drug selection change
$(document).on('change', '#drug-select', function() {
    const medicationId = $(this).val();

    if (medicationId) {
        selectedMedication = medicationId;

        // Detect if this is a direct entry (ward_stock / patient_own)
        var $selectedOpt = $(this).find('option:selected');
        var isDirect = $selectedOpt.data('is-direct') || false;

        // Enable schedule button; disable discontinue/resume for direct entries
        $('#set-schedule-btn').prop('disabled', false);
        $('#discontinue-btn').prop('disabled', isDirect);
        $('#resume-btn').prop('disabled', isDirect);

        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(medicationId, startDateStr, endDateStr);
    } else {
        selectedMedication = null;
        $('#medication-calendar').hide();
        $('#calendar-legend').hide();
        $('#set-schedule-btn, #discontinue-btn, #resume-btn').prop('disabled', true);
        $('#medication-status').empty();
    }
});

// Calendar navigation buttons
$(document).on('click', '#prev-month-btn', function() {
    if (selectedMedication) {
        calendarStartDate.setDate(calendarStartDate.getDate() - 30);
        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#next-month-btn', function() {
    if (selectedMedication) {
        calendarStartDate.setDate(calendarStartDate.getDate() + 30);
        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#today-btn', function() {
    if (selectedMedication) {
        calendarStartDate = new Date();
        calendarStartDate.setDate(calendarStartDate.getDate() - 15);

        const endDate = new Date();
        endDate.setDate(endDate.getDate() + 15);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#apply-date-range-btn', function() {
    if (!selectedMedication) {
        toastr.warning('Please select a medication first.');
        return;
    }

    const startDateStr = $('#med-start-date').val();
    const endDateStr = $('#med-end-date').val();

    if (!startDateStr || !endDateStr) {
        toastr.warning('Please select both start and end dates.');
        return;
    }

    if (new Date(startDateStr) > new Date(endDateStr)) {
        toastr.warning('Start date cannot be after end date.');
        return;
    }

    calendarStartDate = new Date(startDateStr);
    loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
});

function loadMedicationCalendarWithDateRange(medicationId, startDate, endDate) {
    if (!medicationId || !PATIENT_ID) return;

    $('#medication-loading').show();
    $('#medication-calendar').hide();

    // Determine if this is a direct entry by checking the selected option
    var $selectedOpt = $('#drug-select').find('option[value="' + medicationId + '"]');
    var isDirect = $selectedOpt.data('is-direct') || false;
    var url;

    if (isDirect) {
        // Direct entry â€” use directCalendar endpoint with query params
        var drugSource = $selectedOpt.data('drug-source');
        var productId = $selectedOpt.data('product-id');
        var externalDrugName = $selectedOpt.data('external-drug-name');

        url = medicationChartDirectCalendarRoute.replace(':patient', PATIENT_ID);
        var queryParams = {
            drug_source: drugSource,
            start_date: startDate,
            end_date: endDate
        };
        if (productId) queryParams.product_id = productId;
        if (externalDrugName) queryParams.external_drug_name = externalDrugName;

        $.ajax({
            url: url,
            type: 'GET',
            data: queryParams,
            success: function(data) {
                $('#medication-loading').hide();
                handleCalendarResponse(data, medicationId);
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    } else {
        // Standard POSR â€” use calendar route
        url = medicationChartCalendarRoute
            .replace(':patient', PATIENT_ID)
            .replace(':medication', medicationId)
            .replace(':start_date', startDate);

        $.ajax({
            url: url,
            type: 'GET',
            data: { start_date: startDate, end_date: endDate },
            success: function(data) {
                $('#medication-loading').hide();
                handleCalendarResponse(data, medicationId);
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    }
}

// Shared handler for calendar response (works for both POSR and direct entries)
function handleCalendarResponse(data, medicationId) {
    if (data.medication) {
        const medication = data.medication;
        currentSchedules = data.schedules || [];
        currentAdministrations = data.administrations || [];

        // Store total counts from server (all-time, not just date range)
        window._currentMedCounts = {
            totalScheduled: data.total_scheduled || currentSchedules.length,
            totalAdministered: data.total_administered || currentAdministrations.filter(a => a.administered_at).length,
            rangeScheduled: currentSchedules.length,
            rangeAdministered: currentAdministrations.filter(a => a.administered_at).length
        };

        updateMedicationStatus(medication);
        updateMedicationButtons(medication);

        // Store history
        let logEntries = [];
        if (data.history && Array.isArray(data.history)) {
            logEntries = [...data.history];
        }
        if (data.adminHistory && Array.isArray(data.adminHistory)) {
            data.adminHistory.forEach(admin => {
                logEntries.push({
                    date: admin.administered_at,
                    action: 'administration',
                    details: `${admin.dose} ${admin.route} ${admin.comment ? '- ' + admin.comment : ''}`,
                    user: admin.administered_by_name || getUserName(admin) || 'Unknown',
                    id: admin.id
                });
            });
        }
        medicationHistory[selectedMedication] = logEntries;

        renderCalendarView(medication, currentSchedules, currentAdministrations, data.period);
        renderLegend();
        $('#medication-calendar').show();
        $('#calendar-legend').show();
    }
}

function updateMedicationStatus(medication) {
    let statusHtml = '';
    var counts = window._currentMedCounts || { totalScheduled: 0, totalAdministered: 0 };
    var countsHtml = '<span class="badge bg-light text-dark border me-1"><i class="mdi mdi-calendar-clock"></i> ' + counts.totalScheduled + ' scheduled</span>' +
                     '<span class="badge bg-light text-dark border"><i class="mdi mdi-check-circle"></i> ' + counts.totalAdministered + ' administered</span>';

    // Direct entries have product_name at top level; POSR entries have medication.product.product_name
    var productName = '';
    if (medication.is_direct_entry) {
        productName = medication.product_name || 'Direct Entry';
        var sourceLabel = medication.drug_source === 'patient_own' ? "Patient's Own" : 'Ward Stock';
        var sourceBadge = medication.drug_source === 'patient_own' ? 'bg-purple' : 'bg-info';
        statusHtml = `
            <div class="alert alert-info py-2 mb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="mdi mdi-pill me-2"></i>
                        <strong>${productName}</strong>: <span class="badge ${sourceBadge}">${sourceLabel}</span>
                    </div>
                    <div>${countsHtml}</div>
                </div>
            </div>`;
    } else if (medication.product && medication.product.product_name) {
        productName = medication.product.product_name;

        if (medication.discontinued_at && !medication.resumed_at) {
            statusHtml = `
                <div class="alert alert-danger py-2 mb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="mdi mdi-calendar-remove me-2"></i>
                            <strong>${productName}</strong>: Discontinued
                            <div class="small">Reason: ${medication.discontinued_reason || 'N/A'}</div>
                        </div>
                        <div>${countsHtml}</div>
                    </div>
                </div>`;
        } else {
            statusHtml = `
                <div class="alert alert-success py-2 mb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="mdi mdi-check-circle me-2"></i>
                            <strong>${productName}</strong>: <span class="badge bg-success">Active</span>
                        </div>
                        <div>${countsHtml}</div>
                    </div>
                </div>`;
        }
    }

    $('#medication-status').html(statusHtml);
}

function updateMedicationButtons(medication) {
    // Direct entries don't support discontinue/resume
    if (medication.is_direct_entry) {
        $('#discontinue-btn').prop('disabled', true);
        $('#resume-btn').prop('disabled', true);
        $('#set-schedule-btn').prop('disabled', false);
        $('#view-logs-btn').prop('disabled', false);
        return;
    }

    const isDiscontinued = !!medication.discontinued_at;
    const isResumed = !!medication.resumed_at;
    const effectivelyDiscontinued = isDiscontinued && !isResumed;

    $('#discontinue-btn').prop('disabled', effectivelyDiscontinued);
    $('#resume-btn').prop('disabled', !effectivelyDiscontinued);
    $('#set-schedule-btn').prop('disabled', effectivelyDiscontinued);
    $('#view-logs-btn').prop('disabled', false);
}

// View logs button handler
$(document).on('click', '#view-logs-btn', function() {
    if (!selectedMedication) return;

    const logs = medicationHistory[selectedMedication] || [];
    let logsHtml = '';

    if (logs.length === 0) {
        logsHtml = '<div class="alert alert-info">No activity logs available for this medication.</div>';
    } else {
        logsHtml = '<div class="table-responsive"><table class="table table-sm table-striped">';
        logsHtml += '<thead><tr><th>Date</th><th>Action</th><th>Details</th><th>User</th></tr></thead><tbody>';

        logs.forEach(log => {
            const logDate = new Date(log.date);
            let actionBadge = 'bg-primary';
            let actionText = log.action || 'N/A';

            switch((log.action || '').toLowerCase()) {
                case 'administration': actionBadge = 'bg-success'; actionText = 'Administered'; break;
                case 'edit': actionBadge = 'bg-info'; actionText = 'Edited'; break;
                case 'delete': actionBadge = 'bg-dark'; actionText = 'Deleted'; break;
                case 'discontinue': actionBadge = 'bg-warning'; actionText = 'Discontinued'; break;
                case 'resume': actionBadge = 'bg-success'; actionText = 'Resumed'; break;
            }

            logsHtml += `<tr>
                <td><small>${formatDateTime(logDate)}</small></td>
                <td><span class="badge ${actionBadge}">${actionText}</span></td>
                <td>${log.details || log.reason || '-'}</td>
                <td><small>${log.user || 'Unknown'}</small></td>
            </tr>`;
        });

        logsHtml += '</tbody></table></div>';
    }

    // Get medication name from dropdown option data (works for both POSR and direct entries)
    let medicationName = 'Medication';
    const $logsSelectedOpt = $('#drug-select').find('option:selected');
    const logsDirectEntry = $logsSelectedOpt.data('direct-entry');
    if (logsDirectEntry) {
        medicationName = logsDirectEntry.product_name || logsDirectEntry.external_drug_name || 'Direct Entry';
    } else {
        const logsRx = $logsSelectedOpt.data('rx');
        if (logsRx) {
            medicationName = logsRx.product_name || 'Medication';
        }
    }

    $('#medication-logs-title').text('Activity Logs: ' + medicationName);
    $('#medication-logs-content').html(logsHtml);
    $('#medicationLogsModal').modal('show');
});

function renderLegend() {
    const legendHtml = `
        <div class="card-modern shadow-sm mb-3">
            <div class="card-body p-2">
                <h6 class="card-title mb-2"><i class="mdi mdi-information-outline text-primary me-1"></i> Legend</h6>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-primary rounded-pill"><i class="mdi mdi-calendar-clock"></i> Scheduled</span>
                    <span class="badge bg-success rounded-pill"><i class="mdi mdi-check"></i> Administered</span>
                    <span class="badge bg-info rounded-pill"><i class="mdi mdi-pencil"></i> Edited</span>
                    <span class="badge bg-dark rounded-pill"><i class="mdi mdi-close"></i> Deleted</span>
                    <span class="badge bg-danger rounded-pill"><i class="mdi mdi-calendar-remove"></i> Missed</span>
                    <span class="badge bg-secondary rounded-pill"><i class="mdi mdi-calendar"></i> Discontinued</span>
                </div>
            </div>
        </div>`;

    $('#calendar-legend').html(legendHtml);
}

function renderCalendarView(medication, schedules, administrations, period) {
    const startDate = new Date(period.start);
    const endDate = new Date(period.end);
    const product = medication.product || {};
    // Direct entries have product_name at top level; POSR entries have it under .product
    const productName = medication.product_name || product.product_name || 'Medication';

    let doctorDose = medication.dose || '';
    let doctorInfoHtml = '';
    if (doctorDose) {
        doctorInfoHtml = `<div class="my-2"><span class="badge bg-warning text-dark fw-bold">Doctor's Order: ${doctorDose}</span></div>`;
    }

    const dateRange = `${formatDate(startDate)} to ${formatDate(endDate)}`;

    $('#calendar-title').html(`
        <div class="mb-1"><span class="text-primary fw-bold">${productName}</span></div>
        ${doctorInfoHtml}
        <div class="small text-muted"><i class="mdi mdi-calendar-range"></i> ${dateRange}</div>
    `);

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const days = [];
    for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
        days.push(new Date(d));
    }

    // Build weekday header
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    let headerHtml = '<div class="calendar-weekday-header">';
    weekdays.forEach(day => {
        headerHtml += `<div class="weekday-name">${day}</div>`;
    });
    headerHtml += '</div>';

    // Build calendar grid
    let gridHtml = '<div class="medication-calendar-grid">';

    // Add empty cells for padding to align first day with correct weekday
    const firstDayOfWeek = days.length > 0 ? days[0].getDay() : 0;
    for (let i = 0; i < firstDayOfWeek; i++) {
        gridHtml += '<div class="calendar-day-cell empty-day"></div>';
    }

    // Add day cells
    days.forEach((day) => {
        const isToday = day.toDateString() === today.toDateString();
        const isWeekend = day.getDay() === 0 || day.getDay() === 6;
        const isPast = day < today;

        let cellClasses = 'calendar-day-cell';
        if (isToday) cellClasses += ' today';
        if (isWeekend) cellClasses += ' weekend';
        if (isPast && !isToday) cellClasses += ' past-date';

        gridHtml += `<div class="${cellClasses}" data-date="${formatDateForApi(day)}">`;
        gridHtml += `<div class="calendar-day-header">`;
        gridHtml += `<span>${getDayOfWeek(day)}</span>`;
        gridHtml += `<span class="calendar-day-date">${day.getDate()}</span>`;
        gridHtml += `</div>`;
        gridHtml += `<div class="calendar-schedules">`;

        const daySchedules = schedules.filter(s => {
            const scheduleDate = new Date(s.scheduled_time);
            return scheduleDate.toDateString() === day.toDateString();
        });

        // Find unscheduled (direct) administrations for this day
        const dayUnscheduledAdmins = administrations.filter(a => {
            if (!a.administered_at || a.schedule_id) return false;
            const adminDate = new Date(a.administered_at);
            return adminDate.toDateString() === day.toDateString();
        });

        if (daySchedules.length === 0 && dayUnscheduledAdmins.length === 0) {
            gridHtml += `<span class="text-muted small fst-italic">No activity</span>`;
        } else {
            // Render scheduled items
            daySchedules.forEach(schedule => {
                const scheduleTime = new Date(schedule.scheduled_time);
                const formattedTime = formatTime(scheduleTime);
                const admin = administrations.find(a => a.schedule_id === schedule.id);

                // Detect if schedule is for a direct entry (ward_stock / patient_own)
                const isDirectSchedule = schedule.drug_source && schedule.drug_source !== 'pharmacy_dispensed';

                let badgeClass = 'bg-primary';
                let badgeContent = `<i class="mdi mdi-calendar-clock"></i> ${formattedTime}`;
                // All schedule slots open the administer modal (handler routes to correct endpoint)
                let adminAction = `data-bs-target="#administerModal" data-schedule-id="${schedule.id}"`;
                let tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Scheduled`;
                if (isDirectSchedule) {
                    tooltipContent += `<br><em>${schedule.drug_source === 'ward_stock' ? 'Ward Stock' : "Patient's Own"}</em>`;
                }

                const isDiscontinued = medication.discontinued_at &&
                    new Date(medication.discontinued_at) < scheduleTime &&
                    (!medication.resumed_at || new Date(medication.resumed_at) > scheduleTime);

                if (isDiscontinued) {
                    badgeClass = 'bg-secondary';
                    adminAction = '';
                    tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Discontinued`;
                } else if (admin) {
                    badgeClass = 'bg-success';
                    badgeContent = `<i class="mdi mdi-check"></i> ${formattedTime}`;
                    adminAction = `data-bs-target="#adminDetailsModal" data-admin-id="${admin.id}"`;
                    tooltipContent = `Dose: ${admin.dose}<br>Route: ${admin.route}<br>Status: Administered`;

                    if (admin.edited_at) {
                        badgeClass = 'bg-info';
                        badgeContent = `<i class="mdi mdi-pencil"></i> ${formattedTime}`;
                    }
                    if (admin.deleted_at) {
                        badgeClass = 'bg-dark';
                        badgeContent = `<i class="mdi mdi-close"></i> ${formattedTime}`;
                        adminAction = '';
                    }
                } else {
                    const now = new Date();
                    if (scheduleTime < now) {
                        badgeClass = 'bg-danger';
                        badgeContent = `<i class="mdi mdi-alert"></i> ${formattedTime}`;
                        tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Missed`;
                    }
                }

                let removeBtn = '';
                if (!admin && !isDiscontinued) {
                    removeBtn = `<button class='btn btn-sm btn-outline-danger remove-schedule-btn' data-schedule-id='${schedule.id}' title='Remove schedule'><i class='mdi mdi-trash-can-outline'></i></button>`;
                }

                gridHtml += `<div class="calendar-schedule-item">`;
                gridHtml += `<span class="schedule-slot badge ${badgeClass}" ${adminAction} data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="${tooltipContent}">${badgeContent}</span>`;
                gridHtml += removeBtn;
                gridHtml += `</div>`;
            });

            // Render unscheduled (direct) administrations â€” these come from ward stock / patient's own buttons
            dayUnscheduledAdmins.forEach(admin => {
                const adminTime = new Date(admin.administered_at);
                const formattedTime = formatTime(adminTime);
                let badgeClass = 'bg-success';
                let badgeContent = `<i class="mdi mdi-check"></i> ${formattedTime}`;
                let adminAction = `data-bs-target="#adminDetailsModal" data-admin-id="${admin.id}"`;
                let tooltipContent = `Dose: ${admin.dose || 'N/A'}<br>Route: ${admin.route || 'N/A'}<br>Direct Administration`;

                if (admin.edited_at) {
                    badgeClass = 'bg-info';
                    badgeContent = `<i class="mdi mdi-pencil"></i> ${formattedTime}`;
                }
                if (admin.deleted_at) {
                    badgeClass = 'bg-dark';
                    badgeContent = `<i class="mdi mdi-close"></i> ${formattedTime}`;
                    adminAction = '';
                }

                gridHtml += `<div class="calendar-schedule-item">`;
                gridHtml += `<span class="schedule-slot badge ${badgeClass}" ${adminAction} data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="${tooltipContent}">${badgeContent}</span>`;
                gridHtml += `</div>`;
            });
        }

        gridHtml += `</div></div>`;
    });

    // Add trailing empty cells to complete the last week row
    const lastDayOfWeek = days.length > 0 ? days[days.length - 1].getDay() : 6;
    for (let i = lastDayOfWeek + 1; i < 7; i++) {
        gridHtml += '<div class="calendar-day-cell empty-day"></div>';
    }

    gridHtml += '</div>';

    // Output to container
    $('#calendar-container').html(headerHtml + gridHtml);

    // Initialize tooltips
    $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip('dispose');
    $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip({
        placement: 'top',
        trigger: 'hover',
        container: 'body',
        html: true
    });
}

// Remove schedule handler
$(document).off('click', '.remove-schedule-btn').on('click', '.remove-schedule-btn', function(e) {
    e.preventDefault();
    const scheduleId = $(this).data('schedule-id');
    if (!scheduleId || !confirm('Remove this schedule entry?')) return;

    const btn = $(this);
    btn.prop('disabled', true);

    $.ajax({
        url: medicationChartRemoveScheduleRoute,
        type: 'POST',
        data: { schedule_id: scheduleId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Schedule removed.');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to remove schedule.');
                btn.prop('disabled', false);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to remove schedule.');
            btn.prop('disabled', false);
        }
    });
});

// Set schedule button
$(document).on('click', '#set-schedule-btn', function() {
    if (!selectedMedication) return;

    // Detect if this is a direct entry
    var $selectedOpt = $('#drug-select').find('option:selected');
    var isDirect = $selectedOpt.data('is-direct') || false;

    if (isDirect) {
        // Direct entry: populate drug_source, product_id/external_drug_name
        var drugSource = $selectedOpt.data('drug-source') || '';
        var productId = $selectedOpt.data('product-id') || '';
        var externalDrugName = $selectedOpt.data('external-drug-name') || '';

        $('#schedule_medication_id').val(''); // No POSR for direct entries
        $('#schedule_drug_source').val(drugSource);
        $('#schedule_product_id').val(productId);
        $('#schedule_external_drug_name').val(externalDrugName);
    } else {
        // Standard POSR medication
        $('#schedule_medication_id').val(selectedMedication);
        $('#schedule_drug_source').val('pharmacy_dispensed');
        $('#schedule_product_id').val('');
        $('#schedule_external_drug_name').val('');
    }

    $('#schedule_date').val(new Date().toISOString().split('T')[0]);
    $('#setScheduleModal').modal('show');
});

// Set schedule form submit
$(document).on('submit', '#setScheduleForm', function(e) {
    e.preventDefault();

    const formData = $(this).serialize();

    $.ajax({
        url: medicationChartScheduleRoute,
        type: 'POST',
        data: formData + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Schedule created successfully.');
                $('#setScheduleModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to create schedule.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create schedule.');
        }
    });
});

// Discontinue button
$(document).on('click', '#discontinue-btn', function() {
    if (!selectedMedication) return;
    $('#discontinue_medication_id').val(selectedMedication);
    const discRx = $('#drug-select').find('option:selected').data('rx');
    const discName = discRx?.product_name || 'Medication';
    $('#discontinue-medication-name').text(discName);
    $('#discontinueModal').modal('show');
});

// Discontinue form submit
$(document).on('submit', '#discontinueForm', function(e) {
    e.preventDefault();

    const btn = $('#discontinueSubmitBtn');
    btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

    $.ajax({
        url: medicationChartDiscontinueRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Medication discontinued.');
                $('#discontinueModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to discontinue.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to discontinue.');
        },
        complete: function() {
            btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
        }
    });
});

// Resume button
$(document).on('click', '#resume-btn', function() {
    if (!selectedMedication) return;
    $('#resume_medication_id').val(selectedMedication);
    const resRx = $('#drug-select').find('option:selected').data('rx');
    const resName = resRx?.product_name || 'Medication';
    $('#resume-medication-name').text(resName);
    $('#resumeModal').modal('show');
});

// Resume form submit
$(document).on('submit', '#resumeForm', function(e) {
    e.preventDefault();

    const btn = $('#resumeSubmitBtn');
    btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

    $.ajax({
        url: medicationChartResumeRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Medication resumed.');
                $('#resumeModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to resume.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to resume.');
        },
        complete: function() {
            btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
        }
    });
});

// Store stock data cache for medication administration
var administerStockData = null;

// Administer modal population â€” Â§6.5: enriched with _rxLookup data
$(document).on('click', '[data-bs-target="#administerModal"]', function() {
    const scheduleId = $(this).data('schedule-id');
    const schedule = currentSchedules.find(s => s.id == scheduleId);

    if (schedule) {
        $('#administer_schedule_id').val(scheduleId);

        // Determine drug source from schedule
        var scheduleDrugSource = schedule.drug_source || 'pharmacy_dispensed';
        var isDirect = (scheduleDrugSource === 'ward_stock' || scheduleDrugSource === 'patient_own');
        var productId = '';

        // Reset all conditional sections
        $('#administer-ward-stock-section').addClass('d-none');
        $('#administer-patient-own-section').addClass('d-none');
        $('#administer-pharmacy-qty-section').addClass('d-none');
        $('#administer-stock-info').addClass('d-none');
        $('#administer-stock-warning').addClass('d-none');
        administerStockData = null;

        if (isDirect) {
            // Direct entry schedule: use schedule data directly
            productId = schedule.product_id || '';
            var drugName = schedule.external_drug_name || 'Direct Entry';

            // Try to get product name from the selected dropdown option
            var $selectedOpt = $('#drug-select').find('option:selected');
            var directEntry = $selectedOpt.data('direct-entry');
            if (directEntry) {
                drugName = directEntry.product_name || directEntry.external_drug_name || drugName;
            }

            $('#administer_product_id').val(productId);
            $('#administer_product_request_id').val('');
            $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + drugName);
            setDrugSource(scheduleDrugSource);

            // Set the external_drug_name hidden field for patient_own
            $('#administer_external_drug_name').val(schedule.external_drug_name || '');

            // Update source badge
            if (scheduleDrugSource === 'ward_stock') {
                $('#administer-source-badge').html(
                    '<span class="badge bg-warning text-dark"><i class="mdi mdi-hospital-box"></i> Ward Stock</span>' +
                    '<small class="text-muted ms-2">Stock will be deducted from selected store</small>'
                );
                // Show ward stock section + load stores
                $('#administer-ward-stock-section').removeClass('d-none');
                loadAdministerStores(productId);
            } else {
                $('#administer-source-badge').html(
                    '<span class="badge bg-info"><i class="mdi mdi-account-heart"></i> Patient\'s Own</span>' +
                    '<small class="text-muted ms-2">Patient-supplied medication</small>'
                );
                // Show patient's own section
                $('#administer-patient-own-section').removeClass('d-none');
            }
        } else {
            // Â§6.5: Pharmacy dispensed â€” use enriched _rxLookup
            var posrId = selectedMedication;
            var rx = window._rxLookup ? window._rxLookup[posrId] : null;

            if (rx) {
                productId = rx.product_id || '';
                var productName = rx.product_name || 'N/A';
                $('#administer_product_id').val(productId);
                $('#administer_product_request_id').val(rx.product_request_id || '');
                $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + productName);
            } else {
                var fallbackRx = window._rxLookup ? window._rxLookup[selectedMedication] : null;
                productId = fallbackRx ? (fallbackRx.product_id || '') : '';
                var productName = fallbackRx ? (fallbackRx.product_name || 'N/A') : 'N/A';
                $('#administer_product_id').val(productId);
                $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + productName);
            }

            setDrugSource('pharmacy_dispensed');
            $('#administer_external_drug_name').val('');

            // Restore pharmacy dispensed badge
            $('#administer-source-badge').html(
                '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>' +
                '<small class="text-muted ms-2">Source is determined by the selected medication</small>'
            );

            // Show pharmacy qty section and populate remaining info
            $('#administer-pharmacy-qty-section').removeClass('d-none');
            $('#administer_pharmacy_qty').val(1);
            if (rx) {
                var qtyAdmin = rx.qty_administered || 0;
                var remaining = rx.remaining_doses || 0;
                $('#administer-remaining-info').html(
                    '<i class="mdi mdi-pill"></i> Prescribed: <strong>' + (rx.qty_prescribed || 0) + '</strong>' +
                    ' &nbsp;|&nbsp; Administered: <strong>' + qtyAdmin + '</strong>' +
                    ' &nbsp;|&nbsp; Remaining: <strong class="' + (remaining <= 0 ? 'text-danger' : 'text-success') + '">' + remaining + '</strong>'
                );
            } else {
                $('#administer-remaining-info').html('');
            }
        }

        const scheduledTime = new Date(schedule.scheduled_time);
        $('#administer-scheduled-time').html('<i class="mdi mdi-clock-outline"></i> Scheduled: ' + formatDateTime(scheduledTime));
        $('#administered_at').val(new Date().toISOString().slice(0, 16));
        $('#administered_dose').val(schedule.dose);
        $('#administered_route').val(schedule.route);
        $('#administered_note').val('');
        $('#administer_store_id').val('');
        $('#administer_qty').val(1);
        $('#administer_external_qty').val(1);

        // Show scheduled/administered counts
        var counts = window._currentMedCounts || { totalScheduled: 0, totalAdministered: 0 };
        $('#administer-counts-info').html(
            '<i class="mdi mdi-calendar-clock"></i> ' + counts.totalScheduled + ' scheduled &nbsp;|&nbsp; ' +
            '<i class="mdi mdi-check-circle"></i> ' + counts.totalAdministered + ' administered'
        );

        // Explicitly show modal (data-bs-toggle is used for tooltip, not modal)
        $('#administerModal').modal('show');
    }
});

// Load stores into the administer modal's ward stock store select
function loadAdministerStores(productId) {
    var $select = $('#administer_store_id');
    $select.find('option:not(:first)').remove();
    $.ajax({
        url: "{{ url('pharmacy-workbench/stores') }}",
        type: 'GET',
        success: function(stores) {
            stores.forEach(function(store) {
                $select.append('<option value="' + store.id + '">' + store.store_name +
                    (store.location ? ' (' + store.location + ')' : '') + '</option>');
            });
        }
    });
}

// Store selection change in administer modal - show stock for selected store
$(document).on('change', '#administer_store_id', function() {
    const storeId = $(this).val();
    const productId = $('#administer_product_id').val();
    const $stockInfo = $('#administer-stock-info');
    const $stockQty = $('#administer-stock-qty');
    const $stockWarning = $('#administer-stock-warning');

    if (!storeId) {
        $stockInfo.addClass('d-none');
        $stockWarning.addClass('d-none');
        return;
    }

    $stockInfo.removeClass('d-none');
    $stockQty.removeClass('bg-success bg-warning bg-danger').addClass('bg-secondary').text('Loading...');

    // Fetch stock for this product/store combination
    if (productId && storeId) {
        $.ajax({
            url: '/nursing-workbench/product-batches',
            method: 'GET',
            data: { product_id: productId, store_id: storeId },
            success: function(response) {
                if (response.success) {
                    const totalAvailable = response.total_available || 0;
                    $stockQty.text(totalAvailable + ' units');
                    if (totalAvailable <= 0) {
                        $stockQty.removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                        $stockWarning.removeClass('d-none');
                        $('#administer-stock-warning-text').text('No stock available in this store!');
                    } else if (totalAvailable < 5) {
                        $stockQty.removeClass('bg-secondary bg-success bg-danger').addClass('bg-warning');
                        $stockWarning.addClass('d-none');
                    } else {
                        $stockQty.removeClass('bg-secondary bg-warning bg-danger').addClass('bg-success');
                        $stockWarning.addClass('d-none');
                    }
                    administerStockData = { stores: [{ store_id: storeId, quantity: totalAvailable }] };
                } else {
                    $stockQty.text('0 units').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                    $stockWarning.removeClass('d-none');
                    $('#administer-stock-warning-text').text('Failed to check stock');
                }
            },
            error: function() {
                $stockQty.text('Error').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                $stockWarning.removeClass('d-none');
                $('#administer-stock-warning-text').text('Failed to check stock availability');
            }
        });
    }
});

// Administer form submit â€” routes to correct endpoint based on drug source
$(document).on('submit', '#administerForm', function(e) {
    e.preventDefault();

    const drugSource = $('#administer_drug_source').val() || 'pharmacy_dispensed';
    const isDirect = (drugSource === 'ward_stock' || drugSource === 'patient_own');

    // â”€â”€ Direct entry (ward_stock / patient_own): submit to administerDirect â”€â”€
    if (isDirect) {
        // Validate required fields per source
        if (drugSource === 'ward_stock') {
            var storeId = $('#administer_store_id').val();
            if (!storeId) {
                toastr.error('Please select a dispensing store');
                $('#administer_store_id').focus();
                return;
            }
            var qty = parseInt($('#administer_qty').val()) || 0;
            if (qty < 1) {
                toastr.error('Quantity must be at least 1');
                $('#administer_qty').focus();
                return;
            }
        } else if (drugSource === 'patient_own') {
            var extQty = parseFloat($('#administer_external_qty').val()) || 0;
            if (extQty <= 0) {
                toastr.error('Quantity must be greater than 0');
                $('#administer_external_qty').focus();
                return;
            }
        }

        var $btn = $('#administerSubmitBtn');
        var originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Administering...');

        var directUrl = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);

        // Build form data â€” collect what administerDirect expects
        var formData = {
            _token: CSRF_TOKEN,
            drug_source: drugSource,
            schedule_id: $('#administer_schedule_id').val(),
            administered_at: $('#administered_at').val(),
            administered_dose: $('#administered_dose').val(),
            route: $('#administered_route').val(),
            note: $('#administered_note').val() || ''
        };

        if (drugSource === 'ward_stock') {
            formData.product_id = $('#administer_product_id').val();
            formData.store_id = $('#administer_store_id').val();
            formData.qty = $('#administer_qty').val();
        } else {
            formData.external_drug_name = $('#administer_external_drug_name').val();
            formData.external_qty = $('#administer_external_qty').val();
        }

        $.ajax({
            url: directUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                if (response.success) {
                    toastr.success(response.message || 'Medication administered successfully.');
                    try { bootstrap.Modal.getOrCreateInstance('#administerModal').hide(); } catch(e) {}
                    if (selectedMedication) {
                        loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                    }
                } else {
                    toastr.error(response.message || 'Failed to administer.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                var msg = xhr.responseJSON?.message || 'Failed to administer.';
                if (xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                }
                toastr.error(msg);
            }
        });
        return; // Don't fall through to pharmacy_dispensed flow
    }

    // â”€â”€ Pharmacy dispensed: standard flow (no store needed â€” stock from dispensed POSR) â”€â”€
    var $btn = $('#administerSubmitBtn');
    var originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Administering...');

    // Validate pharmacy qty
    var pharmacyQty = parseFloat($('#administer_pharmacy_qty').val()) || 0;
    if (pharmacyQty <= 0) {
        toastr.error('Quantity must be greater than 0');
        $btn.prop('disabled', false).html(originalBtnHtml);
        $('#administer_pharmacy_qty').focus();
        return;
    }

    $.ajax({
        url: medicationChartAdministerRoute,
        type: 'POST',
        data: $('#administerForm').serialize() + '&qty=' + pharmacyQty + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Medication administered successfully.');
                try { bootstrap.Modal.getOrCreateInstance('#administerModal').hide(); } catch(e) {}
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to administer.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            var msg = xhr.responseJSON?.message || 'Failed to administer.';
            if (xhr.responseJSON?.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
            }
            toastr.error(msg);
        }
    });
});

// Administration Details Modal - show details when clicking administered badge
$(document).on('click', '[data-bs-target="#adminDetailsModal"]', function() {
    const adminId = $(this).data('admin-id');
    const admin = currentAdministrations.find(a => a.id == adminId);

    if (admin) {
        // Get medication name from dropdown option data (works for both POSR and direct entries)
        let productName = 'Medication';
        const $detailsSelectedOpt = $('#drug-select').find('option:selected');
        const detailsDirectEntry = $detailsSelectedOpt.data('direct-entry');
        if (detailsDirectEntry) {
            productName = detailsDirectEntry.product_name || detailsDirectEntry.external_drug_name || 'Direct Entry';
        } else {
            const detailsRx = $detailsSelectedOpt.data('rx');
            if (detailsRx) {
                productName = detailsRx.product_name || 'Medication';
            }
        }
        const adminTime = new Date(admin.administered_at);

        let detailsHtml = `
            <div class="mb-3">
                <h6 class="text-primary fw-bold"><i class="mdi mdi-pill"></i> ${productName}</h6>
                <small class="text-muted">
                    <i class="mdi mdi-calendar-clock"></i> ${(window._currentMedCounts || {}).totalScheduled || 0} scheduled
                    &nbsp;|&nbsp;
                    <i class="mdi mdi-check-circle"></i> ${(window._currentMedCounts || {}).totalAdministered || 0} administered
                </small>
            </div>
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="text-muted" width="40%"><i class="mdi mdi-clock-outline"></i> Administered At:</td>
                    <td class="fw-bold">${formatDateTime(adminTime)}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-medical-bag"></i> Dose:</td>
                    <td class="fw-bold">${admin.dose || '-'}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-pill"></i> Qty:</td>
                    <td class="fw-bold">${admin.qty || admin.external_qty || 1}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-routes"></i> Route:</td>
                    <td class="fw-bold">${admin.route || '-'}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-account"></i> Administered By:</td>
                    <td class="fw-bold">${admin.administered_by_name || admin.administeredBy?.name || 'Unknown'}</td>
                </tr>`;

        if (admin.store_name) {
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-store"></i> Dispensed From:</td>
                    <td class="fw-bold">${admin.store_name}</td>
                </tr>`;
        }

        if (admin.drug_source && admin.drug_source !== 'pharmacy_dispensed') {
            var sourceBadge = admin.drug_source === 'ward_stock'
                ? '<span class="badge bg-warning text-dark">Ward Stock</span>'
                : '<span class="badge bg-info">Patient\'s Own</span>';
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-tag"></i> Source:</td>
                    <td>${sourceBadge}</td>
                </tr>`;
        }

        if (admin.comment) {
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-note-text"></i> Notes:</td>
                    <td>${admin.comment}</td>
                </tr>`;
        }

        if (admin.edited_at) {
            detailsHtml += `
                <tr class="table-info">
                    <td class="text-muted"><i class="mdi mdi-pencil"></i> Edited At:</td>
                    <td>${formatDateTime(new Date(admin.edited_at))}</td>
                </tr>
                <tr class="table-info">
                    <td class="text-muted"><i class="mdi mdi-account-edit"></i> Edited By:</td>
                    <td>${admin.edited_by_name || admin.editedBy?.name || '-'}</td>
                </tr>`;
        }

        if (admin.deleted_at) {
            detailsHtml += `
                <tr class="table-danger">
                    <td class="text-muted"><i class="mdi mdi-delete"></i> Deleted At:</td>
                    <td>${formatDateTime(new Date(admin.deleted_at))}</td>
                </tr>
                <tr class="table-danger">
                    <td class="text-muted"><i class="mdi mdi-account-remove"></i> Deleted By:</td>
                    <td>${admin.deleted_by_name || admin.deletedBy?.name || '-'}</td>
                </tr>`;
        }

        detailsHtml += '</table>';

        $('#admin-details-content').html(detailsHtml);

        // Store admin id for edit/delete buttons
        $('#edit-admin-btn').data('admin-id', adminId);
        $('#delete-admin-btn').data('admin-id', adminId);

        // Show/hide edit/delete buttons based on status
        if (admin.deleted_at) {
            $('#edit-admin-btn, #delete-admin-btn').hide();
        } else {
            $('#edit-admin-btn, #delete-admin-btn').show();
        }

        // Explicitly show modal (data-bs-toggle is used for tooltip, not modal)
        $('#adminDetailsModal').modal('show');
    } else {
        $('#admin-details-content').html('<div class="alert alert-warning">Administration details not found.</div>');
    }
});

// Edit administration button handler
$(document).on('click', '#edit-admin-btn', function() {
    const adminId = $(this).data('admin-id');
    const admin = currentAdministrations.find(a => a.id == adminId);

    if (admin) {
        // Close details modal and open edit modal
        $('#adminDetailsModal').modal('hide');

        // Populate edit form
        $('#edit_admin_id').val(adminId);
        $('#edit_administered_at').val(admin.administered_at.replace(' ', 'T').slice(0, 16));
        $('#edit_dose').val(admin.dose);
        $('#edit_route').val(admin.route);
        $('#edit_comment').val(admin.comment || '');
        $('#edit_reason').val('');

        $('#editAdminModal').modal('show');
    }
});

// Delete administration button handler
$(document).on('click', '#delete-admin-btn', function() {
    const adminId = $(this).data('admin-id');

    if (adminId) {
        // Close details modal and open delete modal
        $('#adminDetailsModal').modal('hide');

        $('#delete_admin_id').val(adminId);
        $('#delete_reason').val('');

        $('#deleteAdminModal').modal('show');
    }
});

// Edit Administration Form Submit
$(document).on('submit', '#editAdminForm', function(e) {
    e.preventDefault();

    const $btn = $('#editAdminSubmitBtn');
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

    $.ajax({
        url: medicationChartEditRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Administration updated successfully.');
                $('#editAdminModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to update administration.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to update administration.');
        }
    });
});

// Delete Administration Form Submit
$(document).on('submit', '#deleteAdminForm', function(e) {
    e.preventDefault();

    const $btn = $('#deleteAdminSubmitBtn');
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting...');

    $.ajax({
        url: medicationChartDeleteRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Administration deleted successfully.');
                $('#deleteAdminModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to delete administration.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to delete administration.');
        }
    });
});

// Repeat type toggle for days selector
$(document).on('change', 'input[name="repeat_type"]', function() {
    if ($(this).val() === 'selected') {
        $('#days-selector').show();
    } else {
        $('#days-selector').hide();
    }
});

// =============================================
// Â§6.2â€“6.4: WARD STOCK & PATIENT'S OWN â€” DIRECT ADMINISTRATION
// =============================================

// â”€â”€ Button click handlers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$('#btn-add-patient-own').on('click', function() {
    // Reset form
    $('#patientOwnForm')[0].reset();
    setCurrentDateTime('po_administered_at');
    var modal = new bootstrap.Modal(document.getElementById('patientOwnModal'));
    modal.show();
});

$('#btn-add-ward-stock').on('click', function() {
    // Reset form
    $('#wardStockForm')[0].reset();
    $('#ws_product_id').val('');
    $('#ws_product_info').hide();
    $('#ws_product_results').hide();
    $('#ws_product_search').val('');
    setCurrentDateTime('ws_administered_at');

    // Load stores
    loadWardStores();

    var modal = new bootstrap.Modal(document.getElementById('wardStockModal'));
    modal.show();
});

// â”€â”€ Patient's Own Modal Submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$('#patientOwnForm').on('submit', function(e) {
    e.preventDefault();

    var $btn = $('#patientOwnSubmitBtn');
    var $spinner = $btn.find('.spinner-border');
    $btn.prop('disabled', true);
    $spinner.removeClass('d-none');

    var url = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);
    var formData = {
        drug_source: 'patient_own',
        external_drug_name: $('#po_drug_name').val(),
        external_qty: $('#po_qty').val(),
        external_batch_number: $('#po_batch').val(),
        external_expiry_date: $('#po_expiry').val(),
        external_source_note: $('#po_source_note').val(),
        administered_dose: $('#po_dose').val(),
        route: $('#po_route').val(),
        administered_at: $('#po_administered_at').val(),
        note: $('#po_comment').val()
    };

    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        data: formData,
        success: function(resp) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            toastr.success(resp.message || 'Patient\'s own drug administered successfully');
            try {
                var modalEl = document.getElementById('patientOwnModal');
                var modalInst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInst.hide();
            } catch(e) { $('#patientOwnModal').modal('hide'); }
            // Reload medication list to show the new entry
            if (typeof loadMedicationsList === 'function') loadMedicationsList();
        },
        error: function(xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = 'Failed to administer';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            toastr.error(msg);
        }
    });
});

// â”€â”€ Ward Stock: Load stores â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function loadWardStores() {
    $.ajax({
        url: "{{ url('pharmacy-workbench/stores') }}",
        type: 'GET',
        success: function(stores) {
            var $select = $('#ws_store');
            $select.find('option:not(:first)').remove();
            stores.forEach(function(store) {
                $select.append('<option value="' + store.id + '">' + store.store_name + (store.location ? ' (' + store.location + ')' : '') + '</option>');
            });
        },
        error: function() {
            toastr.error('Failed to load stores');
        }
    });
}

// â”€â”€ Ward Stock: Product search â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
var wsSearchTimeout;
$('#ws_product_search').on('input', function() {
    var query = $(this).val();
    clearTimeout(wsSearchTimeout);

    if (query.length < 2) {
        $('#ws_product_results').hide();
        return;
    }

    wsSearchTimeout = setTimeout(function() {
        $.ajax({
            url: "{{ url('live-search-products') }}",
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: PATIENT_ID },
            success: function(data) {
                var $results = $('#ws_product_results');
                $results.html('');

                if (!data || data.length === 0) {
                    $results.html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    var name = item.product_name || 'Unknown';
                    var code = item.product_code || '';
                    var qty = (item.stock && item.stock.current_quantity !== undefined) ? item.stock.current_quantity : 0;
                    var price = (item.price && item.price.current_sale_price !== undefined) ? item.price.current_sale_price : 0;
                    var qtyClass = qty > 0 ? 'text-success' : 'text-danger';

                    var li = '<li class="list-group-item list-group-item-action" style="cursor:pointer;" ' +
                        'data-id="' + item.id + '" ' +
                        'data-name="' + name + '" ' +
                        'data-code="' + code + '" ' +
                        'data-qty="' + qty + '" ' +
                        'data-price="' + price + '">' +
                        '<div class="d-flex justify-content-between">' +
                        '<div><strong>' + name + '</strong> <small class="text-muted">[' + code + ']</small></div>' +
                        '<div class="text-end"><span class="' + qtyClass + '"><strong>' + qty + '</strong> avail.</span><br><small>â‚¦' + Number(price).toLocaleString() + '</small></div>' +
                        '</div></li>';
                    $results.append(li);
                });
                $results.show();
            },
            error: function() {
                $('#ws_product_results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// â”€â”€ Ward Stock: Select product from search results â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$(document).on('click', '#ws_product_results li[data-id]', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    var code = $(this).data('code');
    var qty = $(this).data('qty');
    var price = $(this).data('price');

    $('#ws_product_id').val(id);
    $('#ws_product_search').val(name);
    $('#ws_product_name').text(name);
    $('#ws_product_code').text('[' + code + ']');
    $('#ws_product_price').text('â‚¦' + Number(price).toLocaleString());
    $('#ws_product_results').hide();

    // Show stock for the selected store
    var storeId = $('#ws_store').val();
    if (storeId) {
        updateWsStockDisplay(id, storeId);
    } else {
        $('#ws_available_stock').text(qty + ' global stock').removeClass('bg-success bg-danger').addClass('bg-info');
    }

    $('#ws_product_info').slideDown(200);
});

// â”€â”€ Ward Stock: Update stock when store changes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$('#ws_store').on('change', function() {
    var productId = $('#ws_product_id').val();
    var storeId = $(this).val();
    if (productId && storeId) {
        updateWsStockDisplay(productId, storeId);
    }
});

function updateWsStockDisplay(productId, storeId) {
    $.ajax({
        url: '/pharmacy-workbench/product/' + productId + '/stock',
        method: 'GET',
        success: function(resp) {
            var storeStock = (resp.stores || []).find(function(s) { return s.store_id == storeId; });
            var available = storeStock ? storeStock.quantity : 0;
            var badge = $('#ws_available_stock');
            badge.text(available + ' in store');
            if (available > 0) {
                badge.removeClass('bg-danger bg-info').addClass('bg-success');
            } else {
                badge.removeClass('bg-success bg-info').addClass('bg-danger');
            }
        },
        error: function() {
            $('#ws_available_stock').text('? stock').removeClass('bg-success bg-danger').addClass('bg-warning');
        }
    });
}

// Hide product results when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#ws_product_search, #ws_product_results').length) {
        $('#ws_product_results').hide();
    }
});

// â”€â”€ Ward Stock Modal Submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$('#wardStockForm').on('submit', function(e) {
    e.preventDefault();

    var productId = $('#ws_product_id').val();
    if (!productId) {
        toastr.warning('Please search and select a product');
        $('#ws_product_search').focus();
        return;
    }

    var storeId = $('#ws_store').val();
    if (!storeId) {
        toastr.warning('Please select a ward/store');
        $('#ws_store').focus();
        return;
    }

    var $btn = $('#wardStockSubmitBtn');
    var $spinner = $btn.find('.spinner-border');
    $btn.prop('disabled', true);
    $spinner.removeClass('d-none');

    var url = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);
    var formData = {
        drug_source: 'ward_stock',
        product_id: productId,
        store_id: storeId,
        qty: $('#ws_qty').val(),
        administered_dose: $('#ws_dose').val(),
        route: $('#ws_route').val(),
        administered_at: $('#ws_administered_at').val(),
        note: $('#ws_comment').val(),
        bill_patient: $('#ws_bill_patient').is(':checked') ? 1 : 0
    };

    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        data: formData,
        success: function(resp) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = resp.message || 'Ward stock drug administered successfully';
            if (formData.bill_patient) {
                msg += ' (billed)';
            }
            toastr.success(msg);
            try {
                var modalEl = document.getElementById('wardStockModal');
                var modalInst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInst.hide();
            } catch(e) { $('#wardStockModal').modal('hide'); }
            // Reload to show new entry
            if (typeof loadMedicationsList === 'function') loadMedicationsList();
        },
        error: function(xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = 'Failed to administer';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            toastr.error(msg);
        }
    });
});

// =============================================
// INTAKE & OUTPUT CHART FUNCTIONS
// =============================================

function loadFluidPeriods() {
    console.log('loadFluidPeriods called, PATIENT_ID:', PATIENT_ID);
    if (!PATIENT_ID) {
        console.log('No PATIENT_ID, returning');
        return;
    }

    const url = intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID);
    const startDate = $('#fluid_start_date').val();
    const endDate = $('#fluid_end_date').val();
    console.log('Fetching from:', url, 'with dates:', startDate, endDate);

    $.ajax({
        url: url,
        type: 'GET',
        data: { type: 'fluid', start_date: startDate, end_date: endDate },
        success: function(data) {
            console.log('loadFluidPeriods response:', data);
            fluidPeriods = data.fluidPeriods || [];
            console.log('fluidPeriods array:', fluidPeriods);
            renderFluidPeriods();
        },
        error: function(xhr) {
            console.error('loadFluidPeriods error:', xhr);
            $('#fluid-periods-list').html('<p class="text-danger">Failed to load fluid data.</p>');
        }
    });
}

function loadSolidPeriods() {
    if (!PATIENT_ID) return;

    const url = intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID);
    const startDate = $('#solid_start_date').val();
    const endDate = $('#solid_end_date').val();

    $.ajax({
        url: url,
        type: 'GET',
        data: { type: 'solid', start_date: startDate, end_date: endDate },
        success: function(data) {
            solidPeriods = data.solidPeriods || [];
            renderSolidPeriods();
        },
        error: function() {
            $('#solid-periods-list').html('<p class="text-danger">Failed to load solid data.</p>');
        }
    });
}

function renderFluidPeriods() {
    console.log('renderFluidPeriods called, fluidPeriods:', fluidPeriods);
    if (fluidPeriods.length === 0) {
        console.log('No periods, showing empty message');
        $('#fluid-periods-list').html('<p class="text-muted">No fluid intake/output periods found. Click "Start New Period" to begin.</p>');
        return;
    }

    let html = '';
    fluidPeriods.forEach(period => {
        console.log('Rendering period:', period);
        const isActive = !period.ended_at;
        const statusBadge = isActive ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Ended</span>';
        const totalIntake = period.total_intake || 0;
        const totalOutput = period.total_output || 0;
        const balance = totalIntake - totalOutput;

        // Build records table
        let recordsHtml = '';
        if (period.records && period.records.length > 0) {
            recordsHtml = `
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Nurse</th>
                            </tr>
                        </thead>
                        <tbody>`;
            period.records.forEach(record => {
                const recordTime = new Date(record.recorded_at);
                const typeBadge = record.type === 'intake'
                    ? '<span class="badge bg-primary">Intake</span>'
                    : '<span class="badge bg-warning text-dark">Output</span>';
                const deleteBtn = record.can_delete
                    ? `<button class="btn btn-sm btn-outline-danger delete-io-record-btn" data-record-id="${record.id}" data-type="fluid" title="Delete record"><i class="mdi mdi-delete"></i></button>`
                    : '';
                recordsHtml += `
                    <tr>
                        <td><small>${formatDateTime(recordTime)}</small></td>
                        <td>${typeBadge}</td>
                        <td>${record.amount} ml</td>
                        <td>${record.description || '-'}</td>
                        <td><small>${record.nurse_name || 'Unknown'}</small></td>
                        <td class="text-end">${deleteBtn}</td>
                    </tr>`;
            });
            recordsHtml += '</tbody></table></div>';
        } else {
            recordsHtml = '<div class="text-muted small mt-2"><em>No records yet. Click "Add Record" to add intake/output.</em></div>';
        }

        html += `
            <div class="card-modern period-card mb-3 ${isActive ? 'border-success' : ''}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><strong>Period:</strong> ${formatDateTime(new Date(period.started_at))} ${statusBadge}</span>
                    <div>
                        ${isActive ? `<button class="btn btn-sm btn-primary add-fluid-record-btn" data-period-id="${period.id}"><i class="mdi mdi-plus"></i> Add Record</button>
                        <button class="btn btn-sm btn-warning end-fluid-period-btn" data-period-id="${period.id}"><i class="mdi mdi-stop"></i> End Period</button>` : ''}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-primary mb-0"><i class="mdi mdi-water"></i> Intake: ${totalIntake} ml</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-warning mb-0"><i class="mdi mdi-water-off"></i> Output: ${totalOutput} ml</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-0"><strong>Balance:</strong> <span class="${balance >= 0 ? 'text-success' : 'text-danger'}">${balance} ml</span></h6>
                        </div>
                    </div>
                    ${recordsHtml}
                </div>
            </div>`;
    });

    $('#fluid-periods-list').html(html);
}

function renderSolidPeriods() {
    if (solidPeriods.length === 0) {
        $('#solid-periods-list').html('<p class="text-muted">No solid intake/output periods found. Click "Start New Period" to begin.</p>');
        return;
    }

    let html = '';
    solidPeriods.forEach(period => {
        const isActive = !period.ended_at;
        const statusBadge = isActive ? '<span class="badge bg-info">Active</span>' : '<span class="badge bg-secondary">Ended</span>';
        const totalIntake = period.total_intake || 0;
        const totalOutput = period.total_output || 0;
        const balance = totalIntake - totalOutput;

        // Build records table
        let recordsHtml = '';
        if (period.records && period.records.length > 0) {
            recordsHtml = `
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Nurse</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>`;
            period.records.forEach(record => {
                const recordTime = new Date(record.recorded_at);
                const typeBadge = record.type === 'intake'
                    ? '<span class="badge bg-success">Intake</span>'
                    : '<span class="badge bg-danger">Output</span>';
                const deleteBtn = record.can_delete
                    ? `<button class="btn btn-sm btn-outline-danger delete-io-record-btn" data-record-id="${record.id}" data-type="solid" title="Delete record"><i class="mdi mdi-delete"></i></button>`
                    : '';
                recordsHtml += `
                    <tr>
                        <td><small>${formatDateTime(recordTime)}</small></td>
                        <td>${typeBadge}</td>
                        <td>${record.amount} g</td>
                        <td>${record.description || '-'}</td>
                        <td><small>${record.nurse_name || 'Unknown'}</small></td>
                        <td class="text-end">${deleteBtn}</td>
                    </tr>`;
            });
            recordsHtml += '</tbody></table></div>';
        } else {
            recordsHtml = '<div class="text-muted small mt-2"><em>No records yet. Click "Add Record" to add intake/output.</em></div>';
        }

        html += `
            <div class="card-modern period-card mb-3 ${isActive ? 'border-info' : ''}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><strong>Period:</strong> ${formatDateTime(new Date(period.started_at))} ${statusBadge}</span>
                    <div>
                        ${isActive ? `<button class="btn btn-sm btn-success add-solid-record-btn" data-period-id="${period.id}"><i class="mdi mdi-plus"></i> Add Record</button>
                        <button class="btn btn-sm btn-warning end-solid-period-btn" data-period-id="${period.id}"><i class="mdi mdi-stop"></i> End Period</button>` : ''}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-success mb-0"><i class="mdi mdi-food-apple"></i> Intake: ${totalIntake} g</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-danger mb-0"><i class="mdi mdi-delete-empty"></i> Output: ${totalOutput} g</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-0"><strong>Balance:</strong> <span class="${balance >= 0 ? 'text-success' : 'text-danger'}">${balance} g</span></h6>
                        </div>
                    </div>
                    ${recordsHtml}
                </div>
            </div>`;
    });

    $('#solid-periods-list').html(html);
}

// Fluid filter buttons
$(document).on('click', '#fluid_apply_filter_btn', function() {
    loadFluidPeriods();
});

$(document).on('click', '#fluid_reset_filter_btn', function() {
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    $('#fluid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#fluid_end_date').val(today.toISOString().split('T')[0]);
    loadFluidPeriods();
});

// Solid filter buttons
$(document).on('click', '#solid_apply_filter_btn', function() {
    loadSolidPeriods();
});

$(document).on('click', '#solid_reset_filter_btn', function() {
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    $('#solid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#solid_end_date').val(today.toISOString().split('T')[0]);
    loadSolidPeriods();
});

// Start fluid period
$(document).on('click', '#startFluidPeriodBtn', function() {
    console.log('startFluidPeriodBtn clicked, PATIENT_ID:', PATIENT_ID);
    if (!PATIENT_ID) {
        toastr.warning('Please select a patient first.');
        return;
    }

    $.ajax({
        url: intakeOutputChartStartRoute,
        type: 'POST',
        data: { patient_id: PATIENT_ID, type: 'fluid', _token: CSRF_TOKEN },
        success: function(response) {
            console.log('Start period response:', response);
            if (response.success) {
                toastr.success('Fluid period started.');
                console.log('Calling loadFluidPeriods...');
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to start period.');
            }
        },
        error: function(xhr) {
            console.error('Start period error:', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to start period.');
        }
    });
});

// Start solid period
$(document).on('click', '#startSolidPeriodBtn', function() {
    if (!PATIENT_ID) {
        toastr.warning('Please select a patient first.');
        return;
    }

    $.ajax({
        url: intakeOutputChartStartRoute,
        type: 'POST',
        data: { patient_id: PATIENT_ID, type: 'solid', _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Solid period started.');
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to start period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to start period.');
        }
    });
});

// End fluid period
$(document).on('click', '.end-fluid-period-btn', function() {
    const periodId = $(this).data('period-id');
    if (!confirm('End this fluid period?')) return;

    $.ajax({
        url: intakeOutputChartEndRoute,
        type: 'POST',
        data: { period_id: periodId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Fluid period ended.');
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to end period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to end period.');
        }
    });
});

// End solid period
$(document).on('click', '.end-solid-period-btn', function() {
    const periodId = $(this).data('period-id');
    if (!confirm('End this solid period?')) return;

    $.ajax({
        url: intakeOutputChartEndRoute,
        type: 'POST',
        data: { period_id: periodId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Solid period ended.');
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to end period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to end period.');
        }
    });
});

// Add fluid record
$(document).on('click', '.add-fluid-record-btn', function() {
    currentFluidPeriodId = $(this).data('period-id');
    $('#fluid_period_id').val(currentFluidPeriodId);
    $('#fluidRecordModal').modal('show');
});

// Add solid record
$(document).on('click', '.add-solid-record-btn', function() {
    currentSolidPeriodId = $(this).data('period-id');
    $('#solid_period_id').val(currentSolidPeriodId);
    $('#solidRecordModal').modal('show');
});

// Delete I/O record handler
$(document).on('click', '.delete-io-record-btn', function() {
    const recordId = $(this).data('record-id');
    const type = $(this).data('type');
    if (!recordId) return;
    if (!confirm('Delete this record? This cannot be undone.')) return;
    const url = intakeOutputChartDeleteRecordRoute.replace(':record', recordId);
    $.ajax({
        url: url,
        type: 'DELETE',
        data: { _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Record deleted.');
                if (type === 'fluid') {
                    loadFluidPeriods();
                } else {
                    loadSolidPeriods();
                }
            } else {
                toastr.error(response.message || 'Failed to delete record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to delete record.');
        }
    });
});

// Fluid record form submit
$(document).on('submit', '#fluidRecordForm', function(e) {
    e.preventDefault();

    $.ajax({
        url: intakeOutputChartRecordRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Fluid record added.');
                $('#fluidRecordModal').modal('hide');
                $('#fluidRecordForm')[0].reset();
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to add record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to add record.');
        }
    });
});

// Solid record form submit
$(document).on('submit', '#solidRecordForm', function(e) {
    e.preventDefault();

    $.ajax({
        url: intakeOutputChartRecordRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Solid record added.');
                $('#solidRecordModal').modal('hide');
                $('#solidRecordForm')[0].reset();
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to add record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to add record.');
        }
    });
});

// Edit Note Logic
let editNoteEditor;

function openEditNoteModal(btn) {
    const noteId = $(btn).data('id');
    // Get content from the rendered card in the table row
    // The button is in .card-footer, content is in .card-body > .note-content
    const card = $(btn).closest('.card');
    const content = card.find('.note-content').html();

    $('#edit-note-id').val(noteId);

    // Initialize Editor if not exists
    if (!editNoteEditor) {
        ClassicEditor
            .create(document.querySelector('#edit-note-editor'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            })
            .then(editor => {
                editNoteEditor = editor;
                editNoteEditor.setData(content);
                $('#editNoteModal').modal('show');
            })
            .catch(error => {
                console.error(error);
            });
    } else {
        editNoteEditor.setData(content);
        $('#editNoteModal').modal('show');
    }
}

function updatedNote() {
    const noteId = $('#edit-note-id').val();
    const content = editNoteEditor.getData();

    if (!content.trim()) {
        showNotification('error', 'Note content cannot be empty');
        return;
    }

    $.ajax({
        url: `/nursing-workbench/nursing-note/${noteId}`,
        type: 'PUT',
        data: {
            note: content,
            _token: CSRF_TOKEN
        },
        success: function(response) {
            showNotification('success', 'Note updated successfully');
            $('#editNoteModal').modal('hide');
            loadNotesHistory(currentPatient); // Reload table
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to update note');
        }
    });
}

// Edit Vital Logic
function openEditVitalModal(btn) {
    const vitalData = JSON.parse($(btn).attr('data-vital'));

    $('#edit-vital-id').val(vitalData.id);
    $('#edit-blood-pressure').val(vitalData.blood_pressure || '');
    $('#edit-temp').val(vitalData.temp || '');
    $('#edit-heart-rate').val(vitalData.heart_rate || '');
    $('#edit-resp-rate').val(vitalData.resp_rate || '');
    $('#edit-weight').val(vitalData.weight || '');
    $('#edit-height').val(vitalData.height || '');
    $('#edit-spo2').val(vitalData.spo2 || '');
    $('#edit-blood-sugar').val(vitalData.blood_sugar || '');
    $('#edit-other-notes').val(vitalData.other_notes || '');

    $('#editVitalModal').modal('show');
}

function updateVital() {
    const vitalId = $('#edit-vital-id').val();

    const data = {
        blood_pressure: $('#edit-blood-pressure').val(),
        temp: $('#edit-temp').val(),
        heart_rate: $('#edit-heart-rate').val(),
        resp_rate: $('#edit-resp-rate').val(),
        weight: $('#edit-weight').val(),
        height: $('#edit-height').val(),
        spo2: $('#edit-spo2').val(),
        blood_sugar: $('#edit-blood-sugar').val(),
        other_notes: $('#edit-other-notes').val(),
        _token: CSRF_TOKEN
    };

    $.ajax({
        url: `/nursing-workbench/vitals/${vitalId}`,
        type: 'PUT',
        data: data,
        success: function(response) {
            if (response.success) {
                showNotification('success', response.message || 'Vitals updated successfully');
                $('#editVitalModal').modal('hide');
                // Reload unified vitals history DataTable (if present)
                $('.unified-vitals-history-table').each(function() {
                    if ($.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable().ajax.reload(null, false);
                    }
                });
                // Reload nursing workbench vitals table (if present)
                if ($.fn.DataTable.isDataTable('#vitals-table')) {
                    $('#vitals-table').DataTable().ajax.reload(null, false);
                }
            } else {
                showNotification('error', response.message || 'Failed to update vitals');
            }
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to update vitals');
        }
    });
}

// =============================================
// SHIFT MANAGEMENT MODULE
// =============================================

const ShiftManager = {
    // State
    activeShift: null,
    shiftTimer: null,
    acknowledgedHandovers: [],
    currentHandoverDetail: null,
    forceEndShift: false,

    // Handover cards state
    handoverCurrentPage: 1,
    handoverPerPage: 12,
    handoverViewMode: 'cards', // 'cards' or 'list'

    // Routes
    routes: {
        check: '/nursing-workbench/shift/check',
        start: '/nursing-workbench/shift/start',
        end: '/nursing-workbench/shift/end',
        preview: '/nursing-workbench/shift/preview',
        pendingHandovers: '/nursing-workbench/shift/pending-handovers',
        wards: '/nursing-workbench/shift/wards',
        actions: '/nursing-workbench/shift/actions',
        handovers: '/nursing-workbench/handovers',
        handoverDetail: '/nursing-workbench/handover',
        acknowledge: '/nursing-workbench/handover/{id}/acknowledge',
        acknowledgeMultiple: '/nursing-workbench/handovers/acknowledge-multiple'
    },

    // Initialize
    init: function() {
        this.bindEvents();
        this.checkShiftStatus();
        this.loadWards();
        this.makeFabDraggable();
    },

    // Bind event handlers
    bindEvents: function() {
        const self = this;

        // Start shift button (on lock overlay)
        $('#start-shift-btn').on('click', function() {
            self.showStartShiftModal();
        });

        // Confirm start shift
        $('#confirm-start-shift-btn').on('click', function() {
            self.startShift();
        });

        // Ward select change - load handovers for that ward
        $('#shift-ward-select').on('change', function() {
            // Reset handovers when ward changes
            $('#shift-handovers-step').hide();
            self.acknowledgedHandovers = [];
        });

        // Check for handovers button
        $('#load-ward-handovers-btn').on('click', function() {
            self.loadHandoversForWard();
        });

        // FAB main button toggle
        $('#shift-fab-btn').on('click', function() {
            self.toggleFabActions();
        });

        // End shift button
        $('#end-shift-btn').on('click', function() {
            self.showEndShiftModal();
        });

        // Confirm end shift
        $('#confirm-end-shift-btn').on('click', function() {
            self.endShift();
        });

        // Load shift preview
        $('#load-shift-preview-btn').on('click', function() {
            self.loadShiftPreview();
        });

        // View shift summary
        $('#view-shift-summary').on('click', function() {
            self.showShiftSummary();
        });

        // View handovers button
        $('#view-handovers-btn').on('click', function() {
            self.showHandoversList();
        });

        // Quick action button for handovers
        $(document).on('click', '#quick-shift-handover', function() {
            self.showHandoversList();
        });

        // Add pending task
        $('#add-pending-task-btn').on('click', function() {
            self.addPendingTaskRow();
        });

        // Remove pending task
        $(document).on('click', '.remove-pending-task', function() {
            $(this).closest('.pending-task-row').remove();
        });

        // Apply handover filters
        $('#apply-handover-filters').on('click', function() {
            self.reloadHandoversCards();
        });

        // Clear handover filters
        $('#clear-handover-filters').on('click', function() {
            self.clearHandoverFilters();
        });

        // Per page change
        $('#handover-per-page').on('change', function() {
            self.handoverPerPage = parseInt($(this).val());
            self.handoverCurrentPage = 1;
            self.loadHandoversCards();
        });

        // Pagination click
        $(document).on('click', '#handover-pagination-list .page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && !$(this).parent().hasClass('disabled') && !$(this).parent().hasClass('active')) {
                self.handoverCurrentPage = page;
                self.loadHandoversCards();
            }
        });

        // View toggle - Cards
        $('#handover-view-cards').on('click', function() {
            self.handoverViewMode = 'cards';
            $(this).addClass('active');
            $('#handover-view-list').removeClass('active');
            self.loadHandoversCards();
        });

        // View toggle - List
        $('#handover-view-list').on('click', function() {
            self.handoverViewMode = 'list';
            $(this).addClass('active');
            $('#handover-view-cards').removeClass('active');
            self.loadHandoversCards();
        });

        // Search input enter key
        $('#handover-filter-search').on('keypress', function(e) {
            if (e.which === 13) {
                self.reloadHandoversCards();
            }
        });

        // View handover detail
        $(document).on('click', '.view-handover', function() {
            const id = $(this).data('id');
            self.showHandoverDetail(id);
        });

        // Acknowledge handover from cards/list
        $(document).on('click', '.acknowledge-handover', function() {
            const id = $(this).data('id');
            self.acknowledgeHandover(id);
        });

        // Acknowledge handover from detail modal
        $('#acknowledge-handover-detail-btn').on('click', function() {
            if (self.currentHandoverDetail) {
                self.acknowledgeHandover(self.currentHandoverDetail.id, true);
            }
        });

        // Load more handovers in start shift modal
        $('#load-more-handovers-btn').on('click', function() {
            self.loadPendingHandovers(48); // Load 48 hours
        });

        // Handover acknowledgment checkboxes
        $(document).on('change', '.handover-ack-checkbox', function() {
            const id = $(this).data('id');
            if ($(this).is(':checked')) {
                if (!self.acknowledgedHandovers.includes(id)) {
                    self.acknowledgedHandovers.push(id);
                }
            } else {
                self.acknowledgedHandovers = self.acknowledgedHandovers.filter(h => h !== id);
            }
        });

        // Restore overlay when start shift modal is closed without starting
        $('#startShiftModal').on('hidden.bs.modal', function() {
            if (!self.activeShift) {
                $('#shift-lock-overlay').removeClass('modal-open-hidden');
            }
        });
    },

    // Check shift status on load
    checkShiftStatus: function() {
        const self = this;

        $.ajax({
            url: this.routes.check,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    if (response.has_active_shift) {
                        self.activeShift = response.shift;

                        // Check if shift is older than 12 hours (43200 seconds)
                        const elapsedSeconds = response.shift.elapsed_seconds || 0;
                        const twelveHoursInSeconds = 12 * 60 * 60; // 43200

                        if (elapsedSeconds > twelveHoursInSeconds) {
                            // Shift is overdue - force end shift
                            self.showWorkbench();
                            self.startShiftTimer();
                            self.forceEndShiftModal(elapsedSeconds);
                        } else {
                            self.showWorkbench();
                            self.startShiftTimer();
                        }
                    } else {
                        self.showLockOverlay();
                    }
                }
            },
            error: function() {
                // If check fails, show workbench anyway (graceful degradation)
                self.showWorkbench();
            }
        });
    },

    // Force end shift modal for overdue shifts (>12 hours)
    forceEndShiftModal: function(elapsedSeconds) {
        const self = this;
        const hours = Math.floor(elapsedSeconds / 3600);
        const minutes = Math.floor((elapsedSeconds % 3600) / 60);
        const overdueHours = hours - 12;

        // Set flag to prevent modal dismissal
        this.forceEndShift = true;

        // Show the end shift modal with forced mode
        this.showEndShiftModal(true);

        // Add overdue warning to the modal
        const warningHtml = `
            <div id="overdue-shift-warning" class="alert alert-danger mb-4">
                <h5 class="alert-heading">
                    <i class="mdi mdi-alert-octagon"></i> Shift Overdue - Action Required
                </h5>
                <hr>
                <p class="mb-2">
                    <strong>Your shift has been running for ${hours} hours and ${minutes} minutes.</strong>
                </p>
                <p class="mb-2">
                    Standard shifts are 8-12 hours. Your shift is now <strong class="text-danger">${overdueHours > 0 ? overdueHours + ' hour(s)' : 'more than 12 hours'}</strong> overdue.
                </p>
                <p class="mb-0">
                    <i class="mdi mdi-information-outline"></i>
                    <strong>You must end this shift to continue.</strong> This ensures proper handover documentation and accurate shift records.
                    Please review your activities, add any critical notes, and end your shift.
                </p>
            </div>
        `;

        // Insert warning at the top of modal body if not already there
        if ($('#overdue-shift-warning').length === 0) {
            $('#endShiftModal .modal-body').prepend(warningHtml);
        }

        // Change modal header to indicate forced mode
        $('#endShiftModalLabel').html('<i class="mdi mdi-alert-octagon"></i> End Overdue Shift (Required)');

        // Hide cancel button and prevent modal dismiss
        $('#endShiftModal .btn-secondary[data-bs-dismiss="modal"]').hide();
        $('#endShiftModal .btn-close').hide();

        // Make modal static (cannot dismiss by clicking outside)
        $('#endShiftModal').attr('data-bs-backdrop', 'static');
        $('#endShiftModal').attr('data-bs-keyboard', 'false');

        // Update end shift button text
        $('#confirm-end-shift-btn').html('<i class="mdi mdi-stop-circle"></i> End Overdue Shift');

        toastr.warning('Your shift is overdue. Please end your shift and create a handover document.', 'Shift Overdue', {
            timeOut: 10000,
            closeButton: true
        });
    },

    // Reset end shift modal to normal mode
    resetEndShiftModal: function() {
        this.forceEndShift = false;

        // Remove overdue warning
        $('#overdue-shift-warning').remove();

        // Restore modal header
        $('#endShiftModalLabel').html('<i class="mdi mdi-stop-circle"></i> End Your Shift');

        // Show cancel button and close button
        $('#endShiftModal .btn-secondary[data-bs-dismiss="modal"]').show();
        $('#endShiftModal .btn-close').show();

        // Remove static backdrop
        $('#endShiftModal').removeAttr('data-bs-backdrop');
        $('#endShiftModal').removeAttr('data-bs-keyboard');

        // Reset end shift button text
        $('#confirm-end-shift-btn').html('<i class="mdi mdi-stop-circle"></i> End Shift');
    },

    // Show lock overlay
    showLockOverlay: function() {
        $('#shift-lock-overlay').show();
        $('#shift-control-fab').hide();
        // Show a simple count of handovers (user will see details after selecting ward in modal)
        this.loadPendingHandoversCount();
    },

    // Show workbench (unlock)
    showWorkbench: function() {
        $('#shift-lock-overlay').hide();
        $('#shift-control-fab').show();
        this.updateFabDisplay();
    },

    // Load pending handovers count for lock overlay (just shows count, not details)
    loadPendingHandoversCount: function() {
        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: { hours: 24 },
            success: function(response) {
                if (response.success && response.total_pending > 0) {
                    $('#pending-handovers-count').text(response.total_pending);
                    $('#pending-handovers-preview').show();
                    // Show simplified list
                    let html = '';
                    response.handovers.slice(0, 3).forEach(function(h) {
                        html += `
                            <div class="pending-handover-item ${h.has_critical_notes ? 'has-critical' : ''}">
                                <div>
                                    <strong>${h.created_by_name}</strong>
                                    <span class="text-muted">â”¬â•– ${h.created_at_ago}</span>
                                    ${h.has_critical_notes ? '<span class="badge badge-danger ml-2">Critical</span>' : ''}
                                </div>
                                <span>${h.shift_type_badge}</span>
                            </div>
                        `;
                    });
                    if (response.total_pending > 3) {
                        html += `<p class="text-center text-muted mt-2 mb-0">+${response.total_pending - 3} more</p>`;
                    }
                    $('#pending-handovers-list').html(html);
                } else {
                    $('#pending-handovers-preview').hide();
                }
            }
        });
    },

    // Load pending handovers preview for lock overlay (DEPRECATED - use loadPendingHandoversCount)
    loadPendingHandoversPreview: function() {
        const self = this;

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: { hours: 24 },
            success: function(response) {
                if (response.success && response.total_pending > 0) {
                    $('#pending-handovers-count').text(response.total_pending);

                    let html = '';
                    response.handovers.forEach(function(h) {
                        html += `
                            <div class="pending-handover-item ${h.has_critical_notes ? 'has-critical' : ''}">
                                <div>
                                    <strong>${h.created_by_name}</strong>
                                    <span class="text-muted">â”¬â•– ${h.created_at_ago}</span>
                                    ${h.has_critical_notes ? '<span class="badge badge-danger ml-2">Critical</span>' : ''}
                                </div>
                                <span>${h.shift_type_badge}</span>
                            </div>
                        `;
                    });
                    $('#pending-handovers-list').html(html);
                    $('#pending-handovers-preview').show();
                } else {
                    $('#pending-handovers-preview').hide();
                }
            }
        });
    },

    // Load wards for select
    loadWards: function() {
        $.ajax({
            url: this.routes.wards,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">All Wards (Floating)</option>';
                    response.wards.forEach(function(ward) {
                        options += `<option value="${ward.id}">${ward.name}</option>`;
                    });
                    $('#shift-ward-select, #handover-filter-ward').html(options);
                }
            }
        });
    },

    // Show start shift modal
    showStartShiftModal: function() {
        const self = this;
        this.acknowledgedHandovers = [];

        // Show modal with config step first, hide handovers until ward is selected
        $('#shift-config-step').show();
        $('#shift-handovers-step').hide();
        $('#start-shift-handovers-list').html('');

        // Temporarily hide overlay so modal is visible
        $('#shift-lock-overlay').addClass('modal-open-hidden');
        $('#startShiftModal').modal('show');
    },

    // Load handovers for selected ward (called when ward changes)
    loadHandoversForWard: function() {
        const self = this;
        const wardId = $('#shift-ward-select').val();

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: {
                ward_id: wardId || null,
                hours: 24
            },
            success: function(response) {
                if (response.success && response.handovers.length > 0) {
                    self.renderPendingHandovers(response.handovers);
                    $('#shift-handovers-step').show();
                } else {
                    $('#shift-handovers-step').hide();
                    $('#start-shift-handovers-list').html('<p class="text-muted text-center py-3">No pending handovers for this ward</p>');
                }
            },
            error: function() {
                $('#shift-handovers-step').hide();
            }
        });
    },

    // Render pending handovers in modal
    renderPendingHandovers: function(handovers) {
        const self = this;
        let html = '';
        handovers.forEach(function(h) {
            const isAcked = self.acknowledgedHandovers.includes(h.id);
            html += `
                <div class="handover-ack-item ${h.has_critical_notes ? 'critical' : ''}">
                    <div class="handover-ack-header">
                        <div>
                            ${h.shift_type_badge}
                            <span class="ml-2 text-muted">${h.ward_name}</span>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input handover-ack-checkbox"
                                data-id="${h.id}" ${isAcked ? 'checked' : ''}>
                            <label class="form-check-label">Acknowledged</label>
                        </div>
                    </div>
                    <div class="handover-ack-meta">
                        <strong>${h.created_by_name}</strong> â”¬â•– ${h.created_at_ago}
                    </div>
                    <div class="handover-ack-content mt-2">
                        ${h.summary_preview}
                        ${h.has_critical_notes ? '<div class="text-danger mt-1"><i class="mdi mdi-alert"></i> Contains critical notes</div>' : ''}
                    </div>
                    <div class="handover-ack-footer">
                        <span class="text-muted">${h.pending_tasks_count} pending task(s)</span>
                        <button class="btn btn-sm btn-outline-info view-handover" data-id="${h.id}">
                            <i class="mdi mdi-eye"></i> View Full
                        </button>
                    </div>
                </div>
            `;
        });
        $('#start-shift-handovers-list').html(html);
    },

    // Load pending handovers for acknowledgment
    loadPendingHandovers: function(hours = 24) {
        const self = this;
        const wardId = $('#shift-ward-select').val();

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: {
                ward_id: wardId,
                hours: hours
            },
            success: function(response) {
                if (response.success && response.handovers.length > 0) {
                    self.renderPendingHandovers(response.handovers);
                    $('#shift-handovers-step').show();
                } else {
                    $('#shift-handovers-step').hide();
                }
            }
        });
    },

    // Start shift
    startShift: function() {
        const self = this;
        const wardId = $('#shift-ward-select').val();
        const shiftType = $('#shift-type-select').val();

        // Check if there are critical handovers that need acknowledgment
        const criticalHandovers = $('.handover-ack-item.critical');
        const unacknowledgedCritical = criticalHandovers.filter(function() {
            return !$(this).find('.handover-ack-checkbox').is(':checked');
        });

        if (unacknowledgedCritical.length > 0) {
            // Highlight unacknowledged critical handovers
            unacknowledgedCritical.addClass('shake-highlight');
            setTimeout(() => unacknowledgedCritical.removeClass('shake-highlight'), 500);

            toastr.warning('Please acknowledge all critical handovers (marked in red) before starting your shift');

            // Scroll to first unacknowledged
            const firstUnacked = unacknowledgedCritical.first();
            if (firstUnacked.length) {
                firstUnacked[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        const btn = $('#confirm-start-shift-btn');
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Starting...');

        $.ajax({
            url: this.routes.start,
            type: 'POST',
            data: {
                ward_id: wardId || null,
                shift_type: shiftType || null,
                acknowledged_handovers: this.acknowledgedHandovers,
                _token: CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    self.activeShift = response.shift;
                    $('#startShiftModal').modal('hide');
                    self.showWorkbench();
                    self.startShiftTimer();
                    toastr.success(response.message || 'Shift started successfully');
                } else if (response.requires_acknowledgment) {
                    toastr.warning(response.message);
                    // Highlight unacknowledged critical handovers
                    btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
                } else {
                    toastr.error(response.message || 'Failed to start shift');
                    btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
                }
            },
            error: function(xhr) {
                const resp = xhr.responseJSON || {};
                if (resp.requires_acknowledgment) {
                    toastr.warning(resp.message || 'Please acknowledge critical handovers first');
                    // Highlight unacknowledged critical handovers
                    $('.handover-ack-item.critical').each(function() {
                        if (!$(this).find('.handover-ack-checkbox').is(':checked')) {
                            $(this).addClass('shake-highlight');
                            setTimeout(() => $(this).removeClass('shake-highlight'), 500);
                        }
                    });
                } else {
                    toastr.error(resp.message || 'Failed to start shift');
                }
                btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
            }
        });
    },

    // Show end shift modal
    showEndShiftModal: function(forced = false) {
        if (!this.activeShift) return;

        // Reset modal to normal mode if not forced
        if (!forced) {
            this.resetEndShiftModal();
        }

        // Populate summary
        $('#end-shift-duration').text(this.formatElapsedTime(this.activeShift.elapsed_seconds || 0));
        $('#end-shift-vitals').text(this.activeShift.counters?.vitals || 0);
        $('#end-shift-medications').text(this.activeShift.counters?.medications || 0);
        $('#end-shift-notes').text(this.activeShift.counters?.notes || 0);
        $('#end-shift-total').text(this.activeShift.total_actions || 0);

        // Clear form
        $('#end-shift-critical-notes').val('');
        $('#end-shift-concluding-notes').val('');
        $('#pending-tasks-container').html(`
            <div class="pending-task-row mb-2">
                <div class="input-group">
                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>
            </div>
        `);
        $('#create-handover-checkbox').prop('checked', true);

        // Reset preview section and show loading state
        $('#shift-activity-preview').html(`
            <div class="text-center text-muted py-3">
                <i class="mdi mdi-loading mdi-spin"></i> Loading activity preview...
            </div>
        `);

        $('#endShiftModal').modal('show');

        // Auto-load preview after modal is shown
        this.loadShiftPreview();
    },

    // Load shift preview with audit-based activities
    loadShiftPreview: function() {
        const self = this;
        const btn = $('#load-shift-preview-btn');
        const container = $('#shift-activity-preview');

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');

        $.ajax({
            url: this.routes.preview,
            type: 'GET',
            success: function(response) {
                if (response.success && response.preview) {
                    self.renderShiftPreview(response.preview);
                } else {
                    container.html(`
                        <div class="alert alert-warning mb-0">
                            <i class="mdi mdi-alert"></i> No activity data found for this shift
                        </div>
                    `);
                }
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh"></i> Refresh Preview');
            },
            error: function(xhr) {
                container.html(`
                    <div class="alert alert-danger mb-0">
                        <i class="mdi mdi-alert-circle"></i> Failed to load preview
                    </div>
                `);
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh"></i> Load Preview');
            }
        });
    },

    // Render shift preview HTML
    renderShiftPreview: function(preview) {
        let html = '';

        // Summary stats
        html += `
            <div class="d-flex justify-content-around text-center mb-3 pb-3 border-bottom">
                <div>
                    <div class="h5 mb-0 text-primary">${preview.total_events || 0}</div>
                    <small class="text-muted">Total Events</small>
                </div>
                <div>
                    <div class="h5 mb-0 text-info">${preview.total_patients || 0}</div>
                    <small class="text-muted">Patients</small>
                </div>
                <div>
                    <div class="h5 mb-0 text-secondary">${preview.elapsed_time || '--'}</div>
                    <small class="text-muted">Duration</small>
                </div>
            </div>
        `;

        // Activity breakdown
        if (preview.activity_summary && preview.activity_summary.length > 0) {
            html += '<h6 class="mb-2"><i class="mdi mdi-chart-bar"></i> Activity Breakdown</h6>';
            html += '<div class="row">';
            preview.activity_summary.forEach(function(activity) {
                html += `
                    <div class="col-6 col-md-4 mb-2">
                        <div class="d-flex align-items-center p-2 border rounded bg-white">
                            <i class="mdi ${activity.icon || 'mdi-circle'} text-${activity.color || 'secondary'} mr-2" style="font-size: 1.5rem;"></i>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold">${activity.count}</div>
                                <small class="text-muted">${activity.label}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }

        // Patient highlights (collapsible)
        if (preview.patient_highlights && preview.patient_highlights.length > 0) {
            html += `
                <h6 class="mb-2 mt-3"><i class="mdi mdi-account-group"></i> Patient Highlights</h6>
                <div class="patient-highlights-preview">
            `;
            preview.patient_highlights.slice(0, 5).forEach(function(patient, idx) {
                html += `
                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom bg-white">
                        <div>
                            <i class="mdi mdi-account text-primary mr-1"></i>
                            <strong>${patient.patient_name}</strong>
                            <small class="text-muted ml-1">(${patient.patient_no || 'N/A'})</small>
                        </div>
                        <span class="badge badge-primary badge-pill">${patient.total_events} events</span>
                    </div>
                `;
            });
            if (preview.patient_highlights.length > 5) {
                html += `<div class="text-center py-2 text-muted small">... and ${preview.patient_highlights.length - 5} more patients</div>`;
            }
            html += '</div>';
        }

        // Auto-generated summary preview
        if (preview.detailed_summary) {
            html += `
                <div class="mt-3 pt-3 border-top">
                    <h6 class="mb-2"><i class="mdi mdi-clipboard-text"></i> Auto-Generated Summary</h6>
                    <div class="bg-white p-2 border rounded small" style="max-height: 150px; overflow-y: auto;">
                        ${preview.detailed_summary.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        }

        $('#shift-activity-preview').html(html || '<div class="text-center text-muted">No activities recorded yet</div>');
    },

    // End shift
    endShift: function() {
        const self = this;
        const btn = $('#confirm-end-shift-btn');
        const wasForced = this.forceEndShift;

        // Gather pending tasks
        const pendingTasks = [];
        $('.pending-task-row').each(function() {
            const desc = $(this).find('.pending-task-desc').val().trim();
            if (desc) {
                pendingTasks.push({
                    description: desc,
                    priority: $(this).find('.pending-task-priority').val()
                });
            }
        });

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Ending...');

        $.ajax({
            url: this.routes.end,
            type: 'POST',
            data: {
                critical_notes: $('#end-shift-critical-notes').val(),
                concluding_notes: $('#end-shift-concluding-notes').val(),
                pending_tasks: pendingTasks,
                create_handover: $('#create-handover-checkbox').is(':checked'),
                _token: CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    // Reset forced mode before hiding modal
                    self.resetEndShiftModal();

                    $('#endShiftModal').modal('hide');
                    self.activeShift = null;
                    self.forceEndShift = false;
                    self.stopShiftTimer();

                    if (wasForced) {
                        toastr.success('Overdue shift ended successfully. Thank you for completing the handover.', 'Shift Ended');
                    } else {
                        toastr.success(response.message || 'Shift ended successfully');
                    }

                    if (response.handover_created) {
                        toastr.info('Handover document created for incoming nurse');
                    }

                    // Show summary
                    setTimeout(function() {
                        self.showLockOverlay();
                    }, 1000);
                } else {
                    toastr.error(response.message || 'Failed to end shift');
                    btn.prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to end shift');
                btn.prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');
            }
        });
    },

    // Add pending task row
    addPendingTaskRow: function() {
        const html = `
            <div class="pending-task-row mb-2">
                <div class="input-group">
                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#pending-tasks-container').append(html);
    },

    // Show handovers list (Cards-based modal)
    showHandoversList: function() {
        const self = this;
        // Reset pagination and load first page
        this.handoverCurrentPage = 1;
        this.handoverPerPage = parseInt($('#handover-per-page').val()) || 12;
        this.handoverViewMode = 'cards';

        // Set default view toggle
        $('#handover-view-cards').addClass('active');
        $('#handover-view-list').removeClass('active');

        // Set default dates (last 2 days)
        const today = new Date();
        const twoDaysAgo = new Date(today);
        twoDaysAgo.setDate(today.getDate() - 2);

        const formatDate = (date) => date.toISOString().split('T')[0];
        $('#handover-filter-from').val(formatDate(twoDaysAgo));
        $('#handover-filter-to').val(formatDate(today));

        // Populate wards if not already done
        if ($('#handover-filter-ward option').length <= 1) {
            this.populateHandoverWards();
        }

        this.loadHandoversCards();
        $('#handoversListModal').modal('show');
    },

    // Populate ward filter options
    populateHandoverWards: function() {
        const select = $('#handover-filter-ward');
        $.ajax({
            url: this.routes.wards || '/wards',
            type: 'GET',
            success: function(response) {
                const wards = response.wards || response.data || response;
                if (Array.isArray(wards)) {
                    wards.forEach(function(ward) {
                        select.append(`<option value="${ward.id}">${ward.name}</option>`);
                    });
                }
            }
        });
    },

    // Load handovers cards with backend processing
    loadHandoversCards: function() {
        const self = this;
        const container = $('#handover-cards-grid');
        const loadingEl = $('#handover-loading');
        const emptyEl = $('#handover-empty');

        // Show loading state
        container.html('');
        loadingEl.show();
        emptyEl.hide();

        // Build filter params
        const params = {
            page: this.handoverCurrentPage,
            per_page: this.handoverPerPage,
            ward_id: $('#handover-filter-ward').val(),
            shift_type: $('#handover-filter-shift').val(),
            status: $('#handover-filter-status').val(),
            priority: $('#handover-filter-priority').val(),
            date_from: $('#handover-filter-from').val(),
            date_to: $('#handover-filter-to').val(),
            search: $('#handover-filter-search').val(),
            sort: $('#handover-filter-sort').val(),
            format: 'cards'
        };

        $.ajax({
            url: this.routes.handovers,
            type: 'GET',
            data: params,
            success: function(response) {
                loadingEl.hide();

                if (response.success && response.data && response.data.length > 0) {
                    self.renderHandoversCards(response.data);
                    self.updateHandoverStats(response.stats);
                    self.renderHandoverPagination(response.pagination);
                } else {
                    emptyEl.show();
                    self.updateHandoverStats({ total: 0, pending: 0, critical: 0 });
                    self.renderHandoverPagination(null);
                }
            },
            error: function() {
                loadingEl.hide();
                container.html(`
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="mdi mdi-alert-circle"></i> Failed to load handovers. Please try again.
                        </div>
                    </div>
                `);
            }
        });
    },

    // Render handover cards
    renderHandoversCards: function(handovers) {
        const self = this;
        const container = $('#handover-cards-grid');
        const viewMode = this.handoverViewMode;

        if (viewMode === 'list') {
            this.renderHandoversList(handovers);
            return;
        }

        let html = '';
        handovers.forEach(function(h) {
            const isCritical = h.has_critical_notes;
            const isPending = !h.is_acknowledged;
            const cardClasses = [
                'handover-card',
                isCritical ? 'critical' : '',
                isPending ? 'pending' : 'acknowledged'
            ].filter(Boolean).join(' ');

            const shiftBadgeClass = {
                'morning': 'morning',
                'afternoon': 'afternoon',
                'night': 'night'
            }[h.shift_type] || 'secondary';

            const shiftIcon = {
                'morning': 'â‰¡Æ’Ã®Ã ',
                'afternoon': 'Î“Ã¿Ã‡âˆ©â••Ã…',
                'night': 'â‰¡Æ’Ã®Ã–'
            }[h.shift_type] || 'Î“Ã…â–‘';

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="${cardClasses}" data-handover-id="${h.id}">
                        <div class="handover-card-header">
                            <span class="handover-card-shift-badge ${shiftBadgeClass}">
                                ${shiftIcon} ${h.shift_type_label || h.shift_type}
                            </span>
                            <span class="handover-card-time" title="${h.created_at_full}">
                                ${h.created_at_ago}
                            </span>
                        </div>
                        <div class="handover-card-body">
                            <div class="handover-card-meta">
                                <span class="handover-card-nurse">
                                    <i class="mdi mdi-account-nurse"></i> ${h.created_by_name}
                                </span>
                            </div>
                            <div class="handover-card-ward">
                                <i class="mdi mdi-hospital-building"></i> ${h.ward_name}
                            </div>
                            <div class="handover-card-summary">
                                ${h.summary_preview || '<span class="text-muted">No summary provided</span>'}
                            </div>
                            ${isCritical ? `
                                <div class="handover-card-critical-preview">
                                    <i class="mdi mdi-alert-circle"></i>
                                    <strong>Critical:</strong> ${h.critical_notes_preview || 'See details'}
                                </div>
                            ` : ''}
                            <div class="handover-card-stats">
                                ${isCritical ? '<span class="handover-card-stat danger"><i class="mdi mdi-alert"></i> Critical</span>' : ''}
                                ${h.pending_tasks_count > 0 ? `<span class="handover-card-stat warning"><i class="mdi mdi-clipboard-list"></i> ${h.pending_tasks_count} tasks</span>` : ''}
                                ${h.action_count ? `<span class="handover-card-stat"><i class="mdi mdi-chart-bar"></i> ${h.action_count} actions</span>` : ''}
                            </div>
                        </div>
                        <div class="handover-card-footer">
                            <span class="handover-card-status ${isPending ? 'pending' : 'acknowledged'}">
                                ${isPending ? '<i class="mdi mdi-clock-alert"></i> Pending' : '<i class="mdi mdi-check-circle"></i> Acknowledged'}
                            </span>
                            <div class="handover-card-actions">
                                <button class="btn btn-sm btn-outline-primary view-handover" data-id="${h.id}" title="View Details">
                                    <i class="mdi mdi-eye"></i>
                                </button>
                                ${isPending ? `
                                    <button class="btn btn-sm btn-success acknowledge-handover" data-id="${h.id}" title="Acknowledge">
                                        <i class="mdi mdi-check"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.html(html);
    },

    // Render handovers as list
    renderHandoversList: function(handovers) {
        const container = $('#handover-cards-grid');
        let html = '<div class="col-12">';

        handovers.forEach(function(h) {
            const isCritical = h.has_critical_notes;
            const isPending = !h.is_acknowledged;

            html += `
                <div class="handover-list-item ${isCritical ? 'critical' : ''}" data-handover-id="${h.id}">
                    <div class="handover-list-shift">
                        <span class="badge badge-${h.shift_type === 'morning' ? 'warning' : h.shift_type === 'afternoon' ? 'info' : 'dark'}">
                            ${h.shift_type_label || h.shift_type}
                        </span>
                    </div>
                    <div class="handover-list-info">
                        <div class="handover-list-meta">
                            <strong>${h.created_by_name}</strong>
                            <span class="text-muted">Î“Ã‡Ã³</span>
                            <span class="text-muted">${h.ward_name}</span>
                            <span class="text-muted">Î“Ã‡Ã³</span>
                            <small class="text-muted">${h.created_at_ago}</small>
                            ${isCritical ? '<span class="badge badge-danger ms-2">Critical</span>' : ''}
                        </div>
                        <div class="handover-list-summary">
                            ${h.summary_preview || 'No summary'}
                        </div>
                    </div>
                    <div class="handover-list-status">
                        ${isPending
                            ? '<span class="badge badge-warning">Pending</span>'
                            : '<span class="badge badge-success">Acknowledged</span>'}
                    </div>
                    <div class="handover-list-actions">
                        <button class="btn btn-sm btn-outline-primary view-handover" data-id="${h.id}">
                            <i class="mdi mdi-eye"></i>
                        </button>
                        ${isPending ? `
                            <button class="btn btn-sm btn-success acknowledge-handover ms-1" data-id="${h.id}">
                                <i class="mdi mdi-check"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.html(html);
    },

    // Update handover stats display
    updateHandoverStats: function(stats) {
        $('#handover-total-count span').text(stats.total || 0);
        $('#handover-pending-count span').text(stats.pending || 0);
        $('#handover-critical-count span').text(stats.critical || 0);
    },

    // Render pagination
    renderHandoverPagination: function(pagination) {
        const self = this;
        const container = $('#handover-pagination-list');
        const pageInfo = $('#handover-page-info');

        if (!pagination || pagination.total_pages <= 1) {
            container.html('');
            pageInfo.text('');
            return;
        }

        pageInfo.text(`Page ${pagination.current_page} of ${pagination.total_pages}`);

        let html = '';

        // Previous button
        html += `
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                    <i class="mdi mdi-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.total_pages}">${pagination.total_pages}</a></li>`;
        }

        // Next button
        html += `
            <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                    <i class="mdi mdi-chevron-right"></i>
                </a>
            </li>
        `;

        container.html(html);
    },

    // Reload handovers with current filters
    reloadHandoversCards: function() {
        this.handoverCurrentPage = 1;
        this.loadHandoversCards();
    },

    // Clear all handover filters
    clearHandoverFilters: function() {
        $('#handover-filter-ward').val('');
        $('#handover-filter-shift').val('');
        $('#handover-filter-status').val('');
        $('#handover-filter-priority').val('');
        $('#handover-filter-from').val('');
        $('#handover-filter-to').val('');
        $('#handover-filter-search').val('');
        $('#handover-filter-sort').val('newest');
        this.reloadHandoversCards();
    },

    // Show handover detail
    showHandoverDetail: function(id) {
        const self = this;

        $.ajax({
            url: this.routes.handoverDetail + '/' + id,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    self.currentHandoverDetail = response.handover;
                    self.renderHandoverDetail(response.handover);

                    // Show/hide acknowledge button
                    if (!response.handover.is_acknowledged) {
                        $('#acknowledge-handover-detail-btn').show();
                    } else {
                        $('#acknowledge-handover-detail-btn').hide();
                    }

                    $('#handoverDetailModal').modal('show');
                } else {
                    toastr.error('Failed to load handover details');
                }
            },
            error: function() {
                toastr.error('Failed to load handover details');
            }
        });
    },

    // Render handover detail content
    renderHandoverDetail: function(h) {
        let pendingTasksHtml = '';
        if (h.pending_tasks && h.pending_tasks.length > 0) {
            pendingTasksHtml = '<ul class="list-group list-group-flush">';
            h.pending_tasks.forEach(function(task) {
                const priorityColors = { low: 'secondary', normal: 'primary', high: 'warning', urgent: 'danger' };
                pendingTasksHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${task.description}
                        <span class="badge badge-${priorityColors[task.priority] || 'secondary'}">${task.priority || 'normal'}</span>
                    </li>
                `;
            });
            pendingTasksHtml += '</ul>';
        } else {
            pendingTasksHtml = '<p class="text-muted">No pending tasks</p>';
        }

        // Build action summary HTML with icons and colors
        let actionSummaryHtml = '';
        if (h.action_summary && Object.keys(h.action_summary).length > 0) {
            actionSummaryHtml = '<div class="row text-center mt-3">';
            for (const [key, value] of Object.entries(h.action_summary)) {
                const icon = value.icon || 'mdi-checkbox-blank-circle';
                const color = value.color || 'secondary';
                const count = value.count || 0;
                const label = value.label || key;
                actionSummaryHtml += `
                    <div class="col-4 col-md-3 mb-2">
                        <div class="stat-box p-2 border rounded">
                            <i class="mdi ${icon} text-${color}" style="font-size: 1.5rem;"></i>
                            <div class="stat-value h5 mb-0">${count}</div>
                            <div class="stat-label small text-muted">${label}</div>
                        </div>
                    </div>
                `;
            }
            actionSummaryHtml += '</div>';
        }

        // Build patient highlights HTML
        let patientHighlightsHtml = '';
        if (h.patient_highlights && h.patient_highlights.length > 0) {
            patientHighlightsHtml = `
                <div class="mt-4">
                    <h6><i class="mdi mdi-account-group"></i> Patient Activity Summary</h6>
                    <div class="accordion" id="patientHighlightsAccordion">
            `;

            h.patient_highlights.forEach(function(patient, idx) {
                const collapseId = `patientCollapse${idx}`;
                patientHighlightsHtml += `
                    <div class="card-modern mb-2">
                        <div class="card-header p-2" id="heading${idx}">
                            <h6 class="mb-0">
                                <button class="btn btn-link btn-sm w-100 text-left d-flex justify-content-between align-items-center"
                                        type="button" data-toggle="collapse" data-target="#${collapseId}">
                                    <span>
                                        <i class="mdi mdi-account"></i> ${patient.patient_name}
                                        <span class="text-muted ml-2">(${patient.patient_no || 'N/A'})</span>
                                    </span>
                                    <span class="badge badge-primary badge-pill">${patient.total_events} events</span>
                                </button>
                            </h6>
                        </div>
                        <div id="${collapseId}" class="collapse${idx === 0 ? ' show' : ''}" data-parent="#patientHighlightsAccordion">
                            <div class="card-body p-2">
                                <ul class="list-unstyled mb-0">
                `;

                if (patient.activities && patient.activities.length > 0) {
                    patient.activities.forEach(function(activity) {
                        patientHighlightsHtml += `
                            <li class="mb-1">
                                <i class="mdi ${activity.icon || 'mdi-circle'} text-${activity.color || 'secondary'} mr-1"></i>
                                <span class="text-muted">${activity.label}:</span>
                                <strong>${activity.count}</strong>
                                ${activity.events && activity.events.length > 0 ?
                                    `<span class="text-muted small">(${activity.events.slice(0, 3).join(', ')}${activity.events.length > 3 ? '...' : ''})</span>`
                                    : ''
                                }
                            </li>
                        `;
                    });
                }

                patientHighlightsHtml += `
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
            });

            patientHighlightsHtml += '</div></div>';
        }

        // Build audit details HTML (detailed changes)
        let auditDetailsHtml = '';
        if (h.audit_details && h.audit_details.length > 0) {
            auditDetailsHtml = `
                <div class="mt-4">
                    <h6><i class="mdi mdi-history"></i> Detailed Activity Log <small class="text-muted">(${h.audit_details.length} changes)</small></h6>
                    <div class="audit-details-list" style="max-height: 400px; overflow-y: auto;">
            `;

            // Group by patient
            const byPatient = {};
            h.audit_details.forEach(function(detail) {
                const patientKey = detail.patient_name || 'General';
                if (!byPatient[patientKey]) {
                    byPatient[patientKey] = [];
                }
                byPatient[patientKey].push(detail);
            });

            for (const [patient, details] of Object.entries(byPatient)) {
                auditDetailsHtml += `
                    <div class="audit-patient-group mb-3">
                        <h6 class="text-primary mb-2">
                            <i class="mdi mdi-account"></i> ${patient}
                        </h6>
                        <div class="audit-items pl-3 border-left">
                `;

                details.forEach(function(detail) {
                    const eventBadge = detail.event === 'created'
                        ? '<span class="badge badge-success badge-sm">New</span>'
                        : detail.event === 'updated'
                        ? '<span class="badge badge-warning badge-sm">Updated</span>'
                        : '<span class="badge badge-danger badge-sm">Deleted</span>';

                    let changesHtml = '<ul class="list-unstyled mb-0 pl-3 small">';
                    if (detail.changes && detail.changes.length > 0) {
                        detail.changes.forEach(function(change) {
                            if (change.type === 'created') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <strong>' + change.value + '</strong></li>';
                            } else if (change.type === 'changed') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <del class="text-danger">' + change.old + '</del> Î“Ã¥Ã† <strong class="text-success">' + change.new + '</strong></li>';
                            } else if (change.type === 'deleted') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <del class="text-danger">' + change.value + '</del></li>';
                            }
                        });
                    }
                    changesHtml += '</ul>';

                    auditDetailsHtml += `
                        <div class="audit-item mb-2 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <i class="mdi ${detail.icon} text-${detail.color}"></i>
                                    <strong class="ml-1">${detail.category}</strong>
                                    ${eventBadge}
                                </div>
                                <small class="text-muted">${detail.time}</small>
                            </div>
                            ${changesHtml}
                        </div>
                    `;
                });

                auditDetailsHtml += '</div></div>';
            }

            auditDetailsHtml += '</div></div>';
        }

        const html = `
            <div class="handover-detail">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        ${h.shift_type_badge}
                        <span class="ml-2">${h.ward_name}</span>
                    </div>
                    ${h.status_badge}
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted">Created By</small>
                        <div><strong>${h.created_by.name}</strong></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Date/Time</small>
                        <div>${h.created_at} <span class="text-muted">(${h.created_at_ago})</span></div>
                    </div>
                </div>

                ${h.shift_duration ? `<div class="mb-3"><small class="text-muted">Shift Duration</small><div>${h.shift_duration}</div></div>` : ''}

                ${actionSummaryHtml ? `
                    <div class="mt-3">
                        <h6><i class="mdi mdi-chart-bar"></i> Activity Summary</h6>
                        ${actionSummaryHtml}
                    </div>
                ` : ''}

                ${h.critical_notes ? `
                    <div class="alert alert-danger mt-4">
                        <h6 class="alert-heading"><i class="mdi mdi-alert"></i> Critical Notes</h6>
                        <div>${h.critical_notes}</div>
                    </div>
                ` : ''}

                <div class="mt-4">
                    <h6><i class="mdi mdi-clipboard-text"></i> Summary</h6>
                    <div class="bg-light p-3 rounded">${h.summary || '<em>No summary provided</em>'}</div>
                </div>

                ${h.concluding_notes ? `
                    <div class="mt-4">
                        <h6><i class="mdi mdi-note-text"></i> Concluding Notes</h6>
                        <div class="bg-light p-3 rounded">${h.concluding_notes}</div>
                    </div>
                ` : ''}

                ${patientHighlightsHtml}

                ${auditDetailsHtml}

                <div class="mt-4">
                    <h6><i class="mdi mdi-format-list-checks"></i> Pending Tasks</h6>
                    ${pendingTasksHtml}
                </div>

                ${h.is_acknowledged ? `
                    <div class="mt-4 alert alert-success">
                        <i class="mdi mdi-check-circle"></i> Acknowledged by <strong>${h.acknowledged_by_name}</strong> on ${h.acknowledged_at}
                    </div>
                ` : ''}
            </div>
        `;

        $('#handover-detail-content').html(html);
    },

    // Acknowledge handover
    acknowledgeHandover: function(id, fromDetail = false) {
        const self = this;

        $.ajax({
            url: this.routes.acknowledge.replace('{id}', id),
            type: 'POST',
            data: { _token: CSRF_TOKEN },
            success: function(response) {
                if (response.success) {
                    toastr.success('Handover acknowledged');

                    if (fromDetail) {
                        $('#acknowledge-handover-detail-btn').hide();
                        self.currentHandoverDetail.is_acknowledged = true;
                        self.currentHandoverDetail.acknowledged_at = response.acknowledged_at;
                    }

                    // Reload cards list
                    self.loadHandoversCards();
                } else {
                    toastr.error(response.message || 'Failed to acknowledge');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to acknowledge');
            }
        });
    },

    // Show shift summary
    showShiftSummary: function() {
        if (!this.activeShift) return;

        const self = this;

        // Load actions for current shift
        $.ajax({
            url: this.routes.actions,
            type: 'GET',
            success: function(response) {
                self.renderShiftSummary(response);
                $('#shiftSummaryModal').modal('show');
            },
            error: function() {
                // Still show basic summary
                self.renderShiftSummary({ actions: {}, total: 0 });
                $('#shiftSummaryModal').modal('show');
            }
        });
    },

    // Render shift summary
    renderShiftSummary: function(data) {
        const shift = this.activeShift;
        const counters = shift.counters || {};

        let actionsHtml = '';
        if (data.actions && Object.keys(data.actions).length > 0) {
            actionsHtml = '<div class="mt-4"><h6>Actions by Type</h6><div class="list-group">';
            for (const [type, info] of Object.entries(data.actions)) {
                actionsHtml += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="mdi ${info.config.icon} text-${info.config.color}"></i> ${info.config.label}</span>
                        <span class="badge badge-${info.config.color}">${info.count}</span>
                    </div>
                `;
            }
            actionsHtml += '</div></div>';
        }

        const html = `
            <div class="shift-summary-content">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-clock-outline text-primary mr-2" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-muted small">Shift Started</div>
                                <strong>${shift.started_at_full}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-timer-outline text-info mr-2" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-muted small">Elapsed Time</div>
                                <strong id="summary-elapsed-time">${shift.elapsed_time}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="text-muted small">Shift Type</div>
                        <span class="badge badge-info">${shift.shift_type_label}</span>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Ward</div>
                        <strong>${shift.ward_name}</strong>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Scheduled End</div>
                        <strong>${shift.scheduled_end || 'N/A'}</strong>
                    </div>
                </div>

                <h6 class="text-muted mb-3">Activity Summary</h6>
                <div class="row text-center">
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-danger">${counters.vitals || 0}</div>
                            <div class="stat-label">Vitals</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-warning">${counters.medications || 0}</div>
                            <div class="stat-label">Medications</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-info">${counters.injections || 0}</div>
                            <div class="stat-label">Injections</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-success">${counters.immunizations || 0}</div>
                            <div class="stat-label">Immunizations</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-primary">${counters.notes || 0}</div>
                            <div class="stat-label">Notes</div>
                        </div>
                    </div>
                </div>

                ${actionsHtml}

                <div class="mt-4 text-center">
                    <div class="h4 text-primary">${shift.total_actions || 0}</div>
                    <div class="text-muted">Total Actions This Shift</div>
                </div>
            </div>
        `;

        $('#shift-summary-content').html(html);
    },

    // Start shift timer
    startShiftTimer: function() {
        const self = this;

        if (this.shiftTimer) {
            clearInterval(this.shiftTimer);
        }

        this.updateFabDisplay();

        this.shiftTimer = setInterval(function() {
            if (self.activeShift) {
                self.activeShift.elapsed_seconds = (self.activeShift.elapsed_seconds || 0) + 1;
                self.updateFabDisplay();

                // Check for overdue
                if (self.activeShift.remaining_seconds !== null) {
                    self.activeShift.remaining_seconds = Math.max(0, (self.activeShift.remaining_seconds || 0) - 1);
                }
            }
        }, 1000);
    },

    // Stop shift timer
    stopShiftTimer: function() {
        if (this.shiftTimer) {
            clearInterval(this.shiftTimer);
            this.shiftTimer = null;
        }
    },

    // Update FAB display
    updateFabDisplay: function() {
        if (!this.activeShift) return;

        const elapsed = this.activeShift.elapsed_seconds || 0;
        $('#shift-elapsed-time').text(this.formatElapsedTime(elapsed));

        // Check if overdue (past max shift duration)
        if (this.activeShift.is_overdue || elapsed > 12 * 3600) {
            $('.shift-fab-timer').addClass('overdue');
        } else {
            $('.shift-fab-timer').removeClass('overdue');
        }

        // Update FAB button state
        $('#shift-fab-btn').addClass('active');
    },

    // Format elapsed time
    formatElapsedTime: function(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    },

    // Toggle FAB actions
    toggleFabActions: function() {
        const actions = $('.shift-fab-actions');
        if (actions.is(':visible')) {
            actions.slideUp(200);
        } else {
            actions.slideDown(200);
        }
    },

    // Make FAB draggable
    makeFabDraggable: function() {
        const fab = document.getElementById('shift-control-fab');
        if (!fab) return;

        let isDragging = false;
        let startX, startY, startLeft, startBottom;

        fab.addEventListener('mousedown', startDrag);
        fab.addEventListener('touchstart', startDrag, { passive: false });

        function startDrag(e) {
            if (e.target.tagName === 'BUTTON') return; // Don't drag when clicking buttons

            isDragging = true;
            const rect = fab.getBoundingClientRect();

            if (e.type === 'touchstart') {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            } else {
                startX = e.clientX;
                startY = e.clientY;
            }

            startLeft = rect.left;
            startBottom = window.innerHeight - rect.bottom;

            document.addEventListener('mousemove', drag);
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchend', stopDrag);
        }

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();

            let clientX, clientY;
            if (e.type === 'touchmove') {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }

            const deltaX = clientX - startX;
            const deltaY = startY - clientY;

            const newRight = window.innerWidth - (startLeft + fab.offsetWidth + deltaX);
            const newBottom = startBottom + deltaY;

            // Keep within bounds
            fab.style.right = Math.max(10, Math.min(window.innerWidth - fab.offsetWidth - 10, newRight)) + 'px';
            fab.style.bottom = Math.max(10, Math.min(window.innerHeight - fab.offsetHeight - 10, newBottom)) + 'px';
        }

        function stopDrag() {
            isDragging = false;
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchend', stopDrag);
        }
    }
};

// Initialize Shift Manager on document ready
$(document).ready(function() {
    ShiftManager.init();
});
</script>

{{-- Transfer to Ward Modal --}}
<div class="modal fade" id="transferWardModal" tabindex="-1" aria-labelledby="transferWardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="transferWardModalLabel">
                    <i class="mdi mdi-swap-horizontal"></i> Transfer to Ward
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="transfer-ward-admission-id">
                <div class="alert alert-info py-2 mb-3">
                    <i class="mdi mdi-account"></i> Transferring: <strong id="transfer-ward-patient-name"></strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Target Bed <span class="text-danger">*</span></label>
                    <select class="form-select" id="transfer-ward-bed-select">
                        <option value="">-- Loading... --</option>
                    </select>
                    <small class="text-muted">Patient will be moved from their current emergency bed to the selected bed.</small>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="transfer-ward-submit-btn" onclick="submitWardTransfer()">
                    <i class="mdi mdi-check"></i> Transfer Patient
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Emergency Intake Modal --}}
@include('admin.partials.emergency-intake-modal')

@endsection
