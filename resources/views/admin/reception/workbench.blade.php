@extends('admin.layouts.app')

@section('title', 'Reception Workbench')

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
       REQUEST DETAILS MODAL STYLES
       ============================================= */
    #requestDetailsModal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-bottom: none;
    }

    #requestDetailsModal .modal-header.lab-header {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    }

    #requestDetailsModal .modal-header.imaging-header {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: #333;
    }

    #requestDetailsModal .modal-header.imaging-header .close {
        color: #333;
    }

    #requestDetailsModal .modal-header.product-header {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    }

    .request-header-section h4 {
        font-weight: 700;
        color: #333;
    }

    .badge-lg {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
    }

    /* Timeline Styles */
    .timeline-vertical {
        position: relative;
        padding-left: 30px;
    }

    .timeline-vertical::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 1.25rem;
        padding-left: 20px;
    }

    .timeline-item:last-child {
        padding-bottom: 0;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -20px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #e9ecef;
        border: 2px solid white;
        z-index: 1;
    }

    .timeline-item.completed::before {
        background: #28a745;
    }

    .timeline-item.in-progress::before {
        background: #17a2b8;
        animation: pulse 1.5s infinite;
    }

    .timeline-item.pending::before {
        background: #6c757d;
    }

    .timeline-item .timeline-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 0.25rem;
    }

    .timeline-item .timeline-subtitle {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .timeline-item .timeline-meta {
        font-size: 0.75rem;
        color: #adb5bd;
    }

    .bg-warning-light {
        background-color: #fff9e6 !important;
    }

    /* Billing Badge Styles for Details Modal */
    .billing-badge.billing-pending {
        background: #fff3cd;
        color: #856404;
    }

    .billing-badge.billing-billed {
        background: #cce5ff;
        color: #004085;
    }

    .billing-badge.billing-paid {
        background: #d4edda;
        color: #155724;
    }

    /* Delivery Badge Styles for Details Modal */
    .delivery-badge.delivery-pending {
        background: #f8d7da;
        color: #721c24;
    }

    .delivery-badge.delivery-progress {
        background: #cce5ff;
        color: #004085;
    }

    .delivery-badge.delivery-completed {
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

    /* ============================================
       PATIENT FORM MODAL STYLES
       ============================================ */

    #patientFormModal .modal-dialog {
        max-width: 900px;
    }

    #patientFormModal .modal-content {
        border: none;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    #patient-form-header {
        background: linear-gradient(135deg, var(--hospital-primary) 0%, #0052a3 100%);
        color: white;
        padding: 1.25rem 1.5rem;
        border: none;
    }

    #patient-form-header.edit-mode {
        background: linear-gradient(135deg, #28a745 0%, #1e7b34 100%);
    }

    #patient-form-title {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Form Stepper */
    .form-stepper {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 1.5rem 2rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        gap: 0;
    }

    .stepper-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .stepper-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #6c757d;
        transition: all 0.3s ease;
        border: 3px solid transparent;
    }

    .stepper-item.active .stepper-icon {
        background: var(--hospital-primary);
        color: white;
        border-color: rgba(0, 123, 255, 0.3);
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.15);
    }

    .stepper-item.completed .stepper-icon {
        background: #28a745;
        color: white;
    }

    .stepper-label {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stepper-item.active .stepper-label {
        color: var(--hospital-primary);
    }

    .stepper-item.completed .stepper-label {
        color: #28a745;
    }

    .stepper-line {
        width: 60px;
        height: 3px;
        background: #e9ecef;
        margin: 0 0.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
    }

    .stepper-line.completed {
        background: #28a745;
    }

    /* Form Steps Container */
    .form-steps-container {
        padding: 0;
        max-height: 55vh;
        overflow-y: auto;
    }

    .form-step {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .form-step.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .step-header {
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
        border-bottom: 1px solid #e9ecef;
    }

    .step-header h6 {
        margin: 0 0 0.25rem;
        color: #212529;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .step-header h6 i {
        color: var(--hospital-primary);
    }

    .step-content {
        padding: 1.5rem;
    }

    /* Floating Labels */
    .floating-label {
        position: relative;
        margin-bottom: 1.25rem;
    }

    .floating-label label {
        position: absolute;
        top: 0.75rem;
        left: 0.75rem;
        font-size: 0.875rem;
        color: #6c757d;
        transition: all 0.2s ease;
        pointer-events: none;
        background: transparent;
        padding: 0 0.25rem;
        z-index: 1;
    }

    .floating-label .form-control {
        padding: 0.75rem;
        padding-top: 1.25rem;
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
        background: #fff;
    }

    .floating-label .form-control:focus {
        border-color: var(--hospital-primary);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .floating-label .form-control:focus + label,
    .floating-label .form-control:not(:placeholder-shown) + label,
    .floating-label .form-control.has-value + label,
    .floating-label select.form-control + label {
        top: -0.5rem;
        left: 0.5rem;
        font-size: 0.75rem;
        background: white;
        color: var(--hospital-primary);
        font-weight: 600;
    }

    .floating-label select.form-control {
        padding-top: 1.25rem;
    }

    .floating-label .form-control.is-valid {
        border-color: #28a745;
    }

    .floating-label .form-control.is-invalid {
        border-color: #dc3545;
    }

    .floating-label .invalid-feedback {
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }

    .floating-label .input-addon {
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
    }

    .floating-label .toggle-edit {
        cursor: pointer;
        padding: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .floating-label .toggle-edit input {
        cursor: pointer;
    }

    .floating-label .toggle-edit i {
        color: #6c757d;
        font-size: 1rem;
    }

    /* File Number Label Row */
    .file-no-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.25rem;
    }

    .file-no-next-badge {
        font-size: 0.7rem;
        color: #6c757d;
        background: #e9ecef;
        padding: 0.15rem 0.5rem;
        border-radius: 0.25rem;
    }

    .file-no-next-badge strong {
        color: #28a745;
    }

    .file-no-next-badge.manual-mode {
        background: #fff3cd;
        color: #856404;
    }

    .file-no-next-badge.manual-mode strong {
        color: #fd7e14;
    }

    /* File Number Button Group */
    .file-no-btn-group {
        display: flex;
        margin-bottom: 0.5rem;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 2px solid #e9ecef;
    }

    .file-no-mode-btn {
        flex: 1;
        padding: 0.5rem 1rem;
        border: none;
        background: #f8f9fa;
        color: #6c757d;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }

    .file-no-mode-btn:first-child {
        border-right: 1px solid #e9ecef;
    }

    .file-no-mode-btn:hover:not(.active) {
        background: #e9ecef;
    }

    .file-no-mode-btn.active[data-mode="auto"] {
        background: #28a745;
        color: white;
    }

    .file-no-mode-btn.active[data-mode="manual"] {
        background: #fd7e14;
        color: white;
    }

    .file-no-mode-btn i {
        font-size: 1rem;
    }

    /* File Number Input */
    .file-no-input {
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .file-no-input[readonly] {
        background: #f8f9fa;
        border-color: #28a745;
    }

    .file-no-input:not([readonly]) {
        background: #fff;
        border-color: #fd7e14;
    }

    .file-no-input:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(253, 126, 20, 0.15);
    }

    #pf-file-no-hint {
        margin-top: 0.35rem;
    }

    #pf-file-no-hint.manual-mode {
        color: #fd7e14;
    }

    #pf-file-no-hint.manual-mode i {
        animation: pulse 1s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Allergies Input */
    .allergies-input-container {
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 0.5rem;
        background: white;
        min-height: 80px;
    }

    .allergies-input-container:focus-within {
        border-color: var(--hospital-primary);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .allergies-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .allergy-tag-item {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        background: linear-gradient(135deg, #ffe5e5 0%, #ffcccc 100%);
        border: 1px solid #ff9999;
        border-radius: 2rem;
        font-size: 0.85rem;
        color: #cc0000;
        font-weight: 500;
    }

    .allergy-tag-item .remove-allergy {
        cursor: pointer;
        margin-left: 0.25rem;
        color: #cc0000;
        font-size: 1rem;
        line-height: 1;
    }

    .allergy-tag-item .remove-allergy:hover {
        color: #990000;
    }

    #pf-allergy-input {
        border: none;
        outline: none;
        width: 100%;
        padding: 0.25rem;
        font-size: 0.9rem;
    }

    /* Registration Summary */
    .registration-summary {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border: 2px solid #81c784;
        border-radius: 0.75rem;
        padding: 1.25rem;
    }

    .registration-summary > h6 {
        margin: 0 0 1rem;
        color: #2e7d32;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }

    .summary-section {
        background: rgba(255, 255, 255, 0.5);
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .summary-section:last-child {
        margin-bottom: 0;
    }

    .summary-section-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: #495057;
        margin: 0 0 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        padding-bottom: 0.35rem;
    }

    .summary-section-title i {
        color: var(--hospital-primary);
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.5rem;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 0.35rem 0.5rem;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 0.25rem;
        font-size: 0.8rem;
    }

    .summary-item.full-width {
        grid-column: 1 / -1;
    }

    .summary-label {
        color: #6c757d;
        font-size: 0.75rem;
        flex-shrink: 0;
        margin-right: 0.5rem;
    }

    .summary-value {
        font-weight: 600;
        color: #212529;
        font-size: 0.8rem;
        text-align: right;
        word-break: break-word;
    }

    .summary-value.text-success {
        color: #28a745 !important;
    }

    /* File Upload Preview */
    .passport-preview, .old-records-info {
        display: flex;
        align-items: center;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        border: 1px dashed #dee2e6;
    }

    .passport-preview img {
        border: 2px solid #28a745;
    }

    /* Modal Footer */
    #patientFormModal .modal-footer {
        display: flex;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }

    .footer-left, .footer-right {
        display: flex;
        gap: 0.5rem;
    }

    #pf-btn-prev, #pf-btn-next, #pf-btn-submit {
        padding: 0.625rem 1.25rem;
        font-weight: 600;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #pf-btn-submit {
        background: linear-gradient(135deg, #28a745 0%, #1e7b34 100%);
        border: none;
    }

    #pf-btn-submit:hover {
        background: linear-gradient(135deg, #218838 0%, #196d2e 100%);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .form-stepper {
            padding: 1rem;
            flex-wrap: wrap;
        }

        .stepper-line {
            display: none;
        }

        .stepper-item {
            margin-bottom: 0.5rem;
        }

        .stepper-icon {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .stepper-label {
            font-size: 0.65rem;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }
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

        <div class="search-container" style="position: relative;">
            <input type="text"
                   id="patient-search-input"
                   placeholder="Search or scan barcode..."
                   autocomplete="off">
            <div class="search-results" id="patient-search-results"></div>
        </div>

        <div class="queue-widget">
            <h6><i class="mdi mdi-format-list-bulleted"></i> DOCTOR QUEUE</h6>
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
            <button class="quick-action-btn" id="btn-today-stats">
                <i class="mdi mdi-chart-bar"></i>
                <span>Today's Stats</span>
            </button>
            <button class="quick-action-btn" id="btn-view-reports">
                <i class="mdi mdi-file-chart"></i>
                <span>Reports</span>
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
                                        @foreach(\App\Models\Hmo::orderBy('name')->get() as $hmo)
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
                </div>
            </div>
        </div>

        <!-- Patient Header -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div style="flex: 1;">
                    <div class="patient-name" id="patient-name"></div>
                    <div class="patient-meta" id="patient-meta"></div>
                    <div class="patient-allergies" id="patient-allergies" style="display: none;">
                        <span class="allergy-alert-badge"><i class="mdi mdi-alert"></i> Allergies: <span id="allergy-list"></span></span>
                    </div>
                </div>
                <div class="patient-account-balance" id="patient-header-balance" style="display: none;">
                    <div class="balance-label">Account Balance</div>
                    <div class="balance-value" id="header-balance-amount">₦0.00</div>
                </div>
                <button class="btn btn-sm btn-light" id="btn-edit-patient" title="Edit Patient">
                    <i class="mdi mdi-pencil"></i> Edit
                </button>
                <button class="btn btn-sm btn-info" id="btn-print-card" title="Print Hospital Card">
                    <i class="mdi mdi-card-account-details"></i> Print Card
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
                                        <div class="form-group mb-3">
                                            <label><i class="mdi mdi-medical-bag"></i> Service <span class="text-danger">*</span></label>
                                            <select class="form-control" id="booking-service" required>
                                                <option value="">-- Select Service --</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label><i class="mdi mdi-hospital-building"></i> Clinic <span class="text-danger">*</span></label>
                                            <select class="form-control" id="booking-clinic" required>
                                                <option value="">-- Select Clinic --</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label><i class="mdi mdi-doctor"></i> Doctor</label>
                                            <select class="form-control" id="booking-doctor">
                                                <option value="">Any Available Doctor</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-lg w-100" id="btn-book-consultation">
                                            <i class="mdi mdi-send"></i> Send to Queue
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card-modern" id="tariff-preview-card" style="display: none;">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0"><i class="mdi mdi-calculator"></i> Tariff Preview</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td>Service Price:</td>
                                                <td class="text-right"><strong id="tariff-base-price">₦0</strong></td>
                                            </tr>
                                            <tr id="tariff-hmo-row" style="display: none;">
                                                <td>HMO Coverage (<span id="tariff-coverage-mode"></span>):</td>
                                                <td class="text-right text-success"><strong id="tariff-claims-amount">₦0</strong></td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td><strong>Patient Pays:</strong></td>
                                                <td class="text-right"><strong id="tariff-payable-amount">₦0</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="alert alert-info" id="tariff-validation-alert" style="display: none;">
                                        <i class="mdi mdi-information"></i> <span id="tariff-validation-message"></span>
                                    </div>
                                </div>
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
                                            placeholder="ðŸ” Search services/products...">
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
        </div>
    </div>
</div>

<!-- My Transactions Modal (Global Access) -->
<div class="modal fade" id="myTransactionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--hospital-primary); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-receipt"></i> My Transactions</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="my-transactions-modal-body">
                <!-- Filter Panel -->
                <div class="my-transactions-filter">
                    <div class="row">
                        <div class="col-md-2">
                            <label>From Date</label>
                            <input type="date" class="form-control" id="my-trans-from-date">
                        </div>
                        <div class="col-md-2">
                            <label>To Date</label>
                            <input type="date" class="form-control" id="my-trans-to-date">
                        </div>
                        <div class="col-md-2">
                            <label>Payment Type</label>
                            <select class="form-control" id="my-trans-payment-type">
                                <option value="">All Types</option>
                                <option value="CASH">Cash</option>
                                <option value="POS">POS/Card</option>
                                <option value="TRANSFER">Bank Transfer</option>
                                <option value="MOBILE">Mobile Money</option>
                                <option value="ACC_DEPOSIT">Account Deposit</option>
                                <option value="ACC_WITHDRAW">Account Withdrawal</option>
                                <option value="ACC_ADJUSTMENT">Adjustment</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Bank</label>
                            <select class="form-control" id="my-trans-bank">
                                <option value="">All Banks</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label>
                            <div class="btn-group btn-block">
                                <button class="btn btn-primary" id="load-my-transactions">
                                    <i class="mdi mdi-filter"></i> Load
                                </button>
                                <button class="btn btn-info" id="print-my-transactions">
                                    <i class="mdi mdi-printer"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="my-transactions-summary" id="my-transactions-summary" style="display: none;">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-transactions">0</div>
                                <div class="stat-label">Total Transactions</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-amount">₦0.00</div>
                                <div class="stat-label">Total Amount</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-stat-card">
                                <div class="stat-value" id="my-total-discounts">₦0.00</div>
                                <div class="stat-label">Total Discounts</div>
                            </div>
                        </div>
                    </div>
                    <!-- Breakdown by payment type -->
                    <div class="payment-type-breakdown" id="payment-type-breakdown"></div>
                </div>

                <!-- Transactions Table -->
                <div class="my-transactions-container">
                    <table class="table table-hover" id="my-transactions-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>File No</th>
                                <th>Reference</th>
                                <th>Method</th>
                                <th>Bank</th>
                                <th>Amount</th>
                                <th>Discount</th>
                            </tr>
                        </thead>
                        <tbody id="my-transactions-tbody">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                                    <p>Click "Load" to fetch your transactions</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Receipt Preview Modal */
.receipt-modal-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.receipt-modal-tab {
    flex: 1;
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
    justify-content: center;
    gap: 0.5rem;
}

.receipt-modal-tab:hover {
    background: #e9ecef;
    color: var(--hospital-primary);
}

.receipt-modal-tab.active {
    background: white;
    color: var(--hospital-primary);
    border-bottom-color: var(--hospital-primary);
}

.receipt-modal-content {
    padding: 1.5rem;
    background: #f8f9fa;
    max-height: 60vh;
    overflow-y: auto;
}

.receipt-modal-pane {
    background: white;
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#receiptPreviewModal .modal-footer {
    justify-content: center;
    gap: 0.5rem;
}
</style>

<!-- Receipt Preview Modal -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-receipt"></i> Receipt Preview</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="receipt-modal-tabs">
                    <button class="receipt-modal-tab active" data-format="a4">
                        <i class="mdi mdi-file-document"></i> A4 Receipt
                    </button>
                    <button class="receipt-modal-tab" data-format="thermal">
                        <i class="mdi mdi-receipt"></i> Thermal Receipt
                    </button>
                </div>
                <div class="receipt-modal-content">
                    <div class="receipt-modal-pane active" id="modal-receipt-a4"></div>
                    <div class="receipt-modal-pane" id="modal-receipt-thermal" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="modal-print-a4">
                    <i class="mdi mdi-printer"></i> Print A4
                </button>
                <button type="button" class="btn btn-info" id="modal-print-thermal">
                    <i class="mdi mdi-printer"></i> Print Thermal
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
            </div>
        </div>
    </div>
</div>

<!-- Patient Form Modal (Register/Edit) -->
<div class="modal fade" id="patientFormModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" id="patient-form-header">
                <h5 class="modal-title" id="patient-form-title"><i class="mdi mdi-account-plus"></i> New Patient Registration</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="patient-form" novalidate>
                <input type="hidden" id="patient-form-mode" value="create">
                <input type="hidden" id="patient-form-id" value="">

                <div class="modal-body p-0">
                    <!-- Progress Stepper -->
                    <div class="form-stepper">
                        <div class="stepper-item active" data-step="1">
                            <div class="stepper-icon"><i class="mdi mdi-account"></i></div>
                            <div class="stepper-label">Basic Info</div>
                        </div>
                        <div class="stepper-line"></div>
                        <div class="stepper-item" data-step="2">
                            <div class="stepper-icon"><i class="mdi mdi-clipboard-pulse"></i></div>
                            <div class="stepper-label">Medical</div>
                        </div>
                        <div class="stepper-line"></div>
                        <div class="stepper-item" data-step="3">
                            <div class="stepper-icon"><i class="mdi mdi-account-supervisor"></i></div>
                            <div class="stepper-label">Next of Kin</div>
                        </div>
                        <div class="stepper-line"></div>
                        <div class="stepper-item" data-step="4">
                            <div class="stepper-icon"><i class="mdi mdi-shield-account"></i></div>
                            <div class="stepper-label">Insurance</div>
                        </div>
                    </div>

                    <div class="form-steps-container">
                        <!-- Step 1: Basic Information -->
                        <div class="form-step active" data-step="1">
                            <div class="step-header">
                                <h6><i class="mdi mdi-account"></i> Basic Information</h6>
                                <p class="text-muted mb-0">Personal details and contact information</p>
                            </div>
                            <div class="step-content">
                                <div class="row align-items-end">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <div class="file-no-label-row">
                                                <label class="form-label mb-0">File Number <span class="text-danger">*</span></label>
                                                <span class="file-no-next-badge" id="pf-file-no-hint" title="Next auto-generated number">
                                                    Next: <strong id="pf-next-file-no">--</strong>
                                                </span>
                                            </div>
                                            <div class="file-no-btn-group">
                                                <button type="button" class="file-no-mode-btn active" data-mode="auto">
                                                    <i class="mdi mdi-autorenew"></i> Auto
                                                </button>
                                                <button type="button" class="file-no-mode-btn" data-mode="manual">
                                                    <i class="mdi mdi-pencil"></i> Manual
                                                </button>
                                            </div>
                                            <input type="text" class="form-control file-no-input" id="pf-file-no" readonly placeholder="Auto-generated">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Surname <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="pf-surname" required data-validate="required|min:2" placeholder="Enter surname">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="pf-firstname" required data-validate="required|min:2" placeholder="Enter first name">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Other Names</label>
                                            <input type="text" class="form-control" id="pf-othername" placeholder="Enter other names">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Gender <span class="text-danger">*</span></label>
                                            <select class="form-control" id="pf-gender" required data-validate="required">
                                                <option value="">Select gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Others">Others</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Date of Birth <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="pf-dob" required data-validate="required">
                                            <div class="invalid-feedback"></div>
                                            <small class="form-text" id="pf-age-display"></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Phone Number</label>
                                            <input type="tel" class="form-control" id="pf-phone" data-validate="phone" placeholder="Enter phone number">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Email Address</label>
                                            <input type="email" class="form-control" id="pf-email" data-validate="email" placeholder="Enter email address">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Residential Address</label>
                                            <textarea class="form-control" id="pf-address" rows="2" placeholder="Enter residential address"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1"><i class="mdi mdi-camera text-primary"></i> Passport Photo</label>
                                            <input type="file" class="form-control" id="pf-passport" accept="image/*">
                                            <small class="form-text text-muted">Upload patient photo (JPG, PNG)</small>
                                            <!-- Existing passport preview -->
                                            <div class="passport-preview-container mt-2" style="display: none;">
                                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light">
                                                    <img src="" alt="Current Photo" id="passport-preview-img" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 2px solid #007bff;">
                                                    <div class="flex-grow-1">
                                                        <small class="text-success d-block"><i class="mdi mdi-check-circle"></i> Current Photo</small>
                                                        <small class="text-muted">Select new file to replace</small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="pf-clear-passport" title="Remove photo">
                                                        <i class="mdi mdi-close"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- New file preview -->
                                            <div class="passport-new-preview mt-2" id="pf-passport-new-preview" style="display: none;">
                                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-success-subtle">
                                                    <img src="" alt="New Photo" id="passport-new-img" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 2px solid #28a745;">
                                                    <div class="flex-grow-1">
                                                        <small class="text-success d-block"><i class="mdi mdi-upload"></i> New Photo Selected</small>
                                                        <small class="text-muted" id="passport-new-name"></small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pf-cancel-passport" title="Cancel">
                                                        <i class="mdi mdi-undo"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1"><i class="mdi mdi-file-document text-info"></i> Old Records</label>
                                            <input type="file" class="form-control" id="pf-old-records" accept=".pdf,.doc,.docx,.jpg,.png">
                                            <small class="form-text text-muted">Upload previous medical records (PDF, DOC, images)</small>
                                            <!-- Existing old records preview -->
                                            <div class="old-records-preview-container mt-2" style="display: none;">
                                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light">
                                                    <div class="file-icon-preview" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 6px;">
                                                        <img src="" alt="Record" id="old-records-preview-img" style="max-width: 100%; max-height: 100%; border-radius: 4px; display: none;">
                                                        <i class="mdi mdi-file-document text-info" id="old-records-preview-icon" style="font-size: 28px; display: none;"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <small class="text-success d-block"><i class="mdi mdi-check-circle"></i> Current Record</small>
                                                        <small class="text-muted text-truncate d-block" id="old-records-preview-name" style="max-width: 150px;"></small>
                                                    </div>
                                                    <a href="#" class="btn btn-sm btn-outline-info" id="pf-view-old-records" title="View" target="_blank">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" id="pf-clear-old-records" title="Remove">
                                                        <i class="mdi mdi-close"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- New file preview -->
                                            <div class="old-records-new-preview mt-2" id="pf-old-records-new-preview" style="display: none;">
                                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-success-subtle">
                                                    <div class="file-icon-preview" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #d4edda; border-radius: 6px;">
                                                        <i class="mdi mdi-file-upload text-success" style="font-size: 28px;"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <small class="text-success d-block"><i class="mdi mdi-upload"></i> New File Selected</small>
                                                        <small class="text-muted text-truncate d-block" id="old-records-new-name" style="max-width: 150px;"></small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pf-cancel-old-records" title="Cancel">
                                                        <i class="mdi mdi-undo"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Medical Information -->
                        <div class="form-step" data-step="2">
                            <div class="step-header">
                                <h6><i class="mdi mdi-clipboard-pulse"></i> Medical Information</h6>
                                <p class="text-muted mb-0">Health and demographic details</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Blood Group</label>
                                            <select class="form-control" id="pf-blood-group">
                                                <option value="">Select blood group</option>
                                                <option value="A+">A+</option>
                                                <option value="A-">A-</option>
                                                <option value="B+">B+</option>
                                                <option value="B-">B-</option>
                                                <option value="AB+">AB+</option>
                                                <option value="AB-">AB-</option>
                                                <option value="O+">O+</option>
                                                <option value="O-">O-</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Genotype</label>
                                            <select class="form-control" id="pf-genotype">
                                                <option value="">Select genotype</option>
                                                <option value="AA">AA</option>
                                                <option value="AS">AS</option>
                                                <option value="AC">AC</option>
                                                <option value="SS">SS</option>
                                                <option value="SC">SC</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Disability Status</label>
                                            <select class="form-control" id="pf-disability">
                                                <option value="0">No Disability</option>
                                                <option value="1">Has Disability</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Nationality</label>
                                            <input type="text" class="form-control" id="pf-nationality" value="Nigerian" placeholder="Enter nationality">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Ethnicity</label>
                                            <input type="text" class="form-control" id="pf-ethnicity" placeholder="Enter ethnicity">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1"><i class="mdi mdi-alert-circle text-warning"></i> Known Allergies</label>
                                            <div class="allergies-input-container">
                                                <div class="allergies-tags" id="pf-allergies-tags"></div>
                                                <input type="text" class="form-control" id="pf-allergy-input" placeholder="Type allergy and press Enter">
                                            </div>
                                            <input type="hidden" id="pf-allergies" value="[]">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Medical History</label>
                                            <textarea class="form-control" id="pf-medical-history" rows="3" placeholder="Enter relevant medical history"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Additional Notes</label>
                                            <textarea class="form-control" id="pf-misc" rows="2" placeholder="Any additional notes"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Next of Kin -->
                        <div class="form-step" data-step="3">
                            <div class="step-header">
                                <h6><i class="mdi mdi-account-supervisor"></i> Next of Kin / Emergency Contact</h6>
                                <p class="text-muted mb-0">Emergency contact information</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Next of Kin Name</label>
                                            <input type="text" class="form-control" id="pf-nok-name" placeholder="Enter next of kin name">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Next of Kin Phone</label>
                                            <input type="tel" class="form-control" id="pf-nok-phone" placeholder="Enter phone number">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Next of Kin Address</label>
                                            <textarea class="form-control" id="pf-nok-address" rows="2" placeholder="Enter address"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-3">
                                    <i class="mdi mdi-information"></i>
                                    <strong>Tip:</strong> Next of kin information is optional but recommended for emergency situations.
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Insurance Information -->
                        <div class="form-step" data-step="4">
                            <div class="step-header">
                                <h6><i class="mdi mdi-shield-account"></i> Insurance / HMO Information</h6>
                                <p class="text-muted mb-0">Health insurance and payment details</p>
                            </div>
                            <div class="step-content">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">HMO Provider</label>
                                            <select class="form-control" id="pf-hmo">
                                                <!-- Options populated by JS, HMO ID 1 (Private) is default -->
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="pf-hmo-no-container" style="display: none;">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">HMO Enrollment Number</label>
                                            <input type="text" class="form-control" id="pf-hmo-no" placeholder="Enter enrollment number">
                                        </div>
                                    </div>
                                </div>
                                <div class="row" id="pf-hmo-no-container-alt" style="display: none;">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">HMO Plan</label>
                                            <input type="text" class="form-control" id="pf-hmo-plan" placeholder="Enter HMO plan">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label class="form-label mb-1">Company/Organization</label>
                                            <input type="text" class="form-control" id="pf-company" placeholder="Enter company name">
                                        </div>
                                    </div>
                                </div>

                                <!-- Comprehensive Summary Card -->
                                <div class="registration-summary mt-4" id="registration-summary">
                                    <h6><i class="mdi mdi-clipboard-check"></i> Registration Summary</h6>

                                    <!-- Basic Information -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-account"></i> Basic Information</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">File No:</span>
                                                <span class="summary-value" id="summary-file-no">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Name:</span>
                                                <span class="summary-value" id="summary-name">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Gender:</span>
                                                <span class="summary-value" id="summary-gender">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Date of Birth:</span>
                                                <span class="summary-value" id="summary-dob">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Age:</span>
                                                <span class="summary-value" id="summary-age">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Phone:</span>
                                                <span class="summary-value" id="summary-phone">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Email:</span>
                                                <span class="summary-value" id="summary-email">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Address:</span>
                                                <span class="summary-value" id="summary-address">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Passport:</span>
                                                <span class="summary-value" id="summary-passport">Not uploaded</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Old Records:</span>
                                                <span class="summary-value" id="summary-old-records">Not uploaded</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Medical Information -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-clipboard-pulse"></i> Medical Information</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">Blood Group:</span>
                                                <span class="summary-value" id="summary-blood-group">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Genotype:</span>
                                                <span class="summary-value" id="summary-genotype">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Disability:</span>
                                                <span class="summary-value" id="summary-disability">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Nationality:</span>
                                                <span class="summary-value" id="summary-nationality">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Ethnicity:</span>
                                                <span class="summary-value" id="summary-ethnicity">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Allergies:</span>
                                                <span class="summary-value" id="summary-allergies">None</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Medical History:</span>
                                                <span class="summary-value" id="summary-medical-history">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Next of Kin -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-account-supervisor"></i> Next of Kin</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">Name:</span>
                                                <span class="summary-value" id="summary-nok-name">-</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">Phone:</span>
                                                <span class="summary-value" id="summary-nok-phone">-</span>
                                            </div>
                                            <div class="summary-item full-width">
                                                <span class="summary-label">Address:</span>
                                                <span class="summary-value" id="summary-nok-address">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Insurance Information -->
                                    <div class="summary-section">
                                        <h6 class="summary-section-title"><i class="mdi mdi-shield-account"></i> Insurance</h6>
                                        <div class="summary-grid">
                                            <div class="summary-item">
                                                <span class="summary-label">HMO:</span>
                                                <span class="summary-value" id="summary-hmo">Private</span>
                                            </div>
                                            <div class="summary-item">
                                                <span class="summary-label">HMO No:</span>
                                                <span class="summary-value" id="summary-hmo-no">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button type="button" class="btn btn-outline-secondary" id="pf-btn-prev" style="display: none;">
                            <i class="mdi mdi-chevron-left"></i> Previous
                        </button>
                    </div>
                    <div class="footer-right">
                        <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="pf-btn-next">
                            Next <i class="mdi mdi-chevron-right"></i>
                        </button>
                        <button type="submit" class="btn btn-success" id="pf-btn-submit" style="display: none;">
                            <i class="mdi mdi-check"></i> <span id="pf-submit-text">Register Patient</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Register Modal (Legacy - kept for compatibility) -->
<div class="modal fade" id="quickRegisterModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="mdi mdi-account-plus"></i> Quick Patient Registration</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="quick-register-form">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>File Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="quick-register-file-no" readonly>
                                    <div class="input-group-append">
                                        <div class="input-group-text" style="padding: 0;">
                                            <label class="mb-0 px-2 d-flex align-items-center" title="Toggle manual edit" style="cursor: pointer;">
                                                <input type="checkbox" id="toggle-file-no-edit" class="mr-1">
                                                <i class="mdi mdi-pencil"></i>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">Next serial number auto-generated</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" class="form-control" id="quick-register-phone" placeholder="08012345678">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-register-firstname" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-register-lastname" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Gender <span class="text-danger">*</span></label>
                                <select class="form-control" id="quick-register-gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" class="form-control" id="quick-register-dob">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>HMO</label>
                                <select class="form-control" id="quick-register-hmo">
                                    <option value="">No HMO (Private)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="hmo-no-row" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>HMO Number</label>
                                <input type="text" class="form-control" id="quick-register-hmo-no" placeholder="Enter HMO enrollment number">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-account-plus"></i> Register Patient
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" id="request-details-header">
                <h5 class="modal-title" id="request-details-title">
                    <i class="mdi mdi-clipboard-text"></i> Request Details
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="request-details-body">
                <div class="text-center py-5" id="request-details-loading">
                    <i class="mdi mdi-loading mdi-spin mdi-36px text-primary"></i>
                    <p class="mt-2 mb-0">Loading request details...</p>
                </div>
                <div id="request-details-content" style="display: none;">
                    <!-- Header Section -->
                    <div class="request-header-section mb-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-1" id="detail-request-no"></h4>
                                <span class="badge badge-lg" id="detail-type-badge"></span>
                            </div>
                            <div class="text-right">
                                <div class="mb-2">
                                    <span class="mr-2" id="detail-billing-badge"></span>
                                    <span id="detail-delivery-badge"></span>
                                </div>
                                <small class="text-muted" id="detail-requested-at"></small>
                            </div>
                        </div>
                    </div>

                    <!-- Service/Product Info -->
                    <div class="card mb-3">
                        <div class="card-header py-2 bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-information"></i> <span id="detail-info-title">Service Information</span></h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="mb-1" id="detail-item-name"></h5>
                                    <p class="text-muted mb-0" id="detail-item-category"></p>
                                    <div id="detail-dose-section" style="display: none;" class="mt-2">
                                        <small class="text-muted">Dosage:</small>
                                        <strong id="detail-dose"></strong>
                                    </div>
                                    <div id="detail-quantity-section" style="display: none;" class="mt-2">
                                        <small class="text-muted">Quantity:</small>
                                        <strong id="detail-quantity"></strong>
                                        <span class="text-muted"> × ₦<span id="detail-unit-price"></span></span>
                                    </div>
                                </div>
                                <div class="col-md-4 text-right">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted">Price:</td>
                                            <td class="text-right"><strong id="detail-price"></strong></td>
                                        </tr>
                                        <tr id="detail-hmo-row">
                                            <td class="text-success">HMO Covers:</td>
                                            <td class="text-right text-success"><strong id="detail-hmo-covers"></strong></td>
                                        </tr>
                                        <tr class="border-top">
                                            <td class="text-primary">Patient Pays:</td>
                                            <td class="text-right text-primary"><strong id="detail-payable"></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clinical Note (if any) -->
                    <div class="card mb-3" id="detail-note-card" style="display: none;">
                        <div class="card-header py-2 bg-warning-light">
                            <h6 class="mb-0"><i class="mdi mdi-note-text"></i> Clinical Note</h6>
                        </div>
                        <div class="card-body py-3">
                            <p class="mb-0" id="detail-clinical-note"></p>
                        </div>
                    </div>

                    <!-- Timeline / Status History -->
                    <div class="card mb-3">
                        <div class="card-header py-2 bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-timeline"></i> Status Timeline</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="timeline-vertical" id="detail-timeline">
                                <!-- Timeline items will be populated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Result Summary (Lab/Imaging only) -->
                    <div class="card mb-3" id="detail-result-card" style="display: none;">
                        <div class="card-header py-2 bg-success text-white">
                            <h6 class="mb-0"><i class="mdi mdi-file-document"></i> Result Summary</h6>
                        </div>
                        <div class="card-body py-3">
                            <div id="detail-result-content">
                                <p class="text-muted mb-0" id="detail-result-summary"></p>
                            </div>
                            <div id="detail-no-result" style="display: none;">
                                <p class="text-muted mb-0"><i class="mdi mdi-clock-outline"></i> Result not yet available</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info (if paid) -->
                    <div class="card mb-0" id="detail-payment-card" style="display: none;">
                        <div class="card-header py-2 bg-success text-white">
                            <h6 class="mb-0"><i class="mdi mdi-cash-check"></i> Payment Information</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Payment Reference:</small>
                                    <p class="mb-2"><strong id="detail-payment-ref"></strong></p>
                                </div>
                                <div class="col-md-6 text-right">
                                    <small class="text-muted">Payment Date:</small>
                                    <p class="mb-0"><strong id="detail-payment-date"></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Discard Request Modal -->
<div class="modal fade" id="discardRequestModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-delete-alert"></i> Discard Request</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="discardRequestForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert"></i>
                        <strong>Warning:</strong> This action will discard the request. This cannot be undone easily.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="discard_service_name"></span></p>
                        <p><strong>Request No:</strong> <span id="discard_request_no"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="discard_reason">Reason for Discarding <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="discard_reason" name="reason" rows="3"
                                  placeholder="Please provide a reason for discarding this request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="mdi mdi-close"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" id="confirmDiscardBtn">
                        <i class="mdi mdi-delete"></i> Discard Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hospital Patient Card Modal -->
<div class="modal fade" id="hospitalCardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ appsettings()->hos_color ?? '#0066cc' }}; color: white;">
                <h5 class="modal-title"><i class="mdi mdi-card-account-details"></i> Hospital Patient Card</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <!-- Card Preview Container -->
                <div id="hospital-card-container" style="display: inline-block;">
                    <!-- FRONT SIDE -->
                    <div class="hospital-card hospital-card-front" id="hospital-card-preview">
                        <!-- Card Header with Hospital Info -->
                        <div class="card-header-section">
                            <div class="hospital-logo-section">
                                @if(appsettings()->logo)
                                    <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="Hospital Logo" class="hospital-logo">
                                @else
                                    <div class="hospital-logo-placeholder">
                                        <i class="mdi mdi-hospital-building"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="hospital-info-section">
                                <div class="hospital-name-text">{{ appsettings()->site_name ?? 'Hospital Name' }}</div>
                                <div class="hospital-address-text">{{ appsettings()->contact_address ?? '' }}</div>
                                <div class="hospital-phone-text">{{ appsettings()->contact_phones ?? '' }}</div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body-section">
                            <div class="patient-photo-section">
                                <img src="" alt="Patient Photo" id="card-patient-photo" class="patient-photo">
                            </div>
                            <div class="patient-info-section">
                                <div class="patient-name-text" id="card-patient-name">Jane Doe</div>
                                <div class="patient-details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Patient ID</span>
                                        <span class="detail-value" id="card-patient-id">JD123456</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">DOB</span>
                                        <span class="detail-value" id="card-dob">01/01/1970</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Blood</span>
                                        <span class="detail-value" id="card-blood-type">O+</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Genotype</span>
                                        <span class="detail-value" id="card-genotype">AA</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Barcode Section -->
                        <div class="card-barcode-section">
                            <svg id="card-barcode"></svg>
                            <div class="barcode-number" id="card-barcode-number"></div>
                        </div>

                        <!-- Card Footer -->
                        <div class="card-footer-section">
                            <span class="card-type-badge">PATIENT CARD</span>
                            <span class="powered-by">CoreHealth by corestream.ng</span>
                        </div>
                    </div>

                    <!-- BACK SIDE -->
                    <div class="hospital-card hospital-card-back" id="hospital-card-back" style="margin-top: 15px;">
                        <!-- Back Header -->
                        <div class="card-back-header">
                            <div class="back-title">PATIENT INFORMATION</div>
                        </div>

                        <!-- Back Body -->
                        <div class="card-back-body">
                            <div class="back-info-row">
                                <span class="back-label">Gender:</span>
                                <span class="back-value" id="card-gender">Female</span>
                            </div>
                            <div class="back-info-row">
                                <span class="back-label">Phone:</span>
                                <span class="back-value" id="card-phone">08012345678</span>
                            </div>
                            <div class="back-info-row full-width">
                                <span class="back-label">Address:</span>
                                <span class="back-value" id="card-address">123 Main Street, Lagos</span>
                            </div>
                            <div class="back-info-row full-width">
                                <span class="back-label">Allergies:</span>
                                <span class="back-value" id="card-allergies">None known</span>
                            </div>
                            <div class="back-divider"></div>
                            <div class="back-section-title">Emergency Contact</div>
                            <div class="back-info-row">
                                <span class="back-label">Name:</span>
                                <span class="back-value" id="card-nok-name">John Doe</span>
                            </div>
                            <div class="back-info-row">
                                <span class="back-label">Phone:</span>
                                <span class="back-value" id="card-nok-phone">08098765432</span>
                            </div>
                        </div>

                        <!-- Back Footer -->
                        <div class="card-back-footer">
                            <div class="emergency-note">In case of emergency, please contact the hospital</div>
                            <div class="powered-by-back">CoreHealth by corestream.ng</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn-print-card-now">
                    <i class="mdi mdi-printer"></i> Print Card
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Hospital Card Styles - Front */
.hospital-card {
    width: 340px;
    height: 215px;
    background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    overflow: hidden;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    position: relative;
    display: flex;
    flex-direction: column;
}

.hospital-card .card-header-section {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.hospital-card .hospital-logo-section {
    width: 35px;
    height: 35px;
    flex-shrink: 0;
}

.hospital-card .hospital-logo {
    width: 35px;
    height: 35px;
    object-fit: contain;
    background: white;
    border-radius: 4px;
    padding: 2px;
}

.hospital-card .hospital-logo-placeholder {
    width: 35px;
    height: 35px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hospital-card .hospital-logo-placeholder i {
    font-size: 20px;
}

.hospital-card .hospital-info-section {
    flex: 1;
    line-height: 1.2;
}

.hospital-card .hospital-name-text {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.hospital-card .hospital-address-text {
    font-size: 7px;
    opacity: 0.9;
}

.hospital-card .hospital-phone-text {
    font-size: 7px;
    opacity: 0.9;
}

.hospital-card .card-body-section {
    display: flex;
    padding: 8px 10px;
    gap: 10px;
    flex: 1;
    min-height: 0;
}

.hospital-card .patient-photo-section {
    flex-shrink: 0;
}

.hospital-card .patient-photo {
    width: 60px;
    height: 75px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid {{ appsettings()->hos_color ?? '#0066cc' }};
    background: #f0f0f0;
}

.hospital-card .patient-info-section {
    flex: 1;
    text-align: left;
    overflow: hidden;
}

.hospital-card .patient-name-text {
    font-size: 12px;
    font-weight: 700;
    color: #333;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-bottom: 1px solid #ddd;
    padding-bottom: 4px;
}

.hospital-card .patient-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 8px;
}

.hospital-card .detail-item {
    display: flex;
    flex-direction: column;
}

.hospital-card .detail-label {
    font-size: 6px;
    color: #888;
    text-transform: uppercase;
}

.hospital-card .detail-value {
    font-size: 9px;
    font-weight: 600;
    color: #333;
}

.hospital-card .card-barcode-section {
    padding: 2px 10px;
    text-align: center;
    flex-shrink: 0;
    background: #fff;
}

.hospital-card .card-barcode-section svg {
    height: 20px;
    width: auto;
    max-width: 100%;
}

.hospital-card .barcode-number {
    font-size: 8px;
    font-family: 'Courier New', monospace;
    color: #333;
    letter-spacing: 1px;
}

.hospital-card .card-footer-section {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 3px 10px;
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hospital-card .card-type-badge {
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 1px;
}

.hospital-card .powered-by {
    font-size: 6px;
    opacity: 0.8;
}

/* Hospital Card Styles - Back */
.hospital-card-back {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.hospital-card-back .card-back-header {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 6px 10px;
    text-align: center;
}

.hospital-card-back .back-title {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
}

.hospital-card-back .card-back-body {
    padding: 8px 10px;
    flex: 1;
}

.hospital-card-back .back-info-row {
    display: flex;
    gap: 5px;
    margin-bottom: 4px;
    font-size: 8px;
}

.hospital-card-back .back-info-row.full-width {
    flex-direction: column;
    gap: 1px;
}

.hospital-card-back .back-label {
    font-weight: 600;
    color: #555;
    min-width: 50px;
}

.hospital-card-back .back-value {
    color: #333;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hospital-card-back .back-info-row.full-width .back-value {
    white-space: normal;
    font-size: 7px;
    line-height: 1.3;
}

.hospital-card-back .back-divider {
    border-top: 1px dashed #ccc;
    margin: 6px 0;
}

.hospital-card-back .back-section-title {
    font-size: 8px;
    font-weight: 700;
    color: {{ appsettings()->hos_color ?? '#0066cc' }};
    margin-bottom: 4px;
    text-transform: uppercase;
}

.hospital-card-back .card-back-footer {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 4px 10px;
    text-align: center;
}

.hospital-card-back .emergency-note {
    font-size: 7px;
    opacity: 0.9;
}

.hospital-card-back .powered-by-back {
    font-size: 6px;
    opacity: 0.7;
    margin-top: 2px;
}

/* Print Styles for Hospital Card */
@media print {
    body * {
        visibility: hidden;
    }

    #hospital-card-container, #hospital-card-container * {
        visibility: visible;
    }

    #hospital-card-container {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }

    .hospital-card {
        box-shadow: none;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .hospital-card-back {
        page-break-before: always;
        margin-top: 20px;
    }
}
</style>

@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/js/jsbarcode.all.min.js') }}"></script>
<script>
// =============================================
// RECEPTION WORKBENCH JAVASCRIPT
// =============================================

// Global state
let currentPatient = null;
let currentPatientData = null;
let queueRefreshInterval = null;
let patientSearchTimeout = null;
let queueDataTable = null;
let visitHistoryTable = null;

// Cached reference data
let cachedClinics = [];
let cachedServices = { consultation: [], lab: [], imaging: [] };
let cachedProducts = [];
let cachedHmos = [];

$(document).ready(function() {
    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadReferenceData();
});

// =============================================
// EVENT LISTENERS
// =============================================
function initializeEventListeners() {
    // Patient search with debounce - supports barcode scanner input
    let lastInputTime = 0;
    let inputBuffer = '';

    $('#patient-search-input').on('input', function() {
        clearTimeout(patientSearchTimeout);
        const query = $(this).val().trim();
        const currentTime = Date.now();

        // Detect barcode scanner - rapid input (less than 50ms between characters)
        if (currentTime - lastInputTime < 50 && inputBuffer.length > 0) {
            // Likely barcode scanner - wait for complete input
            inputBuffer = query;
            patientSearchTimeout = setTimeout(() => {
                // If input is a file number pattern (fast input complete)
                if (inputBuffer.length >= 3) {
                    searchPatients(inputBuffer, true); // true = auto-select if single result
                }
                inputBuffer = '';
            }, 100);
        } else {
            inputBuffer = query;

            if (query.length < 2) {
                $('#patient-search-results').hide();
                return;
            }

            patientSearchTimeout = setTimeout(() => searchPatients(query, false), 300);
        }
        lastInputTime = currentTime;
    });

    // Handle Enter key for barcode scanner (many scanners add Enter at end)
    $('#patient-search-input').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            const query = $(this).val().trim();
            if (query.length >= 2) {
                searchPatients(query, true); // Auto-select if single result
            }
        }
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

    // Navigation buttons (mobile)
    $('#btn-back-to-search').on('click', function() {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    });

    $('#btn-view-work-pane').on('click', function() {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    });

    $('#btn-toggle-search').on('click', function() {
        $('#left-panel').toggleClass('hidden');
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

    // Queue clinic filter
    $('#queue-clinic-filter').on('change', function() {
        if (queueDataTable) {
            queueDataTable.ajax.reload();
        }
    });

    // Quick actions
    $('#btn-new-patient').on('click', function() {
        showQuickRegisterModal();
    });

    $('#btn-today-stats').on('click', function() {
        showTodayStats();
    });

    // Ward Dashboard quick action
    $('#btn-ward-dashboard').on('click', function() {
        showWardDashboard();
    });

    $('#btn-close-ward-dashboard').on('click', function() {
        hideWardDashboard();
    });

    // Reports quick action
    $('#btn-view-reports').on('click', function() {
        showReports();
    });

    $('#btn-close-reports').on('click', function() {
        hideReports();
    });

    // Reports filter form
    $('#reports-filter-form').on('submit', function(e) {
        e.preventDefault();
        reloadReportsData();
    });

    $('#clear-report-filters').on('click', function() {
        $('#reports-filter-form')[0].reset();
        // Reset to default dates (this month)
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        $('#report-date-from').val(firstDay.toISOString().split('T')[0]);
        $('#report-date-to').val(today.toISOString().split('T')[0]);
        reloadReportsData();
    });

    // View patient from reports tables
    $(document).on('click', '.view-patient-btn', function() {
        const patientId = $(this).data('id');
        if (patientId) {
            hideReports();
            selectPatient(patientId);
        }
    });

    // Edit patient button - open edit modal
    $('#btn-edit-patient').on('click', function() {
        if (currentPatient && currentPatientData) {
            showPatientFormModal('edit', currentPatientData);
        }
    });

    // Print Hospital Card button
    $('#btn-print-card').on('click', function() {
        if (currentPatient && currentPatientData) {
            showHospitalCard(currentPatientData);
        }
    });

    // Print card button in modal
    $('#btn-print-card-now').on('click', function() {
        printHospitalCard();
    });

    // Expand patient details button
    $('#btn-expand-patient').on('click', function() {
        $(this).toggleClass('expanded');
        $('#patient-details-expanded').toggleClass('show');
        const $text = $(this).find('.btn-expand-text');
        if ($(this).hasClass('expanded')) {
            $text.text('less biodata');
        } else {
            $text.text('more biodata');
        }
    });

    // Book Service tab - Clinic selection
    $('#booking-clinic').on('change', function() {
        const clinicId = $(this).val();
        loadDoctorsByClinic(clinicId);
        updateServicesByClinic(clinicId);
    });

    // Book Service - Service type selection
    $('input[name="service-type"]').on('change', function() {
        const type = $(this).val();
        updateServiceTypeUI(type);
    });

    // Book Service - Service selection for tariff preview
    $('#booking-service, #booking-doctor').on('change', function() {
        updateTariffPreview();
    });

    // Book Consultation form submit
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();
        bookConsultation();
    });

    // Walk-in Sales tab - Search and selection
    initializeWalkinSales();

    // Walk-in search input
    $('#walkin-search').on('input', function() {
        const query = $(this).val().toLowerCase();
        const activeType = $('#walkin-subtabs .nav-link.active').data('type') || 'lab';
        searchWalkinServices(query, activeType);
    });

    // Walk-in subtab change
    $('#walkin-subtabs .nav-link').on('click', function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        $('#walkin-subtabs .nav-link').removeClass('active');
        $(this).addClass('active');
        const query = $('#walkin-search').val().toLowerCase();
        searchWalkinServices(query, type);
    });

    // Quick Register form
    $('#quick-register-form').on('submit', function(e) {
        e.preventDefault();
        submitQuickRegister();
    });

    // HMO selection - show/hide HMO number field
    $('#quick-register-hmo').on('change', function() {
        const hmoId = $(this).val();
        if (hmoId) {
            $('#hmo-no-row').show();
        } else {
            $('#hmo-no-row').hide();
            $('#quick-register-hmo-no').val('');
        }
    });
}

// =============================================
// LOAD REFERENCE DATA
// =============================================
function loadReferenceData() {
    // Load clinics
    $.get('{{ route("reception.clinics") }}', function(data) {
        cachedClinics = Array.isArray(data) ? data : (data.clinics || []);
        populateClinicDropdowns();
    });

    // Load HMOs
    $.get('{{ route("reception.hmos") }}', function(data) {
        cachedHmos = Array.isArray(data) ? data : (data.hmos || []);
        populateHmoDropdown();
    });

    // Load consultation services
    $.get('{{ route("reception.services.consultation") }}', function(data) {
        cachedServices.consultation = Array.isArray(data) ? data : (data.services || []);
        populateConsultationServices();
    });

    // Load lab services
    $.get('{{ route("reception.services.lab") }}', function(data) {
        cachedServices.lab = Array.isArray(data) ? data : (data.services || []);
    });

    // Load imaging services
    $.get('{{ route("reception.services.imaging") }}', function(data) {
        cachedServices.imaging = Array.isArray(data) ? data : (data.services || []);
    });

    // Load products
    $.get('{{ route("reception.products") }}', function(data) {
        cachedProducts = Array.isArray(data) ? data : (data.products || []);
    });
}

function populateClinicDropdowns() {
    const $bookClinic = $('#booking-clinic');
    const $queueClinic = $('#queue-clinic-filter');

    $bookClinic.empty().append('<option value="">Select Clinic</option>');
    $queueClinic.empty().append('<option value="">All Clinics</option>');

    cachedClinics.forEach(clinic => {
        const option = `<option value="${clinic.id}">${clinic.name}</option>`;
        $bookClinic.append(option);
        $queueClinic.append(option);
    });
}

function populateHmoDropdown() {
    // Group HMOs by scheme
    const hmosByScheme = {};
    cachedHmos.forEach(hmo => {
        const scheme = hmo.scheme || 'General';
        if (!hmosByScheme[scheme]) {
            hmosByScheme[scheme] = [];
        }
        hmosByScheme[scheme].push(hmo);
    });

    // Populate quick register HMO dropdown with optgroups
    const $hmoSelect = $('#quick-register-hmo');
    if ($hmoSelect.length) {
        $hmoSelect.empty().append('<option value="">No HMO (Private)</option>');

        Object.keys(hmosByScheme).sort().forEach(scheme => {
            const $optgroup = $(`<optgroup label="${scheme}"></optgroup>`);
            hmosByScheme[scheme].forEach(hmo => {
                $optgroup.append(`<option value="${hmo.id}">${hmo.name}</option>`);
            });
            $hmoSelect.append($optgroup);
        });
    }

    // Populate report HMO filter dropdown with optgroups
    const $reportHmoSelect = $('#report-hmo-filter');
    if ($reportHmoSelect.length) {
        $reportHmoSelect.empty().append('<option value="">All HMOs</option>');

        Object.keys(hmosByScheme).sort().forEach(scheme => {
            const $optgroup = $(`<optgroup label="${scheme}"></optgroup>`);
            hmosByScheme[scheme].forEach(hmo => {
                $optgroup.append(`<option value="${hmo.id}">${hmo.name}</option>`);
            });
            $reportHmoSelect.append($optgroup);
        });
    }
}

function loadDoctorsByClinic(clinicId) {
    const $doctorSelect = $('#booking-doctor');
    $doctorSelect.empty().append('<option value="">Select Doctor</option>');

    if (!clinicId) return;

    $.get(`{{ url('reception/clinics') }}/${clinicId}/doctors`, function(data) {
        const doctors = Array.isArray(data) ? data : (data.doctors || []);
        doctors.forEach(doctor => {
            $doctorSelect.append(`<option value="${doctor.id}">${doctor.name}</option>`);
        });
    });
}

function updateServicesByClinic(clinicId) {
    const $serviceSelect = $('#booking-service');

    $serviceSelect.empty().append('<option value="">Select Service</option>');

    // Always use consultation services for booking tab
    let services = cachedServices.consultation || [];

    services.forEach(service => {
        const price = service.price ? ` - ₦${parseFloat(service.price).toLocaleString()}` : '';
        $serviceSelect.append(`<option value="${service.id}" data-price="${service.price || 0}">${service.name}${price}</option>`);
    });
}

function populateConsultationServices() {
    const $serviceSelect = $('#booking-service');
    $serviceSelect.empty().append('<option value="">Select Service</option>');

    let services = cachedServices.consultation || [];

    services.forEach(service => {
        const price = service.price ? ` - ₦${parseFloat(service.price).toLocaleString()}` : '';
        $serviceSelect.append(`<option value="${service.id}" data-price="${service.price || 0}">${service.name}${price}</option>`);
    });
}

function updateServiceTypeUI(type) {
    // Update service dropdown based on type
    updateServicesByClinic($('#booking-clinic').val());

    // Show/hide doctor selection (only for consultation)
    if (type === 'consultation') {
        $('#doctor-selection-group').show();
    } else {
        $('#doctor-selection-group').hide();
    }

    // Update button text
    const buttonTexts = {
        consultation: 'Book Consultation',
        lab: 'Book Lab Test',
        imaging: 'Book Imaging'
    };
    $('#btn-book-consultation').html(`<i class="mdi mdi-check-circle"></i> ${buttonTexts[type] || 'Book Service'}`);

    updateTariffPreview();
}

// =============================================
// PATIENT SEARCH & LOAD
// =============================================
function searchPatients(query, autoSelectSingle = false) {
    $.ajax({
        url: '{{ route("reception.search-patients") }}',
        method: 'GET',
        data: { q: query },
        success: function(results) {
            // Auto-select if single result and barcode scan mode
            if (autoSelectSingle && results.length === 1) {
                loadPatient(results[0].id);
                $('#patient-search-results').hide();
                $('#patient-search-input').val('');
                return;
            }
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
        $container.html('<div class="search-result-item text-muted">No patients found</div>');
    } else {
        const defaultAvatar = '{{ asset("assets/images/default-avatar.png") }}';
        results.forEach((patient, index) => {
            const photoUrl = patient.photo || defaultAvatar;
            const item = $(`
                <div class="search-result-item ${index === 0 ? 'active' : ''}" data-patient-id="${patient.id}">
                    <img src="${photoUrl}" alt="${patient.name}" onerror="this.onerror=null; this.src='${defaultAvatar}';">
                    <div class="search-result-info">
                        <div class="search-result-name">${patient.name}</div>
                        <div class="search-result-details">
                            ${patient.file_no} | ${patient.age || 'N/A'} ${patient.gender} | ${patient.phone || 'N/A'}
                        </div>
                    </div>
                    ${patient.hmo_name ? `<span class="badge badge-info">${patient.hmo_name}</span>` : ''}
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
    $('#queue-view').hide();
    $('#workspace-content').addClass('active');
    $('#patient-header').addClass('active');

    // Show loading indicator
    $('#patient-name').html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');
    $('#patient-meta').html('');

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Load patient data
    $.ajax({
        url: `{{ url('reception/patient') }}/${patientId}`,
        method: 'GET',
        success: function(data) {
            currentPatientData = data.patient;
            displayPatientInfo(data.patient);

            // Switch to profile tab by default
            switchWorkspaceTab('profile');

            // Initialize visit history DataTable
            initializeVisitHistoryTable(patientId);

            // Initialize service requests DataTable
            initializeServiceRequestsTable(patientId);

            // Load recent requests for walk-in cart
            loadRecentRequests();
        },
        error: function(xhr) {
            console.error('Error loading patient:', xhr);
            toastr.error('Failed to load patient data');
        }
    });
}

function displayPatientInfo(patient) {
    $('#patient-name').text(patient.name);

    // Parse allergies
    let allergiesHtml = '';
    if (patient.allergies) {
        let allergies = patient.allergies;
        if (typeof allergies === 'string') {
            try {
                allergies = JSON.parse(allergies);
            } catch(e) {
                allergies = [];
            }
        }
        if (Array.isArray(allergies) && allergies.length > 0) {
            allergiesHtml = `
                <div class="patient-allergies">
                    <i class="mdi mdi-alert-circle text-danger"></i>
                    <span class="text-danger">Allergies: ${allergies.join(', ')}</span>
                </div>
            `;
        }
    }

    const metaHtml = `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details"></i>
            <span>File: ${patient.file_no}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-calendar"></i>
            <span>${patient.age || 'N/A'}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-gender-${patient.gender === 'Male' ? 'male' : 'female'}"></i>
            <span>${patient.gender}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-water"></i>
            <span>${patient.blood_group || 'N/A'} ${patient.genotype && patient.genotype !== 'N/A' ? '(' + patient.genotype + ')' : ''}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-phone"></i>
            <span>${patient.phone || 'N/A'}</span>
        </div>
        ${patient.hmo_name ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-hospital-building"></i>
            <span>${patient.hmo_name} ${patient.hmo_category && patient.hmo_category !== 'N/A' ? '[' + patient.hmo_category + ']' : ''} ${patient.hmo_no ? '(' + patient.hmo_no + ')' : ''}</span>
        </div>
        ` : ''}
        ${allergiesHtml}
    `;

    $('#patient-meta').html(metaHtml);

    // Populate expanded patient details
    const expandedDetailsHtml = `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-water"></i> Blood Group</div>
            <div class="patient-detail-value">${patient.blood_group || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-dna"></i> Genotype</div>
            <div class="patient-detail-value">${patient.genotype || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-phone"></i> Phone</div>
            <div class="patient-detail-value">${patient.phone || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-map-marker"></i> Address</div>
            <div class="patient-detail-value">${patient.address || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-flag"></i> Nationality</div>
            <div class="patient-detail-value">${patient.nationality || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-account-group"></i> Ethnicity</div>
            <div class="patient-detail-value">${patient.ethnicity || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-wheelchair-accessibility"></i> Disability</div>
            <div class="patient-detail-value">${patient.disability || 'No'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-hospital-building"></i> HMO</div>
            <div class="patient-detail-value">${patient.hmo_name || 'Private'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-tag"></i> HMO Category</div>
            <div class="patient-detail-value">${patient.hmo_category || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-card-account-details"></i> HMO Number</div>
            <div class="patient-detail-value">${patient.hmo_no || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-shield-account"></i> Insurance Scheme</div>
            <div class="patient-detail-value">${patient.insurance_scheme || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-account-heart"></i> Next of Kin</div>
            <div class="patient-detail-value">${patient.next_of_kin_name || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-phone-outline"></i> NOK Phone</div>
            <div class="patient-detail-value">${patient.next_of_kin_phone || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-map-marker-outline"></i> NOK Address</div>
            <div class="patient-detail-value">${patient.next_of_kin_address || 'N/A'}</div>
        </div>
        ${patient.medical_history ? `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-clipboard-text"></i> Medical History</div>
            <div class="patient-detail-value text-content">${patient.medical_history}</div>
        </div>
        ` : ''}
        ${patient.misc ? `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-note-text"></i> Additional Notes</div>
            <div class="patient-detail-value text-content">${patient.misc}</div>
        </div>
        ` : ''}
    `;
    $('#patient-details-grid').html(expandedDetailsHtml);

    // Update profile tab with patient details
    updateProfileTab(patient);
}

function updateProfileTab(patient) {
    // Populate Patient Information table
    let allergies = patient.allergies || [];
    if (typeof allergies === 'string') {
        try {
            allergies = JSON.parse(allergies);
        } catch(e) {
            allergies = [];
        }
    }

    const allergiesBadges = allergies.length > 0
        ? allergies.map(a => `<span class="badge badge-danger mr-1">${a}</span>`).join(' ')
        : '<span class="text-muted">No known allergies</span>';

    const profileInfoHtml = `
        <tr>
            <td class="text-muted" width="35%">File No:</td>
            <td><strong>${patient.file_no || 'N/A'}</strong></td>
        </tr>
        <tr>
            <td class="text-muted">Full Name:</td>
            <td>${patient.name || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Phone:</td>
            <td>${patient.phone || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Email:</td>
            <td>${patient.email || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Gender:</td>
            <td>${patient.gender || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Date of Birth:</td>
            <td>${patient.dob || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Age:</td>
            <td>${patient.age ? `${patient.age} years` : 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Address:</td>
            <td>${patient.address || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Allergies:</td>
            <td>${allergiesBadges}</td>
        </tr>
    `;
    $('#profile-info-table').html(profileInfoHtml);

    // Populate HMO Information table
    const hmoInfoHtml = `
        <tr>
            <td class="text-muted" width="35%">HMO/Insurance:</td>
            <td><strong>${patient.hmo_name || '<span class="text-warning">Private (No HMO)</span>'}</strong></td>
        </tr>
        ${patient.hmo_no ? `
        <tr>
            <td class="text-muted">HMO Number:</td>
            <td>${patient.hmo_no}</td>
        </tr>
        ` : ''}
        ${patient.hmo_plan ? `
        <tr>
            <td class="text-muted">Plan:</td>
            <td>${patient.hmo_plan}</td>
        </tr>
        ` : ''}
        ${patient.company ? `
        <tr>
            <td class="text-muted">Company:</td>
            <td>${patient.company}</td>
        </tr>
        ` : ''}
    `;
    $('#profile-hmo-table').html(hmoInfoHtml);

    // Load current queue entries for this patient
    loadPatientQueueEntries(patient.id);
}

function loadPatientQueueEntries(patientId) {
    $.get(`{{ url('reception/patient') }}/${patientId}/queue`, function(data) {
        const $container = $('#current-queue-entries');
        const entries = Array.isArray(data) ? data : (data.entries || []);

        if (entries.length === 0) {
            $container.html('<p class="text-muted">No active queue entries</p>');
            return;
        }

        let html = '<div class="queue-entries-list">';
        entries.forEach(entry => {
            const statusClass = {
                1: 'badge-warning',
                2: 'badge-info',
                3: 'badge-primary',
                4: 'badge-success'
            }[entry.status] || 'badge-secondary';

            const statusText = {
                1: 'Waiting',
                2: 'Vitals Pending',
                3: 'In Consultation',
                4: 'Completed'
            }[entry.status] || 'Unknown';

            html += `
                <div class="queue-entry-item d-flex justify-content-between align-items-center p-2 border-bottom">
                    <div>
                        <strong>Q-${entry.queue_no || 'N/A'}</strong>
                        <span class="text-muted ml-2">${entry.clinic_name || 'N/A'}</span>
                        <br><small><i class="mdi mdi-account"></i> ${entry.patient_name || 'N/A'} <span class="text-muted">(#${entry.patient_file_no || 'N/A'})</span></small>
                        ${entry.doctor_name ? `<br><small class="text-muted"><i class="mdi mdi-doctor"></i> Dr. ${entry.doctor_name}</small>` : ''}
                    </div>
                    <span class="badge ${statusClass}">${statusText}</span>
                </div>
            `;
        });
        html += '</div>';
        $container.html(html);
    }).fail(function() {
        $('#current-queue-entries').html('<p class="text-muted">Failed to load queue entries</p>');
    });
}

// =============================================
// WORKSPACE TABS
// =============================================
function switchWorkspaceTab(tab) {
    // Update tab buttons
    $('.workspace-tab').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

    // Update tab content
    $('.workspace-tab-content').removeClass('active');
    $(`#${tab}-tab`).addClass('active');

    // Tab-specific actions
    if (tab === 'history' && currentPatient) {
        if (visitHistoryTable) {
            visitHistoryTable.ajax.reload();
        }
    }
}

// =============================================
// QUEUE MANAGEMENT
// =============================================
function loadQueueCounts() {
    $.get('{{ route("reception.queue-counts") }}', function(counts) {
        $('#queue-waiting-count').text(counts.waiting || 0);
        $('#queue-vitals-count').text(counts.vitals_pending || 0);
        $('#queue-consultation-count').text(counts.in_consultation || 0);
        $('#queue-admitted-count').text(counts.admitted || 0);
        updateSyncIndicator();
    }).fail(function() {
        console.error('Failed to load queue counts');
    });
}

function startQueueRefresh() {
    queueRefreshInterval = setInterval(function() {
        loadQueueCounts();

        // Refresh queue DataTable if visible
        if ($('#queue-view').hasClass('active') && queueDataTable) {
            queueDataTable.ajax.reload(null, false);
        }
    }, 30000); // 30 seconds
}

function showQueue(filter) {
    $('#empty-state').hide();
    $('#workspace-content').removeClass('active');
    $('#patient-header').removeClass('active');
    $('#queue-view').addClass('active');

    // Update filter buttons
    $('.queue-item').removeClass('active');
    $(`.queue-item[data-filter="${filter}"]`).addClass('active');

    initializeQueueDataTable(filter);
}

function hideQueue() {
    $('#queue-view').removeClass('active');

    if (currentPatient) {
        $('#workspace-content').addClass('active');
        $('#patient-header').addClass('active');
    } else {
        $('#empty-state').show();
    }
}

// =============================================
// WARD DASHBOARD FUNCTIONS
// =============================================
function showWardDashboard() {
    // Hide all other views
    $('#empty-state').hide();
    $('#patient-details-panel').hide();
    $('#queue-view').removeClass('active');
    $('#reports-view').hide();
    $('#workspace-content').removeClass('active');
    $('#patient-header').removeClass('active');

    // Show ward dashboard
    $('#ward-dashboard-view').show().addClass('active');

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
    $('#ward-dashboard-view').hide().removeClass('active');

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

// =============================================
// REPORTS FUNCTIONS
// =============================================
let registrationsDataTable = null;
let queueReportDataTable = null;
let visitsDataTable = null;
let registrationsChart = null;
let hmoDistributionChart = null;
let peakHoursChart = null;

function showReports() {
    // Hide all other views
    $('#empty-state').hide();
    $('#patient-details-panel').hide();
    $('#queue-view').removeClass('active');
    $('#ward-dashboard-view').hide().removeClass('active');
    $('#workspace-content').removeClass('active');
    $('#patient-header').removeClass('active');

    // Show reports view
    $('#reports-view').show().addClass('active');

    // Set default date range (this month)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#report-date-from').val(firstDay.toISOString().split('T')[0]);
    $('#report-date-to').val(today.toISOString().split('T')[0]);

    // Load data
    loadReportsStatistics();
    loadChartData();
    initReportsDataTables();

    // On mobile, show main workspace
    if (window.innerWidth < 768) {
        $('#main-workspace').addClass('active');
        $('#left-panel').addClass('hidden');
    }
}

function hideReports() {
    $('#reports-view').hide().removeClass('active');

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

function getReportFilters() {
    return {
        date_from: $('#report-date-from').val(),
        date_to: $('#report-date-to').val(),
        report_type: $('#report-type-filter').val(),
        clinic_id: $('#report-clinic-filter').val(),
        hmo_id: $('#report-hmo-filter').val(),
        patient_search: $('#report-patient-search').val()
    };
}

function loadReportsStatistics() {
    const filters = getReportFilters();

    $.ajax({
        url: '{{ route("reception.reports.statistics") }}',
        method: 'GET',
        data: filters,
        success: function(data) {
            $('#stat-new-registrations').text(data.new_registrations || 0);
            $('#stat-total-queued').text(data.total_queued || 0);
            $('#stat-completed-visits').text(data.completed_visits || 0);
            $('#stat-pending-queue').text(data.pending_queue || 0);
            $('#stat-avg-wait-time').text((data.avg_wait_time || 0) + 'm');
            $('#stat-return-rate').text((data.return_rate || 0) + '%');

            // Update top clinics table
            let clinicsHtml = '';
            if (data.top_clinics && data.top_clinics.length > 0) {
                data.top_clinics.forEach(function(clinic) {
                    clinicsHtml += `
                        <tr>
                            <td>${clinic.name}</td>
                            <td class="text-center">${clinic.visits}</td>
                            <td class="text-right">${clinic.percentage}%</td>
                        </tr>
                    `;
                });
            } else {
                clinicsHtml = '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>';
            }
            $('#top-clinics-body').html(clinicsHtml);
        },
        error: function() {
            toastr.error('Failed to load statistics');
        }
    });
}

function loadChartData() {
    const filters = getReportFilters();

    $.ajax({
        url: '{{ route("reception.reports.chart-data") }}',
        method: 'GET',
        data: filters,
        success: function(data) {
            renderRegistrationsChart(data.registration_trends);
            renderHmoDistributionChart(data.hmo_distribution);
            renderPeakHoursChart(data.peak_hours);
        },
        error: function() {
            console.error('Failed to load chart data');
        }
    });
}

function renderRegistrationsChart(data) {
    const ctx = document.getElementById('registrations-chart');
    if (!ctx) return;

    if (registrationsChart) {
        registrationsChart.destroy();
    }

    registrationsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Registrations',
                data: data.data,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

function renderHmoDistributionChart(data) {
    const ctx = document.getElementById('hmo-distribution-chart');
    if (!ctx) return;

    if (hmoDistributionChart) {
        hmoDistributionChart.destroy();
    }

    const colors = ['#667eea', '#f093fb', '#ffecd2', '#a8edea', '#11998e', '#4facfe'];

    hmoDistributionChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: colors
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { boxWidth: 12 }
                }
            }
        }
    });
}

function renderPeakHoursChart(data) {
    const ctx = document.getElementById('peak-hours-chart');
    if (!ctx) return;

    if (peakHoursChart) {
        peakHoursChart.destroy();
    }

    peakHoursChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Queue Entries',
                data: data.data,
                backgroundColor: '#11998e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

function initReportsDataTables() {
    // Initialize Registrations DataTable
    if (registrationsDataTable) {
        registrationsDataTable.destroy();
    }

    registrationsDataTable = $('#registrations-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("reception.reports.registrations") }}',
            data: function(d) {
                const filters = getReportFilters();
                Object.assign(d, filters);
            }
        },
        columns: [
            { data: 'date', name: 'created_at' },
            { data: 'file_no', name: 'file_no' },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'gender', name: 'gender' },
            { data: 'age', name: 'age', orderable: false },
            { data: 'phone', name: 'phone_no' },
            { data: 'hmo', name: 'hmo', orderable: false },
            { data: 'registered_by', name: 'registered_by', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: 'No registrations found for selected period'
        }
    });

    // Initialize Queue Report DataTable
    if (queueReportDataTable) {
        queueReportDataTable.destroy();
    }

    queueReportDataTable = $('#queue-report-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("reception.reports.queue") }}',
            data: function(d) {
                const filters = getReportFilters();
                Object.assign(d, filters);
            }
        },
        columns: [
            { data: 'datetime', name: 'created_at' },
            { data: 'file_no', name: 'file_no', orderable: false },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'clinic', name: 'clinic', orderable: false },
            { data: 'doctor', name: 'doctor', orderable: false },
            { data: 'service', name: 'service', orderable: false },
            { data: 'status', name: 'status' },
            { data: 'wait_time', name: 'wait_time', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: 'No queue entries found for selected period'
        }
    });

    // Initialize Visits DataTable
    if (visitsDataTable) {
        visitsDataTable.destroy();
    }

    visitsDataTable = $('#visits-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("reception.reports.visits") }}',
            data: function(d) {
                const filters = getReportFilters();
                Object.assign(d, filters);
            }
        },
        columns: [
            { data: 'date', name: 'created_at' },
            { data: 'file_no', name: 'file_no', orderable: false },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'clinic', name: 'clinic', orderable: false },
            { data: 'doctor', name: 'doctor', orderable: false },
            { data: 'reason', name: 'reason', orderable: false },
            { data: 'hmo', name: 'hmo', orderable: false },
            { data: 'type', name: 'type', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: 'No visits found for selected period'
        }
    });
}

function reloadReportsData() {
    loadReportsStatistics();
    loadChartData();

    if (registrationsDataTable) {
        registrationsDataTable.ajax.reload();
    }
    if (queueReportDataTable) {
        queueReportDataTable.ajax.reload();
    }
    if (visitsDataTable) {
        visitsDataTable.ajax.reload();
    }
}

function initializeQueueDataTable(filter) {
    if (queueDataTable) {
        queueDataTable.destroy();
    }

    queueDataTable = $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("reception.queue-list") }}',
            data: function(d) {
                d.filter = filter;
                d.clinic_id = $('#queue-clinic-filter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'patient_name', name: 'patient_name' },
            { data: 'patient_file_no', name: 'patient_file_no' },
            { data: 'patient_hmo', name: 'patient_hmo' },
            { data: 'clinic_name', name: 'clinic_name' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'service_name', name: 'service_name' },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'time', name: 'created_at' },
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-primary select-queue-patient" data-patient-id="${row.patient_id}">
                            <i class="mdi mdi-account-search"></i> Select
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 15,
        language: {
            emptyTable: 'No patients in queue',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        },
        drawCallback: function() {
            // Bind click handler for select buttons
            $('.select-queue-patient').off('click').on('click', function() {
                const patientId = $(this).data('patient-id');
                loadPatient(patientId);
                hideQueue();
            });
        }
    });
}

// =============================================
// BOOK SERVICE FUNCTIONALITY
// =============================================
function updateTariffPreview() {
    if (!currentPatient) return;

    const serviceId = $('#booking-service').val();
    const serviceType = $('input[name="service-type"]:checked').val() || 'consultation';

    if (!serviceId) {
        $('#tariff-preview-card').hide();
        return;
    }

    // Get service price from option data
    const servicePrice = parseFloat($('#booking-service option:selected').data('price')) || 0;

    $.ajax({
        url: '{{ route("reception.tariff-preview") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            service_id: serviceId
        },
        success: function(data) {
            displayTariffPreview(data);
        },
        error: function() {
            $('#tariff-preview-card').hide();
        }
    });
}

function displayTariffPreview(data) {
    const $card = $('#tariff-preview-card');

    // Update tariff values
    $('#tariff-base-price').text(`₦${parseFloat(data.total_base_price || data.base_price || 0).toLocaleString()}`);
    $('#tariff-payable-amount').text(`₦${parseFloat(data.payable_amount || 0).toLocaleString()}`);

    if (data.hmo_name && data.claims_amount > 0) {
        $('#tariff-hmo-row').show();
        $('#tariff-coverage-mode').text(data.coverage_mode || 'N/A');
        $('#tariff-claims-amount').text(`₦${parseFloat(data.claims_amount || 0).toLocaleString()}`);

        if (data.validation_required) {
            $('#tariff-validation-alert').show();
            $('#tariff-validation-message').text('HMO validation required before service');
        } else {
            $('#tariff-validation-alert').hide();
        }
    } else {
        $('#tariff-hmo-row').hide();
        $('#tariff-validation-alert').hide();
    }

    $card.show();
}

function bookConsultation() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    const clinicId = $('#booking-clinic').val();
    const doctorId = $('#booking-doctor').val();
    const serviceId = $('#booking-service').val();
    const serviceType = $('input[name="service-type"]:checked').val() || 'consultation';
    const reason = $('#book-reason').val();

    if (!clinicId) {
        toastr.warning('Please select a clinic');
        return;
    }

    if (serviceType === 'consultation' && !doctorId) {
        toastr.warning('Please select a doctor');
        return;
    }

    if (!serviceId) {
        toastr.warning('Please select a service');
        return;
    }

    const $btn = $('#btn-book-consultation');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Booking...');

    $.ajax({
        url: '{{ route("reception.book-consultation") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            clinic_id: clinicId,
            doctor_id: doctorId,
            service_id: serviceId,
            service_type: serviceType,
            reason: reason
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Service booked successfully');
                // Reset form
                $('#booking-clinic').val('');
                $('#booking-doctor').empty().append('<option value="">Select Doctor</option>');
                $('#booking-service').empty().append('<option value="">Select Service</option>');
                $('#book-reason').val('');
                $('#tariff-preview-container').hide();

                // Refresh queue counts
                loadQueueCounts();
            } else {
                toastr.error(response.message || 'Failed to book service');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to book service');
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// =============================================
// WALK-IN SALES
// =============================================
function initializeWalkinSales() {
    // Add to cart
    $(document).on('click', '.add-to-cart', function() {
        const serviceId = $(this).data('id');
        const serviceType = $(this).data('type');
        const serviceName = $(this).data('name');
        const servicePrice = $(this).data('price');
        addToWalkinCart(serviceId, serviceType, serviceName, servicePrice);
    });

    // Remove from cart
    $(document).on('click', '.remove-from-cart', function() {
        const index = $(this).data('index');
        removeFromWalkinCart(index);
    });

    // Submit walk-in
    $('#btn-submit-walkin').on('click', function() {
        submitWalkinServices();
    });
}

let walkinCart = [];

function searchWalkinServices(query, type) {
    const $container = $('#walkin-search-results');
    $container.empty();

    let services = [];
    if (type === 'lab') {
        services = (cachedServices.lab || []).map(s => ({ ...s, type: 'lab' }));
    } else if (type === 'imaging') {
        services = (cachedServices.imaging || []).map(s => ({ ...s, type: 'imaging' }));
    } else if (type === 'product') {
        services = (cachedProducts || []).map(p => ({ ...p, type: 'product' }));
    }

    // Filter by query if provided
    if (query) {
        services = services.filter(s => s.name.toLowerCase().includes(query));
    }

    if (services.length === 0) {
        $container.html('<p class="text-muted text-center py-3">No services found</p>');
        return;
    }

    services.slice(0, 20).forEach(service => {
        const price = parseFloat(service.price || 0);
        const typeLabel = type === 'lab' ? 'Lab' : (type === 'imaging' ? 'Imaging' : 'Product');
        const typeClass = type === 'lab' ? 'info' : (type === 'imaging' ? 'warning' : 'success');

        $container.append(`
            <div class="walkin-service-item d-flex justify-content-between align-items-center p-2 border-bottom">
                <div>
                    <span class="badge badge-${typeClass}">${typeLabel}</span>
                    <span class="ml-2">${service.name}</span>
                </div>
                <div>
                    <span class="text-muted mr-2">₦${price.toLocaleString()}</span>
                    <button class="btn btn-sm btn-primary add-to-cart"
                            data-id="${service.id}"
                            data-type="${type}"
                            data-name="${service.name}"
                            data-price="${price}">
                        <i class="mdi mdi-plus"></i>
                    </button>
                </div>
            </div>
        `);
    });
}

function addToWalkinCart(id, type, name, price) {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Check if item already in cart
    const existingIndex = walkinCart.findIndex(item => item.id == id && item.type == type);
    if (existingIndex >= 0) {
        toastr.info('Item already in cart');
        return;
    }

    // Fetch tariff preview with HMO calculations
    const isProduct = type === 'product';
    $.ajax({
        url: '{{ route("reception.tariff-preview") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            service_id: isProduct ? null : id,
            product_id: isProduct ? id : null,
            qty: 1
        },
        success: function(data) {
            walkinCart.push({
                id: id,
                type: type,
                name: name,
                base_price: parseFloat(data.base_price || price),
                payable_amount: parseFloat(data.payable_amount || price),
                claims_amount: parseFloat(data.claims_amount || 0),
                coverage_mode: data.coverage_mode || null,
                hmo_name: data.hmo_name || 'Private',
                quantity: 1
            });
            updateWalkinCartUI();
        },
        error: function() {
            // Fallback without HMO
            walkinCart.push({
                id: id,
                type: type,
                name: name,
                base_price: parseFloat(price),
                payable_amount: parseFloat(price),
                claims_amount: 0,
                coverage_mode: null,
                hmo_name: 'Private',
                quantity: 1
            });
            updateWalkinCartUI();
        }
    });
}

function removeFromWalkinCart(index) {
    walkinCart.splice(index, 1);
    updateWalkinCartUI();
}

function updateWalkinCartUI() {
    const $container = $('#walkin-cart-body');
    $container.empty();

    // Update cart count badge
    $('#cart-count-badge').text(walkinCart.length);

    if (walkinCart.length === 0) {
        $container.html(`
            <tr id="walkin-cart-empty">
                <td colspan="5" class="text-center text-muted py-4">
                    <i class="mdi mdi-cart-outline" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">No items selected</p>
                </td>
            </tr>
        `);
        $('#walkin-subtotal').text('₦0');
        $('#walkin-cart-total').text('₦0');
        $('#walkin-hmo-row').hide();
        $('#btn-submit-walkin').prop('disabled', true);
        return;
    }

    let subtotal = 0;
    let totalPayable = 0;
    let totalClaims = 0;
    let hmoName = 'Private';

    walkinCart.forEach((item, index) => {
        const itemSubtotal = item.base_price * item.quantity;
        const itemPayable = item.payable_amount * item.quantity;
        const itemClaims = item.claims_amount * item.quantity;

        subtotal += itemSubtotal;
        totalPayable += itemPayable;
        totalClaims += itemClaims;

        if (item.hmo_name && item.hmo_name !== 'Private') {
            hmoName = item.hmo_name;
        }

        // Coverage info
        const hasHmoCoverage = itemClaims > 0;
        const coverageLabel = item.coverage_mode ? item.coverage_mode.charAt(0).toUpperCase() + item.coverage_mode.slice(1) : '';
        const coverageBadge = hasHmoCoverage
            ? `<span class="badge badge-success" style="font-size: 0.7rem;">${coverageLabel}</span>`
            : '';

        $container.append(`
            <tr>
                <td>
                    <strong>${item.name}</strong>
                    <br>
                    <small class="text-muted">${item.type}</small>
                    ${coverageBadge}
                </td>
                <td class="text-right">
                    <span>₦${itemSubtotal.toLocaleString()}</span>
                </td>
                <td class="text-right text-success">
                    ${hasHmoCoverage ? `<span>-₦${itemClaims.toLocaleString()}</span>` : '<span class="text-muted">-</span>'}
                </td>
                <td class="text-right text-primary">
                    <strong>₦${itemPayable.toLocaleString()}</strong>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger remove-from-cart" data-index="${index}" title="Remove">
                        <i class="mdi mdi-close"></i>
                    </button>
                </td>
            </tr>
        `);
    });

    // Update summary
    $('#walkin-subtotal').text(`₦${subtotal.toLocaleString()}`);

    if (totalClaims > 0) {
        $('#walkin-hmo-row').show();
        $('#walkin-hmo-name').text(hmoName);
        $('#walkin-hmo-amount').text(`-₦${totalClaims.toLocaleString()}`);
    } else {
        $('#walkin-hmo-row').hide();
    }

    $('#walkin-cart-total').text(`₦${totalPayable.toLocaleString()}`);
    $('#btn-submit-walkin').prop('disabled', false);
}

function submitWalkinServices() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    if (walkinCart.length === 0) {
        toastr.warning('Cart is empty');
        return;
    }

    const $btn = $('#btn-submit-walkin');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: '{{ route("reception.book-walkin") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            items: walkinCart
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Services created successfully');
                walkinCart = [];
                updateWalkinCartUI();
                // Refresh recent requests
                loadRecentRequests();
                // Switch to recent tab to show the new request
                $('#walkin-cart-tabs a[href="#walkin-recent-pane"]').tab('show');
            } else {
                toastr.error(response.message || 'Failed to create services');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create services');
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// =============================================
// RECENT REQUESTS (Last 24 hours)
// =============================================
function loadRecentRequests() {
    if (!currentPatient) return;

    const $container = $('#recent-requests-container');
    $container.html('<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div>');

    $.ajax({
        url: `{{ url('reception/patient') }}/${currentPatient}/recent-requests`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.requests && response.requests.length > 0) {
                let html = '';
                response.requests.forEach(req => {
                    const typeClass = getTypeClass(req.type);
                    const billingClass = getBillingStatusClass(req.billing_status);
                    const deliveryClass = getDeliveryStatusClass(req.delivery_status);
                    const coverageBadge = req.coverage_mode ? `<span class="badge badge-outline-success ml-1">${req.coverage_mode}</span>` : '';
                    const createdAt = new Date(req.created_at).toLocaleString('en-GB', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'});

                    html += `
                        <div class="recent-request-item">
                            <div class="recent-request-header">
                                <div>
                                    <span class="recent-request-name">${req.name}</span>
                                    <span class="badge badge-${typeClass} recent-request-type ml-2">${req.type_label}</span>
                                    ${coverageBadge}
                                </div>
                                <small class="text-muted">${createdAt}</small>
                            </div>
                            <div class="recent-request-details">
                                <div class="recent-request-pricing">
                                    <span>
                                        <span class="price-label">Price</span>
                                        <span class="price-value">₦${parseFloat(req.price || 0).toLocaleString()}</span>
                                    </span>
                                    <span>
                                        <span class="price-label">HMO</span>
                                        <span class="price-value text-success">${req.hmo_covers > 0 ? '-₦' + parseFloat(req.hmo_covers).toLocaleString() : '-'}</span>
                                    </span>
                                    <span>
                                        <span class="price-label">Payable</span>
                                        <span class="price-value text-primary">₦${parseFloat(req.payable || 0).toLocaleString()}</span>
                                    </span>
                                </div>
                                <div class="recent-request-status">
                                    <span class="billing-badge ${billingClass}">${req.billing_status || 'Pending'}</span>
                                    <span class="delivery-badge ${deliveryClass}">${req.delivery_status || 'Pending'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                $container.html(html);
            } else {
                $container.html(`
                    <div class="text-center text-muted py-4">
                        <i class="mdi mdi-clock-outline" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-2">No recent requests</p>
                    </div>
                `);
            }
        },
        error: function() {
            $container.html(`
                <div class="text-center text-muted py-4">
                    <i class="mdi mdi-alert-circle" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">Failed to load recent requests</p>
                </div>
            `);
        }
    });
}

function getTypeClass(type) {
    const classes = {
        'lab': 'info',
        'imaging': 'warning',
        'product': 'success',
        'consultation': 'primary',
        'procedure': 'secondary'
    };
    return classes[type?.toLowerCase()] || 'secondary';
}

function getBillingStatusClass(status) {
    if (!status) return 'billing-pending';
    const statusLower = status.toLowerCase();
    if (statusLower.includes('paid')) return 'billing-paid';
    if (statusLower.includes('billed')) return 'billing-billed';
    return 'billing-pending';
}

function getDeliveryStatusClass(status) {
    if (!status) return 'delivery-pending';
    const statusLower = status.toLowerCase();
    if (statusLower.includes('completed') || statusLower.includes('dispensed')) return 'delivery-completed';
    if (statusLower.includes('progress') || statusLower.includes('sample') || statusLower.includes('awaiting')) return 'delivery-progress';
    return 'delivery-pending';
}

// =============================================
// SERVICE REQUESTS TAB
// =============================================
let serviceRequestsTable = null;

function initializeServiceRequestsTable(patientId) {
    if (serviceRequestsTable) {
        serviceRequestsTable.destroy();
    }

    // Set default date range to this month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    if (!$('#req-date-from').val()) {
        $('#req-date-from').val(firstDay.toISOString().split('T')[0]);
    }
    if (!$('#req-date-to').val()) {
        $('#req-date-to').val(lastDay.toISOString().split('T')[0]);
    }

    serviceRequestsTable = $('#service-requests-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: `{{ url('reception/patient') }}/${patientId}/service-requests`,
            type: 'GET',
            data: function(d) {
                d.date_from = $('#req-date-from').val();
                d.date_to = $('#req-date-to').val();
                d.type_filter = $('#req-type-filter').val();
                d.billing_filter = $('#req-billing-filter').val();
                d.delivery_filter = $('#req-delivery-filter').val();
            }
        },
        columns: [
            { data: 'date_formatted', name: 'created_at' },
            { data: 'request_no', name: 'request_no' },
            { data: 'type_badge', name: 'type' },
            { data: 'name', name: 'name' },
            { data: 'price_formatted', name: 'price', className: 'text-right' },
            { data: 'hmo_covers_formatted', name: 'hmo_covers', className: 'text-right text-success' },
            { data: 'payable_formatted', name: 'payable', className: 'text-right text-primary font-weight-bold' },
            { data: 'billing_badge', name: 'billing_status' },
            { data: 'delivery_badge', name: 'delivery_status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip',
        language: {
            emptyTable: 'No service requests found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        },
        drawCallback: function() {
            // Update summary stats after table loads
            loadServiceRequestsStats(patientId);
        }
    });
}

function loadServiceRequestsStats(patientId) {
    $.ajax({
        url: `{{ url('reception/patient') }}/${patientId}/service-requests-stats`,
        method: 'GET',
        data: {
            date_from: $('#req-date-from').val(),
            date_to: $('#req-date-to').val(),
            type_filter: $('#req-type-filter').val(),
            billing_filter: $('#req-billing-filter').val(),
            delivery_filter: $('#req-delivery-filter').val()
        },
        success: function(response) {
            if (response.success && response.stats) {
                $('#req-total-requests').text(response.stats.total_requests || 0);
                $('#req-hmo-covered').text(response.stats.hmo_covered || '₦0');
                $('#req-patient-payable').text(response.stats.patient_payable || '₦0');
                $('#req-completed-count').text(response.stats.completed || 0);
            }
        }
    });
}

function reloadServiceRequestsData() {
    if (serviceRequestsTable) {
        serviceRequestsTable.ajax.reload();
    }
}

// Event handlers for service requests
$(document).on('submit', '#service-requests-filter-form', function(e) {
    e.preventDefault();
    reloadServiceRequestsData();
});

$(document).on('click', '#clear-req-filters', function() {
    // Reset to this month defaults
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    $('#req-date-from').val(firstDay.toISOString().split('T')[0]);
    $('#req-date-to').val(lastDay.toISOString().split('T')[0]);
    $('#req-type-filter, #req-billing-filter, #req-delivery-filter').val('');
    reloadServiceRequestsData();
});

// Export handlers
$(document).on('click', '#export-requests-excel', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val(),
        format: 'excel'
    });
    window.location.href = `{{ url('reception/patient') }}/${currentPatient}/service-requests/export?${params}`;
});

$(document).on('click', '#export-requests-pdf', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val(),
        format: 'pdf'
    });
    window.location.href = `{{ url('reception/patient') }}/${currentPatient}/service-requests/export?${params}`;
});

$(document).on('click', '#print-requests', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val()
    });
    window.open(`{{ url('reception/patient') }}/${currentPatient}/service-requests/print?${params}`, '_blank');
});

// View Request Details Handler
$(document).on('click', '.view-request-btn', function() {
    const type = $(this).data('type');
    const id = $(this).data('id');

    showRequestDetails(type, id);
});

// Discard Request Handler
let discardRequestType = null;
let discardRequestId = null;

$(document).on('click', '.discard-request-btn', function() {
    discardRequestType = $(this).data('type');
    discardRequestId = $(this).data('id');
    const serviceName = $(this).data('name');
    const requestNo = $(this).data('request-no');

    $('#discard_service_name').text(serviceName);
    $('#discard_request_no').text(requestNo);
    $('#discard_reason').val('');
    $('#discardRequestModal').modal('show');
});

$('#discardRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#discard_reason').val();

    if (reason.length < 10) {
        toastr.warning('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $('#confirmDiscardBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Discarding...');

    $.ajax({
        url: `{{ url('reception/request') }}/${discardRequestType}/${discardRequestId}/discard`,
        method: 'DELETE',
        data: {
            _token: '{{ csrf_token() }}',
            reason: reason
        },
        success: function(response) {
            $('#discardRequestModal').modal('hide');
            toastr.success(response.message || 'Request discarded successfully');

            // Reload the service requests table
            reloadServiceRequestsData();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to discard request');
        },
        complete: function() {
            $('#confirmDiscardBtn').prop('disabled', false).html('<i class="mdi mdi-delete"></i> Discard Request');
        }
    });
});

function showRequestDetails(type, id) {
    // Reset modal
    $('#request-details-loading').show();
    $('#request-details-content').hide();

    // Set header color based on type
    const headerClass = type + '-header';
    $('#request-details-header').removeClass('lab-header imaging-header product-header').addClass(headerClass);

    // Update title icon
    const icons = {
        'lab': 'mdi-test-tube',
        'imaging': 'mdi-x-ray',
        'product': 'mdi-pill'
    };
    $('#request-details-title').html(`<i class="mdi ${icons[type] || 'mdi-clipboard-text'}"></i> Request Details`);

    // Show modal
    $('#requestDetailsModal').modal('show');

    // Fetch details
    $.ajax({
        url: `{{ url('reception/request') }}/${type}/${id}/details`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.details) {
                populateRequestDetails(response.details);
                $('#request-details-loading').hide();
                $('#request-details-content').show();
            } else {
                toastr.error('Failed to load request details');
                $('#requestDetailsModal').modal('hide');
            }
        },
        error: function(xhr) {
            toastr.error('Failed to load request details');
            $('#requestDetailsModal').modal('hide');
        }
    });
}

function populateRequestDetails(details) {
    // Request number and type badge
    $('#detail-request-no').text(details.request_no);

    const typeBadgeColors = {
        'lab': 'badge-info',
        'imaging': 'badge-warning',
        'product': 'badge-success'
    };
    $('#detail-type-badge').removeClass('badge-info badge-warning badge-success badge-primary badge-secondary')
        .addClass(typeBadgeColors[details.type] || 'badge-secondary')
        .text(details.type_label);

    // Billing & Delivery badges
    const billingBadgeClass = getBillingStatusClass(details.billing_status);
    const deliveryBadgeClass = getDeliveryStatusClass(details.delivery_status);
    $('#detail-billing-badge').html(`<span class="billing-badge ${billingBadgeClass}">${details.billing_status}</span>`);
    $('#detail-delivery-badge').html(`<span class="delivery-badge ${deliveryBadgeClass}">${details.delivery_status}</span>`);

    // Requested at
    $('#detail-requested-at').text('Requested: ' + details.requested_at);

    // Service/Product info
    if (details.type === 'product') {
        $('#detail-info-title').text('Product Information');
        $('#detail-item-name').text(details.product_name);
        $('#detail-item-category').text(details.product_category);
        $('#detail-dose-section').toggle(!!details.dose);
        $('#detail-dose').text(details.dose || '');
        $('#detail-quantity-section').show();
        $('#detail-quantity').text(details.quantity);
        $('#detail-unit-price').text(numberFormat(details.unit_price));
    } else {
        $('#detail-info-title').text('Service Information');
        $('#detail-item-name').text(details.service_name);
        $('#detail-item-category').text(details.service_category);
        $('#detail-dose-section').hide();
        $('#detail-quantity-section').hide();
    }

    // Pricing
    $('#detail-price').text('₦' + numberFormat(details.price));
    $('#detail-hmo-row').toggle(details.hmo_covers > 0);
    $('#detail-hmo-covers').text('-₦' + numberFormat(details.hmo_covers));
    $('#detail-payable').text('₦' + numberFormat(details.payable));

    // Clinical note
    if (details.clinical_note) {
        $('#detail-note-card').show();
        $('#detail-clinical-note').text(details.clinical_note);
    } else {
        $('#detail-note-card').hide();
    }

    // Build timeline
    buildRequestTimeline(details);

    // Result section (lab/imaging only)
    if (details.type === 'lab' || details.type === 'imaging') {
        $('#detail-result-card').show();
        if (details.has_result) {
            $('#detail-result-content').show();
            $('#detail-no-result').hide();
            $('#detail-result-summary').text(details.result_summary || 'Result available - view in ' + details.type_label + ' workbench');
        } else {
            $('#detail-result-content').hide();
            $('#detail-no-result').show();
        }
    } else {
        $('#detail-result-card').hide();
    }

    // Payment info
    if (details.payment_reference) {
        $('#detail-payment-card').show();
        $('#detail-payment-ref').text(details.payment_reference);
        $('#detail-payment-date').text(details.payment_date);
    } else {
        $('#detail-payment-card').hide();
    }
}

function buildRequestTimeline(details) {
    let timelineHtml = '';

    // 1. Request Created
    timelineHtml += `
        <div class="timeline-item completed">
            <div class="timeline-title"><i class="mdi mdi-plus-circle text-primary"></i> Request Created</div>
            <div class="timeline-subtitle">${details.requested_by}</div>
            <div class="timeline-meta">${details.requested_at}</div>
        </div>
    `;

    // 2. Billing step
    if (details.billing_status_code === 'billed' || details.billing_status_code === 'paid') {
        timelineHtml += `
            <div class="timeline-item completed">
                <div class="timeline-title"><i class="mdi mdi-receipt text-info"></i> Billed</div>
                <div class="timeline-subtitle">${details.billed_by || 'System'}</div>
                <div class="timeline-meta">${details.billed_at || '-'}</div>
            </div>
        `;
    } else {
        timelineHtml += `
            <div class="timeline-item pending">
                <div class="timeline-title"><i class="mdi mdi-receipt text-muted"></i> Awaiting Billing</div>
                <div class="timeline-subtitle text-muted">Not yet billed</div>
            </div>
        `;
    }

    // 3. Payment step (if applicable)
    if (details.billing_status_code === 'paid') {
        timelineHtml += `
            <div class="timeline-item completed">
                <div class="timeline-title"><i class="mdi mdi-cash-check text-success"></i> Paid</div>
                <div class="timeline-subtitle">${details.payment_reference || 'Payment received'}</div>
                <div class="timeline-meta">${details.payment_date || '-'}</div>
            </div>
        `;
    } else if (details.billing_status_code === 'billed') {
        timelineHtml += `
            <div class="timeline-item in-progress">
                <div class="timeline-title"><i class="mdi mdi-cash text-warning"></i> Awaiting Payment</div>
                <div class="timeline-subtitle text-muted">Patient to pay</div>
            </div>
        `;
    }

    // Type-specific steps
    if (details.type === 'lab') {
        // Sample collection step
        if (details.sample_taken) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-test-tube text-info"></i> Sample Collected</div>
                    <div class="timeline-subtitle">${details.sample_taken_by || 'Lab Staff'}</div>
                    <div class="timeline-meta">${details.sample_date || '-'}</div>
                </div>
            `;
        } else if (details.billing_status_code === 'paid' || details.billing_status_code === 'billed') {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-test-tube text-muted"></i> Awaiting Sample</div>
                    <div class="timeline-subtitle text-muted">Sample not yet collected</div>
                </div>
            `;
        }

        // Results step
        if (details.has_result) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-file-document text-success"></i> Result Available</div>
                    <div class="timeline-subtitle">${details.result_by || 'Lab Scientist'}</div>
                    <div class="timeline-meta">${details.result_date || '-'}</div>
                </div>
            `;
        } else if (details.sample_taken) {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-file-document text-muted"></i> Awaiting Results</div>
                    <div class="timeline-subtitle text-muted">Processing in lab</div>
                </div>
            `;
        }
    } else if (details.type === 'imaging') {
        // Results step
        if (details.has_result) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-file-image text-success"></i> Result Available</div>
                    <div class="timeline-subtitle">${details.result_by || 'Radiologist'}</div>
                    <div class="timeline-meta">${details.result_date || '-'}</div>
                </div>
            `;

            if (details.has_attachments && details.attachment_count > 0) {
                timelineHtml += `
                    <div class="timeline-item completed">
                        <div class="timeline-title"><i class="mdi mdi-image-multiple text-info"></i> Images Attached</div>
                        <div class="timeline-subtitle">${details.attachment_count} image(s) uploaded</div>
                    </div>
                `;
            }
        } else if (details.billing_status_code === 'paid' || details.billing_status_code === 'billed') {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-file-image text-muted"></i> Awaiting Results</div>
                    <div class="timeline-subtitle text-muted">Processing in imaging</div>
                </div>
            `;
        }
    } else if (details.type === 'product') {
        // Dispensing step
        if (details.dispensed_by) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-pill text-success"></i> Dispensed</div>
                    <div class="timeline-subtitle">${details.dispensed_by}</div>
                    <div class="timeline-meta">${details.dispense_date || '-'}</div>
                </div>
            `;
        } else if (details.billing_status_code === 'paid' || details.billing_status_code === 'billed') {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-pill text-muted"></i> Awaiting Dispensing</div>
                    <div class="timeline-subtitle text-muted">Ready for pickup</div>
                </div>
            `;
        }
    }

    $('#detail-timeline').html(timelineHtml);
}

function numberFormat(number) {
    if (number === null || number === undefined) return '0.00';
    return parseFloat(number).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// =============================================
// VISIT HISTORY
// =============================================
function initializeVisitHistoryTable(patientId) {
    if (visitHistoryTable) {
        visitHistoryTable.destroy();
    }

    visitHistoryTable = $('#visit-history-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: `{{ url('reception/patient') }}/${patientId}/visits`,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'date', name: 'created_at' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'service_name', name: 'service_name' },
            { data: 'reason', name: 'reason' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            emptyTable: 'No visit history found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// =============================================
// QUICK REGISTRATION
// =============================================
function showQuickRegisterModal() {
    $('#quickRegisterModal').modal('show');
    // Reset the toggle
    $('#toggle-file-no-edit').prop('checked', false);
    $('#quick-register-file-no').prop('readonly', true);
    // Generate new file number from server
    generateFileNumber();
}

function generateFileNumber() {
    $.ajax({
        url: '/reception/patient/next-file-number',
        method: 'GET',
        success: function(response) {
            $('#quick-register-file-no').val(response.file_no);
        },
        error: function() {
            // Fallback: use timestamp-based number if server fails
            const now = new Date();
            const fallbackNo = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(Math.floor(Math.random() * 10000)).padStart(4, '0')}`;
            $('#quick-register-file-no').val(fallbackNo);
            toastr.warning('Could not fetch next file number, using auto-generated');
        }
    });
}

// Toggle file number edit
$('#toggle-file-no-edit').on('change', function() {
    const isChecked = $(this).is(':checked');
    $('#quick-register-file-no').prop('readonly', !isChecked);
    if (isChecked) {
        $('#quick-register-file-no').focus();
    }
});

function submitQuickRegister() {
    const $form = $('#quick-register-form');
    const $btn = $form.find('button[type="submit"]');
    const originalHtml = $btn.html();

    // Basic validation
    const firstName = $('#quick-register-firstname').val().trim();
    const lastName = $('#quick-register-lastname').val().trim();
    const phone = $('#quick-register-phone').val().trim();
    const gender = $('#quick-register-gender').val();

    if (!firstName || !lastName) {
        toastr.warning('First name and last name are required');
        return;
    }

    if (!gender) {
        toastr.warning('Please select gender');
        return;
    }

    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Registering...');

    $.ajax({
        url: '{{ route("reception.patient.quick-register") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            surname: lastName,
            firstname: firstName,
            phone_no: phone,
            gender: gender,
            dob: $('#quick-register-dob').val(),
            hmo_id: $('#quick-register-hmo').val(),
            hmo_no: $('#quick-register-hmo-no').val()
        },
        success: function(response) {
            if (response.success) {
                toastr.success('Patient registered successfully');
                $('#quickRegisterModal').modal('hide');
                $form[0].reset();

                // Load the newly registered patient
                if (response.patient && response.patient.id) {
                    loadPatient(response.patient.id);
                }
            } else {
                toastr.error(response.message || 'Registration failed');
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(err => {
                    toastr.error(err[0]);
                });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Registration failed');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// =============================================
// TODAY'S STATS
// =============================================
function showTodayStats() {
    $.ajax({
        url: '{{ route("reception.today-stats") }}',
        method: 'GET',
        success: function(data) {
            displayTodayStats(data);
        },
        error: function() {
            toastr.error('Failed to load statistics');
        }
    });
}

function displayTodayStats(data) {
    const html = `
        <div class="modal fade" id="todayStatsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="mdi mdi-chart-bar"></i> Today's Statistics</h5>
                        <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-primary text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.total_queued || 0}</h3>
                                    <small>Total Queued Today</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-success text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.new_registrations || 0}</h3>
                                    <small>New Registrations</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-info text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.consultations_done || 0}</h3>
                                    <small>Consultations Done</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-warning text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.pending_services || 0}</h3>
                                    <small>Pending Services</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#todayStatsModal').remove();

    // Add and show modal
    $('body').append(html);
    $('#todayStatsModal').modal('show');

    // Clean up on close
    $('#todayStatsModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

// =============================================
// UTILITY FUNCTIONS
// =============================================
function updateSyncIndicator() {
    const $indicator = $('#sync-indicator');
    if ($indicator.length) {
        $indicator.html(`
            <i class="mdi mdi-check-circle text-success"></i>
            <small class="text-muted">Synced</small>
        `);
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
           ' ' + date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

// Cleanup on page unload
$(window).on('beforeunload', function() {
    if (queueRefreshInterval) {
        clearInterval(queueRefreshInterval);
    }
});

// =============================================
// HOSPITAL CARD FUNCTIONS
// =============================================
function showHospitalCard(patientData) {
    const defaultAvatar = '{{ asset("assets/images/default-avatar.png") }}';

    // Populate FRONT card data
    $('#card-patient-photo').attr('src', patientData.photo || defaultAvatar);
    $('#card-patient-name').text(patientData.name || 'Patient Name');
    $('#card-patient-id').text(patientData.file_no || 'N/A');
    $('#card-dob').text(patientData.dob || 'N/A');
    $('#card-blood-type').text(patientData.blood_group || 'N/A');
    $('#card-genotype').text(patientData.genotype || 'N/A');
    $('#card-barcode-number').text(patientData.file_no || '');

    // Populate BACK card data
    $('#card-gender').text(patientData.gender || 'N/A');
    $('#card-phone').text(patientData.phone_no || 'N/A');
    $('#card-address').text(patientData.address || 'Not provided');

    // Handle allergies (may be array or string)
    let allergiesText = 'None known';
    if (patientData.allergies) {
        if (Array.isArray(patientData.allergies)) {
            allergiesText = patientData.allergies.length > 0 ? patientData.allergies.join(', ') : 'None known';
        } else {
            allergiesText = patientData.allergies;
        }
    }
    $('#card-allergies').text(allergiesText);

    $('#card-nok-name').text(patientData.next_of_kin_name || 'Not provided');
    $('#card-nok-phone').text(patientData.next_of_kin_phone || 'N/A');

    // Generate barcode using JsBarcode if available, otherwise use simple display
    if (typeof JsBarcode !== 'undefined' && patientData.file_no) {
        try {
            JsBarcode('#card-barcode', patientData.file_no, {
                format: 'CODE128',
                width: 1.5,
                height: 30,
                displayValue: false,
                margin: 0,
                background: 'transparent'
            });
        } catch (e) {
            console.error('Barcode generation failed:', e);
            // Fallback: show text-based barcode
            generateTextBarcode(patientData.file_no);
        }
    } else {
        // Fallback: generate text-based barcode representation
        generateTextBarcode(patientData.file_no);
    }

    // Show modal
    $('#hospitalCardModal').modal('show');
}

function generateTextBarcode(code) {
    // Create a simple CSS-based barcode representation
    if (!code) return;

    const svg = document.getElementById('card-barcode');
    const width = 200;
    const height = 30;

    // Create simple bars based on character codes
    let bars = '';
    const barWidth = width / (code.length * 11 + 2);
    let x = barWidth;

    for (let i = 0; i < code.length; i++) {
        const charCode = code.charCodeAt(i);
        // Generate pattern based on character
        const pattern = charCode.toString(2).padStart(8, '0');

        for (let j = 0; j < pattern.length; j++) {
            if (pattern[j] === '1') {
                bars += `<rect x="${x}" y="0" width="${barWidth}" height="${height}" fill="#000"/>`;
            }
            x += barWidth;
        }
        x += barWidth; // Space between characters
    }

    svg.innerHTML = bars;
    svg.setAttribute('width', width);
    svg.setAttribute('height', height);
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
}

function printHospitalCard() {
    const cardContent = document.getElementById('hospital-card-container').innerHTML;
    const printWindow = window.open('', '_blank', 'width=450,height=350');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Hospital Patient Card</title>
            <style>
                @page {
                    size: 3.375in 2.125in;
                    margin: 0;
                }
                body {
                    margin: 0;
                    padding: 10px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                .hospital-card {
                    width: 340px;
                    height: 215px;
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                    border-radius: 12px;
                    overflow: hidden;
                    position: relative;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .card-header-section {
                    background: {{ appsettings()->hos_color ?? '#0066cc' }} !important;
                    color: white;
                    padding: 8px 12px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    -webkit-print-color-adjust: exact !important;
                }
                .hospital-logo-section { width: 35px; height: 35px; flex-shrink: 0; }
                .hospital-logo { width: 35px; height: 35px; object-fit: contain; background: white; border-radius: 4px; padding: 2px; }
                .hospital-logo-placeholder { width: 35px; height: 35px; background: rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; }
                .hospital-info-section { flex: 1; }
                .hospital-name-text { font-size: 11px; font-weight: 700; text-transform: uppercase; line-height: 1.2; }
                .card-type-label { font-size: 8px; opacity: 0.9; letter-spacing: 0.5px; }
                .card-icon-section { font-size: 24px; opacity: 0.7; }
                .card-body-section { display: flex; padding: 10px 12px; gap: 12px; }
                .patient-photo-section { flex-shrink: 0; }
                .patient-photo { width: 65px; height: 80px; object-fit: cover; border-radius: 6px; border: 2px solid {{ appsettings()->hos_color ?? '#0066cc' }}; background: #f0f0f0; }
                .patient-info-section { flex: 1; text-align: left; }
                .info-label { font-size: 7px; color: #6c757d; text-transform: uppercase; margin-bottom: 2px; }
                .patient-name-text { font-size: 13px; font-weight: 700; color: #333; margin-bottom: 6px; }
                .patient-details-row { font-size: 9px; color: #555; margin-bottom: 3px; }
                .patient-details-row strong { color: #333; }
                .card-barcode-section { padding: 0 12px; text-align: center; }
                .card-barcode-section svg { height: 30px; width: auto; max-width: 100%; }
                .barcode-number { font-size: 9px; font-family: 'Courier New', monospace; color: #333; letter-spacing: 2px; margin-top: 2px; }
                .card-footer-section {
                    background: {{ appsettings()->hos_color ?? '#0066cc' }} !important;
                    color: white;
                    padding: 4px 12px;
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    -webkit-print-color-adjust: exact !important;
                }
                .contact-info { font-size: 7px; text-align: center; opacity: 0.9; }
            </style>
        </head>
        <body>
            ${cardContent}
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        window.close();
                    }, 250);
                };
            <\/script>
        </body>
        </html>
    `);

    printWindow.document.close();
}

// =============================================
// PATIENT FORM MODAL (REGISTER/EDIT)
// =============================================
let patientFormCurrentStep = 1;
const patientFormTotalSteps = 4;
let patientFormAllergies = [];

function showPatientFormModal(mode = 'create', patientData = null) {
    // Reset form
    resetPatientForm();

    // Set mode
    $('#patient-form-mode').val(mode);

    if (mode === 'edit' && patientData) {
        $('#patient-form-id').val(patientData.id);
        $('#patient-form-title').html('<i class="mdi mdi-account-edit"></i> Edit Patient');
        $('#patient-form-header').addClass('edit-mode');
        $('#pf-submit-text').text('Update Patient');

        // Populate form with patient data
        populatePatientForm(patientData);
    } else {
        $('#patient-form-title').html('<i class="mdi mdi-account-plus"></i> New Patient Registration');
        $('#patient-form-header').removeClass('edit-mode');
        $('#pf-submit-text').text('Register Patient');

        // Generate new file number
        generatePatientFormFileNumber();
    }

    // Populate HMO dropdown
    populatePatientFormHMO();

    // Show modal
    $('#patientFormModal').modal('show');
}

function resetPatientForm() {
    // Reset form fields
    $('#patient-form')[0].reset();
    $('#patient-form-id').val('');
    $('#patient-form-mode').val('create');

    // Reset file number toggle to Auto mode
    $('#pf-file-no').prop('readonly', true);
    $('#pf-file-no-toggle').prop('checked', false);
    $('#mode-auto-label').addClass('active');
    $('#mode-manual-label').removeClass('active');
    $('#pf-file-no-hint').html('<i class="mdi mdi-information-outline"></i> Next number: <strong id="pf-last-file-no">--</strong> + 1 = <strong id="pf-next-file-no">--</strong>').removeClass('manual-mode');

    // Reset stepper
    patientFormCurrentStep = 1;
    updatePatientFormStepper();

    // Reset allergies
    patientFormAllergies = [];
    updateAllergiesTags();

    // Reset validation states
    $('#patient-form .form-control').removeClass('is-valid is-invalid');

    // Clear file uploads
    $('#pf-passport').val('').removeData('existing');
    $('#pf-old-records').val('').removeData('existing');
    $('.passport-preview-container').hide();
    $('#passport-preview-img').attr('src', '');
    $('#pf-passport-new-preview').hide();
    $('#passport-new-img').attr('src', '');
    $('.old-records-preview-container').hide();
    $('#old-records-preview-img').attr('src', '').hide();
    $('#old-records-preview-icon').hide();
    $('#old-records-preview-name').text('');
    $('#pf-old-records-new-preview').hide();
    $('#old-records-new-name').text('');
    $('#pf-view-old-records').attr('href', '#');

    // Show step 1
    $('.form-step').removeClass('active');
    $('.form-step[data-step="1"]').addClass('active');

    // Show/hide navigation buttons
    updatePatientFormNavigation();

    // Clear summary
    clearPatientFormSummary();
}

function generatePatientFormFileNumber() {
    $.ajax({
        url: '/reception/patient/next-file-number',
        method: 'GET',
        success: function(response) {
            $('#pf-file-no').val(response.file_no);
            // Show the last and next file numbers
            $('#pf-last-file-no').text(response.last_file_no || '0');
            $('#pf-next-file-no').text(response.file_no);
        },
        error: function() {
            const now = new Date();
            const fallbackNo = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(Math.floor(Math.random() * 10000)).padStart(4, '0')}`;
            $('#pf-file-no').val(fallbackNo);
            $('#pf-last-file-no').text('--');
            $('#pf-next-file-no').text(fallbackNo);
        }
    });
}

function toggleFileNumberEdit(mode) {
    const $input = $('#pf-file-no');
    const $hint = $('#pf-file-no-hint');
    const $buttons = $('.file-no-mode-btn');

    // Update button states
    $buttons.removeClass('active');
    $buttons.filter('[data-mode="' + mode + '"]').addClass('active');

    if (mode === 'manual') {
        // Manual mode - allow editing
        $input.prop('readonly', false).attr('placeholder', 'Enter file number');
        $hint.addClass('manual-mode');
        $input.focus().select();
    } else {
        // Auto mode - readonly with generated number
        $input.prop('readonly', true).attr('placeholder', 'Auto-generated');
        $hint.removeClass('manual-mode');
        generatePatientFormFileNumber();
    }
}

function populatePatientFormHMO() {
    const $select = $('#pf-hmo');
    $select.empty();

    // Group HMOs by scheme
    if (cachedHmos && cachedHmos.length) {
        const grouped = {};
        cachedHmos.forEach(hmo => {
            const schemeName = hmo.scheme_name || hmo.scheme || 'Other';
            if (!grouped[schemeName]) {
                grouped[schemeName] = [];
            }
            grouped[schemeName].push(hmo);
        });

        Object.keys(grouped).sort().forEach(scheme => {
            const $optgroup = $('<optgroup>').attr('label', scheme);
            grouped[scheme].forEach(hmo => {
                // HMO ID 1 is Private and is the default
                const selected = hmo.id === 1 ? ' selected' : '';
                $optgroup.append(`<option value="${hmo.id}"${selected}>${hmo.name}</option>`);
            });
            $select.append($optgroup);
        });
    }

    // Default select HMO ID 1 (Private)
    $select.val(1);
}

function populatePatientForm(data) {
    // Basic info
    $('#pf-file-no').val(data.file_no || '');
    $('#pf-surname').val(data.surname || '');
    $('#pf-firstname').val(data.firstname || '');
    $('#pf-othername').val(data.othername || '');
    $('#pf-gender').val(data.gender || '');

    // Parse DOB (may be in d/m/Y format)
    if (data.dob) {
        let dob = data.dob;
        // Check if it's in d/m/Y format
        if (dob.includes('/')) {
            const parts = dob.split('/');
            if (parts.length === 3) {
                dob = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        $('#pf-dob').val(dob);
        updatePatientFormAge();
    }

    $('#pf-phone').val(data.phone_no || '');
    $('#pf-email').val(data.email || '');
    $('#pf-address').val(data.address || '');

    // Medical info
    $('#pf-blood-group').val(data.blood_group || '');
    $('#pf-genotype').val(data.genotype || '');
    $('#pf-disability').val(data.disability ? '1' : '0');
    $('#pf-nationality').val(data.nationality || 'Nigerian');
    $('#pf-ethnicity').val(data.ethnicity || '');
    $('#pf-medical-history').val(data.medical_history || '');
    $('#pf-misc').val(data.misc || '');

    // Allergies
    if (data.allergies) {
        try {
            patientFormAllergies = typeof data.allergies === 'string' ? JSON.parse(data.allergies) : data.allergies;
            updateAllergiesTags();
        } catch (e) {
            patientFormAllergies = [];
        }
    }

    // Next of Kin
    $('#pf-nok-name').val(data.next_of_kin_name || '');
    $('#pf-nok-phone').val(data.next_of_kin_phone || '');
    $('#pf-nok-address').val(data.next_of_kin_address || '');

    // Insurance
    if (data.hmo_id) {
        setTimeout(() => {
            $('#pf-hmo').val(data.hmo_id);
            $('#pf-hmo-no').val(data.hmo_no || '');
            $('#pf-hmo-no-container').show();
        }, 100);
    }

    // Handle existing passport photo
    if (data.passport_url) {
        $('.passport-preview-container').show();
        $('#passport-preview-img').attr('src', data.passport_url);
        // Store existing filename for reference
        $('#pf-passport').data('existing', data.filename);
    } else {
        $('.passport-preview-container').hide();
        $('#passport-preview-img').attr('src', '');
    }

    // Handle existing old records
    if (data.old_records_url) {
        $('.old-records-preview-container').show();
        const ext = data.old_records.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
            $('#old-records-preview-img').attr('src', data.old_records_url).show();
            $('#old-records-preview-icon').hide();
        } else {
            $('#old-records-preview-img').hide();
            $('#old-records-preview-icon').show();
        }
        $('#old-records-preview-name').text(data.old_records);
        $('#pf-view-old-records').attr('href', data.old_records_url);
        // Store existing filename for reference
        $('#pf-old-records').data('existing', data.old_records);
    } else {
        $('.old-records-preview-container').hide();
        $('#old-records-preview-img').attr('src', '').hide();
        $('#old-records-preview-name').text('');
        $('#pf-view-old-records').attr('href', '#');
    }

    // Trigger change for floating labels
    $('#patient-form .form-control').each(function() {
        if ($(this).val()) {
            $(this).addClass('has-value');
        }
    });
}

function updatePatientFormStepper() {
    $('.stepper-item').each(function() {
        const step = parseInt($(this).data('step'));
        $(this).removeClass('active completed');

        if (step < patientFormCurrentStep) {
            $(this).addClass('completed');
        } else if (step === patientFormCurrentStep) {
            $(this).addClass('active');
        }
    });

    $('.stepper-line').each(function(index) {
        $(this).removeClass('completed');
        if (index + 1 < patientFormCurrentStep) {
            $(this).addClass('completed');
        }
    });
}

function updatePatientFormNavigation() {
    // Show/hide prev button
    if (patientFormCurrentStep === 1) {
        $('#pf-btn-prev').hide();
    } else {
        $('#pf-btn-prev').show();
    }

    // Show/hide next/submit buttons
    if (patientFormCurrentStep === patientFormTotalSteps) {
        $('#pf-btn-next').hide();
        $('#pf-btn-submit').show();
        updatePatientFormSummary();
    } else {
        $('#pf-btn-next').show();
        $('#pf-btn-submit').hide();
    }
}

function goToPatientFormStep(step) {
    if (step < 1 || step > patientFormTotalSteps) return;

    // Validate current step before moving forward
    if (step > patientFormCurrentStep && !validatePatientFormStep(patientFormCurrentStep)) {
        return;
    }

    patientFormCurrentStep = step;

    // Show the step
    $('.form-step').removeClass('active');
    $(`.form-step[data-step="${step}"]`).addClass('active');

    // Update stepper
    updatePatientFormStepper();

    // Update navigation
    updatePatientFormNavigation();

    // Scroll to top of modal body
    $('.form-steps-container').scrollTop(0);
}

function validatePatientFormStep(step) {
    let isValid = true;
    const $step = $(`.form-step[data-step="${step}"]`);

    // Clear previous validations
    $step.find('.form-control').removeClass('is-valid is-invalid');
    $step.find('.invalid-feedback').text('');

    if (step === 1) {
        // Validate basic info
        const surname = $('#pf-surname').val().trim();
        const firstname = $('#pf-firstname').val().trim();
        const gender = $('#pf-gender').val();
        const dob = $('#pf-dob').val();

        if (!surname) {
            $('#pf-surname').addClass('is-invalid');
            $('#pf-surname').siblings('.invalid-feedback').text('Surname is required');
            isValid = false;
        } else {
            $('#pf-surname').addClass('is-valid');
        }

        if (!firstname) {
            $('#pf-firstname').addClass('is-invalid');
            $('#pf-firstname').siblings('.invalid-feedback').text('First name is required');
            isValid = false;
        } else {
            $('#pf-firstname').addClass('is-valid');
        }

        if (!gender) {
            $('#pf-gender').addClass('is-invalid');
            $('#pf-gender').siblings('.invalid-feedback').text('Gender is required');
            isValid = false;
        } else {
            $('#pf-gender').addClass('is-valid');
        }

        if (!dob) {
            $('#pf-dob').addClass('is-invalid');
            $('#pf-dob').siblings('.invalid-feedback').text('Date of birth is required');
            isValid = false;
        } else {
            $('#pf-dob').addClass('is-valid');
        }

        // Validate phone if provided
        const phone = $('#pf-phone').val().trim();
        if (phone && !isValidPhone(phone)) {
            $('#pf-phone').addClass('is-invalid');
            $('#pf-phone').siblings('.invalid-feedback').text('Invalid phone number');
            isValid = false;
        }

        // Validate email if provided
        const email = $('#pf-email').val().trim();
        if (email && !isValidEmail(email)) {
            $('#pf-email').addClass('is-invalid');
            $('#pf-email').siblings('.invalid-feedback').text('Invalid email address');
            isValid = false;
        }
    }

    if (!isValid) {
        // Focus first invalid field
        $step.find('.is-invalid:first').focus();
        toastr.warning('Please fill in all required fields correctly');
    }

    return isValid;
}

function isValidPhone(phone) {
    return /^[\d\s+\-()]{7,20}$/.test(phone);
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function updatePatientFormAge() {
    const dob = $('#pf-dob').val();
    if (!dob) {
        $('#pf-age-display').text('');
        return;
    }

    const birthDate = new Date(dob);
    const today = new Date();

    let years = today.getFullYear() - birthDate.getFullYear();
    let months = today.getMonth() - birthDate.getMonth();
    let days = today.getDate() - birthDate.getDate();

    if (days < 0) {
        months--;
        days += new Date(today.getFullYear(), today.getMonth(), 0).getDate();
    }

    if (months < 0) {
        years--;
        months += 12;
    }

    let ageText = '';
    if (years > 0) {
        ageText = `${years} year${years !== 1 ? 's' : ''}`;
        if (months > 0) {
            ageText += `, ${months} month${months !== 1 ? 's' : ''}`;
        }
    } else if (months > 0) {
        ageText = `${months} month${months !== 1 ? 's' : ''}`;
        if (days > 0) {
            ageText += `, ${days} day${days !== 1 ? 's' : ''}`;
        }
    } else {
        ageText = `${days} day${days !== 1 ? 's' : ''}`;
    }

    $('#pf-age-display').html(`<i class="mdi mdi-calendar-account"></i> Age: ${ageText}`);
}

function updateAllergiesTags() {
    const $container = $('#pf-allergies-tags');
    $container.empty();

    patientFormAllergies.forEach((allergy, index) => {
        $container.append(`
            <span class="allergy-tag-item">
                <i class="mdi mdi-alert-circle"></i>
                ${escapeHtml(allergy)}
                <span class="remove-allergy" data-index="${index}">&times;</span>
            </span>
        `);
    });

    $('#pf-allergies').val(JSON.stringify(patientFormAllergies));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function updatePatientFormSummary() {
    const fullName = [
        $('#pf-surname').val().trim(),
        $('#pf-firstname').val().trim(),
        $('#pf-othername').val().trim()
    ].filter(Boolean).join(' ');

    // Basic Information
    $('#summary-file-no').text($('#pf-file-no').val() || '-');
    $('#summary-name').text(fullName || '-');
    $('#summary-gender').text($('#pf-gender').val() || '-');
    $('#summary-phone').text($('#pf-phone').val() || 'N/A');
    $('#summary-email').text($('#pf-email').val() || 'N/A');
    $('#summary-address').text($('#pf-address').val().trim() || 'N/A');

    // Date of Birth & Age
    const dob = $('#pf-dob').val();
    if (dob) {
        const birthDate = new Date(dob);
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        $('#summary-dob').text(birthDate.toLocaleDateString('en-US', options));

        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        $('#summary-age').text(`${age} years`);
    } else {
        $('#summary-dob').text('-');
        $('#summary-age').text('-');
    }

    // File uploads - check for new file or existing file
    const passportFile = $('#pf-passport')[0].files[0];
    const existingPassport = $('#pf-passport').data('existing');
    if (passportFile) {
        $('#summary-passport').html('<span class="text-success"><i class="mdi mdi-check-circle"></i> ' + passportFile.name + ' <em>(new)</em></span>');
    } else if (existingPassport) {
        $('#summary-passport').html('<span class="text-info"><i class="mdi mdi-file-image"></i> ' + existingPassport + ' <em>(existing)</em></span>');
    } else {
        $('#summary-passport').text('Not uploaded');
    }

    const oldRecordsFile = $('#pf-old-records')[0].files[0];
    const existingOldRecords = $('#pf-old-records').data('existing');
    if (oldRecordsFile) {
        $('#summary-old-records').html('<span class="text-success"><i class="mdi mdi-check-circle"></i> ' + oldRecordsFile.name + ' <em>(new)</em></span>');
    } else if (existingOldRecords) {
        $('#summary-old-records').html('<span class="text-info"><i class="mdi mdi-file-document"></i> ' + existingOldRecords + ' <em>(existing)</em></span>');
    } else {
        $('#summary-old-records').text('Not uploaded');
    }

    // Medical Information
    $('#summary-blood-group').text($('#pf-blood-group').val() || '-');
    $('#summary-genotype').text($('#pf-genotype').val() || '-');
    $('#summary-disability').text($('#pf-disability option:selected').text() || '-');
    $('#summary-nationality').text($('#pf-nationality').val() || '-');
    $('#summary-ethnicity').text($('#pf-ethnicity').val() || '-');

    // Allergies
    if (patientFormAllergies && patientFormAllergies.length > 0) {
        $('#summary-allergies').html(patientFormAllergies.map(a => '<span class="badge bg-danger me-1">' + a + '</span>').join(' '));
    } else {
        $('#summary-allergies').text('None');
    }

    $('#summary-medical-history').text($('#pf-medical-history').val().trim() || 'None');

    // Next of Kin
    $('#summary-nok-name').text($('#pf-nok-name').val().trim() || '-');
    $('#summary-nok-phone').text($('#pf-nok-phone').val().trim() || '-');
    $('#summary-nok-address').text($('#pf-nok-address').val().trim() || '-');

    // HMO
    const hmoId = $('#pf-hmo').val();
    if (hmoId) {
        $('#summary-hmo').text($('#pf-hmo option:selected').text());
    } else {
        $('#summary-hmo').text('Private');
    }
    $('#summary-hmo-no').text($('#pf-hmo-no').val().trim() || '-');
}

function clearPatientFormSummary() {
    // Clear all summary fields
    $('#summary-file-no, #summary-name, #summary-gender, #summary-dob, #summary-age').text('-');
    $('#summary-phone, #summary-email, #summary-address').text('-');
    $('#summary-passport, #summary-old-records').text('Not uploaded');
    $('#summary-blood-group, #summary-genotype, #summary-disability').text('-');
    $('#summary-nationality, #summary-ethnicity').text('-');
    $('#summary-allergies').text('None');
    $('#summary-medical-history').text('-');
    $('#summary-nok-name, #summary-nok-phone, #summary-nok-address').text('-');
    $('#summary-hmo').text('Private');
    $('#summary-hmo-no').text('-');
}

function submitPatientForm() {
    // Final validation
    if (!validatePatientFormStep(1)) {
        goToPatientFormStep(1);
        return;
    }

    const mode = $('#patient-form-mode').val();
    const patientId = $('#patient-form-id').val();
    const $btn = $('#pf-btn-submit');
    const originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

    // Use FormData for file uploads
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('file_no', $('#pf-file-no').val());
    formData.append('surname', $('#pf-surname').val().trim());
    formData.append('firstname', $('#pf-firstname').val().trim());
    formData.append('othername', $('#pf-othername').val().trim());
    formData.append('gender', $('#pf-gender').val());
    formData.append('dob', $('#pf-dob').val());
    formData.append('phone_no', $('#pf-phone').val().trim());
    formData.append('email', $('#pf-email').val().trim());
    formData.append('address', $('#pf-address').val().trim());
    formData.append('blood_group', $('#pf-blood-group').val());
    formData.append('genotype', $('#pf-genotype').val());
    formData.append('disability', $('#pf-disability').val());
    formData.append('nationality', $('#pf-nationality').val());
    formData.append('ethnicity', $('#pf-ethnicity').val());
    formData.append('allergies', JSON.stringify(patientFormAllergies));
    formData.append('medical_history', $('#pf-medical-history').val().trim());
    formData.append('misc', $('#pf-misc').val().trim());
    formData.append('next_of_kin_name', $('#pf-nok-name').val().trim());
    formData.append('next_of_kin_phone', $('#pf-nok-phone').val().trim());
    formData.append('next_of_kin_address', $('#pf-nok-address').val().trim());
    formData.append('hmo_id', $('#pf-hmo').val() || 1);
    formData.append('hmo_no', $('#pf-hmo-no').val().trim());

    // Add file uploads if present
    const passportFile = $('#pf-passport')[0].files[0];
    if (passportFile) {
        formData.append('filename', passportFile);
    }

    const oldRecordsFile = $('#pf-old-records')[0].files[0];
    if (oldRecordsFile) {
        formData.append('old_records', oldRecordsFile);
    }

    let url;
    if (mode === 'edit' && patientId) {
        url = `/reception/patient/${patientId}/update`;
        formData.append('_method', 'PUT');
    } else {
        url = '{{ route("reception.patient.quick-register") }}';
    }

    $.ajax({
        url: url,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                toastr.success(mode === 'edit' ? 'Patient updated successfully' : 'Patient registered successfully');
                $('#patientFormModal').modal('hide');

                // Reload patient data if editing current patient or load new patient
                const newPatientId = response.patient?.id || patientId;
                if (newPatientId) {
                    loadPatient(newPatientId);
                }
            } else {
                toastr.error(response.message || 'Operation failed');
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(err => {
                    toastr.error(err[0]);
                });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Operation failed');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// Patient Form Event Listeners
$(document).ready(function() {
    // Stepper navigation
    $('.stepper-item').on('click', function() {
        const step = parseInt($(this).data('step'));
        goToPatientFormStep(step);
    });

    // Next button
    $('#pf-btn-next').on('click', function() {
        goToPatientFormStep(patientFormCurrentStep + 1);
    });

    // Previous button
    $('#pf-btn-prev').on('click', function() {
        goToPatientFormStep(patientFormCurrentStep - 1);
    });

    // Submit button
    $('#patient-form').on('submit', function(e) {
        e.preventDefault();
        submitPatientForm();
    });

    // File number mode buttons
    $(document).on('click', '.file-no-mode-btn', function() {
        const mode = $(this).data('mode');
        toggleFileNumberEdit(mode);
    });

    // DOB change - update age
    $('#pf-dob').on('change', function() {
        updatePatientFormAge();
    });

    // HMO change - show/hide HMO number field
    $('#pf-hmo').on('change', function() {
        if ($(this).val() && $(this).val() != 1) {
            $('#pf-hmo-no-container').show();
        } else {
            $('#pf-hmo-no-container').hide();
            $('#pf-hmo-no').val('');
        }
    });

    // Passport file preview - when new file selected
    $('#pf-passport').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#passport-new-img').attr('src', e.target.result);
                $('#passport-new-name').text(file.name);
                $('#pf-passport-new-preview').show();
                // Hide existing preview if showing
                $('.passport-preview-container').hide();
            };
            reader.readAsDataURL(file);
        } else {
            $('#pf-passport-new-preview').hide();
            // Show existing preview back if there was one
            if ($('#pf-passport').data('existing')) {
                $('.passport-preview-container').show();
            }
        }
    });

    // Cancel new passport selection - revert to existing
    $('#pf-cancel-passport').on('click', function() {
        $('#pf-passport').val('');
        $('#pf-passport-new-preview').hide();
        // Show existing preview back if there was one
        if ($('#pf-passport').data('existing')) {
            $('.passport-preview-container').show();
        }
    });

    // Clear existing passport (mark for removal)
    $('#pf-clear-passport').on('click', function() {
        $('#pf-passport').val('').removeData('existing');
        $('.passport-preview-container').hide();
        $('#pf-passport-new-preview').hide();
    });

    // Old records file preview - when new file selected
    $('#pf-old-records').on('change', function() {
        const file = this.files[0];
        if (file) {
            $('#old-records-new-name').text(file.name);
            $('#pf-old-records-new-preview').show();
            // Hide existing preview if showing
            $('.old-records-preview-container').hide();
        } else {
            $('#pf-old-records-new-preview').hide();
            // Show existing preview back if there was one
            if ($('#pf-old-records').data('existing')) {
                $('.old-records-preview-container').show();
            }
        }
    });

    // Cancel new old records selection - revert to existing
    $('#pf-cancel-old-records').on('click', function() {
        $('#pf-old-records').val('');
        $('#pf-old-records-new-preview').hide();
        // Show existing preview back if there was one
        if ($('#pf-old-records').data('existing')) {
            $('.old-records-preview-container').show();
        }
    });

    // Clear existing old records (mark for removal)
    $('#pf-clear-old-records').on('click', function() {
        $('#pf-old-records').val('').removeData('existing');
        $('.old-records-preview-container').hide();
        $('#pf-old-records-new-preview').hide();
    });

    // Allergies input
    $('#pf-allergy-input').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const allergy = $(this).val().trim();
            if (allergy && !patientFormAllergies.includes(allergy)) {
                patientFormAllergies.push(allergy);
                updateAllergiesTags();
            }
            $(this).val('');
        }
    });

    // Remove allergy
    $(document).on('click', '.remove-allergy', function() {
        const index = $(this).data('index');
        patientFormAllergies.splice(index, 1);
        updateAllergiesTags();
    });

    // Live validation
    $('#patient-form .form-control[required]').on('blur', function() {
        const $field = $(this);
        if ($field.val().trim()) {
            $field.removeClass('is-invalid').addClass('is-valid');
        } else {
            $field.removeClass('is-valid');
        }
    });

    // Floating labels - detect value
    $('#patient-form .form-control').on('input change', function() {
        if ($(this).val()) {
            $(this).addClass('has-value');
        } else {
            $(this).removeClass('has-value');
        }
    });

    // New patient button (update to use new modal)
    $('#btn-new-patient').off('click').on('click', function() {
        showPatientFormModal('create');
    });
});

</script>
@endsection

