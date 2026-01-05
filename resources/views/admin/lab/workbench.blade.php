@extends('admin.layouts.app')

@section('title', 'Lab Workbench')

@section('content')
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
        height: calc(100vh - 100px);
        gap: 0;
        overflow: hidden;
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

    /* Workspace Content */
    .workspace-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        display: none;
    }

    .workspace-content.active {
        display: flex;
    }

    .workspace-tabs {
        display: flex;
        border-bottom: 2px solid #dee2e6;
        background: #f8f9fa;
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
        padding: 1.5rem;
        display: none;
    }

    .workspace-tab-content.active {
        display: block;
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

    /* Responsive */
    @media (max-width: 1366px) {
        .left-panel {
            width: 25%;
        }

        .main-workspace {
            flex: 1;
        }

        .right-panel {
            width: 20%;
        }
    }

    @media (max-width: 1024px) {
        .right-panel {
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
            width: 350px;
            z-index: 1000;
            box-shadow: -4px 0 8px rgba(0, 0, 0, 0.1);
        }
    }
</style>

<div class="lab-workbench-container">
    <!-- Left Panel: Patient Search & Queue -->
    <div class="left-panel">
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
            <button class="quick-action-btn">
                <i class="fa fa-plus-circle"></i>
                <span>New Request</span>
            </button>
            <button class="quick-action-btn">
                <i class="fa fa-file-pdf"></i>
                <span>View Reports</span>
            </button>
            <button class="quick-action-btn">
                <i class="fa fa-boxes"></i>
                <span>Inventory</span>
            </button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="main-workspace">
        <!-- Empty State -->
        <div class="empty-state" id="empty-state">
            <i class="fa fa-flask"></i>
            <h3>Select a patient to begin</h3>
            <p>Use the search box on the left or view pending queue</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                📋 View All Pending Requests
            </button>
        </div>

        <!-- Patient Header -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div>
                    <div class="patient-name" id="patient-name"></div>
                    <div class="patient-meta" id="patient-meta"></div>
                </div>
                <button class="toggle-clinical-btn" id="toggle-clinical-btn">
                    📊 Clinical Context ›
                </button>
            </div>
        </div>

        <!-- Workspace Content -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="pending">
                    <i class="fa fa-clipboard-list"></i>
                    <span>Pending</span>
                    <span class="workspace-tab-badge" id="pending-badge">0</span>
                </button>
                <button class="workspace-tab" data-tab="new-request">
                    <i class="fa fa-plus-circle"></i>
                    <span>New Request</span>
                </button>
                <button class="workspace-tab" data-tab="history">
                    <i class="fa fa-history"></i>
                    <span>History</span>
                </button>
            </div>

            <div class="workspace-tab-content active" id="pending-tab">
                <h5>Loading...</h5>
            </div>

            <div class="workspace-tab-content" id="new-request-tab">
                <h5>New Request Form</h5>
                <p>Create new lab request for this patient...</p>
            </div>

            <div class="workspace-tab-content" id="history-tab">
                <h4>Investigation History</h4>
                <div class="table responsive">
                    <table class="table table-hover" style="width: 100%" id="investigation_history_list">
                        <thead class="table-light">
                            <th><i class="mdi mdi-test-tube"></i> Laboratory Requests</th>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel: Clinical Context -->
    <div class="right-panel" id="right-panel">
        <div class="right-panel-header">
            <h5>
                <i class="fa fa-heartbeat"></i>
                Clinical Context
            </h5>
            <button class="close-panel-btn" id="close-clinical-panel">
                ×
            </button>
        </div>
        <div class="right-panel-content">
            <!-- Vitals Panel -->
            <div class="clinical-panel">
                <div class="clinical-panel-header" data-panel="vitals">
                    <h6 class="clinical-panel-title">
                        <i class="fa fa-heartbeat"></i>
                        Recent Vitals (10)
                    </h6>
                    <div class="clinical-panel-actions">
                        <button class="clinical-panel-btn refresh-btn" data-panel="vitals" title="Refresh">
                            <i class="fa fa-sync"></i>
                        </button>
                        <button class="clinical-panel-btn collapse-btn" title="Collapse">
                            <i class="fa fa-chevron-up"></i>
                        </button>
                    </div>
                </div>
                <div class="clinical-panel-body active" id="vitals-panel-body">
                    <p class="text-muted">Loading vitals...</p>
                </div>
            </div>

            <!-- Notes Panel -->
            <div class="clinical-panel">
                <div class="clinical-panel-header" data-panel="notes">
                    <h6 class="clinical-panel-title">
                        <i class="fa fa-notes-medical"></i>
                        Doctor Notes (10)
                    </h6>
                    <div class="clinical-panel-actions">
                        <button class="clinical-panel-btn refresh-btn" data-panel="notes" title="Refresh">
                            <i class="fa fa-sync"></i>
                        </button>
                        <button class="clinical-panel-btn collapse-btn" title="Collapse">
                            <i class="fa fa-chevron-up"></i>
                        </button>
                    </div>
                </div>
                <div class="clinical-panel-body active" id="notes-panel-body">
                    <p class="text-muted">Loading notes...</p>
                </div>
            </div>

            <!-- Medications Panel -->
            <div class="clinical-panel">
                <div class="clinical-panel-header" data-panel="medications">
                    <h6 class="clinical-panel-title">
                        <i class="fa fa-pills"></i>
                        Medications (20)
                    </h6>
                    <div class="clinical-panel-actions">
                        <button class="clinical-panel-btn refresh-btn" data-panel="medications" title="Refresh">
                            <i class="fa fa-sync"></i>
                        </button>
                        <button class="clinical-panel-btn collapse-btn" title="Collapse">
                            <i class="fa fa-chevron-up"></i>
                        </button>
                    </div>
                </div>
                <div class="clinical-panel-body active" id="medications-panel-body">
                    <p class="text-muted">Loading medications...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="lab-footer">
    <div class="sync-indicator" id="sync-indicator">
        <i class="fa fa-circle" style="color: var(--success);"></i>
        <span>Last sync: <span id="last-sync-time">Just now</span></span>
    </div>
    <div>
        <span>Server: <strong style="color: var(--success);">Online</strong></span>
        <span class="mx-2">|</span>
        <span>Queue refreshing every 30s</span>
    </div>
</div>

<!-- Result Entry Modal -->
<div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="investResForm" action="{{ route('lab.saveResult') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                            id="invest_res_service_name"></span>)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- V1 Template: WYSIWYG Editor -->
                    <div id="v1_template_container">
                        <textarea id="invest_res_template_editor" class="ckeditor"></textarea>
                    </div>

                    <!-- V2 Template: Structured Form -->
                    <div id="v2_template_container" style="display: none;">
                        <div id="v2_form_fields"></div>
                    </div>

                    <input type="hidden" id="invest_res_entry_id" name="invest_res_entry_id">
                    <input type="hidden" name="invest_res_template_submited" id="invest_res_template_submited">
                    <input type="hidden" id="invest_res_template_version" name="invest_res_template_version" value="1">
                    <input type="hidden" id="invest_res_template_data" name="invest_res_template_data">
                    <input type="hidden" id="invest_res_is_edit" name="invest_res_is_edit" value="0">
                    <input type="hidden" id="deleted_attachments" name="deleted_attachments" value="[]">

                    <hr>

                    <!-- Existing Attachments -->
                    <div id="existing_attachments_container" style="display: none;">
                        <label><i class="mdi mdi-paperclip"></i> Existing Attachments</label>
                        <div id="existing_attachments_list" class="mb-3"></div>
                    </div>

                    <!-- New File Upload -->
                    <div class="form-group">
                        <label for="result_attachments"><i class="mdi mdi-file-plus"></i> Add New Files (Optional)</label>
                        <input type="file" class="form-control" id="result_attachments" name="result_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">You can attach multiple files (PDF, Images, Word documents). Max 10MB per file.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" id="invest_res_submit_btn"
                        class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Result View Modal -->
<div class="modal fade" id="investResViewModal" tabindex="-1" role="dialog" aria-labelledby="investResViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            @php
                $sett = appsettings();
                $hosColor = $sett->hos_color ?? '#0066cc';
            @endphp
            <style>
                #resultViewTable { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; width: 100%; max-width: 100%; }
                .result-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 3px solid {{ $hosColor }}; }
                .result-header-left { display: flex; align-items: center; gap: 15px; }
                .result-logo { width: 70px; height: 70px; object-fit: contain; }
                .result-hospital-name { font-size: 24px; font-weight: bold; color: {{ $hosColor }}; text-transform: uppercase; }
                .result-header-right { text-align: right; font-size: 13px; color: #666; line-height: 1.6; }
                .result-title-section { background: {{ $hosColor }}; color: white; text-align: center; padding: 15px; font-size: 28px; font-weight: bold; letter-spacing: 6px; }
                .result-patient-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 20px; background: #f8f9fa; }
                .result-info-box { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                .result-info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                .result-info-row:last-child { border-bottom: none; }
                .result-info-label { font-weight: 600; color: #333; min-width: 120px; }
                .result-info-value { color: #666; flex: 1; }
                .result-section { padding: 20px; }
                .result-section-title { font-size: 20px; font-weight: bold; color: {{ $hosColor }}; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid {{ $hosColor }}; }
                .result-table { width: 100% !important; max-width: 100% !important; border-collapse: collapse; margin-top: 15px; table-layout: fixed; }
                .result-table thead { background: {{ $hosColor }}; color: white; }
                .result-table th { padding: 12px; text-align: left; font-weight: 600; }
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
let queueRefreshInterval = null;
let searchTimeout = null;

$(document).ready(function() {
    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadUserPreferences();
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

    // Toggle clinical context
    $('#toggle-clinical-btn, #close-clinical-panel').on('click', toggleClinicalPanel);

    // Clinical panel collapse
    $('.clinical-panel-header').on('click', function(e) {
        if (!$(e.target).closest('.clinical-panel-actions').length) {
            $(this).next('.clinical-panel-body').slideToggle(200);
            $(this).find('.collapse-btn i').toggleClass('fa-chevron-up fa-chevron-down');
        }
    });

    // Refresh buttons
    $('.refresh-btn').on('click', function(e) {
        e.stopPropagation();
        const panel = $(this).data('panel');
        refreshClinicalPanel(panel);
    });

    // Queue filter
    $('.queue-item').on('click', function() {
        const filter = $(this).data('filter');
        // TODO: Implement filtering
        console.log('Filter by:', filter);
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

    // Load patient requests
    $.ajax({
        url: `/lab-workbench/patient/${patientId}/requests`,
        method: 'GET',
        success: function(data) {
            displayPatientInfo(data.patient);
            displayPendingRequests(data.requests);

            // Load clinical context if panel is visible
            if ($('#right-panel').hasClass('active')) {
                loadClinicalContext(patientId);
            }

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
            <i class="fa fa-user"></i>
            <span>${patient.age} ${patient.gender}</span>
        </div>
        <div class="patient-meta-item">
            <i class="fa fa-tint"></i>
            <span>${patient.blood_type}</span>
        </div>
        <div class="patient-meta-item">
            <i class="fa fa-phone"></i>
            <span>${patient.phone}</span>
        </div>
    `);
}

function displayPendingRequests(requests) {
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length;
    $('#pending-badge').text(totalPending);

    const $container = $('#pending-tab');
    $container.empty();

    if (totalPending === 0) {
        $container.html('<div class="alert alert-info">No pending lab requests for this patient</div>');
        return;
    }

    // Billing Section (Status 1)
    if (requests.billing.length > 0) {
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
    if (requests.sample.length > 0) {
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
    if (requests.results.length > 0) {
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

    // Results section has individual action button instead of checkbox
    const checkboxOrAction = section === 'results' ? `
        <button class="btn btn-sm btn-primary enter-result-btn" data-request-id="${request.id}">
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
    const $container = $('#vitals-panel-body');
    $container.empty();

    if (vitals.length === 0) {
        $container.html('<p class="text-muted">No recent vitals recorded</p>');
        return;
    }

    vitals.forEach(vital => {
        const vitalDate = formatDateTime(vital.created_at);
        const bmi = calculateBMI(vital.weight, vital.height);
        const temp = vital.temperature || 'N/A';
        const pulse = vital.pulse || 'N/A';
        const bp = vital.blood_pressure || 'N/A';
        const rr = vital.respiratory_rate || 'N/A';
        const weight = vital.weight || 'N/A';

        const vitalHtml = `
            <div class="vital-entry">
                <div class="vital-entry-header">
                    <span class="vital-date">${vitalDate}</span>
                    ${bmi ? `<span class="vital-bmi">BMI: ${bmi}</span>` : ''}
                </div>
                <div class="vital-entry-grid">
                    <div class="vital-item ${getTempClass(temp)}">
                        <i class="mdi mdi-thermometer"></i>
                        <span class="vital-value">${temp}°C</span>
                        <span class="vital-label">Temp</span>
                    </div>
                    <div class="vital-item ${getPulseClass(pulse)}">
                        <i class="mdi mdi-heart-pulse"></i>
                        <span class="vital-value">${pulse}</span>
                        <span class="vital-label">Pulse</span>
                    </div>
                    <div class="vital-item ${getBPClass(bp)}">
                        <i class="mdi mdi-water"></i>
                        <span class="vital-value">${bp}</span>
                        <span class="vital-label">BP</span>
                    </div>
                    <div class="vital-item">
                        <i class="mdi mdi-lungs"></i>
                        <span class="vital-value">${rr}</span>
                        <span class="vital-label">RR</span>
                    </div>
                    <div class="vital-item">
                        <i class="mdi mdi-weight-kilogram"></i>
                        <span class="vital-value">${weight}kg</span>
                        <span class="vital-label">Weight</span>
                    </div>
                </div>
            </div>
        `;
        $container.append(vitalHtml);
    });

    $container.append(`
        <a href="/patients/show/${currentPatient}?section=vitalsCardBody" target="_blank" class="show-all-link">
            Show All Vitals →
        </a>
    `);
}

function calculateBMI(weight, height) {
    if (!weight || !height || height === 0) return null;
    const bmi = weight / ((height / 100) ** 2);
    return bmi.toFixed(1);
}

function getTempClass(temp) {
    if (temp === 'N/A') return '';
    const t = parseFloat(temp);
    if (t < 36.1 || t > 38.0) return 'vital-warning';
    return 'vital-normal';
}

function getPulseClass(pulse) {
    if (pulse === 'N/A') return '';
    const p = parseInt(pulse);
    if (p < 60 || p > 100) return 'vital-warning';
    return 'vital-normal';
}

function getBPClass(bp) {
    if (bp === 'N/A' || !bp.includes('/')) return '';
    const [systolic, diastolic] = bp.split('/').map(v => parseInt(v));
    if (systolic > 140 || systolic < 90 || diastolic > 90 || diastolic < 60) return 'vital-warning';
    return 'vital-normal';
}

function displayNotes(notes) {
    const $container = $('#notes-panel-body');
    $container.empty();

    if (notes.length === 0) {
        $container.html('<p class="text-muted">No recent doctor notes</p>');
        return;
    }

    notes.forEach((note, index) => {
        const noteDate = formatDateTime(note.date);
        const doctor = note.doctor_name || 'Unknown Doctor';
        const diagnosis = note.diagnosis || 'No diagnosis';
        const content = note.notes || note.complaints || 'No notes recorded';
        const truncatedContent = truncateText(content, 150);
        const noteId = `note-${index}`;

        const noteHtml = `
            <div class="note-entry">
                <div class="note-header">
                    <span class="note-doctor">
                        <i class="mdi mdi-doctor"></i>
                        ${doctor}
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
        $container.append(noteHtml);
    });

    // Read More toggle handler
    $container.on('click', '.read-more-link', function(e) {
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

    $container.append(`
        <a href="/patients/show/${currentPatient}?section=encountersCardBody" target="_blank" class="show-all-link">
            Show All Notes →
        </a>
    `);
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
    const $container = $('#medications-panel-body');
    $container.empty();

    if (meds.length === 0) {
        $container.html('<p class="text-muted">No recent medications</p>');
        return;
    }

    // Add filter buttons
    const filterHtml = `
        <div class="medication-filters">
            <button class="medication-filter-btn active" data-filter="all">All (${meds.length})</button>
            <button class="medication-filter-btn" data-filter="active">Active (${meds.filter(m => m.status === 'active').length})</button>
            <button class="medication-filter-btn" data-filter="stopped">Stopped (${meds.filter(m => m.status === 'stopped').length})</button>
        </div>
    `;
    $container.append(filterHtml);

    const $medsContainer = $('<div class="medications-list"></div>');

    meds.forEach(med => {
        const drugName = med.drug_name || med.product_name || 'Unknown Drug';
        const dosage = med.dosage || 'N/A';
        const frequency = med.frequency || 'N/A';
        const status = med.status || 'active';
        const statusText = status.charAt(0).toUpperCase() + status.slice(1);
        const startDate = med.created_at ? formatDate(med.created_at) : 'N/A';
        const prescribedBy = med.doctor_name || 'Unknown';

        const medHtml = `
            <div class="medication-entry" data-status="${status}">
                <div class="medication-header">
                    <span class="medication-name">${drugName}</span>
                    <span class="medication-status-badge status-${status}">${statusText}</span>
                </div>
                <div class="medication-details">
                    <div class="medication-detail-row">
                        <i class="mdi mdi-pill"></i>
                        <span><strong>Dosage:</strong> ${dosage}</span>
                    </div>
                    <div class="medication-detail-row">
                        <i class="mdi mdi-clock-outline"></i>
                        <span><strong>Frequency:</strong> ${frequency}</span>
                    </div>
                    <div class="medication-detail-row">
                        <i class="mdi mdi-calendar"></i>
                        <span><strong>Started:</strong> ${startDate}</span>
                    </div>
                    <div class="medication-detail-row">
                        <i class="mdi mdi-doctor"></i>
                        <span><strong>By:</strong> ${prescribedBy}</span>
                    </div>
                </div>
            </div>
        `;
        $medsContainer.append(medHtml);
    });

    $container.append($medsContainer);

    // Filter button handlers
    $container.on('click', '.medication-filter-btn', function() {
        const filter = $(this).data('filter');
        $('.medication-filter-btn').removeClass('active');
        $(this).addClass('active');

        if (filter === 'all') {
            $('.medication-entry').show();
        } else {
            $('.medication-entry').hide();
            $(`.medication-entry[data-status="${filter}"]`).show();
        }
    });

    $container.append(`
        <a href="/patients/show/${currentPatient}?section=prescriptionsCardBody" target="_blank" class="show-all-link">
            Show All Medications →
        </a>
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
    queueRefreshInterval = setInterval(loadQueueCounts, 30000); // 30 seconds
}

function updateSyncIndicator() {
    $('#last-sync-time').text('Just now');
    // TODO: Implement relative time updates
}

function toggleClinicalPanel() {
    $('#right-panel').toggleClass('active');

    const isActive = $('#right-panel').hasClass('active');
    $('#toggle-clinical-btn').html(isActive ? '📊 Clinical Context ×' : '📊 Clinical Context ›');

    // Load data if opening and patient selected
    if (isActive && currentPatient) {
        loadClinicalContext(currentPatient);
    }

    // Save preference
    localStorage.setItem('clinicalPanelVisible', isActive);
}

function refreshClinicalPanel(panel) {
    if (!currentPatient) return;

    const $btn = $(`.refresh-btn[data-panel="${panel}"]`);
    $btn.find('i').addClass('fa-spin');

    // TODO: Reload specific panel data

    setTimeout(() => {
        $btn.find('i').removeClass('fa-spin');
    }, 1000);
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


</script>
@endsection
