{{--
    Reusable Patient Clinical Story Component
    Path: resources/views/admin/partials/clinical_story.blade.php
    Description: Displays a chronological, color-coded, interactive patient story with accordion categories, date range filters, and horizontal timeline navigation.
--}}
@php
    $uniqueStoryId = 'story_' . uniqid();
    $encId = isset($encounter) && $encounter ? $encounter->id : '';
    $patId = isset($patient) && $patient ? $patient->id : (isset($patient_id) ? $patient_id : (isset($encounter) && $encounter ? $encounter->patient_id : ''));
@endphp

<div class="clinical-story-wrapper shadow-lg rounded-4 p-4 mb-4" id="clinical-story-{{ $uniqueStoryId }}" data-encounter-id="{{ $encId }}" data-patient-id="{{ $patId }}">
    
    {{-- Header section --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 pb-3 border-bottom border-light">
        <div>
            <h4 class="mb-1 text-primary fw-extrabold d-flex align-items-center gap-2">
                <i class="fa fa-history text-accent"></i> Patient Clinical Story
            </h4>
            <p class="text-muted small mb-0">A comprehensive chronological timeline of all clinical interactions and records.</p>
        </div>
        
        {{-- Quick action / Filter buttons --}}
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" type="button" id="btn-refresh-story-{{ $uniqueStoryId }}">
                <i class="fa fa-refresh me-1"></i> Refresh
            </button>
        </div>
    </div>

    {{-- Prominent Filter Pane --}}
    <div class="story-filter-pane p-3 mb-4" id="filter-pane-{{ $uniqueStoryId }}">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="filter-input-group">
                    <label class="filter-label"><i class="fa fa-calendar text-primary"></i> Start Date</label>
                    <input type="date" class="filter-control" id="filter-date-from-{{ $uniqueStoryId }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="filter-input-group">
                    <label class="filter-label"><i class="fa fa-calendar text-primary"></i> End Date</label>
                    <input type="date" class="filter-control" id="filter-date-to-{{ $uniqueStoryId }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="filter-input-group">
                    <label class="filter-label"><i class="fa fa-user-md text-primary"></i> Filter by Consultation</label>
                    <select class="filter-control filter-select" id="filter-encounter-{{ $uniqueStoryId }}">
                        <option value="">All Encounters</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="filter-actions-group">
                    <button class="filter-btn filter-btn-primary" id="btn-apply-filters-{{ $uniqueStoryId }}">
                        <i class="fa fa-filter"></i> Apply
                    </button>
                    <button class="filter-btn filter-btn-secondary" id="btn-reset-filters-{{ $uniqueStoryId }}">
                        <i class="fa fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Vertical Timeline --}}
    <div class="mb-4">
        <h6 class="small text-uppercase fw-bold text-secondary tracking-wider mb-4 px-2">Clinical Timeline</h6>
        
        <div id="clinical-timeline-container-{{ $uniqueStoryId }}" class="clinical-timeline-wrapper">
            <div class="text-center text-muted py-5"><i class="fa fa-spinner fa-spin fa-2x mb-2"></i><div>Loading timeline...</div></div>
        </div>
        
        {{-- Pagination Controls --}}
        <div id="clinical-timeline-pagination-{{ $uniqueStoryId }}" class="text-center mt-4 d-none">
            <button class="btn btn-outline-primary rounded-pill px-5 py-2 shadow-sm fw-bold" id="btn-load-more-{{ $uniqueStoryId }}">
                <i class="fa fa-angle-double-down me-2"></i> Load Older Records
            </button>
        </div>
    </div>
</div>

{{-- Modal for Detailed View --}}
<div class="modal fade" id="story-detail-modal-{{ $uniqueStoryId }}" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered" role="document">
    <div class="modal-content shadow-lg border-0 rounded-4">
      <div class="modal-header bg-light border-bottom p-3">
        <h5 class="modal-title d-flex align-items-center gap-3" id="story-detail-title-{{ $uniqueStoryId }}">
            <span class="category-icon-bg bg-primary text-white shadow-sm" style="width: 36px; height: 36px; font-size: 1rem;"><i class="fa fa-file-text-o"></i></span>
            <span class="fw-bold">Details</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-white" id="story-detail-body-{{ $uniqueStoryId }}">
         <div class="p-4 text-center text-muted"><i class="fa fa-spinner fa-spin fa-2x mb-2 text-primary"></i><div>Loading details...</div></div>
      </div>
    </div>
  </div>
</div>

{{-- Add Clinical Story Premium CSS stylesheet --}}
<style>
    /* Sleek Filter Pane */
    .story-filter-pane {
        background: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.025) !important;
    }
    .filter-input-group {
        display: flex;
        flex-direction: column;
    }
    .filter-label {
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        color: #475569 !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-control {
        width: 100% !important;
        height: 38px !important;
        padding: 6px 12px !important;
        font-size: 0.82rem !important;
        font-weight: 500 !important;
        color: #1e293b !important;
        background-color: #ffffff !important;
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 8px !important;
        outline: none !important;
        transition: all 0.15s ease-in-out !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    }
    .filter-control:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12) !important;
        background-color: #ffffff !important;
    }
    .filter-select {
        cursor: pointer;
        padding-right: 28px !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 10px 10px !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
    }
    .filter-actions-group {
        display: flex;
        gap: 6px;
        width: 100%;
        margin-top: 20px;
    }
    .filter-btn {
        flex: 1;
        height: 38px !important;
        font-size: 0.78rem !important;
        font-weight: 700 !important;
        border-radius: 8px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s ease-in-out !important;
        border: none !important;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .filter-btn-primary {
        background-color: #0f172a !important;
        color: #ffffff !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
    }
    .filter-btn-primary:hover {
        background-color: #1e293b !important;
        transform: translateY(-1px);
    }
    .filter-btn-secondary {
        background-color: #ffffff !important;
        color: #475569 !important;
        border: 1px solid #e2e8f0 !important;
    }
    .filter-btn-secondary:hover {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        transform: translateY(-1px);
    }

    /* Styling variables and custom classes */
    :root {
        --maternity-color: #e83e8c;
        --maternity-light: #fce4ec;
        --premium-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        --count-badge-bg: #e8740c;
    }

    /* Category icon backgrounds */
    .category-icon-bg {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        font-size: 1.25rem;
    }
    
    .bg-vitals { background: linear-gradient(135deg, #f857a6 0%, #ff5858 100%); }
    .bg-notes { background: linear-gradient(135deg, #17ad97 0%, #17ad7c 100%); }
    .bg-nurse { background: linear-gradient(135deg, #1d976c 0%, #93f9b9 100%); }
    .bg-med-admin { background: linear-gradient(135deg, #4776e6 0%, #8e54e9 100%); }
    .bg-io { background: linear-gradient(135deg, #3a7bd5 0%, #3a6073 100%); }
    .bg-injection { background: linear-gradient(135deg, #f12711 0%, #f5af19 100%); }
    .bg-labs { background: linear-gradient(135deg, #e65c00 0%, #f9d423 100%); }
    .bg-imaging { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .bg-prescriptions { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); }
    .bg-care { background: linear-gradient(135deg, #8a2387 0%, #e94057 100%); }
    .bg-procedures { background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%); }
    .bg-admission { background: linear-gradient(135deg, #654ea3 0%, #eaafc8 100%); }
    .bg-referrals { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
    .bg-anc { background: linear-gradient(135deg, #ec407a 0%, #f48fb1 100%); }
    .bg-delivery { background: linear-gradient(135deg, #d81b60 0%, #ad1457 100%); }
    .bg-postnatal { background: linear-gradient(135deg, #ff80ab 0%, #ff4081 100%); }

    /* ===== Vertical Timeline ===== */
    .clinical-timeline-wrapper {
        position: relative;
        padding-left: 24px;
        margin-left: 8px;
    }
    .clinical-timeline-wrapper::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        width: 2px;
        background: #c8d6e5;
        border-radius: 2px;
    }
    .timeline-node {
        position: relative;
        margin-bottom: 24px;
    }
    .timeline-node:last-child {
        margin-bottom: 0;
    }
    .timeline-marker {
        position: absolute;
        left: -31px;
        top: 14px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #3b82f6;
        border: 2.5px solid #fff;
        box-shadow: 0 0 0 2px #c8d6e5;
        z-index: 2;
    }
    .timeline-content {
        background: #ffffff;
        border-radius: 6px;
        border: 1px solid #dfe6e9;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        padding: 12px 16px;
    }
    .timeline-date-heading {
        font-size: 0.95rem;
        font-weight: 600;
        color: #2d3436;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .timeline-date-heading i { color: #636e72; font-size: 0.9rem; }

    /* ===== Folder sections (tree-style toggles) ===== */
    .timeline-folder {
        margin-bottom: 8px;
        border: none !important;
        border-radius: 0 !important;
    }
    .timeline-folder-header {
        cursor: pointer;
        padding: 6px 4px;
        background: transparent;
        border-bottom: none;
        transition: background 0.15s;
        user-select: none;
    }
    .timeline-folder-header:hover {
        background: rgba(0,0,0,0.02);
    }
    .timeline-folder-header .folder-toggle-icon {
        font-family: monospace;
        font-size: 0.85rem;
        width: 16px;
        text-align: center;
        color: #636e72;
        flex-shrink: 0;
    }
    .timeline-folder-header .folder-icon {
        color: #f6c23e;
        font-size: 1rem;
    }
    .timeline-folder-header[aria-expanded="true"] .folder-icon::before {
        content: "\f07c";
    }
    .timeline-folder-header[aria-expanded="false"] .folder-icon::before {
        content: "\f07b";
    }
    .timeline-folder-header[aria-expanded="true"] .folder-toggle-icon::before {
        content: "⊟";
    }
    .timeline-folder-header[aria-expanded="false"] .folder-toggle-icon::before {
        content: "⊞";
    }
    .timeline-folder-header .folder-title {
        font-weight: 600;
        font-size: 0.88rem;
        color: #2d3436;
    }
    .timeline-folder-body {
        padding: 4px 0 4px 22px;
        background: transparent;
        border-left: 1px dashed #b2bec3;
        margin-left: 7px;
        position: relative;
    }
    .timeline-folder-body .row::before {
        content: '';
        position: absolute;
        left: -22px;
        top: 20px;
        width: 22px;
        border-top: 1px dashed #b2bec3;
        z-index: 1;
    }
    .folder-chevron {
        transition: transform 0.2s ease;
        font-size: 0.75rem;
    }
    .timeline-folder-header[aria-expanded="true"] .folder-chevron {
        transform: rotate(90deg);
    }
    
    /* ===== Category Preview Cards ===== */
    .category-preview-card {
        border-radius: 5px;
        border: 1px solid #d5d8dc;
        background: #ffffff;
        cursor: pointer;
        height: 100%;
        transition: all 0.15s ease;
    }
    .category-preview-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.10);
    }
    .category-preview-header {
        padding: 8px 10px;
        border-bottom: 1px solid #eee;
        background: #fafbfc;
        border-top-left-radius: 5px;
        border-top-right-radius: 5px;
    }
    .category-preview-header .cat-icon {
        color: #636e72;
        font-size: 0.85rem;
        width: 18px;
        text-align: center;
    }
    .category-preview-header .cat-title {
        font-weight: 600;
        font-size: 0.8rem;
        color: #2d3436;
    }
    .category-preview-header .cat-count {
        background: var(--count-badge-bg);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        min-width: 20px;
        height: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        padding: 0 5px;
        line-height: 1;
    }
    .category-preview-body {
        padding: 8px 10px;
        font-size: 0.8rem;
        color: #636e72;
        line-height: 1.4;
    }
    
    /* ===== Encounter badges on timeline ===== */
    .enc-badge {
        font-size: 0.75rem;
        padding: 3px 8px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        background: #f8f9fa;
        color: #495057;
    }
    .enc-badge .enc-dot {
        width: 7px; height: 7px; border-radius: 50%; display: inline-block;
    }
    .enc-dot-active { background: #28a745; }
    .enc-dot-pending { background: #ffc107; }

    /* ===== Detailed view card styling ===== */
    .clinical-story-card {
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.06);
        background: #ffffff;
        box-shadow: 0 1px 4px rgba(0,0,0,0.015);
        margin-bottom: 10px;
        padding: 14px;
    }
</style>

{{-- Reusable lazy loading controller script --}}
<script src="{{ asset('js/clinical-story.js') }}" defer></script>
