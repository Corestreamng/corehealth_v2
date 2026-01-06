@extends('admin.layouts.app')

@section('title', 'Lab Workbench')

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
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
    }

    /* Main Layout */
    .lab-workbench-container {
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

    .btn-clinical-context:hover {
        background: #0056b3;
        border-color: #0056b3;
        color: white;
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

</style>

<div class="lab-workbench-container">
    <!-- Left Panel: Patient Search & Queue -->
    <div class="left-panel" id="left-panel">
        <div class="panel-header">
            <h5><i class="fa fa-search"></i> Patient Search</h5>
            <button class="btn-view-work-pane" id="btn-view-work-pane" title="View Work Pane">
                <i class="fa fa-arrow-right"></i> Work Pane
            </button>
        </div>

        <div class="search-container" style="position: relative;">
            <input type="text"
                   id="patient-search-input"
                   placeholder="🔍 Search patient name or file no..."
                   autocomplete="off">
            <div class="search-results" id="patient-search-results"></div>
        </div>

        <div class="queue-widget">
            <h6>📊 PENDING QUEUE</h6>
            <div class="queue-item" data-filter="billing">
                <span class="queue-item-label">🟡 Awaiting Billing</span>
                <span class="queue-count billing" id="queue-billing-count">0</span>
            </div>
            <div class="queue-item" data-filter="sample">
                <span class="queue-item-label">🟠 Sample Collection</span>
                <span class="queue-count sample" id="queue-sample-count">0</span>
            </div>
            <div class="queue-item" data-filter="results">
                <span class="queue-item-label">🔴 Result Entry</span>
                <span class="queue-count results" id="queue-results-count">0</span>
            </div>
            <button class="btn-queue-all" id="show-all-queue-btn">
                📋 Show All Queue →
            </button>
        </div>

        <div class="quick-actions">
            <h6>⚡ QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-new-request" style="display: none;">
                <i class="mdi mdi-plus-circle"></i>
                <span>New Request</span>
            </button>
            <button class="quick-action-btn" id="btn-view-reports">
                <i class="mdi mdi-chart-box-outline"></i>
                <span>View Reports</span>
            </button>
            <button class="quick-action-btn" disabled style="opacity: 0.5;">
                <i class="mdi mdi-package-variant"></i>
                <span>Inventory (Coming Soon)</span>
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
                <button class="btn-clinical-context" id="btn-clinical-context">
                    <i class="fa fa-heartbeat"></i> Clinical Context
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state">
            <i class="fa fa-flask"></i>
            <h3>Select a patient to begin</h3>
            <p>Use the search box or view pending queue</p>
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
                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview-content" role="tab" aria-controls="overview-content" aria-selected="true">
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
                    <div class="tab-pane fade show active" id="overview-content" role="tabpanel" aria-labelledby="overview-tab">
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
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-bar"></i> Requests by Status</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="status-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
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
                                    <div class="card" id="top-services-card">
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
                            <div class="card">
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
                                    <div class="card">
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
                <button class="workspace-tab active" data-tab="pending">
                    <i class="mdi mdi-clipboard-list"></i>
                    <span>Pending</span>
                    <span class="workspace-tab-badge" id="pending-badge">0</span>
                </button>
                <button class="workspace-tab" data-tab="new-request">
                    <i class="mdi mdi-plus-circle"></i>
                    <span>New Request</span>
                </button>
                <button class="workspace-tab" data-tab="history">
                    <i class="mdi mdi-history"></i>
                    <span>History</span>
                </button>
            </div>

            <div class="workspace-tab-content active" id="pending-tab">
                <div class="pending-subtabs">
                    <button class="pending-subtab active" data-status="all">
                        <i class="mdi mdi-format-list-bulleted"></i>
                        <span>All Pending</span>
                        <span class="subtab-badge" id="all-pending-badge">0</span>
                    </button>
                    <button class="pending-subtab" data-status="billing">
                        <i class="mdi mdi-cash-register"></i>
                        <span>Awaiting Billing</span>
                        <span class="subtab-badge" id="billing-subtab-badge">0</span>
                    </button>
                    <button class="pending-subtab" data-status="sample">
                        <i class="mdi mdi-test-tube"></i>
                        <span>Awaiting Sample</span>
                        <span class="subtab-badge" id="sample-subtab-badge">0</span>
                    </button>
                    <button class="pending-subtab" data-status="results">
                        <i class="mdi mdi-flask"></i>
                        <span>Awaiting Results</span>
                        <span class="subtab-badge" id="results-subtab-badge">0</span>
                    </button>
                </div>
                <div class="pending-subtab-content" id="pending-subtab-container">
                    <h5>Loading...</h5>
                </div>
            </div>

            <div class="workspace-tab-content" id="new-request-tab">
                <div class="new-request-container">
                    <div class="new-request-header">
                        <h4><i class="mdi mdi-plus-circle"></i> Create New Lab Request</h4>
                        <p class="text-muted">Request investigation for <span id="new-request-patient-name"></span></p>
                    </div>
                    <form id="new-lab-request-form" class="new-request-form">
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="service-search-input"><i class="mdi mdi-magnify"></i> Search Laboratory Services *</label>
                                <input type="text" class="form-control" id="service-search-input" placeholder="Type to search for lab services..." autocomplete="off">
                                <ul class="list-group" id="service-search-results" style="display: none; position: absolute; z-index: 1000; max-height: 300px; overflow-y: auto; width: calc(100% - 30px);"></ul>
                            </div>
                        </div>

                        <div id="selected-services-container" style="display: none;">
                            <label><i class="mdi mdi-test-tube"></i> Selected Services</label>
                            <div id="selected-services-list" class="mb-3"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="request-urgency"><i class="mdi mdi-clock-alert"></i> Urgency Level</label>
                                <select class="form-control" id="request-urgency" name="urgency">
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT (Immediate)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="request-priority"><i class="mdi mdi-flag"></i> Priority</label>
                                <select class="form-control" id="request-priority" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="request-clinical-notes"><i class="mdi mdi-note-text"></i> Clinical Notes / Indication</label>
                            <textarea class="form-control" id="request-clinical-notes" name="clinical_notes" rows="4" placeholder="Enter clinical indication, symptoms, or relevant patient history..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="request-special-instructions"><i class="mdi mdi-information"></i> Special Instructions</label>
                            <textarea class="form-control" id="request-special-instructions" name="special_instructions" rows="2" placeholder="Any special handling or processing instructions..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="switchWorkspaceTab('pending')">
                                <i class="mdi mdi-close"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="workspace-tab-content" id="history-tab">
                <h4>Investigation History</h4>
                <div class="history-table">
                    <table class="table table-hover" style="width: 100%" id="investigation_history_list">
                        <thead class="table-light">
                            <th><i class="mdi mdi-test-tube"></i> Laboratory Requests</th>
                        </thead>
                    </table>
                </div>
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
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
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
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
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
                <button type="button" class="close text-white" data-dismiss="modal">
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
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
                <button type="button" class="close" data-dismiss="modal">
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
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

<!-- Trash Aside Panel -->
<div class="trash-panel" id="trashPanel" style="display: none;">
    <div class="trash-panel-header">
        <h5><i class="fa fa-trash"></i> Trash & Dismissed</h5>
        <button class="close-panel-btn" id="closeTrashPanel">&times;</button>
    </div>
    <div class="trash-panel-tabs">
        <button class="trash-tab active" data-trash-tab="dismissed">
            <i class="fa fa-ban"></i> Dismissed
            <span class="badge badge-warning" id="dismissed-count">0</span>
        </button>
        <button class="trash-tab" data-trash-tab="deleted">
            <i class="fa fa-trash"></i> Deleted
            <span class="badge badge-danger" id="deleted-count">0</span>
        </button>
    </div>
    <div class="trash-panel-content">
        <div class="trash-tab-content active" id="dismissed-content">
            <div class="table-responsive">
                <table class="table table-sm table-hover" id="dismissed-table" style="width: 100%">
                    <thead>
                        <tr>
                            <th>Details</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        <div class="trash-tab-content" id="deleted-content">
            <div class="table-responsive">
                <table class="table table-sm table-hover" id="deleted-table" style="width: 100%">
                    <thead>
                        <tr>
                            <th>Details</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-clipboard-list"></i> Audit Trail</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
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
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportAuditLog">
                    <i class="fa fa-download"></i> Export to Excel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Trash Panel Styles */
.trash-panel {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    width: 450px;
    background: white;
    box-shadow: -4px 0 15px rgba(0, 0, 0, 0.2);
    z-index: 2000;
    display: flex;
    flex-direction: column;
}

.trash-panel-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #5a67d8;
}

.trash-panel-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.25rem;
}

