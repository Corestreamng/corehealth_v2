@extends('admin.layouts.app')

@section('title', 'Billing Workbench')

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
    .billing-workbench-container {
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

<div class="billing-workbench-container">
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
            <h6>📊 PAYMENT QUEUE</h6>
            <div class="queue-item" data-filter="all">
                <span class="queue-item-label">🟡 All Unpaid</span>
                <span class="queue-count all-unpaid" id="queue-all-count">0</span>
            </div>
            <div class="queue-item" data-filter="hmo">
                <span class="queue-item-label">🟢 HMO Items</span>
                <span class="queue-count hmo-items" id="queue-hmo-count">0</span>
            </div>
            <div class="queue-item" data-filter="credit">
                <span class="queue-item-label">🟠 Credit Accounts</span>
                <span class="queue-count credit-accounts" id="queue-credit-count">0</span>
            </div>
            <button class="btn-queue-all" id="show-all-queue-btn">
                📋 Show All Queue →
            </button>
        </div>

        <div class="quick-actions">
            <h6>⚡ QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-my-transactions">
                <i class="mdi mdi-receipt"></i>
                <span>My Transactions</span>
            </button>
            <button class="quick-action-btn" disabled style="opacity: 0.5;">
                <i class="mdi mdi-file-invoice-dollar"></i>
                <span>Generate Invoice (Coming Soon)</span>
            </button>
            <button class="quick-action-btn" disabled style="opacity: 0.5;">
                <i class="mdi mdi-wallet"></i>
                <span>Credit Management (Coming Soon)</span>
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
            <i class="mdi mdi-account-cash"></i>
            <h3>No Patient Selected</h3>
            <p>Search and select a patient from the queue to begin billing</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                💰 View Payment Queue
            </button>
        </div>

        <!-- Queue View -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-format-list-bulleted"></i> Payment Queue</h4>
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

        <!-- Patient Header -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div style="flex: 1;">
                    <div class="patient-name" id="patient-name"></div>
                    <div class="patient-meta" id="patient-meta"></div>
                </div>
                <div class="patient-account-balance" id="patient-header-balance" style="display: none;">
                    <div class="balance-label">Account Balance</div>
                    <div class="balance-value" id="header-balance-amount">₦0.00</div>
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
                <button class="workspace-tab active" data-tab="billing">
                    <i class="mdi mdi-cash-register"></i>
                    <span>Billing</span>
                    <span class="workspace-tab-badge" id="billing-badge">0</span>
                </button>
                <button class="workspace-tab" data-tab="receipts">
                    <i class="mdi mdi-receipt"></i>
                    <span>Receipts</span>
                </button>
                <button class="workspace-tab" data-tab="account">
                    <i class="mdi mdi-wallet"></i>
                    <span>Account</span>
                </button>
            </div>

            <div class="workspace-tab-content active" id="billing-tab">
                <div class="billing-tab-header">
                    <h4><i class="mdi mdi-cash-register"></i> Patient Billing Items</h4>
                    <div class="billing-toolbar">
                        <button class="btn btn-sm btn-secondary" id="refresh-billing-items">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-success" id="process-payment-btn" disabled>
                            <i class="mdi mdi-cash"></i> Process Payment
                        </button>
                    </div>
                </div>

                <div class="billing-items-container">
                    <table class="table table-hover" id="billing-items-table">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all-billing-items"></th>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th width="80">Qty</th>
                                <th width="80">Discount %</th>
                                <th>HMO Coverage</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="billing-items-tbody">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                                    <p>No unpaid items for this patient</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Payment Summary Card (appears when items selected) -->
                <div class="payment-summary-card" id="payment-summary-card" style="display: none;">
                    <h5><i class="mdi mdi-calculator"></i> Payment Summary</h5>

                    <!-- Account Balance Info -->
                    <div class="account-balance-info" id="billing-account-balance" style="display: none;">
                        <div class="balance-row">
                            <span><i class="mdi mdi-wallet"></i> Account Balance:</span>
                            <span id="billing-balance-amount" class="balance-amount">₦0.00</span>
                        </div>
                    </div>

                    <div class="summary-details">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="summary-subtotal">₦0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Total Discount:</span>
                            <span id="summary-discount">₦0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total Payable:</span>
                            <span id="summary-total">₦0.00</span>
                        </div>
                    </div>
                    <div class="payment-method-section">
                        <label><i class="mdi mdi-cash-multiple"></i> Payment Method</label>
                        <select class="form-control" id="payment-method">
                            <option value="CASH">Cash</option>
                            <option value="POS">POS/Card</option>
                            <option value="TRANSFER">Bank Transfer</option>
                            <option value="MOBILE">Mobile Money</option>
                            <option value="ACCOUNT" id="account-payment-option" style="display: none;">Pay from Account Balance</option>
                        </select>
                        <small class="text-muted" id="account-payment-note" style="display: none;">
                            <i class="mdi mdi-information"></i> Payment will be deducted from account balance
                        </small>
                    </div>
                    <div class="bank-selection-section" id="bank-selection-section" style="display: none;">
                        <label><i class="mdi mdi-bank"></i> Select Bank</label>
                        <select class="form-control" id="payment-bank">
                            <option value="">-- Select Bank --</option>
                        </select>
                    </div>
                    <div class="payment-reference-section">
                        <label>Reference Number (Optional)</label>
                        <input type="text" class="form-control" id="payment-reference" placeholder="Enter transaction reference">
                    </div>
                    <button class="btn btn-success btn-block btn-lg" id="confirm-payment-btn">
                        <i class="mdi mdi-check-circle"></i> Confirm Payment
                    </button>
                </div>

                <!-- Receipt Display (after payment) -->
                <div class="receipt-display" id="receipt-display" style="display: none;">
                    <div class="receipt-tabs">
                        <button class="receipt-tab active" data-format="a4">A4 Receipt</button>
                        <button class="receipt-tab" data-format="thermal">Thermal Receipt</button>
                    </div>
                    <div class="receipt-content" id="receipt-content-a4"></div>
                    <div class="receipt-content" id="receipt-content-thermal" style="display: none;"></div>
                    <div class="receipt-actions">
                        <button class="btn btn-primary" id="print-a4-receipt">
                            <i class="mdi mdi-printer"></i> Print A4
                        </button>
                        <button class="btn btn-primary" id="print-thermal-receipt">
                            <i class="mdi mdi-printer"></i> Print Thermal
                        </button>
                        <button class="btn btn-secondary" id="close-receipt">
                            <i class="mdi mdi-close"></i> Close
                        </button>
                    </div>
                </div>
            </div>

            <div class="workspace-tab-content" id="receipts-tab">
                <div class="receipts-tab-header">
                    <h4><i class="mdi mdi-receipt"></i> Payment Receipts & Transactions</h4>
                    <div class="receipts-toolbar">
                        <button class="btn btn-sm btn-secondary" id="refresh-receipts">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-primary" id="print-selected-receipts" disabled>
                            <i class="mdi mdi-printer"></i> Print Selected
                        </button>
                        <button class="btn btn-sm btn-info" id="export-receipts">
                            <i class="mdi mdi-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Filter Panel -->
                <div class="transactions-filter-panel">
                    <div class="row">
                        <div class="col-md-3">
                            <label>From Date</label>
                            <input type="date" class="form-control" id="receipts-from-date">
                        </div>
                        <div class="col-md-3">
                            <label>To Date</label>
                            <input type="date" class="form-control" id="receipts-to-date">
                        </div>
                        <div class="col-md-3">
                            <label>Payment Type</label>
                            <select class="form-control" id="receipts-payment-type">
                                <option value="">All Types</option>
                                <option value="CASH">Cash</option>
                                <option value="POS">POS/Card</option>
                                <option value="TRANSFER">Bank Transfer</option>
                                <option value="MOBILE">Mobile Money</option>
                                <option value="ACCOUNT">Account Balance</option>
                                <option value="ACC_DEPOSIT">Account Deposit</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary btn-block" id="filter-receipts">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="transactions-summary" id="receipts-summary" style="display: none;">
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-count">0</div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-amount">₦0.00</div>
                        <div class="stat-label">Total Amount</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-discounts">₦0.00</div>
                        <div class="stat-label">Total Discounts</div>
                    </div>
                </div>

                <div class="receipts-container">
                    <table class="table table-hover" id="receipts-table">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all-receipts"></th>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Discount</th>
                                <th>Method</th>
                                <th>Cashier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="receipts-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="mdi mdi-receipt" style="font-size: 3rem;"></i>
                                    <p>No receipts found for this patient</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="workspace-tab-content" id="account-tab">
                <!-- Hero Balance Section -->
                <div class="account-hero-section" id="account-hero-section">
                    <div class="account-hero-balance" id="account-hero-balance">
                        <div class="hero-balance-icon">
                            <i class="mdi mdi-wallet"></i>
                        </div>
                        <div class="hero-balance-content">
                            <span class="hero-balance-label">Current Balance</span>
                            <span class="hero-balance-amount" id="hero-balance-amount">₦0.00</span>
                            <span class="hero-balance-status" id="hero-balance-status">Balanced</span>
                        </div>
                        <div class="hero-balance-actions">
                            <div class="action-btn-group">
                                <button class="btn btn-light btn-sm" id="quick-deposit-btn" title="Make Deposit">
                                    <i class="mdi mdi-plus-circle text-success"></i> Deposit
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="quick-withdraw-btn" title="Withdraw">
                                    <i class="mdi mdi-minus-circle text-danger"></i> Withdraw
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="quick-adjust-btn" title="Adjustment">
                                    <i class="mdi mdi-swap-horizontal text-info"></i> Adjust
                                </button>
                            </div>
                            <div class="action-btn-group mt-2">
                                <button class="btn btn-warning btn-sm" id="print-statement-btn" title="Print Account Statement">
                                    <i class="mdi mdi-file-document-outline"></i> Print Statement
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="refresh-account-data" title="Refresh">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Account Stats Dashboard -->
                    <div class="account-stats-grid">
                        <div class="account-stat-card deposits">
                            <div class="stat-icon"><i class="mdi mdi-arrow-down-bold-circle"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="total-deposits-stat">₦0</span>
                                <span class="stat-label">Total Deposits</span>
                            </div>
                        </div>
                        <div class="account-stat-card withdrawals">
                            <div class="stat-icon"><i class="mdi mdi-arrow-up-bold-circle"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="total-withdrawals-stat">₦0</span>
                                <span class="stat-label">Total Withdrawals</span>
                            </div>
                        </div>
                        <div class="account-stat-card pending">
                            <div class="stat-icon"><i class="mdi mdi-clock-outline"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="pending-bills-stat">₦0</span>
                                <span class="stat-label">Pending Bills</span>
                            </div>
                        </div>
                        <div class="account-stat-card transactions">
                            <div class="stat-icon"><i class="mdi mdi-swap-horizontal"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="tx-count-stat">0</span>
                                <span class="stat-label">Transactions</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Account State -->
                <div class="account-no-account-state" id="no-account-state" style="display: none;">
                    <div class="no-account-content">
                        <div class="no-account-icon">
                            <i class="mdi mdi-wallet-outline"></i>
                        </div>
                        <h4>No Account Found</h4>
                        <p>This patient doesn't have an account yet. Create one to start tracking deposits and payments.</p>
                        <button class="btn btn-primary btn-lg" id="create-account-btn">
                            <i class="mdi mdi-plus-circle"></i> Create Account
                        </button>
                    </div>
                </div>

                <!-- Account Transaction Panel (Deposit/Withdraw/Adjust) -->
                <div class="account-transaction-panel" id="account-transaction-panel" style="display: none;">
                    <div class="transaction-panel-header" id="transaction-panel-header">
                        <h5><i class="mdi mdi-cash-plus" id="transaction-panel-icon"></i> <span id="transaction-panel-title">Make Deposit</span></h5>
                        <button class="btn btn-sm btn-link" id="close-transaction-panel">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                    <div class="transaction-panel-body">
                        <form id="account-transaction-form" class="transaction-form-inline">
                            <input type="hidden" id="transaction-type" value="deposit">
                            <div class="form-group">
                                <label>Amount</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-lg" id="transaction-amount" placeholder="0.00" required>
                                </div>
                                <small class="form-text text-muted" id="transaction-amount-help">Enter amount to deposit</small>
                            </div>
                            <div class="form-group" id="transaction-payment-method-group">
                                <label>Payment Method</label>
                                <select class="form-control" id="transaction-payment-method">
                                    <option value="CASH">Cash</option>
                                    <option value="POS">POS/Card</option>
                                    <option value="TRANSFER">Bank Transfer</option>
                                    <option value="MOBILE">Mobile Money</option>
                                </select>
                            </div>
                            <div class="form-group" id="transaction-bank-group" style="display: none;">
                                <label>Select Bank</label>
                                <select class="form-control" id="transaction-bank">
                                    <option value="">-- Select Bank --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Description <small class="text-muted">(Required for adjustments)</small></label>
                                <input type="text" class="form-control" id="transaction-description" placeholder="e.g., Cash deposit, Refund, Correction, etc.">
                            </div>
                            <div class="transaction-actions">
                                <button type="submit" class="btn btn-block" id="transaction-submit-btn">
                                    <i class="mdi mdi-check"></i> <span id="transaction-submit-text">Confirm Deposit</span>
                                </button>
                            </div>
                        </form>

                        <!-- Balance Preview -->
                        <div class="balance-preview" id="balance-preview">
                            <div class="balance-preview-row">
                                <span>Current Balance:</span>
                                <span id="preview-current-balance">₦0.00</span>
                            </div>
                            <div class="balance-preview-row">
                                <span id="preview-change-label">After Deposit:</span>
                                <span id="preview-new-balance">₦0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction History Section -->
                <div class="account-transactions-section" id="account-transactions-section">
                    <div class="transactions-section-header">
                        <h5><i class="mdi mdi-history"></i> Account Transactions</h5>
                        <div class="transactions-filters">
                            <div class="filter-group">
                                <input type="date" class="form-control form-control-sm" id="account-tx-from-date">
                            </div>
                            <div class="filter-group">
                                <input type="date" class="form-control form-control-sm" id="account-tx-to-date">
                            </div>
                            <div class="filter-group">
                                <select class="form-control form-control-sm" id="account-tx-type-filter">
                                    <option value="">All Types</option>
                                    <option value="ACC_DEPOSIT">Deposits</option>
                                    <option value="ACC_WITHDRAW">Withdrawals/Payments</option>
                                    <option value="ACC_ADJUSTMENT">Adjustments</option>
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary" id="filter-account-tx">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- Transaction Timeline -->
                    <div class="transaction-timeline" id="transaction-timeline">
                        <div class="timeline-empty-state">
                            <i class="mdi mdi-swap-horizontal"></i>
                            <p>No account transactions yet</p>
                            <small>Deposits and withdrawals will appear here</small>
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

/* Statement Modal Styles */
.statement-modal-tabs {
    display: flex;
    border-bottom: 2px solid #e9ecef;
    background: #f8f9fa;
}
.statement-modal-tab {
    flex: 1;
    padding: 12px;
    border: none;
    background: transparent;
    color: #6c757d;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}
.statement-modal-tab:hover {
    background: #e9ecef;
    color: #495057;
}
.statement-modal-tab.active {
    background: #fff;
    color: #007bff;
    border-bottom: 3px solid #007bff;
    margin-bottom: -2px;
}
.statement-modal-content {
    padding: 15px;
    max-height: 60vh;
    overflow-y: auto;
}
.statement-modal-pane {
    display: none;
}
.statement-modal-pane.active {
    display: block;
}
.statement-config-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}
.statement-config-section h6 {
    margin-bottom: 15px;
    color: #495057;
    font-weight: 600;
}
.statement-type-checkbox {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin: 5px 0;
    border-radius: 6px;
    transition: all 0.2s;
}
.statement-type-checkbox:hover {
    background: #e9ecef;
}
.statement-type-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-right: 10px;
}
.statement-type-checkbox label {
    margin: 0;
    cursor: pointer;
    font-weight: 500;
}
.statement-type-checkbox .type-icon {
    margin-left: auto;
    font-size: 18px;
}
.statement-type-checkbox.deposits .type-icon { color: #28a745; }
.statement-type-checkbox.payments .type-icon { color: #dc3545; }
.statement-type-checkbox.withdrawals .type-icon { color: #fd7e14; }
.statement-type-checkbox.services .type-icon { color: #6610f2; }
.statement-summary-preview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    margin-bottom: 15px;
}
.statement-summary-preview h5 {
    margin-bottom: 10px;
}
.statement-summary-preview .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}
.statement-date-presets {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.statement-date-presets button {
    font-size: 11px;
    padding: 4px 10px;
}
</style>

<!-- Account Statement Modal -->
<div class="modal fade" id="accountStatementModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="mdi mdi-file-document-outline"></i> Account Statement</h5>
                <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Config Panel -->
                <div id="statement-config-panel" style="display: block;">
                    <div class="p-3">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Date Range -->
                                <div class="statement-config-section">
                                    <h6><i class="mdi mdi-calendar-range"></i> Date Range</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="small text-muted">From</label>
                                            <input type="date" id="statement-date-from" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted">To</label>
                                            <input type="date" id="statement-date-to" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <div class="statement-date-presets">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="7days">Last 7 Days</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="30days">Last 30 Days</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="thisMonth">This Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="lastMonth">Last Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="thisYear">This Year</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="all">All Time</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Transaction Types -->
                                <div class="statement-config-section">
                                    <h6><i class="mdi mdi-filter-outline"></i> Include Transaction Types</h6>
                                    <div class="statement-type-checkbox deposits">
                                        <input type="checkbox" id="include-deposits" checked>
                                        <label for="include-deposits">Deposits</label>
                                        <i class="mdi mdi-arrow-down-bold-circle type-icon"></i>
                                    </div>
                                    <div class="statement-type-checkbox payments">
                                        <input type="checkbox" id="include-payments" checked>
                                        <label for="include-payments">Direct Payments</label>
                                        <i class="mdi mdi-credit-card type-icon"></i>
                                    </div>
                                    <div class="statement-type-checkbox withdrawals">
                                        <input type="checkbox" id="include-withdrawals" checked>
                                        <label for="include-withdrawals">Withdrawals & Adjustments</label>
                                        <i class="mdi mdi-arrow-up-bold-circle type-icon"></i>
                                    </div>
                                    <div class="statement-type-checkbox services">
                                        <input type="checkbox" id="include-services" checked>
                                        <label for="include-services">Deposit Applications</label>
                                        <i class="mdi mdi-application type-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-warning btn-lg px-5" id="generate-statement-btn">
                                <i class="mdi mdi-file-document"></i> Generate Statement
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statement Preview Panel -->
                <div id="statement-preview-panel" style="display: none;">
                    <div class="statement-modal-tabs">
                        <button class="statement-modal-tab active" data-format="a4">
                            <i class="mdi mdi-file-document"></i> A4 Statement
                        </button>
                        <button class="statement-modal-tab" data-format="thermal">
                            <i class="mdi mdi-receipt"></i> Thermal Statement
                        </button>
                        <button class="statement-modal-tab" data-format="config">
                            <i class="mdi mdi-cog"></i> Options
                        </button>
                    </div>
                    <div class="statement-modal-content">
                        <div class="statement-modal-pane active" id="statement-pane-a4"></div>
                        <div class="statement-modal-pane" id="statement-pane-thermal"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="statement-modal-footer" style="display: none;">
                <button type="button" class="btn btn-outline-secondary" id="statement-back-btn">
                    <i class="mdi mdi-arrow-left"></i> Back to Options
                </button>
                <button type="button" class="btn btn-primary" id="statement-print-a4">
                    <i class="mdi mdi-printer"></i> Print A4
                </button>
                <button type="button" class="btn btn-info" id="statement-print-thermal">
                    <i class="mdi mdi-printer"></i> Print Thermal
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

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

@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script>
// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let patientSearchTimeout = null;
let vitalTooltip = null;

$(document).ready(function() {
    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadUserPreferences();
    createVitalTooltip();
    loadBanks(); // Load available banks for payment
});

function initializeEventListeners() {
    // Generate initial reference number
    generateReferenceNumber();

    // Patient search
    $('#patient-search-input').on('input', function() {
        clearTimeout(patientSearchTimeout);
        const query = $(this).val().trim();

        if (query.length < 2) {
            $('#patient-search-results').hide();
            return;
        }

        patientSearchTimeout = setTimeout(() => searchPatients(query), 300);
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

    // Refresh billing items button
    $('#refresh-billing-items').on('click', function() {
        if (currentPatient) {
            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Refreshing...');

            loadPatient(currentPatient);

            // Re-enable button after a short delay
            setTimeout(() => {
                $btn.prop('disabled', false).html(originalHtml);
            }, 1000);
        }
    });

    // Payment method change handler
    $('#payment-method').on('change', function() {
        const method = $(this).val();
        if (method === 'ACCOUNT') {
            $('#account-payment-note').show();
            $('#bank-selection-section').hide();
        } else if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
            $('#account-payment-note').hide();
            $('#bank-selection-section').show();
        } else {
            $('#account-payment-note').hide();
            $('#bank-selection-section').hide();
        }
    });

    // Filter receipts
    $('#filter-receipts').on('click', function() {
        if (currentPatient) {
            filterReceipts();
        }
    });

    // Export receipts
    $('#export-receipts').on('click', function() {
        if (currentPatient) {
            exportReceipts();
        }
    });

    // Refresh receipts
    $('#refresh-receipts').on('click', function() {
        if (currentPatient) {
            setDefaultReceiptDates();
            filterReceipts();
        }
    });

    // Select all receipts checkbox
    $(document).on('change', '#select-all-receipts', function() {
        $('.receipt-checkbox').prop('checked', $(this).is(':checked'));
        updatePrintSelectedButton();
    });

    // Individual receipt checkbox change
    $(document).on('change', '.receipt-checkbox', function() {
        updatePrintSelectedButton();
    });

    // Reprint individual receipt
    $(document).on('click', '.reprint-receipt', function() {
        const paymentId = $(this).data('id');
        if (paymentId) {
            reprintReceipt([paymentId]);
        }
    });

    // Print individual deposit receipt
    $(document).on('click', '.reprint-deposit-receipt', function() {
        const depositId = $(this).data('deposit-id');
        if (depositId) {
            printDepositReceiptFromList(depositId);
        }
    });

    // Create account button
    $(document).on('click', '#create-account-btn', function() {
        createPatientAccount();
    });

    // View services rendered
    $(document).on('click', '#view-services-rendered', function() {
        if (currentPatient) {
            window.open(`/patient-services-rendered/${currentPatient}`, '_blank');
        }
    });

    // Receipt format tab switching
    $(document).on('click', '.receipt-tab', function() {
        const format = $(this).data('format');
        $('.receipt-tab').removeClass('active');
        $(this).addClass('active');

        if (format === 'a4') {
            $('#receipt-content-a4').show();
            $('#receipt-content-thermal').hide();
        } else {
            $('#receipt-content-a4').hide();
            $('#receipt-content-thermal').show();
        }
    });

    // Print A4 receipt
    $('#print-a4-receipt').on('click', function() {
        printReceipt('receipt-content-a4');
    });

    // Print thermal receipt
    $('#print-thermal-receipt').on('click', function() {
        printReceipt('receipt-content-thermal');
    });

    // Close receipt display
    $('#close-receipt').on('click', function() {
        $('#receipt-display').hide();
        $('#receipt-content-a4').empty();
        $('#receipt-content-thermal').empty();
    });
}

function searchPatients(query) {
    $.ajax({
        url: '{{ route("billing.search-patients") }}',
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

// Global banks cache
let availableBanks = [];

function loadBanks() {
    if (availableBanks.length > 0) {
        return; // Already loaded
    }

    $.ajax({
        url: '/banks/active',
        method: 'GET',
        success: function(response) {
            if (response.success && response.banks) {
                availableBanks = response.banks;
                populateBankDropdowns();
            }
        },
        error: function() {
            console.error('Failed to load banks');
        }
    });
}

function populateBankDropdowns() {
    const $paymentBank = $('#payment-bank');
    const $transactionBank = $('#transaction-bank');

    // Clear existing options except the placeholder
    $paymentBank.find('option:not(:first)').remove();
    $transactionBank.find('option:not(:first)').remove();

    // Populate with banks
    availableBanks.forEach(bank => {
        const optionText = bank.account_number ? `${bank.name} - ${bank.account_number}` : bank.name;
        const option = `<option value="${bank.id}">${optionText}</option>`;
        $paymentBank.append(option);
        $transactionBank.append(option);
    });
}

function generateReferenceNumber() {
    // Generate reference format: PAY-YYYYMMDD-HHMMSS
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    const reference = `PAY-${year}${month}${day}-${hours}${minutes}${seconds}`;
    $('#payment-reference').val(reference);
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
    console.log('loadPatient called with ID:', patientId);
    currentPatient = patientId;

    // Show loading state
    $('#empty-state').hide();
    $('#workspace-content').addClass('active');
    $('#patient-header').addClass('active');

    // Show loading indicator
    $('#patient-name').html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');
    $('#patient-meta').html('');
    $('#billing-items-tbody').html(`
        <tr>
            <td colspan="8" class="text-center text-muted py-5">
                <i class="mdi mdi-loading mdi-spin" style="font-size: 3rem;"></i>
                <p>Loading billing items...</p>
            </td>
        </tr>
    `);

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Load patient billing data
    $.ajax({
        url: `/billing-workbench/patient/${patientId}/billing-data`,
        method: 'GET',
        success: function(data) {
            console.log('Patient billing data loaded:', data);
            currentPatientData = data.patient;
            displayPatientInfo(data.patient);

            // Load billing items for the active Billing tab
            renderBillingItems(data.items);
            updateBillingBadge(data.items.length);

            // Load account balance
            loadAccountBalance(patientId);

            // Switch to billing tab by default
            switchWorkspaceTab('billing');
        },
        error: function(xhr) {
            console.error('Error loading patient:', xhr);
            toastr.error('Failed to load patient data');
        }
    });
}

function displayPatientInfo(patient) {
    $('#patient-name').text(patient.name);

    const metaHtml = `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details"></i>
            <span>File: ${patient.file_no}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-calendar"></i>
            <span>Age: ${patient.age}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-gender-${patient.gender === 'Male' ? 'male' : 'female'}"></i>
            <span>${patient.gender}</span>
        </div>
        ${patient.hmo_name ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-hospital-building"></i>
            <span>${patient.hmo_name}</span>
        </div>
        ` : ''}
        ${patient.hmo_no ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details-outline"></i>
            <span>HMO No: ${patient.hmo_no}</span>
        </div>
        ` : ''}
    `;

    $('#patient-meta').html(metaHtml);
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

function setDefaultReceiptDates() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    $('#receipts-from-date').val(formatDate(firstDay));
    $('#receipts-to-date').val(formatDate(lastDay));
}

function setDefaultReceiptDates() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    $('#receipts-from-date').val(formatDate(firstDay));
    $('#receipts-to-date').val(formatDate(lastDay));
}

function loadQueueCounts() {
    $.get('{{ route("billing.queue-counts") }}', function(counts) {
        $('#queue-all-count').text(counts.total || 0);
        $('#queue-hmo-count').text(counts.hmo || 0);
        $('#queue-credit-count').text(counts.credit || 0);
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

function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

    $('.workspace-tab-content').removeClass('active');
    $(`#${tab}-tab`).addClass('active');

    // Load tab-specific data
    if (!currentPatient) return;

    switch(tab) {
        case 'billing':
            loadBillingItems();
            break;
        case 'receipts':
            setDefaultReceiptDates();
            loadPatientReceipts();
            break;
        case 'account':
            loadAccountSummary();
            break;
    }
}

function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('clinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('📊 Clinical Context ×');
    }
}

// ========== ACCOUNT BALANCE FUNCTIONS ==========

let currentAccountBalance = 0;

function loadAccountBalance(patientId) {
    $.ajax({
        url: `/billing-workbench/patient/${patientId}/account-summary`,
        method: 'GET',
        success: function(data) {
            currentAccountBalance = parseFloat(data.balance) || 0;
            updateAccountBalanceDisplays(data);
        },
        error: function(xhr) {
            console.error('Failed to load account balance', xhr);
        }
    });
}

function updateAccountBalanceDisplays(accountData) {
    const balance = parseFloat(accountData.balance) || 0;
    const formattedBalance = `₦${Math.abs(balance).toLocaleString()}`;

    // Update patient header balance
    $('#header-balance-amount').text(formattedBalance);
    $('#patient-header-balance').show();

    // Update billing tab balance
    $('#billing-balance-amount').text(formattedBalance);

    // Always show billing account balance section and account payment option
    // (Credit facility: allow payments even with zero or negative balance)
    $('#billing-account-balance').show();
    $('#account-payment-option').show();

    // Add visual indicator for negative balance
    if (balance < 0) {
        $('#billing-balance-amount').addClass('text-danger').removeClass('text-success');
        $('#header-balance-amount').addClass('text-danger').removeClass('text-success');
    } else if (balance > 0) {
        $('#billing-balance-amount').addClass('text-success').removeClass('text-danger');
        $('#header-balance-amount').addClass('text-success').removeClass('text-danger');
    } else {
        $('#billing-balance-amount').removeClass('text-success text-danger');
        $('#header-balance-amount').removeClass('text-success text-danger');
    }

    // Update account tab with new modern UI
    if (accountData.account) {
        displayAccountInfo(accountData.account, accountData.unpaid_total);
        // Initialize filters to current month before loading transactions
        initAccountTxFilters();
        loadAccountTransactions();
    } else {
        showNoAccountState();
    }
}

function displayAccountInfo(account, pendingBills) {
    const balance = parseFloat(account.balance) || 0;
    const formattedBalance = `₦${Math.abs(balance).toLocaleString()}`;

    // Update hero balance section
    const heroBalance = $('#account-hero-balance');
    heroBalance.removeClass('credit debit');

    $('#hero-balance-amount').text(`₦${balance.toLocaleString()}`);

    if (balance > 0) {
        heroBalance.addClass('credit');
        $('#hero-balance-status').text('Credit Balance');
    } else if (balance < 0) {
        heroBalance.addClass('debit');
        $('#hero-balance-status').text(`Debit Balance`);
    } else {
        $('#hero-balance-status').text('Balanced');
    }

    // Update pending bills stat
    $('#pending-bills-stat').text(`₦${parseFloat(pendingBills || 0).toLocaleString()}`);

    // Show account UI, hide no-account state
    $('#account-hero-section').show();
    $('#account-transactions-section').show();
    $('#no-account-state').hide();
    $('#account-transaction-panel').hide();
}

function showNoAccountState() {
    $('#account-hero-section').hide();
    $('#account-transactions-section').hide();
    $('#no-account-state').show();
}

function loadAccountTransactions() {
    if (!currentPatient) return;

    const fromDate = $('#account-tx-from-date').val() || '';
    const toDate = $('#account-tx-to-date').val() || '';
    const txType = $('#account-tx-type-filter').val() || '';

    // Load account-specific transaction history
    $.ajax({
        url: `/billing-workbench/patient/${currentPatient}/account-transactions`,
        method: 'GET',
        data: {
            from_date: fromDate,
            to_date: toDate,
            tx_type: txType
        },
        success: function(response) {
            console.log('Account transactions response:', response);
            const transactions = response.transactions || [];
            const summary = response.summary || {};
            renderAccountTransactions(transactions);
            updateAccountStats(summary);
        },
        error: function(xhr) {
            console.error('Failed to load account transactions', xhr);
            $('#transaction-timeline').html(`
                <div class="timeline-empty-state">
                    <i class="mdi mdi-alert-circle"></i>
                    <p>Failed to load transactions</p>
                    <small>Please try refreshing</small>
                </div>
            `);
        }
    });
}

function updateAccountStats(summary) {
    $('#total-deposits-stat').text(`₦${parseFloat(summary.total_deposits || 0).toLocaleString()}`);
    $('#total-withdrawals-stat').text(`₦${parseFloat(summary.total_withdrawals || 0).toLocaleString()}`);
    $('#tx-count-stat').text(summary.transaction_count || 0);
}

function renderAccountTransactions(transactions) {
    console.log('renderAccountTransactions called with:', transactions);
    const timeline = $('#transaction-timeline');
    console.log('Timeline element found:', timeline.length > 0);
    timeline.empty();

    if (!transactions || transactions.length === 0) {
        console.log('No transactions to render');
        timeline.html(`
            <div class="timeline-empty-state">
                <i class="mdi mdi-swap-horizontal"></i>
                <p>No account transactions yet</p>
                <small>Deposits and withdrawals will appear here</small>
            </div>
        `);
        return;
    }

    console.log('Rendering', transactions.length, 'transactions');
    transactions.forEach((tx, index) => {
        const amountClass = parseFloat(tx.amount) >= 0 ? 'positive' : 'negative';
        const amountPrefix = parseFloat(tx.amount) >= 0 ? '+' : '';

        const item = `
            <div class="timeline-item">
                <div class="timeline-icon ${tx.tx_color}">
                    <i class="mdi ${tx.tx_icon}"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-type">${tx.tx_type}</span>
                        <span class="timeline-amount ${amountClass}">${amountPrefix}₦${Math.abs(parseFloat(tx.amount)).toLocaleString()}</span>
                    </div>
                    <div class="timeline-meta">
                        <span><i class="mdi mdi-calendar"></i> ${tx.created_at}</span>
                        <span><i class="mdi mdi-clock"></i> ${tx.created_time}</span>
                        <span><i class="mdi mdi-account"></i> ${tx.cashier}</span>
                    </div>
                    ${tx.description ? `<div class="timeline-description">${tx.description}</div>` : ''}
                    <span class="timeline-balance">Balance after: ₦${parseFloat(tx.running_balance).toLocaleString()}</span>
                </div>
            </div>
        `;
        console.log('Appending item', index, 'to timeline');
        timeline.append(item);
    });
    console.log('Timeline HTML after render:', timeline.html().substring(0, 200));
}

// Account Tab Event Handlers - Transaction Panel
let currentTransactionType = 'deposit';

function openTransactionPanel(type) {
    currentTransactionType = type;
    const panel = $('#account-transaction-panel');
    const icon = $('#transaction-panel-icon');
    const title = $('#transaction-panel-title');
    const submitBtn = $('#transaction-submit-btn');
    const submitText = $('#transaction-submit-text');
    const amountHelp = $('#transaction-amount-help');
    const changeLabel = $('#preview-change-label');

    // Reset form
    $('#account-transaction-form')[0].reset();
    $('#transaction-type').val(type);

    // Update panel styling based on type
    panel.removeClass('deposit withdraw adjust');
    panel.addClass(type);

    // Get current balance for preview
    const balanceText = $('#hero-balance-amount').text().replace('₦', '').replace(/,/g, '');
    currentAccountBalance = parseFloat(balanceText) || 0;
    $('#preview-current-balance').text(`₦${currentAccountBalance.toLocaleString()}`);
    updateBalancePreview();

    // Show/hide payment method based on transaction type
    if (type === 'adjust') {
        // Hide payment method for adjustments
        $('#transaction-payment-method-group').hide();
        $('#transaction-bank-group').hide();
    } else {
        // Show payment method for deposits and withdrawals
        $('#transaction-payment-method-group').show();
        // Reset bank visibility based on current payment method
        const payMethod = $('#transaction-payment-method').val();
        if (['POS', 'TRANSFER', 'MOBILE'].includes(payMethod)) {
            $('#transaction-bank-group').show();
        } else {
            $('#transaction-bank-group').hide();
        }
    }

    if (type === 'deposit') {
        icon.attr('class', 'mdi mdi-plus-circle');
        title.text('Make Deposit');
        submitText.text('Confirm Deposit');
        amountHelp.text('Enter amount to add to account');
        changeLabel.text('After Deposit:');
        $('#transaction-description').removeAttr('required');
        $('#transaction-amount').attr('min', '0.01');
    } else if (type === 'withdraw') {
        icon.attr('class', 'mdi mdi-minus-circle');
        title.text('Make Withdrawal');
        submitText.text('Confirm Withdrawal');
        amountHelp.text('Enter amount to withdraw from account');
        changeLabel.text('After Withdrawal:');
        $('#transaction-description').removeAttr('required');
        $('#transaction-amount').attr('min', '0.01');
    } else if (type === 'adjust') {
        icon.attr('class', 'mdi mdi-swap-horizontal');
        title.text('Account Adjustment');
        submitText.text('Confirm Adjustment');
        amountHelp.text('Enter positive to credit, negative to debit');
        changeLabel.text('After Adjustment:');
        $('#transaction-description').attr('required', 'required');
        $('#transaction-amount').removeAttr('min');
    }

    panel.slideDown();
    $('#transaction-amount').focus();
}

// Transaction payment method change handler
$(document).on('change', '#transaction-payment-method', function() {
    const method = $(this).val();
    if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
        $('#transaction-bank-group').show();
    } else {
        $('#transaction-bank-group').hide();
        $('#transaction-bank').val('');
    }
});

function updateBalancePreview() {
    const amount = parseFloat($('#transaction-amount').val()) || 0;
    let newBalance = currentAccountBalance;

    if (currentTransactionType === 'deposit') {
        newBalance = currentAccountBalance + amount;
    } else if (currentTransactionType === 'withdraw') {
        newBalance = currentAccountBalance - amount;
    } else if (currentTransactionType === 'adjust') {
        newBalance = currentAccountBalance + amount; // Adjustment can be +/-
    }

    const previewElement = $('#preview-new-balance');
    previewElement.text(`₦${newBalance.toLocaleString()}`);
    previewElement.removeClass('positive negative');

    if (newBalance > 0) {
        previewElement.addClass('positive');
    } else if (newBalance < 0) {
        previewElement.addClass('negative');
    }
}

$(document).on('click', '#quick-deposit-btn', function() {
    openTransactionPanel('deposit');
});

$(document).on('click', '#quick-withdraw-btn', function() {
    openTransactionPanel('withdraw');
});

$(document).on('click', '#quick-adjust-btn', function() {
    openTransactionPanel('adjust');
});

$(document).on('click', '#close-transaction-panel', function() {
    $('#account-transaction-panel').slideUp();
});

$(document).on('input', '#transaction-amount', function() {
    updateBalancePreview();
});

$(document).on('submit', '#account-transaction-form', function(e) {
    e.preventDefault();
    processAccountTransaction();
});

function processAccountTransaction() {
    if (!currentPatientData) return;

    const type = $('#transaction-type').val();
    const amountInput = $('#transaction-amount').val();
    const amount = parseFloat(amountInput);
    const description = $('#transaction-description').val();
    const paymentMethod = $('#transaction-payment-method').val();
    const bankId = $('#transaction-bank').val();

    console.log('Processing transaction:', { type, amountInput, amount, description, paymentMethod, bankId });

    // For adjustments, allow any non-zero value (positive or negative)
    // For deposit/withdraw, require positive values
    if (type === 'adjust') {
        if (isNaN(amount) || amount === 0) {
            toastr.warning('Please enter a valid non-zero amount (positive to credit, negative to debit)');
            return;
        }
    } else {
        if (isNaN(amount) || amount <= 0) {
            toastr.warning('Please enter a valid positive amount');
            return;
        }
    }

    if (type === 'adjust' && !description) {
        toastr.warning('Description is required for adjustments');
        return;
    }

    // Validate bank selection for non-cash payments (except adjustments)
    if (type !== 'adjust' && ['POS', 'TRANSFER', 'MOBILE'].includes(paymentMethod) && !bankId) {
        toastr.warning('Please select a bank for this payment method');
        return;
    }

    // Check if withdraw amount exceeds balance
    if (type === 'withdraw' && amount > currentAccountBalance) {
        if (!confirm(`Warning: This withdrawal (₦${amount.toLocaleString()}) exceeds the current balance (₦${currentAccountBalance.toLocaleString()}). Continue anyway?`)) {
            return;
        }
    }

    let confirmMsg = '';
    if (type === 'deposit') {
        confirmMsg = `Deposit ₦${amount.toLocaleString()} to this account?`;
    } else if (type === 'withdraw') {
        confirmMsg = `Withdraw ₦${amount.toLocaleString()} from this account?`;
    } else {
        confirmMsg = `Apply adjustment of ₦${amount.toLocaleString()} to this account?`;
    }

    if (!confirm(confirmMsg)) return;

    $.ajax({
        url: '/billing-workbench/account-transaction',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatientData.id,
            transaction_type: type,
            amount: amount,
            description: description,
            payment_method: type !== 'adjust' ? paymentMethod : null,
            bank_id: (type !== 'adjust' && bankId) ? bankId : null
        },
        success: function(response) {
            toastr.success(response.message || 'Transaction saved successfully!');

            // Close panel and reset form
            $('#account-transaction-panel').slideUp();
            $('#account-transaction-form')[0].reset();

            // Refresh all account data
            loadAccountBalance(currentPatient);
            loadAccountSummary();
            loadAccountTransactions();

            // If on receipts tab, refresh receipts
            if ($('#receipts-tab').hasClass('active')) {
                loadPatientReceipts();
            }

            // Show deposit receipt if available
            if (type === 'deposit' && response.receipt_a4 && response.receipt_thermal) {
                $('#modal-receipt-a4').html(response.receipt_a4);
                $('#modal-receipt-thermal').html(response.receipt_thermal);

                // Reset tabs to A4
                $('.receipt-modal-tab').removeClass('active');
                $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                $('#modal-receipt-a4').show();
                $('#modal-receipt-thermal').hide();

                // Show modal
                $('#receiptPreviewModal').modal('show');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to save transaction');
        }
    });
}

$(document).on('click', '#filter-account-tx', function() {
    loadAccountTransactions();
});

$(document).on('click', '#refresh-account-data', function() {
    loadAccountSummary();
    loadAccountTransactions();
    toastr.info('Refreshing account data...');
});

// Set default dates for account transactions filter
function initAccountTxFilters() {
    const today = new Date().toISOString().split('T')[0];
    const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    $('#account-tx-from-date').val(firstDay);
    $('#account-tx-to-date').val(today);
}

// Initialize when account tab is shown
$(document).on('shown.bs.tab', 'a[href="#account-tab"]', function() {
    initAccountTxFilters();
    if (currentPatient) {
        loadAccountTransactions();
    }
});

// Also call on workspace tab click
$(document).on('click', '.workspace-tab[data-tab="account-tab"]', function() {
    setTimeout(() => {
        initAccountTxFilters();
        if (currentPatient) {
            loadAccountTransactions();
        }
    }, 100);
});

function filterReceipts() {
    if (!currentPatient) return;

    const fromDate = $('#receipts-from-date').val() || '';
    const toDate = $('#receipts-to-date').val() || '';
    const paymentType = $('#receipts-payment-type').val() || '';

    const params = {};
    if (fromDate) params.from_date = fromDate;
    if (toDate) params.to_date = toDate;
    if (paymentType) params.payment_type = paymentType;

    $.ajax({
        url: `/billing-workbench/patient/${currentPatient}/receipts`,
        method: 'GET',
        data: params,
        success: function(data) {
            renderReceipts(data.receipts);
            updateReceiptsStats(data.stats);
        },
        error: function(xhr) {
            toastr.error('Failed to filter receipts');
        }
    });
}

function renderReceipts(receipts) {
    const tbody = $('#receipts-tbody');
    tbody.empty();

    if (receipts.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    <i class="mdi mdi-receipt" style="font-size: 3rem;"></i>
                    <p>No receipts found</p>
                </td>
            </tr>
        `);
        return;
    }

    receipts.forEach(receipt => {
        // Handle different possible field names from backend
        const isDeposit = receipt.source === 'deposit';
        const recordId = receipt.id || (isDeposit ? receipt.deposit_id : receipt.payment_id);
        const referenceNo = receipt.reference_no || receipt.reference_number || 'N/A';
        const dateValue = receipt.created_at || receipt.date || receipt.payment_date;
        const itemCount = receipt.item_count || receipt.items_count || 0;
        const total = parseFloat(receipt.total || 0);
        const discount = parseFloat(receipt.total_discount || receipt.discount || 0);
        const paymentType = receipt.payment_type_label || receipt.payment_type || 'N/A';
        const cashier = receipt.created_by || receipt.cashier || 'N/A';

        // Badge color based on type
        let typeBadge = '';
        if (isDeposit) {
            typeBadge = `<span class="badge badge-success">${paymentType}</span>`;
        } else {
            typeBadge = `<span class="badge badge-primary">${paymentType}</span>`;
        }

        // Reprint button with proper data attributes
        const reprintBtn = isDeposit
            ? `<button class="btn btn-sm btn-success reprint-deposit-receipt" data-deposit-id="${receipt.deposit_id}">
                   <i class="mdi mdi-printer"></i> Print
               </button>`
            : `<button class="btn btn-sm btn-primary reprint-receipt" data-id="${receipt.payment_id}">
                   <i class="mdi mdi-printer"></i> Reprint
               </button>`;

        // For deposits, show items as "Deposit" indicator
        const itemsDisplay = isDeposit
            ? `<span class="text-success"><i class="mdi mdi-arrow-down"></i> Deposit</span>`
            : `${itemCount} item(s)`;

        const row = `
            <tr class="${isDeposit ? 'table-success-light' : ''}">
                <td><input type="checkbox" class="receipt-checkbox" data-id="${recordId}" data-source="${receipt.source || 'payment'}"></td>
                <td>${referenceNo}</td>
                <td>${dateValue}</td>
                <td>${itemsDisplay}</td>
                <td>₦${total.toLocaleString()}</td>
                <td>₦${discount.toLocaleString()}</td>
                <td>${typeBadge}</td>
                <td>${cashier}</td>
                <td>${reprintBtn}</td>
            </tr>
        `;
        tbody.append(row);
    });

    // Reset select all checkbox
    $('#select-all-receipts').prop('checked', false);

    // Update print selected button state
    updatePrintSelectedButton();
}

function updateReceiptsStats(stats) {
    if (stats) {
        $('#receipts-total-count').text(stats.count || 0);
        $('#receipts-total-amount').text(`₦${parseFloat(stats.total || 0).toLocaleString()}`);
        $('#receipts-total-discounts').text(`₦${parseFloat(stats.discounts || 0).toLocaleString()}`);
        $('#receipts-summary').show();
    }
}

function exportReceipts() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    const fromDate = $('#receipts-from-date').val() || '';
    const toDate = $('#receipts-to-date').val() || '';
    const paymentType = $('#receipts-payment-type').val() || '';

    const params = new URLSearchParams();
    if (fromDate) params.append('from_date', fromDate);
    if (toDate) params.append('to_date', toDate);
    if (paymentType) params.append('payment_type', paymentType);

    const url = `/billing-workbench/patient/${currentPatient}/receipts/export?${params.toString()}`;
    window.open(url, '_blank');
}

function createPatientAccount() {
    if (!currentPatientData) return;

    if (!confirm('Create a new account for this patient?')) return;

    $.ajax({
        url: '/billing-workbench/create-account',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatientData.id
        },
        success: function(response) {
            toastr.success(response.message || 'Account created successfully!');

            // Update patient data with new account info
            if (response.account) {
                currentPatientData.account_id = response.account.id;
            }

            // Reload account balance and all displays
            loadAccountBalance(currentPatient);

            // Show account UI state
            $('#no-account-state').hide();
            $('#account-hero-section').show();
            $('#account-transactions-section').show();

            // Reload account summary to show full data
            loadAccountSummary();

            // Load account transactions
            loadAccountTransactions();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create account');
        }
    });
}

// ========== BILLING WORKBENCH FUNCTIONS ==========

function loadBillingItems() {
    if (!currentPatient) return;

    $.ajax({
        url: `/billing-workbench/patient/${currentPatient}/billing-data`,
        method: 'GET',
        success: function(response) {
            renderBillingItems(response.items);
            updateBillingBadge(response.items.length);
        },
        error: function(xhr) {
            console.error('Failed to load billing items', xhr);
            toastr.error('Failed to load billing items');
        }
    });
}

function renderBillingItems(items) {
    console.log('renderBillingItems called with:', items);

    const tbody = $('#billing-items-tbody');
    tbody.empty();

    if (items.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                    <p>No unpaid items for this patient</p>
                </td>
            </tr>
        `);
        return;
    }

    items.forEach(item => {
        const row = `
            <tr data-item-id="${item.id}">
                <td><input type="checkbox" class="billing-item-checkbox" data-id="${item.id}"></td>
                <td>${item.name}</td>
                <td>${item.category || 'N/A'}</td>
                <td>₦${parseFloat(item.price).toLocaleString()}</td>
                <td><input type="number" class="form-control item-qty-input" value="${item.qty}" min="1" data-id="${item.id}"></td>
                <td><input type="number" class="form-control item-discount-input" value="${item.discount || 0}" min="0" max="100" data-id="${item.id}"></td>
                <td>${item.claims_amount > 0 ? `<span class="hmo-badge">₦${parseFloat(item.claims_amount).toLocaleString()}</span>` : '-'}</td>
                <td class="item-total" data-id="${item.id}">₦${calculateItemTotal(item).toLocaleString()}</td>
            </tr>
        `;
        tbody.append(row);
    });

    // Attach event listeners
    $('.billing-item-checkbox').on('change', updatePaymentSummary);
    $('#select-all-billing-items').on('change', function() {
        $('.billing-item-checkbox').prop('checked', $(this).is(':checked'));
        updatePaymentSummary();
    });
    $('.item-qty-input, .item-discount-input').on('input', function() {
        const id = $(this).data('id');
        recalculateItemTotal(id);
        updatePaymentSummary();
    });

    console.log('Rendered', items.length, 'billing items');
}

function calculateItemTotal(item) {
    const qty = parseFloat(item.qty) || 1;
    const price = parseFloat(item.price) || 0;
    const discount = parseFloat(item.discount) || 0;
    const subtotal = price * qty;
    const discountAmount = subtotal * (discount / 100);
    return subtotal - discountAmount;
}

function recalculateItemTotal(itemId) {
    const row = $(`tr[data-item-id="${itemId}"]`);
    const qty = parseFloat(row.find('.item-qty-input').val()) || 1;
    const price = parseFloat(row.find('td:eq(3)').text().replace('₦', '').replace(/,/g, ''));
    const discount = parseFloat(row.find('.item-discount-input').val()) || 0;

    const subtotal = price * qty;
    const discountAmount = subtotal * (discount / 100);
    const total = subtotal - discountAmount;

    row.find('.item-total').text(`₦${total.toLocaleString()}`);
}

function updatePaymentSummary() {
    const selectedItems = $('.billing-item-checkbox:checked');

    if (selectedItems.length === 0) {
        $('#payment-summary-card').hide();
        $('#process-payment-btn').prop('disabled', true);
        return;
    }

    let subtotal = 0;
    let totalDiscount = 0;

    selectedItems.each(function() {
        const row = $(this).closest('tr');
        const qty = parseFloat(row.find('.item-qty-input').val()) || 1;
        const price = parseFloat(row.find('td:eq(3)').text().replace('₦', '').replace(/,/g, ''));
        const discountPercent = parseFloat(row.find('.item-discount-input').val()) || 0;

        const itemSubtotal = price * qty;
        const itemDiscount = itemSubtotal * (discountPercent / 100);

        subtotal += itemSubtotal;
        totalDiscount += itemDiscount;
    });

    const total = subtotal - totalDiscount;

    $('#summary-subtotal').text(`₦${subtotal.toLocaleString()}`);
    $('#summary-discount').text(`₦${totalDiscount.toLocaleString()}`);
    $('#summary-total').text(`₦${total.toLocaleString()}`);

    $('#payment-summary-card').show();
    $('#process-payment-btn').prop('disabled', false);
}

// Process payment button click
$(document).on('click', '#process-payment-btn, #confirm-payment-btn', function() {
    processPayment();
});

function processPayment() {
    const selectedItems = $('.billing-item-checkbox:checked');

    if (selectedItems.length === 0) {
        toastr.warning('Please select items to process payment');
        return;
    }

    const items = [];
    selectedItems.each(function() {
        const row = $(this).closest('tr');
        items.push({
            id: $(this).data('id'),
            qty: parseFloat(row.find('.item-qty-input').val()) || 1,
            discount: parseFloat(row.find('.item-discount-input').val()) || 0
        });
    });

    const paymentType = $('#payment-method').val();
    const referenceNo = $('#payment-reference').val();
    const bankId = $('#payment-bank').val();
    const totalPayable = parseFloat($('#summary-total').text().replace('₦', '').replace(/,/g, ''));

    // Validate bank selection for non-cash payments
    if (['POS', 'TRANSFER', 'MOBILE'].includes(paymentType) && !bankId) {
        toastr.warning('Please select a bank for this payment method');
        return;
    }

    // Validate account balance payment (Credit facility: allow negative balance with warning)
    if (paymentType === 'ACCOUNT') {
        const balanceAfter = currentAccountBalance - totalPayable;

        if (totalPayable > currentAccountBalance) {
            // Show warning for credit/negative balance
            const warningMsg = currentAccountBalance >= 0
                ? `This payment of ₦${totalPayable.toLocaleString()} exceeds the available balance of ₦${currentAccountBalance.toLocaleString()}.\n\nBalance after payment: ₦${balanceAfter.toLocaleString()} (CREDIT/DEBIT)\n\nDo you want to proceed with credit facility?`
                : `Current balance is already ₦${currentAccountBalance.toLocaleString()} (debit).\n\nThis payment will increase the debit to ₦${balanceAfter.toLocaleString()}.\n\nDo you want to proceed?`;

            if (!confirm(warningMsg)) {
                return;
            }
        } else {
            // Normal deduction - show confirmation
            if (!confirm(`Deduct ₦${totalPayable.toLocaleString()} from account balance?\n\nBalance after: ₦${balanceAfter.toLocaleString()}`)) {
                return;
            }
        }
    }

    // Show loading state
    const $confirmBtn = $('#confirm-payment-btn');
    const originalText = $confirmBtn.html();
    $confirmBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing Payment...');

    $.ajax({
        url: '/billing-workbench/process-payment',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            payment_type: paymentType,
            payment_method: paymentType,
            bank_id: bankId || null,
            reference_no: referenceNo,
            items: items
        },
        success: function(response) {
            // Reset button state
            $confirmBtn.prop('disabled', false).html(originalText);

            toastr.success('Payment processed successfully!');

            // Generate new reference number for next payment
            generateReferenceNumber();

            // Display receipt in modal
            $('#modal-receipt-a4').html(response.receipt_a4);
            $('#modal-receipt-thermal').html(response.receipt_thermal);

            // Reset tabs to A4
            $('.receipt-modal-tab').removeClass('active');
            $('.receipt-modal-tab[data-format="a4"]').addClass('active');
            $('#modal-receipt-a4').show();
            $('#modal-receipt-thermal').hide();

            // Show modal
            $('#receiptPreviewModal').modal('show');

            $('#payment-summary-card').hide();

            // Clear all billing selections and reset summary
            $('.billing-item-checkbox').prop('checked', false);
            $('#select-all-items').prop('checked', false);
            $('#summary-subtotal').text('₦0.00');
            $('#summary-discount').text('₦0.00');
            $('#summary-total').text('₦0.00');

            // Reload billing items
            loadBillingItems();

            // Reload account balance to reflect payment deduction
            loadAccountBalance(currentPatient);

            // Refresh receipts to show new payment
            loadPatientReceipts();

            // If account tab is active, reload it
            if ($('#account-tab').hasClass('active')) {
                loadAccountSummary();
            }

            // Update queue counts
            loadQueueCounts();
        },
        error: function(xhr) {
            // Reset button state on error
            $confirmBtn.prop('disabled', false).html(originalText);

            toastr.error(xhr.responseJSON?.message || 'Payment processing failed');
        }
    });
}

$(document).on('click', '#print-thermal-receipt', function() {
    printReceipt('receipt-content-thermal');
});

$(document).on('click', '#close-receipt', function() {
    $('#receipt-display').hide();
    $('#receipt-content-a4').empty();
    $('#receipt-content-thermal').empty();
    $('#payment-summary-card').show();
});

function printReceipt(elementId) {
    const content = $(`#${elementId}`).html();
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Receipt</title>');
    printWindow.document.write('<style>body{font-family: Arial, sans-serif; padding: 20px;} table{width: 100%; border-collapse: collapse;} th, td{padding: 8px; text-align: left; border-bottom: 1px solid #ddd;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

function loadPatientReceipts() {
    if (!currentPatient) return;

    $.ajax({
        url: `/billing-workbench/patient/${currentPatient}/receipts`,
        method: 'GET',
        success: function(response) {
            renderReceipts(response.receipts);
            if (response.stats) {
                updateReceiptsStats(response.stats);
            }
        },
        error: function(xhr) {
            console.error('Failed to load receipts', xhr);
            toastr.error('Failed to load receipts');
        }
    });
}

function printDepositReceiptFromList(depositId) {
    if (!depositId) {
        toastr.warning('Invalid deposit ID');
        return;
    }

    toastr.info('Generating deposit receipt...');

    $.ajax({
        url: `/billing-workbench/print-deposit-receipt/${depositId}`,
        method: 'GET',
        success: function(response) {
            if (response.receipt_a4 && response.receipt_thermal) {
                $('#modal-receipt-a4').html(response.receipt_a4);
                $('#modal-receipt-thermal').html(response.receipt_thermal);

                // Reset tabs to A4
                $('.receipt-modal-tab').removeClass('active');
                $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                $('#modal-receipt-a4').show();
                $('#modal-receipt-thermal').hide();

                // Show modal
                $('#receiptPreviewModal').modal('show');
            } else {
                toastr.error('Failed to generate deposit receipt');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to generate deposit receipt');
        }
    });
}

function updatePrintSelectedButton() {
    const selected = $('.receipt-checkbox:checked').length;
    $('#print-selected-receipts').prop('disabled', selected === 0);
}

$(document).on('click', '#print-selected-receipts', function() {
    const paymentIds = [];
    $('.receipt-checkbox:checked').each(function() {
        paymentIds.push($(this).data('id'));
    });
    reprintReceipt(paymentIds);
});

function reprintReceipt(paymentIds) {
    if (!paymentIds || paymentIds.length === 0) {
        toastr.warning('Please select receipts to print');
        return;
    }

    // Show loading state
    toastr.info('Generating receipt...');

    $.ajax({
        url: '/billing-workbench/print-receipt',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            payment_ids: paymentIds
        },
        success: function(response) {
            // Show in modal
            $('#modal-receipt-a4').html(response.receipt_a4);
            $('#modal-receipt-thermal').html(response.receipt_thermal);

            // Reset tabs to A4
            $('.receipt-modal-tab').removeClass('active');
            $('.receipt-modal-tab[data-format="a4"]').addClass('active');
            $('#modal-receipt-a4').show();
            $('#modal-receipt-thermal').hide();

            // Show modal
            $('#receiptPreviewModal').modal('show');
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to generate receipt');
        }
    });
}

// Receipt Modal Tab Switching
$(document).on('click', '.receipt-modal-tab', function() {
    const format = $(this).data('format');

    $('.receipt-modal-tab').removeClass('active');
    $(this).addClass('active');

    if (format === 'a4') {
        $('#modal-receipt-a4').show();
        $('#modal-receipt-thermal').hide();
    } else {
        $('#modal-receipt-a4').hide();
        $('#modal-receipt-thermal').show();
    }
});

// Modal Print Buttons
$(document).on('click', '#modal-print-a4', function() {
    printReceiptContent('modal-receipt-a4');
});

$(document).on('click', '#modal-print-thermal', function() {
    printReceiptContent('modal-receipt-thermal');
});

function printReceiptContent(elementId) {
    const content = $(`#${elementId}`).html();
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Receipt</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
    printWindow.document.write('th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }');
    printWindow.document.write('.text-center { text-align: center; }');
    printWindow.document.write('.text-right { text-align: right; }');
    printWindow.document.write('.font-weight-bold { font-weight: bold; }');
    printWindow.document.write('@media print { body { padding: 0; } }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

// ==========================================
// ACCOUNT STATEMENT FUNCTIONALITY
// ==========================================

// Store generated statement content
let generatedStatementA4 = null;
let generatedStatementThermal = null;

// Print Statement Button Click
$(document).on('click', '#print-statement-btn', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Reset the modal to config view
    resetStatementModal();

    // Set default dates (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));

    $('#statement-date-to').val(today.toISOString().split('T')[0]);
    $('#statement-date-from').val(thirtyDaysAgo.toISOString().split('T')[0]);

    // Show modal
    $('#accountStatementModal').modal('show');
});

// Reset modal to initial state
function resetStatementModal() {
    $('#statement-config-panel').show();
    $('#statement-preview-panel').hide();
    $('#statement-modal-footer').hide();
    generatedStatementA4 = null;
    generatedStatementThermal = null;

    // Reset checkboxes to checked
    $('#include-deposits').prop('checked', true);
    $('#include-payments').prop('checked', true);
    $('#include-withdrawals').prop('checked', true);
    $('#include-services').prop('checked', true);
}

// Date preset buttons
$(document).on('click', '.statement-date-presets button', function() {
    const preset = $(this).data('preset');
    const today = new Date();
    let fromDate = new Date();

    switch(preset) {
        case '7days':
            fromDate.setDate(today.getDate() - 7);
            break;
        case '30days':
            fromDate.setDate(today.getDate() - 30);
            break;
        case 'thisMonth':
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            break;
        case 'lastMonth':
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            today.setDate(0); // Last day of previous month
            break;
        case 'thisYear':
            fromDate = new Date(today.getFullYear(), 0, 1);
            break;
        case 'all':
            fromDate = new Date(2000, 0, 1);
            break;
    }

    $('#statement-date-from').val(fromDate.toISOString().split('T')[0]);
    $('#statement-date-to').val(preset === 'lastMonth'
        ? new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0]
        : new Date().toISOString().split('T')[0]);

    // Highlight active preset
    $('.statement-date-presets button').removeClass('btn-secondary').addClass('btn-outline-secondary');
    $(this).removeClass('btn-outline-secondary').addClass('btn-secondary');
});

// Generate Statement Button
$(document).on('click', '#generate-statement-btn', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    const dateFrom = $('#statement-date-from').val();
    const dateTo = $('#statement-date-to').val();

    if (!dateFrom || !dateTo) {
        toastr.warning('Please select a date range');
        return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Generating...');

    $.ajax({
        url: `/billing-workbench/patient/${currentPatient}/generate-statement`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            date_from: dateFrom,
            date_to: dateTo,
            include_deposits: $('#include-deposits').is(':checked'),
            include_payments: $('#include-payments').is(':checked'),
            include_withdrawals: $('#include-withdrawals').is(':checked'),
            include_services: $('#include-services').is(':checked')
        },
        success: function(response) {
            if (response.success) {
                // Store generated content
                generatedStatementA4 = response.statement_a4;
                generatedStatementThermal = response.statement_thermal;

                // Update preview panes
                $('#statement-pane-a4').html(response.statement_a4);
                $('#statement-pane-thermal').html(response.statement_thermal);

                // Switch to preview panel
                $('#statement-config-panel').hide();
                $('#statement-preview-panel').show();
                $('#statement-modal-footer').show();

                // Reset tabs to A4
                $('.statement-modal-tab').removeClass('active');
                $('.statement-modal-tab[data-format="a4"]').addClass('active');
                $('#statement-pane-a4').addClass('active').show();
                $('#statement-pane-thermal').removeClass('active').hide();

                toastr.success(`Statement generated with ${response.transaction_count} transactions`);
            } else {
                toastr.error(response.message || 'Failed to generate statement');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to generate statement');
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="mdi mdi-file-document"></i> Generate Statement');
        }
    });
});

// Statement Modal Tab Switching
$(document).on('click', '.statement-modal-tab', function() {
    const format = $(this).data('format');

    // Handle config tab
    if (format === 'config') {
        $('#statement-config-panel').show();
        $('#statement-preview-panel').hide();
        $('#statement-modal-footer').hide();
        return;
    }

    $('.statement-modal-tab').removeClass('active');
    $(this).addClass('active');

    $('.statement-modal-pane').removeClass('active').hide();
    $(`#statement-pane-${format}`).addClass('active').show();
});

// Back to Options Button
$(document).on('click', '#statement-back-btn', function() {
    $('#statement-config-panel').show();
    $('#statement-preview-panel').hide();
    $('#statement-modal-footer').hide();
});

// Statement Print Buttons
$(document).on('click', '#statement-print-a4', function() {
    printStatementContent('statement-pane-a4');
});

$(document).on('click', '#statement-print-thermal', function() {
    printStatementContent('statement-pane-thermal');
});

function printStatementContent(elementId) {
    const content = $(`#${elementId}`).html();
    const printWindow = window.open('', '', 'height=700,width=900');
    printWindow.document.write('<html><head><title>Account Statement</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; padding: 15px; margin: 0; font-size: 12px; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
    printWindow.document.write('th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }');
    printWindow.document.write('th { background: #f5f5f5; font-weight: bold; }');
    printWindow.document.write('.text-center { text-align: center; }');
    printWindow.document.write('.text-right { text-align: right; }');
    printWindow.document.write('.font-weight-bold { font-weight: bold; }');
    printWindow.document.write('.summary-card { display: inline-block; padding: 10px; margin: 5px; border: 1px solid #ddd; border-radius: 5px; }');
    printWindow.document.write('.type-badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }');
    printWindow.document.write('.credit { color: #28a745; }');
    printWindow.document.write('.debit { color: #dc3545; }');
    printWindow.document.write('@media print { body { padding: 5px; } @page { margin: 0.5cm; } }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
    }, 300);
}

// Account Tab
function loadAccountSummary() {
    if (!currentPatient) return;

    $.ajax({
        url: `/billing-workbench/patient/${currentPatient}/account-summary`,
        method: 'GET',
        success: function(response) {
            renderAccountSummary(response);
        },
        error: function(xhr) {
            toastr.error('Failed to load account summary');
        }
    });
}

function renderAccountSummary(data) {
    const balance = parseFloat(data.balance);

    // Update hero balance in new UI
    const heroBalance = $('#account-hero-balance');
    heroBalance.removeClass('credit debit');

    $('#hero-balance-amount').text(`₦${balance.toLocaleString()}`);

    if (balance > 0) {
        heroBalance.addClass('credit');
        $('#hero-balance-status').text('Credit Balance');
    } else if (balance < 0) {
        heroBalance.addClass('debit');
        $('#hero-balance-status').text('Debit Balance');
    } else {
        $('#hero-balance-status').text('Balanced');
    }

    // Update pending bills stat
    $('#pending-bills-stat').text(`₦${parseFloat(data.unpaid_total || 0).toLocaleString()}`);

    // Also update the account tab cards with new modern UI
    if (data.account) {
        displayAccountInfo(data.account, data.unpaid_total);
    } else {
        showNoAccountState();
    }
}

// My Transactions Modal
$(document).on('click', '#btn-my-transactions', function() {
    $('#myTransactionsModal').modal('show');
    // Set default dates to today
    const today = new Date().toISOString().split('T')[0];
    $('#my-trans-from-date').val(today);
    $('#my-trans-to-date').val(today);

    // Populate bank dropdown
    populateMyTransactionsBankDropdown();
});

function populateMyTransactionsBankDropdown() {
    const $bankSelect = $('#my-trans-bank');
    $bankSelect.find('option:not(:first)').remove();

    if (availableBanks.length > 0) {
        availableBanks.forEach(bank => {
            $bankSelect.append(`<option value="${bank.id}">${bank.name}</option>`);
        });
    }
}

$(document).on('click', '#load-my-transactions', function() {
    const fromDate = $('#my-trans-from-date').val();
    const toDate = $('#my-trans-to-date').val();
    const paymentType = $('#my-trans-payment-type').val();
    const bankId = $('#my-trans-bank').val();

    loadMyTransactions(fromDate, toDate, paymentType, bankId);
});

// Print My Transactions
$(document).on('click', '#print-my-transactions', function() {
    const printContent = document.getElementById('my-transactions-modal-body').innerHTML;
    const fromDate = $('#my-trans-from-date').val();
    const toDate = $('#my-trans-to-date').val();

    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>My Transactions Report</title>
            <link rel="stylesheet" href="${window.location.origin}/assets/css/bootstrap.min.css">
            <link rel="stylesheet" href="${window.location.origin}/assets/css/style.css">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background: #fff;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #dee2e6;
                }
                .print-header h2 {
                    margin-bottom: 5px;
                    color: #333;
                }
                .date-range {
                    color: #666;
                    margin-bottom: 0;
                    font-size: 0.9rem;
                }
                .print-date {
                    font-size: 0.8rem;
                    color: #888;
                }
                .table {
                    width: 100%;
                    margin-top: 15px;
                }
                .table th {
                    background-color: #f8f9fa;
                    font-weight: 600;
                    border-top: 2px solid #dee2e6;
                }
                .table td, .table th {
                    padding: 0.5rem;
                    font-size: 0.85rem;
                }
                .my-transactions-filter { display: none !important; }
                .summary-section {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .summary-stat-card {
                    display: inline-block;
                    padding: 12px 20px;
                    margin: 5px;
                    background: #fff;
                    border-radius: 8px;
                    text-align: center;
                    min-width: 140px;
                    border: 1px solid #dee2e6;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                .stat-value {
                    font-size: 1.25rem;
                    font-weight: bold;
                    color: #333;
                    display: block;
                }
                .stat-label {
                    font-size: 0.75rem;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .payment-type-breakdown {
                    margin-top: 15px;
                }
                .card {
                    border: 1px solid #dee2e6;
                    box-shadow: none;
                }
                .card-body {
                    padding: 0.75rem;
                }
                .btn { display: none !important; }
                @media print {
                    body {
                        padding: 0;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .no-print { display: none !important; }
                    .summary-stat-card {
                        background: #f8f9fa !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .table th {
                        background-color: #e9ecef !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container-fluid">
                <div class="print-header">
                    <h2>My Transactions Report</h2>
                    <p class="date-range">Period: ${fromDate} to ${toDate}</p>
                    <p class="print-date">Printed on: ${new Date().toLocaleString()}</p>
                </div>
                ${printContent}
            </div>
            <script>
                // Wait for Bootstrap CSS to load before printing
                setTimeout(function() {
                    window.print();
                }, 500);
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
});

function loadMyTransactions(fromDate, toDate, paymentType, bankId) {
    $.ajax({
        url: '/billing-workbench/my-transactions',
        method: 'GET',
        data: {
            from: fromDate,
            to: toDate,
            payment_type: paymentType,
            bank_id: bankId
        },
        success: function(response) {
            renderMyTransactions(response.transactions);
            renderMyTransactionsSummary(response.summary);
        },
        error: function(xhr) {
            toastr.error('Failed to load transactions');
        }
    });
}

function renderMyTransactions(transactions) {
    const tbody = $('#my-transactions-tbody');
    tbody.empty();

    if (transactions.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                    <p>No transactions found for the selected period</p>
                </td>
            </tr>
        `);
        return;
    }

    transactions.forEach(tx => {
        const row = `
            <tr>
                <td>${tx.created_at}</td>
                <td>${tx.patient_name}</td>
                <td>${tx.file_no}</td>
                <td>${tx.reference_no || 'N/A'}</td>
                <td>${tx.payment_type}</td>
                <td>${tx.bank_name || '-'}</td>
                <td>₦${parseFloat(tx.total).toLocaleString()}</td>
                <td>₦${parseFloat(tx.total_discount).toLocaleString()}</td>
            </tr>
        `;
        tbody.append(row);
    });
}

function renderMyTransactionsSummary(summary) {
    $('#my-total-transactions').text(summary.count);
    $('#my-total-amount').text(`₦${parseFloat(summary.total_amount).toLocaleString()}`);
    $('#my-total-discounts').text(`₦${parseFloat(summary.total_discount).toLocaleString()}`);

    // Render breakdown by payment type
    const breakdown = $('#payment-type-breakdown');
    breakdown.empty();

    if (summary.by_type) {
        let html = '<h6 class="mt-3 mb-2">Breakdown by Payment Type</h6><div class="row">';
        Object.keys(summary.by_type).forEach(type => {
            const data = summary.by_type[type];
            html += `
                <div class="col-md-3 mb-2">
                    <div style="padding: 1rem; background: white; border-radius: 0.5rem; border: 1px solid #dee2e6;">
                        <strong>${type}</strong><br>
                        <small>${data.count} transactions</small><br>
                        <span style="font-size: 1.1rem; color: var(--hospital-primary);">₦${parseFloat(data.amount).toLocaleString()}</span>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        breakdown.html(html);
    }

    $('#my-transactions-summary').show();
}

function updateBillingBadge(count) {
    $('#billing-badge').text(count);
}

// Old lab-specific functions removed, keeping legacy compatibility stubs

function recordBilling(requestIds) {
    console.warn('Legacy function called - no longer applicable in billing workbench');
}

function collectSample(requestIds) {
    console.warn('Legacy function called - no longer applicable in billing workbench');
}

function dismissRequests(requestIds, section) {
    console.warn('Legacy function called - no longer applicable in billing workbench');
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
        'all': '🟡 All Unpaid Items',
        'hmo': '🟢 HMO Items',
        'credit': '🟠 Credit Accounts',
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

function initializeQueueDataTable(filter) {
    // Destroy existing DataTable if it exists
    if (queueDataTable) {
        queueDataTable.destroy();
    }

    // Initialize DataTable for payment queue
    queueDataTable = $('#queue-datatable').DataTable({
        ajax: {
            url: '/billing-workbench/payment-queue',
            data: { filter: filter },
            dataSrc: ''
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="queue-patient-item" data-patient-id="${row.patient_id}" style="cursor: pointer; padding: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="font-weight: 600; font-size: 1rem; color: #212529;">${row.patient_name}</div>
                                <span class="badge badge-primary">${row.file_no}</span>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #6c757d;">
                                <i class="mdi mdi-file-document-outline"></i> ${row.unpaid_count} unpaid item(s)
                                ${row.hmo_items > 0 ? `<span class="hmo-badge ml-2"><i class="mdi mdi-shield-check"></i> ${row.hmo_items} HMO</span>` : ''}
                                ${row.hmo ? `<br><small><i class="mdi mdi-hospital-building"></i> ${row.hmo}</small>` : ''}
                            </div>
                        </div>
                    `;
                }
            }
        ],
        paging: true,
        pageLength: 10,
        searching: true,
        ordering: false,
        info: true,
        responsive: true,
        language: {
            emptyTable: "No patients in this queue",
            zeroRecords: "No patients found",
            info: "Showing _START_ to _END_ of _TOTAL_ patients",
            infoEmpty: "No patients to show",
            infoFiltered: "(filtered from _MAX_ total patients)"
        }
    });

    // Click handler for patient selection from queue
    $('#queue-datatable').on('click', '.queue-patient-item', function() {
        const patientId = $(this).data('patient-id');
        hideQueue();
        loadPatient(patientId);
    });
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

// ==========================================
// MODAL CLEANUP - Fix backdrop/body scroll issues
// ==========================================
$(document).on('hidden.bs.modal', '.modal', function () {
    // Remove any lingering backdrops
    $('.modal-backdrop').remove();

    // Remove modal-open class if no modals are visible
    if ($('.modal:visible').length === 0) {
        $('body').removeClass('modal-open');
        $('body').css({
            'overflow': '',
            'padding-right': ''
        });
    }
});

// Ensure body scroll is restored when modal closes
$('#receiptPreviewModal, #accountStatementModal').on('hidden.bs.modal', function () {
    $('body').removeClass('modal-open');
    $('body').css('overflow', '');
    $('body').css('padding-right', '');
    $('.modal-backdrop').remove();
});

</script>
@endsection