.trash-panel-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.trash-tab {
    flex: 1;
    padding: 1rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #6c757d;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.trash-tab:hover {
    background: rgba(0, 123, 255, 0.05);
    color: #007bff;
}

.trash-tab.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background: white;
}

.trash-panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.trash-tab-content {
    display: none;
}

.trash-tab-content.active {
    display: block;
}

/* Floating Trash Button */
.floating-trash-btn {
    position: fixed;
    right: 30px;
    bottom: 100px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s;
    z-index: 1000;
}

.floating-trash-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.floating-trash-btn .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Audit Log Button */
.floating-audit-btn {
    position: fixed;
    right: 30px;
    bottom: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(240, 147, 251, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s;
    z-index: 1000;
}

.floating-audit-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(240, 147, 251, 0.6);
}
</style>

<!-- Floating Action Buttons -->
<button class="floating-trash-btn" id="openTrashPanel" title="View Trash & Dismissed Requests">
    <i class="fa fa-trash"></i>
    <span class="badge" id="trash-total-count">0</span>
</button>

<button class="floating-audit-btn" id="openAuditLog" title="View Audit Trail">
    <i class="fa fa-clipboard-list"></i>
</button>

@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script>
// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let searchTimeout = null;
let vitalTooltip = null;

$(document).ready(function() {
    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadUserPreferences();
    createVitalTooltip();
});

function initializeEventListeners() {
    // Patient search
    $('#patient-search-input').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();

        if (query.length < 2) {
            $('#patient-search-results').hide();
            return;
        }

        searchTimeout = setTimeout(() => searchPatients(query), 300);
    });

    // Close search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-container').length) {
            $('#patient-search-results').hide();
        }
    });

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
        // Open clinical context modal
        $('#clinical-context-modal').modal('show');
        // Load clinical data if patient selected
        if (currentPatient) {
            loadClinicalContext(currentPatient);
        }
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

    // Queue filter buttons
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

function searchPatients(query) {
    $.ajax({
        url: '{{ route("lab.search-patients") }}',
        method: 'GET',
        data: { term: query },
        success: function(results) {
            displaySearchResults(results);
        },
        error: function() {
            console.error('Search failed');
        }
    });
}

function displaySearchResults(results) {
    const $container = $('#patient-search-results');
    $container.empty();

    if (results.length === 0) {
        $container.html('<div class="search-result-item">No patients found</div>');
    } else {
        results.forEach((patient, index) => {
            const item = $(`
                <div class="search-result-item ${index === 0 ? 'active' : ''}" data-patient-id="${patient.id}">
                    <img src="/storage/image/user/${patient.photo}" alt="${patient.name}">
                    <div class="search-result-info">
                        <div class="search-result-name">${patient.name}</div>
                        <div class="search-result-details">
                            ${patient.file_no} | ${patient.age}y ${patient.gender} | ${patient.phone}
                        </div>
                    </div>
                    ${patient.pending_count > 0 ? `<span class="pending-badge">${patient.pending_count}</span>` : ''}
                </div>
            `);

            item.on('click', function() {
                loadPatient(patient.id);
                $container.hide();
                $('#patient-search-input').val('');
            });

            $container.append(item);
        });
    }

    $container.show();
}

function loadPatient(patientId) {
    currentPatient = patientId;

    // Show loading state
    $('#empty-state').hide();
    $('#workspace-content').addClass('active');
    $('#patient-header').addClass('active');

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Update quick actions visibility
    updateQuickActions();

    // Load patient requests
    $.ajax({
        url: `/lab-workbench/patient/${patientId}/requests`,
        method: 'GET',
        success: function(data) {
            currentPatientData = data.patient; // Store patient data including allergies
            displayPatientInfo(data.patient);
            displayPendingRequests(data.requests);

            // Initialize history DataTable
            initializeHistoryDataTable(patientId);
        },
        error: function() {
            alert('Failed to load patient data');
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

    // Allergies
    if (patient.allergies && patient.allergies.length > 0) {
        const allergiesList = patient.allergies.map(allergy =>
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

function loadClinicalContext(patientId) {
    // Load vitals
    $.get(`/lab-workbench/patient/${patientId}/vitals?limit=10`, function(vitals) {
        displayVitals(vitals);
    });

    // Load notes
    $.get(`/lab-workbench/patient/${patientId}/notes?limit=10`, function(notes) {
        displayNotes(notes);
    });

    // Load medications
    $.get(`/lab-workbench/patient/${patientId}/medications?limit=20`, function(meds) {
        displayMedications(meds);
    });
}

function displayVitals(vitals) {
    // Check if DataTables is loaded
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables library is not loaded');
        $('#vitals-panel-body').html('<p class="text-danger">Error: DataTables library not loaded</p>');
        return;
    }

    // Destroy existing DataTable if present
    if ($.fn.DataTable.isDataTable('#vitals-table')) {
        $('#vitals-table').DataTable().destroy();
    }

    // Initialize DataTable with custom card rendering
    $('#vitals-table').DataTable({
        data: vitals,
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        dom: 't',
        language: {
            emptyTable: '<p class="text-muted">No recent vitals recorded</p>'
        },
        columns: [{
            data: null,
            render: function(data, type, row) {
                // Use time_taken if available, otherwise created_at
                const vitalDate = formatDateTime(row.time_taken || row.created_at);

                // Correct field names from database
                const temp = row.temp || 'N/A';
                const heartRate = row.heart_rate || 'N/A';
                const bp = row.blood_pressure || 'N/A';
                const respRate = row.resp_rate || 'N/A';
                const weight = row.weight || 'N/A';

                return `
                    <div class="vital-entry">
                        <div class="vital-entry-header">
                            <span class="vital-date">${vitalDate}</span>
                        </div>
                        <div class="vital-entry-grid">
                            <div class="vital-item ${getTempClass(temp)}"
                                 onmouseenter="showVitalTooltip(event, 'temperature', '${temp}', '34°C - 39°C')">
                                <i class="mdi mdi-thermometer"></i>
                                <span class="vital-value">${temp}°C</span>
                                <span class="vital-label">Temp</span>
                            </div>
                            <div class="vital-item ${getHeartRateClass(heartRate)}"
                                 onmouseenter="showVitalTooltip(event, 'pulse', '${heartRate}', '60-220 BPM')">
                                <i class="mdi mdi-heart-pulse"></i>
                                <span class="vital-value">${heartRate}</span>
                                <span class="vital-label">Heart Rate</span>
                            </div>
                            <div class="vital-item ${getBPClass(bp)}"
                                 onmouseenter="showVitalTooltip(event, 'bp', '${bp}', '90/60 - 140/90 mmHg')">
                                <i class="mdi mdi-water"></i>
                                <span class="vital-value">${bp}</span>
                                <span class="vital-label">BP (mmHg)</span>
                            </div>
                            <div class="vital-item ${getRespRateClass(respRate)}">
                                <i class="mdi mdi-lungs"></i>
                                <span class="vital-value">${respRate}</span>
                                <span class="vital-label">Resp Rate (BPM)</span>
                            </div>
                            <div class="vital-item">
                                <i class="mdi mdi-weight-kilogram"></i>
                                <span class="vital-value">${weight}</span>
                                <span class="vital-label">Weight (Kg)</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        }],
        drawCallback: function() {
            // Add "Show All" link after table
            const $wrapper = $('#vitals-table_wrapper');
            $wrapper.find('.show-all-link').remove();
            $wrapper.append(`
                <a href="/patients/show/${currentPatient}?section=vitalsCardBody" target="_blank" class="show-all-link">
                    Show All Vitals →
                </a>
            `);
        }
    });
}

function calculateBMI(weight, height) {
    if (!weight || !height || height === 0) return null;
    const bmi = weight / ((height / 100) ** 2);
    return bmi.toFixed(1);
}

function getTempClass(temp) {
    if (temp === 'N/A') return '';
    const t = parseFloat(temp);
    // Based on form: Min: 34, Max: 39
    if (t < 34 || t > 39) return 'vital-critical';
    if (t < 36.1 || t > 38.0) return 'vital-warning';
    return 'vital-normal';
}

function getHeartRateClass(heartRate) {
    if (heartRate === 'N/A') return '';
    const hr = parseInt(heartRate);
    // Based on form: Min: 60, Max: 220
    if (hr < 50 || hr > 220) return 'vital-critical';
    if (hr < 60 || hr > 100) return 'vital-warning';
    return 'vital-normal';
}

function getRespRateClass(respRate) {
    if (respRate === 'N/A') return '';
    const rr = parseInt(respRate);
    // Based on form: Min: 12, Max: 30
    if (rr < 10 || rr > 35) return 'vital-critical';
    if (rr < 12 || rr > 30) return 'vital-warning';
    return 'vital-normal';
}

function getBPClass(bp) {
    if (bp === 'N/A' || !bp.includes('/')) return '';
    const [systolic, diastolic] = bp.split('/').map(v => parseInt(v));
    if (systolic > 180 || systolic < 80 || diastolic > 110 || diastolic < 50) return 'vital-critical';
    if (systolic > 140 || systolic < 90 || diastolic > 90 || diastolic < 60) return 'vital-warning';
    return 'vital-normal';
}

function displayNotes(notes) {
    // Check if DataTables is loaded
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables library is not loaded');
        $('#notes-panel-body').html('<p class="text-danger">Error: DataTables library not loaded</p>');
        return;
    }

    // Destroy existing DataTable if present
    if ($.fn.DataTable.isDataTable('#notes-table')) {
        $('#notes-table').DataTable().destroy();
    }

    // Initialize DataTable with custom card rendering
    $('#notes-table').DataTable({
        data: notes,
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        dom: 't',
        language: {
            emptyTable: '<p class="text-muted">No recent doctor notes</p>'
        },
        columns: [{
            data: null,
            render: function(data, type, row, meta) {
                // Use pre-formatted date from server
                const noteDate = row.date_formatted || row.date;
                const doctor = row.doctor || 'Unknown Doctor';
                const specialty = row.specialty || 'N/A';
                const diagnosis = row.diagnosis || 'No diagnosis';
                const content = row.notes || row.complaints || 'No notes recorded';
                const truncatedContent = truncateText(content, 150);
                const noteId = `note-${meta.row}`;

                return `
                    <div class="note-entry">
                        <div class="note-header">
                            <span class="note-doctor">
                                <i class="mdi mdi-doctor"></i>
                                ${doctor}
                                ${specialty !== 'N/A' ? `<span class="specialty-tag">${specialty}</span>` : ''}
                            </span>
                            <span class="note-date">${noteDate}</span>
                        </div>
                        ${diagnosis !== 'No diagnosis' ? `
                            <div class="note-diagnosis">
                                <span class="diagnosis-badge">${diagnosis}</span>
                            </div>
                        ` : ''}
                        <div class="note-content" id="${noteId}">
                            <p class="note-text ${content.length > 150 ? 'truncated' : ''}" data-full-text="${escapeHtml(content)}">${truncatedContent}</p>
                            ${content.length > 150 ? `<a href="#" class="read-more-link" data-note-id="${noteId}">Read More</a>` : ''}
                        </div>
                    </div>
                `;
            }
        }],
        drawCallback: function() {
            // Add Read More toggle handler
            $('#notes-table').off('click', '.read-more-link').on('click', '.read-more-link', function(e) {
                e.preventDefault();
                const $link = $(this);
                const $noteText = $link.siblings('.note-text');
                const fullText = $noteText.data('full-text');
                const truncatedText = truncateText(fullText, 150);

                if ($noteText.hasClass('truncated')) {
                    $noteText.removeClass('truncated').text(fullText);
                    $link.text('Read Less');
                } else {
                    $noteText.addClass('truncated').text(truncatedText);
                    $link.text('Read More');
                }
            });

            // Add "Show All" link
            const $wrapper = $('#notes-table_wrapper');
            $wrapper.find('.show-all-link').remove();
            $wrapper.append(`
                <a href="/patients/show/${currentPatient}?section=encountersCardBody" target="_blank" class="show-all-link">
                    Show All Notes →
                </a>
            `);
        }
    });
}

function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function displayMedications(meds) {
    const $container = $('#medications-list-container');
    const $alertBanner = $('#allergy-alert-banner');
    const $showAll = $('#medications-show-all');

    // Check for allergies if patient data is available
    const patientAllergies = currentPatientData?.allergies || [];
    const allergyAlerts = checkForAllergies(meds, patientAllergies);

    // Display allergy alert if present
    if (allergyAlerts.length > 0) {
        $alertBanner.html(displayAllergyAlert(allergyAlerts));
    } else {
        $alertBanner.html('');
    }

    // Check if no medications
    if (!meds || meds.length === 0) {
        $container.html('<p class="text-muted text-center">No recent medications</p>');
        $showAll.html('');
        return;
    }

    // Build cards for each medication (matching encounter prescription history style)
    let html = '';
    meds.forEach(med => {
        const drugName = med.drug_name || 'N/A';
        const productCode = med.product_code || '';
        const dose = med.dose || 'N/A';
        const status = med.status || 'pending';
        const requestedDate = med.requested_date || 'N/A';
        const prescribedBy = med.doctor || 'N/A';
        const billedBy = med.billed_by || null;
        const billedDate = med.billed_date || null;
        const dispensedBy = med.dispensed_by || null;
        const dispensedDate = med.dispensed_date || null;

        // Check if this medication triggers allergy alert
        const isAllergic = allergyAlerts.some(alert => alert.medication === drugName);

        // Status badge
        let statusBadge = '';
        if (status === 'dispensed') {
            statusBadge = "<span class='badge bg-info'>Dispensed</span>";
        } else if (status === 'billed') {
            statusBadge = "<span class='badge bg-primary'>Billed</span>";
        } else {
            statusBadge = "<span class='badge bg-secondary'>Pending</span>";
        }

        html += '<div class="medication-card card mb-2" style="border-left: 4px solid ' + (isAllergic ? '#dc3545' : '#0d6efd') + ';">';
        html += '<div class="card-body p-3">';

        // Header with drug name and status
        html += '<div class="d-flex justify-content-between align-items-start mb-3">';
        html += "<h6 class='mb-0'><span class='badge bg-success'>[" + productCode + '] ' + drugName + '</span></h6>';
        html += statusBadge;
        html += '</div>';

        // Allergy warning if applicable
        if (isAllergic) {
            const allergyMatch = allergyAlerts.find(alert => alert.medication === drugName);
            html += '<div class="alert alert-danger mb-2"><small><i class="fa fa-exclamation-triangle"></i> <b>ALLERGY WARNING:</b> Patient allergic to ' + allergyMatch.allergy + '</small></div>';
        }

        // Dosage information
        html += '<div class="alert alert-light mb-3"><small><b><i class="mdi mdi-pill"></i> Dose/Frequency:</b><br>' + dose + '</small></div>';

        // Timeline section
        html += '<div class="mb-2"><small>';
        html += '<div class="mb-2"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
            + prescribedBy + ' <span class="text-muted">(' + requestedDate + ')</span>';
        html += '</div>';

        html += '<div class="mb-2"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> '
            + (billedBy ? billedBy + ' <span class="text-muted">(' + billedDate + ')</span>' : "<span class='badge bg-secondary'>Not billed</span>");
        html += '</div>';

        html += '<div class="mb-2"><i class="mdi mdi-package-variant text-warning"></i> <b>Dispensed by:</b> '
            + (dispensedBy ? dispensedBy + ' <span class="text-muted">(' + dispensedDate + ')</span>' : "<span class='badge bg-secondary'>Not dispensed</span>");
        html += '</div>';

        html += '</small></div>';

        html += '</div>'; // Close card-body
        html += '</div>'; // Close card
    });

    $container.html(html);

    // Add "Show All" link
    $showAll.html(`
        <div class="text-center mt-3">
            <a href="/patients/show/${currentPatient}?section=prescriptionsCardBody" target="_blank" class="btn btn-sm btn-outline-primary">
                Show All Medications <i class="fa fa-arrow-right"></i>
            </a>
        </div>
    `);
}

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
    $.get('{{ route("lab.queue-counts") }}', function(counts) {
        $('#queue-billing-count').text(counts.billing);
        $('#queue-sample-count').text(counts.sample);
        $('#queue-results-count').text(counts.results);
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

    // Silently reload patient requests
    $.get(`/lab-workbench/patient/${currentPatient}/requests`, function(data) {
        displayPendingRequests(data.requests);
        updatePendingSubtabBadges(data.requests);
    }).fail(function() {
        console.error('Failed to refresh patient data');
    });
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

    // Reload specific panel data
    if (panel === 'vitals') {
        $.get(`/lab-workbench/patient/${currentPatient}/vitals?limit=10`, function(vitals) {
            displayVitals(vitals);
            $btn.find('i').removeClass('fa-spin');
        });
    } else if (panel === 'notes') {
        $.get(`/lab-workbench/patient/${currentPatient}/notes?limit=10`, function(notes) {
            displayNotes(notes);
            $btn.find('i').removeClass('fa-spin');
        });
    } else if (panel === 'medications') {
        $.get(`/lab-workbench/patient/${currentPatient}/medications?limit=20`, function(meds) {
            displayMedications(meds);
            $btn.find('i').removeClass('fa-spin');
        });
    }
}

function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

    $('.workspace-tab-content').removeClass('active');
    $(`#${tab}-tab`).addClass('active');
}

function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('clinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('📊 Clinical Context ×');
    }
}

// Action handlers for lab requests
function recordBilling(requestIds) {
    if (!confirm(`Record billing for ${requestIds.length} request(s)?`)) return;

    $.ajax({
        url: '{{ route("lab.recordBilling") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            request_ids: requestIds,
            patient_id: currentPatient
        },
        success: function(response) {
            alert('Billing recorded successfully!');
            loadPatient(currentPatient); // Reload patient data
        },
        error: function(xhr) {
            alert('Error recording billing: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function collectSample(requestIds) {
    if (!confirm(`Mark sample collected for ${requestIds.length} request(s)?`)) return;

    $.ajax({
        url: '{{ route("lab.collectSample") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            request_ids: requestIds,
            patient_id: currentPatient
        },
        success: function(response) {
            alert('Sample collection recorded successfully!');
            loadPatient(currentPatient);
        },
        error: function(xhr) {
            alert('Error recording sample: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function dismissRequests(requestIds, section) {
    if (!confirm(`Dismiss ${requestIds.length} request(s) from ${section} queue?`)) return;

    $.ajax({
        url: '{{ route("lab.dismissRequests") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            request_ids: requestIds,
            patient_id: currentPatient
        },
        success: function(response) {
            alert('Requests dismissed successfully!');
            loadPatient(currentPatient);
        },
        error: function(xhr) {
            alert('Error dismissing requests: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function enterResult(requestId) {
    // Fetch the lab request data
    $.ajax({
        url: `/lab-workbench/lab-service-requests/${requestId}`,
        method: 'GET',
        success: function(request) {
            setResTempInModal(request);
            $('#investResModal').modal('show');
        },
        error: function(xhr) {
            alert('Error loading request: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function setResTempInModal(request) {
    $('#investResModal').find('form').trigger('reset');
    $('#invest_res_service_name').text(request.service ? request.service.name : '');
    $('#invest_res_entry_id').val(request.id);
    $('#invest_res_is_edit').val(0);
    $('#deleted_attachments').val('[]');
    $('#existing_attachments_container').hide();
    $('#existing_attachments_list').html('');

    // Check template version
    const isV2 = request.service && request.service.template_version == 2;

    if (isV2) {
        let structure = request.service.template_structure;
        if (typeof structure === 'string') {
            try {
                structure = JSON.parse(structure);
            } catch (e) {
                console.error('Error parsing V2 template structure:', e);
                structure = null;
            }
        }

        if (structure) {
            // Parse result_data if available (for edit mode)
            let existingData = null;
            if (request.result_data) {
                try {
                    existingData = typeof request.result_data === 'string' ? JSON.parse(request.result_data) : request.result_data;
                } catch (e) {
                    console.error('Error parsing result_data:', e);
                }
            }
            loadV2Template(structure, existingData);
        } else {
            console.error('Invalid V2 template structure');
            // Fallback or error handling
        }
    } else {
        // Use request.result if available (for edit), otherwise template body
        let content = request.result || (request.service ? request.service.template_body : '');
        loadV1Template(content);
    }

    // Load existing attachments if editing (logic to be added if edit mode is supported)
    loadExistingAttachments(request.id);
}

function loadV1Template(template) {
    $('#invest_res_template_version').val('1');
    $('#v1_template_container').show();
    $('#v2_template_container').hide();

    // Re-enable content editing if it was disabled upon save
    if (template) {
        template = template.replace(/contenteditable="false"/g, 'contenteditable="true"');
        template = template.replace(/contenteditable='false'/g, "contenteditable='true'");
    }

    // Initialize CKEditor if not already initialized
    if (!window.investResEditor) {
        ClassicEditor
            .create(document.querySelector('#invest_res_template_editor'), {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'insertTable',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                }
            })
            .then(editor => {
                window.investResEditor = editor;
                editor.setData(template || '');
            })
            .catch(err => {
                console.error(err);
            });
    } else {
        window.investResEditor.setData(template || '');
    }
}

function loadV2Template(template, existingData) {
    $('#invest_res_template_version').val('2');
    $('#v1_template_container').hide();
    $('#v2_template_container').show();

    let formHtml = '<div class="v2-result-form">';
    formHtml += '<h6 class="mb-3">' + (template.template_name || 'Result Entry') + '</h6>';

    // Sort parameters by order
    let parameters = template.parameters ? template.parameters.sort((a, b) => a.order - b.order) : [];

    parameters.forEach(param => {
        if (param.show_in_report === false) {
            return; // Skip hidden parameters
        }

        formHtml += '<div class="form-group row">';
        formHtml += '<label class="col-md-4 col-form-label">';
        formHtml += param.name;
        if (param.unit) {
            formHtml += ' <small class="text-muted">(' + param.unit + ')</small>';
        }
        if (param.required) {
            formHtml += ' <span class="text-danger">*</span>';
        }
        formHtml += '</label>';
        formHtml += '<div class="col-md-8">';

        let fieldId = 'param_' + param.id;
        let value = '';
        if (existingData && existingData[param.id]) {
            // Handle both direct value and object with value property
            if (typeof existingData[param.id] === 'object' && existingData[param.id] !== null && existingData[param.id].hasOwnProperty('value')) {
                value = existingData[param.id].value;
            } else {
                value = existingData[param.id];
            }
        }
        if (value === null || value === undefined) value = '';

        // Generate form field based on type
        if (param.type === 'string') {
            formHtml += '<input type="text" class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'value="' + value + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">';

        } else if (param.type === 'integer') {
            formHtml += '<input type="number" step="1" class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range) {
                formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'value="' + value + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">';

        } else if (param.type === 'float') {
            formHtml += '<input type="number" step="0.01" class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range) {
                formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'value="' + value + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">';

        } else if (param.type === 'boolean') {
            formHtml += '<select class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range && param.reference_range.reference_value !== undefined) {
                formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += '>';
            formHtml += '<option value="">Select</option>';
            formHtml += '<option value="true" ' + (value === true || value === 'true' ? 'selected' : '') + '>Yes/Positive</option>';
            formHtml += '<option value="false" ' + (value === false || value === 'false' ? 'selected' : '') + '>No/Negative</option>';
            formHtml += '</select>';

        } else if (param.type === 'enum') {
            formHtml += '<select class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range && param.reference_range.reference_value) {
                formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += '>';
            formHtml += '<option value="">Select</option>';
            if (param.options) {
                param.options.forEach(opt => {
                    let optVal = typeof opt === 'object' ? opt.value : opt;
                    let optLabel = typeof opt === 'object' ? opt.label : opt;
                    formHtml += '<option value="' + optVal + '" ' + (value === optVal ? 'selected' : '') + '>' + optLabel + '</option>';
                });
            }
            formHtml += '</select>';

        } else if (param.type === 'long_text') {
            formHtml += '<textarea class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'rows="3" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">' + value + '</textarea>';
        }

        // Add reference range info if available
        if (param.reference_range) {
            formHtml += '<small class="form-text text-muted">';
            if (param.type === 'integer' || param.type === 'float') {
                if (param.reference_range.min !== null && param.reference_range.max !== null) {
                    formHtml += 'Normal range: ' + param.reference_range.min + ' - ' + param.reference_range.max;
                }
            } else if (param.type === 'boolean' && param.reference_range.reference_value !== undefined) {
                formHtml += 'Normal: ' + (param.reference_range.reference_value ? 'Yes/Positive' : 'No/Negative');
            } else if (param.type === 'enum' && param.reference_range.reference_value) {
                formHtml += 'Normal: ' + param.reference_range.reference_value;
            } else if (param.reference_range.text) {
                formHtml += param.reference_range.text;
            }
            formHtml += '</small>';
        }

        // Status indicator (will be updated on blur)
        formHtml += '<div class="mt-1"><span class="param-status" id="status_' + param.id + '"></span></div>';

        formHtml += '</div>';
        formHtml += '</div>';
    });

    formHtml += '</div>';

    $('#v2_form_fields').html(formHtml);

    // Add event listeners for value changes to show status
    $('.v2-param-field').on('blur change', function() {
        updateParameterStatus($(this));
    });

    // Trigger status update for pre-filled values
    $('.v2-param-field').each(function() {
        if ($(this).val()) {
            updateParameterStatus($(this));
        }
    });
}

function updateParameterStatus($field) {
    let paramId = $field.data('param-id');
    let paramType = $field.data('param-type');
    let value = $field.val();
    let $statusSpan = $('#status_' + paramId);

    if (!value || value === '') {
        $statusSpan.html('');
        return;
    }

    let status = '';
    let statusClass = '';

    if (paramType === 'integer' || paramType === 'float') {
        let numValue = parseFloat(value);
        let min = $field.data('ref-min');
        let max = $field.data('ref-max');

        if (min !== undefined && max !== undefined && min !== '' && max !== '') {
            if (numValue < min) {
                status = 'Low';
                statusClass = 'badge-warning';
            } else if (numValue > max) {
                status = 'High';
                statusClass = 'badge-danger';
            } else {
                status = 'Normal';
                statusClass = 'badge-success';
            }
        }
    } else if (paramType === 'boolean') {
        let refValue = $field.data('ref-value');
        if (refValue !== undefined) {
            let boolValue = value === 'true';
            let refBool = refValue === true || refValue === 'true';

            if (boolValue === refBool) {
                status = 'Normal';
                statusClass = 'badge-success';
            } else {
                status = 'Abnormal';
                statusClass = 'badge-warning';
            }
        }
    } else if (paramType === 'enum') {
        let refValue = $field.data('ref-value');
        if (refValue) {
            if (value === refValue) {
                status = 'Normal';
                statusClass = 'badge-success';
            } else {
                status = 'Abnormal';
                statusClass = 'badge-warning';
            }
        }
    }

    if (status) {
        $statusSpan.html('<span class="badge ' + statusClass + '">' + status + '</span>');
    } else {
        $statusSpan.html('');
    }
}

function loadExistingAttachments(requestId) {
    const container = $('#existing_attachments_list');
    const wrapper = $('#existing_attachments_container');
    container.empty();
    wrapper.hide();

    $.ajax({
        url: `/lab-workbench/lab-service-requests/${requestId}/attachments`,
        method: 'GET',
        success: function(attachments) {
            if (attachments && attachments.length > 0) {
                wrapper.show();
                attachments.forEach(att => {
                    const attDiv = $('<div>').addClass('attachment-item mb-2 d-flex justify-content-between align-items-center');
                    const link = $('<a>').attr('href', att.url).attr('target', '_blank').text(att.filename);
                    const deleteBtn = $('<button>')
                        .addClass('btn btn-sm btn-danger')
                        .html('<i class="fa fa-trash"></i>')
                        .on('click', function() {
                            markAttachmentForDeletion(att.id);
                            attDiv.remove();
                            if (container.children().length === 0) {
                                wrapper.hide();
                            }
                        });
                    attDiv.append(link).append(deleteBtn);
                    container.append(attDiv);
                });
            }
        }
    });
}

function markAttachmentForDeletion(attachmentId) {
    const current = $('#deleted_attachments').val();
    const deleted = current ? JSON.parse(current) : [];
    deleted.push(attachmentId);
    $('#deleted_attachments').val(JSON.stringify(deleted));
}

// Handle result form submission
$('#investResForm').on('submit', function(e) {
    e.preventDefault();

    // Copy data from editors/inputs to hidden fields
    copyResTemplateToField();

    const formData = new FormData(this);

    $.ajax({
        url: $(this).attr('action'),
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            alert('Result saved successfully!');
            $('#investResModal').modal('hide');
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error saving result: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
});

function copyResTemplateToField() {
    let version = $('#invest_res_template_version').val();

    if (version === '2') {
        // Collect V2 structured data
        let data = {};
        $('.v2-param-field').each(function() {
            let paramId = $(this).data('param-id');
            let paramType = $(this).data('param-type');
            let value = $(this).val();

            // Convert values to appropriate types
            if (paramType === 'integer') {
                data[paramId] = value ? parseInt(value) : null;
            } else if (paramType === 'float') {
                data[paramId] = value ? parseFloat(value) : null;
            } else if (paramType === 'boolean') {
                data[paramId] = value === 'true' ? true : (value === 'false' ? false : null);
            } else {
                data[paramId] = value || null;
            }
        });

        $('#invest_res_template_data').val(JSON.stringify(data));
        // For V2, we still save a simple HTML representation to result column for backward compat
        $('#invest_res_template_submited').val('<p>Structured result data (V2 template)</p>');
    } else {
        // V1: Copy from CKEditor
        if (window.investResEditor) {
            $('#invest_res_template_submited').val(window.investResEditor.getData());
        }
    }
    return true;
}

function editLabResult(obj) {
    const requestId = $(obj).data('id');

    $.ajax({
        url: `/lab-workbench/lab-service-requests/${requestId}`,
        method: 'GET',
        success: function(request) {
            // Populate the form with template structure AND existing result data
            setResTempInModal(request);

            // Set Edit Mode UI
            $('#invest_res_is_edit').val(1);
            $('#investResModalLabel').text('Edit Result: ' + (request.service ? request.service.name : ''));
            $('#invest_res_submit_btn').html('<i class="mdi mdi-content-save"></i> Update Result');

            $('#investResModal').modal('show');
        },
        error: function(xhr) {
            alert('Error loading request: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function setResViewInModal(obj) {
    let res_obj = JSON.parse($(obj).attr('data-result-obj'));

    // Basic service info
    $('.invest_res_service_name_view').text($(obj).attr('data-service-name'));

    // Patient information
    let patientName = res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname;
    $('#res_patient_name').html(patientName);
    $('#res_patient_id').html(res_obj.patient.file_no);

    // Calculate age from date of birth
    let age = 'N/A';
    if (res_obj.patient.date_of_birth) {
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
    let gender = res_obj.patient.gender ? res_obj.patient.gender.toUpperCase() : 'N/A';
    $('#res_patient_gender').html(gender);

    // Test information
    $('#res_test_id').html(res_obj.id);
    $('#res_sample_date').html(res_obj.sample_date || 'N/A');
    $('#res_result_date').html(res_obj.result_date || 'N/A');
    $('#res_result_by').html(res_obj.results_person.firstname + ' ' + res_obj.results_person.surname);

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
    mywindow.focus(); // necessary for IE >= 10*/

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

            // Refresh trash panel if it's open
            if ($('#trashPanel').is(':visible')) {
                loadTrashData();
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

            // Refresh trash panel
            if ($('#trashPanel').is(':visible')) {
                loadTrashData();
            }
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to dismiss request'));
        }
    });
});

// Restore Request (from deleted or dismissed)
function restoreRequest(requestId, type) {
    if (!confirm('Are you sure you want to restore this request?')) return;

    const url = type === 'deleted'
        ? `/lab-workbench/lab-service-requests/${requestId}/restore`
        : `/lab-workbench/lab-service-requests/${requestId}/undismiss`;

    $.ajax({
        url: url,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            alert(response.message);

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }

            // Refresh trash panel
            loadTrashData();
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to restore request'));
        }
    });
}

// Trash Panel Management
$('#openTrashPanel').on('click', function() {
    $('#trashPanel').fadeIn(300);
    loadTrashData();
});

$('#closeTrashPanel').on('click', function() {
    $('#trashPanel').fadeOut(300);
});

$('.trash-tab').on('click', function() {
    const tab = $(this).data('trash-tab');
    $('.trash-tab').removeClass('active');
    $(this).addClass('active');
    $('.trash-tab-content').removeClass('active');
    $(`#${tab}-content`).addClass('active');
});

function loadTrashData() {
    const patientId = currentPatient || null;

    // Load dismissed requests
    $.ajax({
        url: `/lab-workbench/dismissed-requests/${patientId || ''}`,
        method: 'GET',
        success: function(data) {
            $('#dismissed-count').text(data.length);
            updateTrashTotalCount();

            // Check if DataTables is loaded
            if (typeof $.fn.DataTable === 'undefined') {
                console.error('DataTables library is not loaded');
                $('#dismissed-table').html('<tr><td class="text-danger">Error: DataTables library not loaded</td></tr>');
                return;
            }

            if ($.fn.DataTable.isDataTable('#dismissed-table')) {
                $('#dismissed-table').DataTable().destroy();
            }

            $('#dismissed-table').DataTable({
                data: data,
                columns: [{
                    data: null,
                    render: function(data) {
                        return createTrashCard(data, 'dismissed');
                    }
                }],
                ordering: false,
                searching: true,
                pageLength: 10,
                language: {
                    emptyTable: "No dismissed requests found"
                }
            });
        }
    });

    // Load deleted requests
    $.ajax({
        url: `/lab-workbench/deleted-requests/${patientId || ''}`,
        method: 'GET',
        success: function(data) {
            $('#deleted-count').text(data.length);
            updateTrashTotalCount();

            // Check if DataTables is loaded
            if (typeof $.fn.DataTable === 'undefined') {
                console.error('DataTables library is not loaded');
                $('#deleted-table').html('<tr><td class="text-danger">Error: DataTables library not loaded</td></tr>');
                return;
            }

            if ($.fn.DataTable.isDataTable('#deleted-table')) {
                $('#deleted-table').DataTable().destroy();
            }

            $('#deleted-table').DataTable({
                data: data,
                columns: [{
                    data: null,
                    render: function(data) {
                        return createTrashCard(data, 'deleted');
                    }
                }],
                ordering: false,
                searching: true,
                pageLength: 10,
                language: {
                    emptyTable: "No deleted requests found"
                }
            });
        }
    });
}

function createTrashCard(data, type) {
    const serviceName = data.service ? data.service.service_name : 'N/A';
    const patientName = data.patient ? `${data.patient.user.firstname} ${data.patient.user.surname}` : 'N/A';
    const fileNo = data.patient ? data.patient.file_no : 'N/A';
    const doctorName = data.doctor ? `${data.doctor.firstname} ${data.doctor.surname}` : 'N/A';

    const date = type === 'deleted'
        ? new Date(data.deleted_at).toLocaleString()
        : new Date(data.dismissed_at).toLocaleString();

    const reason = type === 'deleted' ? data.deletion_reason : data.dismiss_reason;

    const badgeClass = type === 'deleted' ? 'badge-danger' : 'badge-warning';
    const icon = type === 'deleted' ? 'fa-trash' : 'fa-ban';

    let html = `
        <div class="card mb-2" style="border-left: 4px solid ${type === 'deleted' ? '#dc3545' : '#ffc107'};">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0">
                        <span class="badge ${badgeClass}">
                            <i class="fa ${icon}"></i> ${serviceName}
                        </span>
                    </h6>
                    <button class="btn btn-sm btn-success" onclick="restoreRequest(${data.id}, '${type}')">
                        <i class="fa fa-undo"></i> Restore
                    </button>
                </div>
                <small>
                    <div><strong>Patient:</strong> ${patientName} (${fileNo})</div>
                    <div><strong>Doctor:</strong> ${doctorName}</div>
                    <div><strong>${type === 'deleted' ? 'Deleted' : 'Dismissed'}:</strong> ${date}</div>
                    <div><strong>Reason:</strong> ${reason}</div>
                </small>
            </div>
        </div>
    `;

    return html;
}

function updateTrashTotalCount() {
    const dismissed = parseInt($('#dismissed-count').text()) || 0;
    const deleted = parseInt($('#deleted-count').text()) || 0;
    const total = dismissed + deleted;
    $('#trash-total-count').text(total);

    if (total > 0) {
        $('#trash-total-count').show();
    } else {
        $('#trash-total-count').hide();
    }
}

// Audit Log Management
let auditLogTable = null;

$('#openAuditLog').on('click', function() {
    $('#auditLogModal').modal('show');
    loadAuditLogs();
});

$('#applyAuditFilter').on('click', function() {
    loadAuditLogs();
});

function loadAuditLogs() {
    const filters = {
        action: $('#audit_action_filter').val(),
        from_date: $('#audit_from_date').val(),
        to_date: $('#audit_to_date').val()
    };

    if (currentPatient) {
        filters.patient_id = currentPatient;
    }

    if (auditLogTable) {
        auditLogTable.destroy();
    }

    auditLogTable = $('#audit-log-table').DataTable({
        ajax: {
            url: '/lab-workbench/audit-logs',
            data: filters
        },
        columns: [
            {
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleString();
                }
            },
            {
                data: 'user',
                render: function(data) {
                    return data ? `${data.firstname} ${data.surname}` : 'N/A';
                }
            },
            {
                data: 'action',
                render: function(data) {
                    const badges = {
                        'view': 'badge-info',
                        'edit': 'badge-warning',
                        'delete': 'badge-danger',
                        'restore': 'badge-success',
                        'dismiss': 'badge-warning',
                        'undismiss': 'badge-success',
                        'billing': 'badge-primary',
                        'sample_collection': 'badge-info',
                        'result_entry': 'badge-success'
                    };
                    const badgeClass = badges[data] || 'badge-secondary';
                    return `<span class="badge ${badgeClass}">${data.toUpperCase()}</span>`;
                }
            },
            {
                data: 'description',
                render: function(data, type, row) {
                    let desc = data || 'No description';
                    if (row.new_values && row.new_values.reason) {
                        desc += `<br><small class="text-muted">Reason: ${row.new_values.reason}</small>`;
                    }
                    return desc;
                }
            },
            { data: 'ip_address' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No audit logs found"
        }
    });
}

$('#exportAuditLog').on('click', function() {
    // TODO: Implement export to Excel functionality
    alert('Export feature coming soon!');
});

// Load trash counts on page load
$(document).ready(function() {
    loadTrashData();

    // Refresh trash counts every 60 seconds
    setInterval(function() {
        if (!$('#trashPanel').is(':visible')) {
            const patientId = currentPatient || null;
            $.ajax({
                url: `/lab-workbench/dismissed-requests/${patientId || ''}`,
                method: 'GET',
                success: function(data) {
                    $('#dismissed-count').text(data.length);
                    updateTrashTotalCount();
                }
            });
            $.ajax({
                url: `/lab-workbench/deleted-requests/${patientId || ''}`,
                method: 'GET',
                success: function(data) {
                    $('#deleted-count').text(data.length);
                    updateTrashTotalCount();
                }
            });
        }
    }, 60000);
});

// ============================================
// ENHANCEMENT FUNCTIONS
// ============================================

// Create vital tooltip element
function createVitalTooltip() {
    vitalTooltip = $('<div class="vital-tooltip"></div>').appendTo('body');

    // Hide on mouse leave
    $(document).on('mouseleave', '.vital-item', function() {
        vitalTooltip.removeClass('active');
    });
}

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
        deviation = temp > idealTemp ? `+${diff.toFixed(1)}°C above ideal` : `-${diff.toFixed(1)}°C below ideal`;
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
    if (!patientAllergies || patientAllergies.length === 0) {
        return [];
    }

    const alerts = [];
    medications.forEach(med => {
        const drugName = (med.drug_name || med.product_name || '').toLowerCase();
        patientAllergies.forEach(allergy => {
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
            <div class="allergy-alert-icon">⚠️</div>
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

// =============================================
// QUEUE FUNCTIONALITY
// =============================================

let queueDataTable = null;
let currentQueueFilter = 'all';

function showQueue(filter) {
    currentQueueFilter = filter;

    // Update queue title
    const titles = {
        'billing': '🟢 Awaiting Billing',
        'sample': '🟠 Awaiting Sample Collection',
        'results': '🔴 Awaiting Result Entry',
        'all': '📋 All Pending Requests'
    };
    $('#queue-view-title').html(`<i class="mdi mdi-format-list-bulleted"></i> ${titles[filter] || titles['all']}`);

    // Update active state on queue buttons
    $('.queue-item').removeClass('active');
    if (filter !== 'all') {
        $(`.queue-item[data-filter="${filter}"]`).addClass('active');
    }

    // Hide other views, show queue view
    $('#empty-state').hide();
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active');
    $('#queue-view').addClass('active');

    // On mobile, hide search pane and show main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Initialize or reload DataTable
    initializeQueueDataTable(filter);
}

function hideQueue() {
    $('#queue-view').removeClass('active');
    $('.queue-item').removeClass('active');

    if (currentPatient) {
        // If patient was selected, show their workspace
        $('#patient-header').addClass('active');
        $('#workspace-content').addClass('active');
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

// ==========================================
// REPORTS VIEW FUNCTIONS
// ==========================================

function showReports() {
    // Hide everything else
    $('#empty-state').hide();
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active');
    $('#queue-view').removeClass('active');

    // Show reports view
    $('#reports-view').addClass('active');

    // On mobile, switch to main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Load statistics and initialize if not already done
    if (!window.reportsInitialized) {
        // Don't set default dates - load all records initially
        // setDefaultDateFilters();
        loadFilterOptions();
        loadReportsStatistics();
        initializeReportsDataTable();
        // initializeReportsCharts(); // TODO: Implement when Chart.js is added
        window.reportsInitialized = true;
    } else {
        // Refresh statistics
        loadReportsStatistics();
        if (window.reportsDataTable) {
            window.reportsDataTable.ajax.reload();
        }
    }
}

function hideReports() {
    $('#reports-view').removeClass('active');
    $('#empty-state').show();

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
                <span>${data.age} • ${data.gender}</span>
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
// REPORTS VIEW EVENT HANDLERS
// ==========================================

// Open reports view
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

// Show new request button when patient is selected
function updateQuickActions() {
    if (currentPatient) {
        $('#btn-new-request').show();
    } else {
        $('#btn-new-request').hide();
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
// ==========================================

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
                <td class="text-right">₦${parseFloat(doc.revenue).toLocaleString()}</td>
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
        4: { name: 'Completed', color: '#28a745' }
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

</script>
@endsection
