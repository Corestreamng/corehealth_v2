@extends('admin.layouts.app')
@section('title', 'Procedure Details - ' . ($procedure->service->service_name ?? 'Procedure'))
@section('page_name', 'Procedures')
@section('subpage_name', 'Procedure Details')

@php
    $hosColor = appsettings('hos_color') ?? '#0066cc';

    // Status step index for stepper
    $stepMap = ['requested' => 0, 'scheduled' => 1, 'in_progress' => 2, 'completed' => 3];
    $currentStep = $stepMap[$procedure->procedure_status] ?? 0;

    // Step timestamps
    $stepTimes = [
        0 => $procedure->requested_on,
        1 => $procedure->scheduled_date && $procedure->scheduled_time
              ? \Carbon\Carbon::parse($procedure->scheduled_date->format('Y-m-d') . ' ' . $procedure->scheduled_time)
              : $procedure->scheduled_date,
        2 => $procedure->actual_start_time,
        3 => $procedure->actual_end_time ?? ($procedure->procedure_status === 'completed' ? $procedure->updated_at : null),
    ];

    // Consent
    $consentStatusMap = [
        'obtained'     => ['color' => '#28a745', 'bg' => '#e8f5e9', 'border' => '#28a745', 'icon' => 'fa-check-circle',   'label' => 'Consent Obtained'],
        'waived'       => ['color' => '#6c757d', 'bg' => '#f8f9fa', 'border' => '#6c757d', 'icon' => 'fa-minus-circle',   'label' => 'Consent Waived'],
        'not_required' => ['color' => '#6c757d', 'bg' => '#f8f9fa', 'border' => '#adb5bd', 'icon' => 'fa-ban',            'label' => 'Consent Not Required'],
        'pending'      => ['color' => '#fd7e14', 'bg' => '#fff8f0', 'border' => '#fd7e14', 'icon' => 'fa-clock',          'label' => 'Consent Pending'],
    ];
    $cs = $procedure->consent_status ?? null;
    $consentInfo = $cs ? ($consentStatusMap[$cs] ?? null) : null;
    $consentNeedsAction = in_array($cs, [null, 'pending']);

    // OR duration
    $orDuration = null;
    if ($procedure->actual_start_time && $procedure->actual_end_time) {
        $diff = $procedure->actual_start_time->diff($procedure->actual_end_time);
        $orDuration = ($diff->h > 0 ? $diff->h . 'h ' : '') . $diff->i . 'm';
    }

    // Team groups
    $teamGroupDef = [
        'Surgeons'    => ['chief_surgeon','assistant_surgeon','surgical_first_assistant'],
        'Anesthesia'  => ['anesthesiologist','nurse_anesthetist'],
        'Nursing'     => ['scrub_nurse','circulating_nurse'],
        'Specialists' => ['perfusionist','radiologist','pathologist','other'],
    ];
    $teamGroups = [];
    foreach ($teamGroupDef as $groupName => $roles) {
        $members = $procedure->teamMembers->filter(fn($m) => in_array($m->role, $roles));
        if ($members->count()) $teamGroups[$groupName] = $members;
    }

    // Page role
    $pageRole = auth()->user()->hasAnyRole(['DOCTOR']) ? 'doctor'
              : (auth()->user()->hasAnyRole(['NURSE','SURGERY']) ? 'nurse' : 'admin');

    $pt = $procedure->patient;

    // Item counts
    $labCount     = $procedure->items->filter(fn($i) => $i->lab_service_request_id !== null)->count();
    $imagingCount = $procedure->items->filter(fn($i) => $i->imaging_service_request_id !== null)->count();
    $medsCount    = $procedure->items->filter(fn($i) => $i->product_request_id !== null)->count();
    $totalItems   = $procedure->items->count();

    $isCancelled  = $procedure->procedure_status === 'cancelled';
    $isCompleted  = $procedure->procedure_status === 'completed';
    $isSurgical   = $procedure->procedureDefinition && $procedure->procedureDefinition->is_surgical;
@endphp

@section('content')
{{-- Chosen CSS --}}
<link rel="stylesheet" href="{{ asset('assets/css/chosen.min.css') }}">
<style>
    :root { --proc-primary: {{ $hosColor }}; }

    .procedure-page { font-family: 'Inter', -apple-system, sans-serif; }

    /* ── Chosen ──────────────────────────────── */
    .chosen-container { width: 100% !important; }
    .chosen-container-single .chosen-single {
        height: 38px; line-height: 36px; border-radius: 4px;
        border: 1px solid #ced4da; background: #fff; box-shadow: none;
    }
    .chosen-container-active.chosen-with-drop .chosen-single { border-color: var(--proc-primary); box-shadow: 0 0 0 .2rem rgba(0,102,204,.25); }
    .chosen-container .chosen-drop { border-color: var(--proc-primary); box-shadow: 0 4px 8px rgba(0,0,0,.1); }
    .chosen-container .chosen-results li.highlighted { background: var(--proc-primary); }
    .chosen-container-single .chosen-search input[type="text"] { border: 1px solid #ced4da; border-radius: 4px; padding: 8px 10px; }

    /* ── Command Bar ─────────────────────────── */
    .cmd-bar { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.07); margin-bottom: 18px; overflow: hidden; }

    /* Row A — Title */
    .cmd-bar-title {
        background: linear-gradient(135deg, var(--proc-primary) 0%, #5a9fd4 100%);
        padding: 18px 24px;
        display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
        color: #fff;
    }
    .cmd-bar-title .proc-name { font-size: 1.45rem; font-weight: 700; margin: 0; }
    .cmd-bar-title .proc-meta { font-size: .82rem; opacity: .9; display: flex; flex-wrap: wrap; gap: 10px; margin-top: 4px; }
    .cmd-bar-title .proc-meta span i { margin-right: 3px; }
    .cmd-bar-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

    /* Status badges */
    .badge-status { padding: 5px 13px; border-radius: 20px; font-size: .78rem; font-weight: 600; text-transform: uppercase; }
    .badge-requested   { background: #e9ecef; color: #495057; }
    .badge-scheduled   { background: #cce5ff; color: #004085; }
    .badge-in_progress { background: #fff3cd; color: #856404; animation: statusPulse 2s infinite; }
    .badge-completed   { background: #d4edda; color: #155724; }
    .badge-cancelled   { background: #f8d7da; color: #721c24; }
    @keyframes statusPulse { 0%,100%{ opacity:1 } 50%{ opacity:.7 } }
    .badge-priority { padding: 4px 10px; border-radius: 4px; font-size: .72rem; font-weight: 600; text-transform: uppercase; }
    .badge-routine   { background: #d4edda; color: #155724; }
    .badge-urgent    { background: #fff3cd; color: #856404; }
    .badge-emergency { background: #f8d7da; color: #721c24; animation: statusPulse 1s infinite; }
    .badge-surgical  { background: rgba(255,255,255,.25); color: #fff; font-size: .72rem; padding: 4px 10px; border-radius: 4px; font-weight: 600; }

    /* Row B — Consent Strip */
    .consent-strip {
        display: flex; align-items: center; gap: 12px; padding: 10px 24px; font-size: .88rem; font-weight: 500;
        border-top: 1px solid rgba(0,0,0,.05);
        transition: all .3s;
    }
    .consent-strip.cs-ok     { background: #f0fff4; color: #155724; }
    .consent-strip.cs-warn   { background: #fff8f0; color: #7d4e0f; }
    .consent-strip.cs-danger { background: #fff5f5; color: #721c24; border-left: 4px solid #dc3545; }
    .consent-strip.cs-grey   { background: #f8f9fa; color: #495057; }
    .consent-strip .cs-detail { font-size: .78rem; opacity: .8; margin-left: 4px; }
    .consent-strip .cs-action { margin-left: auto; }

    /* Row C — Status Stepper */
    .status-stepper {
        display: flex; align-items: center; padding: 14px 24px; background: #fafafa;
        border-top: 1px solid #f0f0f0; gap: 0;
    }
    .step-item { display: flex; flex-direction: column; align-items: center; position: relative; flex: 1; }
    .step-connector { flex: 1; height: 2px; background: #dee2e6; margin-top: -22px; position: relative; z-index: 0; }
    .step-connector.done { background: #28a745; }
    .step-dot {
        width: 32px; height: 32px; border-radius: 50%; border: 3px solid #dee2e6;
        background: #fff; display: flex; align-items: center; justify-content: center;
        font-size: .85rem; z-index: 1; position: relative;
    }
    .step-dot.done    { border-color: #28a745; background: #28a745; color: #fff; }
    .step-dot.current { border-color: var(--proc-primary); background: var(--proc-primary); color: #fff; box-shadow: 0 0 0 4px rgba(0,102,204,.2); }
    .step-dot.future  { border-color: #dee2e6; background: #fff; color: #adb5bd; }
    .step-label { font-size: .75rem; font-weight: 600; margin-top: 6px; color: #6c757d; text-align: center; white-space: nowrap; }
    .step-label.current { color: var(--proc-primary); }
    .step-label.done { color: #28a745; }
    .step-time { font-size: .68rem; color: #adb5bd; margin-top: 2px; text-align: center; }
    .cancelled-bar { padding: 10px 24px; background: #fff5f5; border-top: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; color: #721c24; font-weight: 600; font-size: .88rem; }

    /* Row D — Patient Bar */
    .patient-cmd-bar {
        display: flex; align-items: center; gap: 14px; padding: 12px 24px;
        border-top: 1px solid #f0f0f0; background: #fff;
    }
    .patient-avatar-sm {
        width: 40px; height: 40px; border-radius: 50%; background: var(--proc-primary);
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .85rem; flex-shrink: 0;
    }
    .patient-cmd-name { font-weight: 700; font-size: .95rem; color: #222; }
    .patient-cmd-meta { font-size: .8rem; color: #6c757d; }
    .patient-cmd-meta span { margin-right: 10px; }
    .patient-cmd-bar .ml-auto { margin-left: auto; }

    /* ── Section Card ────────────────────────── */
    .section-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; overflow: hidden; }
    .section-card-header { background: #f8f9fa; padding: 14px 20px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
    .section-card-header h5 { margin: 0; font-size: .98rem; font-weight: 600; color: #333; }
    .section-card-header h5 i { color: var(--proc-primary); margin-right: 8px; }
    .section-card-body { padding: 20px; }
    .section-card-body.p-0 { padding: 0; }

    /* ── Info Grid ───────────────────────────── */
    .info-grid { display: grid; gap: 0; }
    .info-item { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .info-item:last-child { border-bottom: none; }
    .info-label { font-weight: 600; color: #6c757d; min-width: 140px; font-size: .88rem; }
    .info-value { color: #333; flex: 1; font-size: .9rem; }

    /* ── Billing ─────────────────────────────── */
    .billing-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 768px) { .billing-grid { grid-template-columns: 1fr; } }
    .billing-box { background: #f8f9fa; border-radius: 8px; padding: 14px; }
    .billing-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed #dee2e6; font-size: .88rem; }
    .billing-row:last-child { border-bottom: none; font-weight: 600; }
    .billing-row.total { font-size: 1rem; color: var(--proc-primary); }

    /* ── Team Board ──────────────────────────── */
    .team-group-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6c757d; padding: 8px 0 4px 0; margin-bottom: 4px; border-bottom: 1px solid #f0f0f0; }
    .team-member-item { display: flex; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; transition: all .2s; }
    .team-member-item:hover { background: #f0f0f0; }
    .team-member-item.is-lead { background: rgba(0,102,204,.05); border-left: 3px solid var(--proc-primary); }
    .team-avatar { width: 40px; height: 40px; background: var(--proc-primary); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: .85rem; margin-right: 12px; flex-shrink: 0; }
    .team-info { flex: 1; }
    .team-info h6 { margin: 0 0 2px 0; font-weight: 600; font-size: .9rem; }
    .team-info small { color: #6c757d; }
    .lead-tag { background: #ffc107; color: #212529; font-size: .62rem; padding: 2px 6px; border-radius: 4px; margin-left: 6px; font-weight: 700; }

    /* ── Notes Timeline ──────────────────────── */
    .notes-filter-pills { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
    .note-pill { padding: 4px 12px; border-radius: 20px; border: 1px solid #dee2e6; background: #fff; font-size: .78rem; font-weight: 600; cursor: pointer; transition: all .15s; color: #495057; }
    .note-pill:hover { border-color: var(--proc-primary); color: var(--proc-primary); }
    .note-pill.active { background: var(--proc-primary); border-color: var(--proc-primary); color: #fff; }
    .quick-note-bar { padding: 12px 16px; background: #f0f7ff; border-bottom: 1px solid #d0e8ff; }
    .quick-note-bar textarea { resize: none; border-radius: 6px; font-size: .88rem; }
    .notes-timeline { padding: 16px; }
    .note-timeline-item { display: flex; gap: 12px; margin-bottom: 16px; }
    .note-timeline-item:last-child { margin-bottom: 0; }
    .note-timeline-left { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
    .note-type-dot { width: 12px; height: 12px; border-radius: 50%; margin-top: 4px; flex-shrink: 0; }
    .note-type-line { width: 2px; background: #e9ecef; flex: 1; margin-top: 4px; min-height: 20px; }
    .note-card { flex: 1; background: #f8f9fa; border-radius: 8px; overflow: hidden; border-left: 3px solid transparent; }
    .note-card-header { padding: 10px 14px; display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .note-card-title { font-weight: 600; font-size: .88rem; color: #333; }
    .note-card-meta { font-size: .75rem; color: #6c757d; }
    .note-card-body { padding: 0 14px 12px; font-size: .88rem; color: #444; }
    .note-card-body .note-content-preview { max-height: 80px; overflow: hidden; transition: max-height .3s; }
    .note-card-body .note-content-preview.expanded { max-height: 9999px; }
    .note-card-body .note-toggle { font-size: .75rem; color: var(--proc-primary); cursor: pointer; }
    /* Note type colors */
    .nt-pre_op     { background: #1976d2; }
    .nt-intra_op   { background: #7b1fa2; }
    .nt-post_op    { background: #388e3c; }
    .nt-anesthesia { background: #f57c00; }
    .nt-nursing    { background: #0097a7; }
    .nc-pre_op     { border-left-color: #1976d2; }
    .nc-intra_op   { border-left-color: #7b1fa2; }
    .nc-post_op    { border-left-color: #388e3c; }
    .nc-anesthesia { border-left-color: #f57c00; }
    .nc-nursing    { border-left-color: #0097a7; }

    /* ── OR Details Panel ────────────────────── */
    .or-panel { background: #f8f9fa; border-radius: 10px; padding: 16px; margin-bottom: 4px; }
    .or-panel.live { background: #fff5f5; border: 1px solid #f5c6cb; }
    .or-row { display: flex; flex-wrap: wrap; gap: 16px; }
    .or-cell { display: flex; flex-direction: column; align-items: center; padding: 12px 16px; background: #fff; border-radius: 8px; border: 1px solid #e9ecef; flex: 1; min-width: 90px; }
    .or-cell-icon { font-size: 1.3rem; color: var(--proc-primary); margin-bottom: 4px; }
    .or-cell-value { font-weight: 700; font-size: .95rem; color: #222; }
    .or-cell-label { font-size: .7rem; color: #6c757d; text-transform: uppercase; letter-spacing: .05em; margin-top: 2px; }
    .live-badge { display: inline-flex; align-items: center; gap: 6px; background: #dc3545; color: #fff; border-radius: 4px; padding: 3px 10px; font-size: .78rem; font-weight: 700; margin-bottom: 10px; }
    .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #fff; animation: blink 1s infinite; }
    @keyframes blink { 0%,100%{ opacity:1 } 50%{ opacity:.2 } }

    /* ── Action Sidebar ──────────────────────── */
    .next-action-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 16px; overflow: hidden; }
    .next-action-header { background: var(--proc-primary); color: #fff; padding: 12px 18px; font-weight: 600; font-size: .9rem; }
    .next-action-body { padding: 16px; }
    .next-action-body .btn { font-weight: 500; }
    .next-action-body .btn i { margin-right: 6px; }
    .danger-zone { border: 1px solid #f5c6cb; border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
    .danger-zone-toggle { width: 100%; background: #fff5f5; border: none; padding: 10px 14px; text-align: left; color: #721c24; font-size: .85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
    .danger-zone-body { padding: 12px 14px; display: none; }
    .danger-zone-body.open { display: block; }

    /* ── Consent Card (tab) ──────────────────── */
    .consent-big-card { border-radius: 10px; padding: 20px; margin-bottom: 16px; border: 2px solid; }
    .consent-big-card.cs-ok     { border-color: #28a745; background: #f0fff4; }
    .consent-big-card.cs-warn   { border-color: #fd7e14; background: #fff8f0; }
    .consent-big-card.cs-danger { border-color: #dc3545; background: #fff5f5; }
    .consent-big-card.cs-grey   { border-color: #adb5bd; background: #f8f9fa; }
    .consent-big-title { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .consent-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
    .consent-detail-item label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; margin-bottom: 2px; }
    .consent-detail-item span { font-size: .9rem; color: #333; }

    /* ── Attachment items ────────────────────── */
    .attachment-item { display: flex; align-items: center; gap: 12px; padding: 10px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; }
    .attachment-preview img { width: 64px; height: 52px; object-fit: cover; border-radius: 4px; }
    .attachment-preview .att-icon { width: 48px; height: 48px; background: #fff; border: 1px solid #dee2e6; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #6c757d; }
    .attachment-info { flex: 1; min-width: 0; }
    .attachment-info .att-name { font-weight: 600; font-size: .88rem; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .attachment-info .att-meta { font-size: .75rem; color: #6c757d; }

    /* ── Misc ────────────────────────────────── */
    .empty-state { text-align: center; padding: 32px 20px; color: #6c757d; }
    .empty-state i { font-size: 2rem; margin-bottom: 10px; opacity: .5; }
    .empty-state p { margin: 0; }
    .item-row { display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
    .item-row:last-child { border-bottom: none; }
    .item-icon { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 1rem; }
    .item-icon.lab { background: #e3f2fd; color: #1976d2; }
    .item-icon.imaging { background: #f3e5f5; color: #7b1fa2; }
    .item-icon.product { background: #e8f5e9; color: #388e3c; }
    .item-info { flex: 1; }
    .item-name { font-weight: 600; color: #333; margin-bottom: 2px; font-size: .88rem; }
    .item-code { font-size: .78rem; color: #6c757d; }
    .bundled-tag { font-size: .68rem; padding: 2px 7px; border-radius: 4px; margin-left: 6px; }
    .bundled-yes { background: #e3f2fd; color: #1565c0; }
    .bundled-no  { background: #fce4ec; color: #c62828; }
    .card-modern { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 12px; overflow: hidden; }
    .history-table-wrapper { max-height: 500px; overflow-y: auto; }
    .history-table-wrapper .dataTables_wrapper { padding: 0; }
    .history-table-wrapper table.dataTable { margin: 0 !important; }
    .history-table-wrapper table.dataTable thead { display: none; }
    .history-table-wrapper table.dataTable tbody td { padding: 0; border: none; }
    .history-table-wrapper .dataTables_info, .history-table-wrapper .dataTables_paginate { padding: 8px 16px; }
    .print-option-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
    .print-option-item:last-child { border-bottom: none; }
    .consent-option-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .consent-option-btn { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 2px solid #dee2e6; border-radius: 8px; cursor: pointer; transition: border-color .15s, background .15s; background: #fff; }
    .consent-option-btn:hover { border-color: #adb5bd; background: #f8f9fa; }
    .consent-option-btn.selected-obtained     { border-color: #28a745; background: #f0fff4; }
    .consent-option-btn.selected-waived       { border-color: #ffc107; background: #fffdf0; }
    .consent-option-btn.selected-not_required { border-color: #6c757d; background: #f8f9fa; }
    .consent-option-btn.selected-pending      { border-color: #fd7e14; background: #fff8f0; }
    .consent-option-btn .opt-label { font-weight: 600; font-size: .85rem; line-height: 1.2; display: block; }
    .consent-option-btn .opt-sub   { font-size: .75rem; color: #6c757d; display: block; }
    .notes-tabs { border-bottom: 1px solid #e9ecef; padding: 0 16px; background: #fafafa; }
    .notes-tabs .nav-link { border: none; color: #6c757d; font-weight: 500; padding: 12px 14px; font-size: .88rem; border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .notes-tabs .nav-link.active { color: var(--proc-primary); border-bottom-color: var(--proc-primary); background: transparent; }
    .notes-tabs .nav-link:hover { color: var(--proc-primary); }

    .chosen-rich-preview {
        margin-top: 8px;
        padding: 10px 12px;
        border: 1px solid #e7edf3;
        border-left: 3px solid var(--proc-primary);
        border-radius: 6px;
        background: #f8fbff;
    }
    .chosen-rich-preview .cr-title { font-size: .86rem; font-weight: 700; color: #2c3e50; }
    .chosen-rich-preview .cr-meta { margin-top: 5px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .chosen-rich-preview .cr-chip { font-size: .72rem; font-weight: 600; padding: 2px 7px; border-radius: 12px; background: #e9eef5; color: #4a5d73; }
    .chosen-rich-preview .cr-pay { color: #d97a00; font-weight: 700; font-size: .78rem; }
    .chosen-rich-preview .cr-claim { color: #1b8f4f; font-weight: 700; font-size: .78rem; }
    .chosen-rich-preview .cr-base { color: #6c757d; font-size: .75rem; }
    .chosen-rich-preview .cr-mode { font-size: .68rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; letter-spacing: .2px; }
    .chosen-rich-preview .cr-mode.mode-express { background: #d4edda; color: #155724; }
    .chosen-rich-preview .cr-mode.mode-primary { background: #cce5ff; color: #004085; }
    .chosen-rich-preview .cr-mode.mode-secondary { background: #fff3cd; color: #856404; }
    .chosen-rich-preview .cr-mode.mode-hmo { background: #d1ecf1; color: #0c5460; }
    .chosen-rich-preview .cr-mode.mode-cash { background: #f1f3f5; color: #495057; }

    /* BillingKit search dropdowns */
    #service-search-results,
    #lab-billing-search-results,
    #imaging-billing-search-results,
    #consumable-search-results {
        border: 1px solid #dee2e6;
        border-top: none;
        background: #fff;
        box-shadow: 0 6px 16px rgba(0, 0, 0, .15);
        border-radius: 0 0 8px 8px;
        padding: 0;
        list-style: none;
    }

    #service-search-results .list-group-item,
    #lab-billing-search-results .list-group-item,
    #imaging-billing-search-results .list-group-item,
    #consumable-search-results .list-group-item {
        cursor: pointer;
        border-left: 3px solid transparent;
        transition: all .15s ease;
        padding: 10px 14px;
    }

    #service-search-results .list-group-item:hover,
    #lab-billing-search-results .list-group-item:hover,
    #imaging-billing-search-results .list-group-item:hover,
    #consumable-search-results .list-group-item:hover {
        background: #f0f8ff;
        border-left-color: var(--proc-primary);
    }

    .billing-search-item-name { font-weight: 600; color: #2c3e50; font-size: .9rem; }
    .billing-search-item-meta { display: flex; align-items: center; gap: 8px; margin-top: 3px; flex-wrap: wrap; }
    .billing-search-item-price { font-weight: 600; color: #27ae60; font-size: .85rem; }
    .billing-search-item-badge { font-size: .7rem; padding: 2px 6px; border-radius: 3px; background: #eef2f7; color: #607d8b; }
    .billing-search-item-stock { font-size: .75rem; margin-left: auto; }

    .billing-search-hmo-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
        padding-top: 4px;
        border-top: 1px dashed #e0e0e0;
        font-size: .78rem;
        flex-wrap: wrap;
    }

    .billing-search-hmo-label { font-size: .7rem; color: #888; }
    .billing-search-hmo-payable { color: #e67e22; font-weight: 600; }
    .billing-search-hmo-claims { color: #27ae60; font-weight: 600; }
    .billing-search-hmo-mode {
        font-size: .68rem;
        padding: 1px 5px;
        border-radius: 3px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .billing-search-hmo-mode.mode-express { background: #d4edda; color: #155724; }
    .billing-search-hmo-mode.mode-primary { background: #cce5ff; color: #004085; }
    .billing-search-hmo-mode.mode-secondary { background: #fff3cd; color: #856404; }
    .billing-search-hmo-mode.mode-hmo { background: #d1ecf1; color: #0c5460; }
    .billing-search-hmo-mode.mode-cash { background: #f1f3f5; color: #495057; }

    .billing-search-no-results {
        padding: 16px;
        text-align: center;
        color: #999;
        font-style: italic;
    }

    /* Role-based ordering */
    [data-page-role="nurse"] .clinical-tab-flex { display: flex; flex-direction: column; }
    [data-page-role="nurse"] .clinical-or-panel  { order: 3; }
    [data-page-role="nurse"] .clinical-notes     { order: 1; }
    [data-page-role="nurse"] .clinical-team      { order: 2; }
    [data-page-role="doctor"] .clinical-tab-flex,
    [data-page-role="admin"]  .clinical-tab-flex { display: flex; flex-direction: column; }
    [data-page-role="doctor"] .clinical-or-panel,
    [data-page-role="admin"]  .clinical-or-panel { order: 1; }
    [data-page-role="doctor"] .clinical-team,
    [data-page-role="admin"]  .clinical-team     { order: 2; }
    [data-page-role="doctor"] .clinical-notes,
    [data-page-role="admin"]  .clinical-notes    { order: 3; }
</style>

<section class="container-fluid procedure-page" data-page-role="{{ $pageRole }}">

    {{-- ╔══════════════════════════════════════════════════════════╗
         ║  COMMAND BAR                                             ║
         ╚══════════════════════════════════════════════════════════╝ --}}
    <div class="cmd-bar">

        {{-- Row A — Back + Title + Badges --}}
        <div class="cmd-bar-title">
            <div>
                <a href="{{ route('surgery-workbench.index') }}" class="btn btn-sm btn-light mr-3" style="opacity:.85;">
                    <i class="fa fa-arrow-left mr-1"></i> Workbench
                </a>
                <h2 class="proc-name d-inline">{{ $procedure->service->service_name ?? 'Procedure' }}</h2>
                <div class="proc-meta mt-1">
                    <span><i class="fa fa-hashtag"></i> {{ $procedure->service->service_code ?? 'N/A' }}</span>
                    @if($procedure->procedureDefinition && $procedure->procedureDefinition->procedureCategory)
                        <span><i class="fa fa-folder"></i> {{ $procedure->procedureDefinition->procedureCategory->name }}</span>
                    @endif
                    @if($isSurgical)
                        <span class="badge-surgical"><i class="fa fa-cut mr-1"></i>SURGICAL</span>
                    @endif
                </div>
            </div>
            <div class="cmd-bar-right">
                <span class="badge-status badge-{{ $procedure->procedure_status }}">
                    @php
                        $statusLabels = ['requested'=>'Requested','scheduled'=>'Scheduled','in_progress'=>'In OR','completed'=>'Completed','cancelled'=>'Cancelled'];
                    @endphp
                    {{ $statusLabels[$procedure->procedure_status] ?? ucfirst($procedure->procedure_status) }}
                </span>
                <span class="badge-priority badge-{{ $procedure->priority }}">{{ ucfirst($procedure->priority) }}</span>
                <button class="btn btn-sm btn-light" onclick="openPrintSelectionModal()" title="Print Report" style="opacity:.85;">
                    <i class="fa fa-print"></i>
                </button>
            </div>
        </div>

        {{-- Row B — Consent Strip --}}
        @if(!$isCancelled)
            @if($consentInfo)
                <div class="consent-strip {{ $cs === 'obtained' ? 'cs-ok' : ($cs === 'pending' ? 'cs-warn' : 'cs-grey') }}">
                    <i class="fa {{ $consentInfo['icon'] }} fa-lg"></i>
                    <strong>{{ $consentInfo['label'] }}</strong>
                    @if($cs === 'obtained' && $procedure->consentMarkedBy)
                        <span class="cs-detail">by {{ optional($procedure->consentMarkedBy)->name }} on {{ $procedure->consent_marked_at?->format('d M Y H:i') }}</span>
                    @elseif($cs === 'waived' && $procedure->consent_notes)
                        <span class="cs-detail">— {{ $procedure->consent_notes }}</span>
                    @endif
                    @if(!$isCompleted)
                        @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
                        <div class="cs-action">
                            <button class="btn btn-sm {{ $consentNeedsAction ? 'btn-warning' : 'btn-outline-secondary' }}" onclick="openConsentModal()">
                                <i class="fa fa-clipboard-check mr-1"></i>{{ $consentNeedsAction ? 'Record Consent' : 'Change' }}
                            </button>
                        </div>
                        @endhasanyrole
                    @endif
                </div>
            @else
                <div class="consent-strip cs-danger">
                    <i class="fa fa-exclamation-triangle fa-lg"></i>
                    <strong>Consent Not Recorded</strong>
                    <span class="cs-detail">— Please document patient consent before proceeding</span>
                    @if(!$isCompleted)
                        @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
                        <div class="cs-action">
                            <button class="btn btn-sm btn-danger" onclick="openConsentModal()">
                                <i class="fa fa-clipboard-check mr-1"></i>Record Consent
                            </button>
                        </div>
                        @endhasanyrole
                    @endif
                </div>
            @endif
        @endif

        {{-- Row C — Status Stepper / Cancelled Bar --}}
        @if($isCancelled)
            <div class="cancelled-bar">
                <i class="fa fa-ban"></i>
                CANCELLED
                @if($procedure->cancelledByUser)
                    <span style="font-weight:400;">— by {{ optional($procedure->cancelledByUser)->name }}</span>
                @endif
                @if($procedure->cancelled_at)
                    <span style="font-weight:400;">on {{ $procedure->cancelled_at->format('d M Y H:i') }}</span>
                @endif
                @if($procedure->cancellation_reason)
                    <span style="font-weight:400; opacity:.8;">· "{{ $procedure->cancellation_reason }}"</span>
                @endif
                @if(($procedure->refund_amount ?? 0) > 0)
                    <span class="ml-auto text-success font-weight-600">Refund: ₦{{ number_format($procedure->refund_amount, 2) }}</span>
                @endif
            </div>
        @else
            @php
                $steps = [
                    ['key'=>'requested',   'label'=>'Requested',  'icon'=>'fa-clock'],
                    ['key'=>'scheduled',   'label'=>'Scheduled',  'icon'=>'fa-calendar-check'],
                    ['key'=>'in_progress', 'label'=>'In OR',      'icon'=>'fa-play-circle'],
                    ['key'=>'completed',   'label'=>'Completed',  'icon'=>'fa-check-circle'],
                ];
            @endphp
            <div class="status-stepper">
                @foreach($steps as $i => $step)
                    @php
                        $state = $i < $currentStep ? 'done' : ($i === $currentStep ? 'current' : 'future');
                        $t = $stepTimes[$i] ?? null;
                    @endphp
                    <div class="step-item">
                        <div class="step-dot {{ $state }}">
                            @if($state === 'done')
                                <i class="fa fa-check" style="font-size:.7rem;"></i>
                            @else
                                <i class="{{ $step['icon'] }}" style="font-size:.7rem;"></i>
                            @endif
                        </div>
                        <div class="step-label {{ $state }}">{{ $step['label'] }}</div>
                        <div class="step-time">{{ $t ? $t->format('d M H:i') : '—' }}</div>
                    </div>
                    @if(!$loop->last)
                        <div class="step-connector {{ $i < $currentStep ? 'done' : '' }}"></div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Row D — Patient Bar --}}
        <div class="patient-cmd-bar">
            @php
                $ptInitials = $pt ? (strtoupper(substr(optional($pt->user)->surname ?? 'P', 0, 1)) . strtoupper(substr(optional($pt->user)->firstname ?? '', 0, 1))) : 'P?';
            @endphp
            <div class="patient-avatar-sm">{{ $ptInitials }}</div>
            <div>
                <div class="patient-cmd-name">{{ $pt ? userfullname($pt->user_id) : 'Unknown Patient' }}</div>
                <div class="patient-cmd-meta">
                    @if($pt)
                        <span><i class="fa fa-id-card mr-1"></i>{{ $pt->file_no ?? 'N/A' }}</span>
                        <span><i class="fa fa-birthday-cake mr-1"></i>{{ $pt->dob ? \Carbon\Carbon::parse($pt->dob)->age . ' yrs' : 'N/A' }}</span>
                        @if($pt->hmo)<span><i class="fa fa-hospital mr-1"></i>{{ $pt->hmo->name }}</span>@endif
                    @endif
                </div>
            </div>
            <div class="ml-auto">
                <a href="{{ route('patient.show', $procedure->patient_id) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="fa fa-external-link-alt mr-1"></i>View Patient
                </a>
            </div>
        </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════╗
         ║  MAIN CONTENT + SIDEBAR                                 ║
         ╚══════════════════════════════════════════════════════════╝ --}}
    <div class="row">
        {{-- LEFT — Tabs (8 cols) --}}
        <div class="col-lg-8">
            <ul class="nav nav-tabs mb-3" id="mainProcTabs" style="border-bottom:2px solid #e9ecef;">
                <li class="nav-item">
                    <a class="nav-link {{ $pageRole !== 'nurse' ? 'active' : '' }}" data-toggle="tab" href="#tab-clinical" id="tab-clinical-link">
                        <i class="fa fa-stethoscope mr-1"></i>Clinical
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-orders" id="tab-orders-link">
                        <i class="fa fa-clipboard-list mr-1"></i>Orders
                        @if($totalItems > 0)<span class="badge badge-primary ml-1">{{ $totalItems }}</span>@endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $pageRole === 'nurse' ? 'active' : '' }}" data-toggle="tab" href="#tab-consent-billing" id="tab-consent-billing-link">
                        <i class="fa fa-clipboard-check mr-1"></i>Consent &amp; Billing
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-attachments" id="tab-attachments-link">
                        <i class="fa fa-paperclip mr-1"></i>Attachments
                        @if($procedure->attachments->count() > 0)<span class="badge badge-secondary ml-1">{{ $procedure->attachments->count() }}</span>@endif
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- ═══════════════════════════════════════════════
                     TAB 1 — CLINICAL
                     ═══════════════════════════════════════════════ --}}
                <div class="tab-pane fade {{ $pageRole !== 'nurse' ? 'show active' : '' }}" id="tab-clinical">
                    <div class="clinical-tab-flex">

                        {{-- OR Details Panel --}}
                        <div class="clinical-or-panel">
                            <div class="section-card">
                                <div class="section-card-header">
                                    <h5><i class="fa fa-hospital-alt"></i> Operating Room</h5>
                                    @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
                                    @if($procedure->procedure_status === 'requested')
                                        <button class="btn btn-sm btn-primary" onclick="openScheduleModal()">
                                            <i class="fa fa-calendar-plus mr-1"></i>Schedule
                                        </button>
                                    @elseif(in_array($procedure->procedure_status, ['scheduled','in_progress']))
                                        <button class="btn btn-sm btn-outline-secondary" onclick="openScheduleModal()">
                                            <i class="fa fa-pencil mr-1"></i>Edit
                                        </button>
                                    @endif
                                    @endhasanyrole
                                </div>
                                <div class="section-card-body">
                                    @if($procedure->procedure_status === 'requested' && !$procedure->scheduled_date)
                                        <div class="empty-state py-3">
                                            <i class="fa fa-calendar-plus"></i>
                                            <p class="mt-2">No schedule set yet</p>
                                            @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
                                            <button class="btn btn-sm btn-primary mt-2" onclick="openScheduleModal()">
                                                <i class="fa fa-calendar-plus mr-1"></i>Schedule Procedure
                                            </button>
                                            @endhasanyrole
                                        </div>
                                    @elseif($procedure->procedure_status === 'in_progress')
                                        <div class="or-panel live">
                                            <div class="live-badge">
                                                <span class="live-dot"></span>LIVE — IN OR
                                            </div>
                                            <div class="or-row">
                                                @if($procedure->operating_room)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-hospital-alt"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->operating_room }}</div>
                                                    <div class="or-cell-label">Room</div>
                                                </div>
                                                @endif
                                                @if($procedure->actual_start_time)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-play-circle"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->actual_start_time->format('H:i') }}</div>
                                                    <div class="or-cell-label">Started</div>
                                                </div>
                                                @endif
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-clock"></i></div>
                                                    <div class="or-cell-value" id="elapsed-timer">—</div>
                                                    <div class="or-cell-label">Elapsed</div>
                                                </div>
                                            </div>
                                        </div>
                                    @elseif($procedure->procedure_status === 'completed')
                                        <div class="or-panel">
                                            <div class="or-row">
                                                @if($procedure->operating_room)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-hospital-alt"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->operating_room }}</div>
                                                    <div class="or-cell-label">Room</div>
                                                </div>
                                                @endif
                                                @if($procedure->actual_start_time)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-play-circle"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->actual_start_time->format('H:i') }}</div>
                                                    <div class="or-cell-label">Started</div>
                                                </div>
                                                @endif
                                                @if($procedure->actual_end_time)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-stop-circle"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->actual_end_time->format('H:i') }}</div>
                                                    <div class="or-cell-label">Ended</div>
                                                </div>
                                                @endif
                                                @if($orDuration)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-hourglass-half"></i></div>
                                                    <div class="or-cell-value">{{ $orDuration }}</div>
                                                    <div class="or-cell-label">Duration</div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        {{-- Scheduled --}}
                                        <div class="or-panel">
                                            <div class="or-row">
                                                @if($procedure->operating_room)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-hospital-alt"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->operating_room }}</div>
                                                    <div class="or-cell-label">Room</div>
                                                </div>
                                                @endif
                                                @if($procedure->scheduled_date)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-calendar"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->scheduled_date->format('d M Y') }}</div>
                                                    <div class="or-cell-label">Date</div>
                                                </div>
                                                @endif
                                                @if($procedure->scheduled_time)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-clock"></i></div>
                                                    <div class="or-cell-value">{{ $procedure->scheduled_time }}</div>
                                                    <div class="or-cell-label">Time</div>
                                                </div>
                                                @endif
                                                @if($procedure->scheduled_date && $procedure->scheduled_time)
                                                <div class="or-cell">
                                                    <div class="or-cell-icon"><i class="fa fa-hourglass-start"></i></div>
                                                    <div class="or-cell-value" id="countdown-display">—</div>
                                                    <div class="or-cell-label">In</div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Surgical Team --}}
                        <div class="clinical-team">
                            <div class="section-card">
                                <div class="section-card-header">
                                    <h5><i class="fa fa-users"></i> Surgical Team</h5>
                                    @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
                                    <button class="btn btn-sm btn-primary" onclick="openAddTeamModal()">
                                        <i class="fa fa-user-plus mr-1"></i>Add Member
                                    </button>
                                    @endhasanyrole
                                </div>
                                <div class="section-card-body" id="team-members-container">
                                    @if(count($teamGroups) > 0)
                                        @foreach($teamGroups as $groupName => $members)
                                            <div class="team-group-label">{{ $groupName }}</div>
                                            @foreach($members as $member)
                                                <div class="team-member-item {{ $member->is_lead ? 'is-lead' : '' }}" id="team-member-{{ $member->id }}">
                                                    <div class="team-avatar">
                                                        {{ strtoupper(substr(optional($member->user)->surname ?? 'U', 0, 1)) }}{{ strtoupper(substr(optional($member->user)->firstname ?? '', 0, 1)) }}
                                                    </div>
                                                    <div class="team-info">
                                                        <h6>
                                                            {{ optional($member->user)->name ?? 'Unknown' }}
                                                            @if($member->is_lead)<span class="lead-tag">LEAD</span>@endif
                                                        </h6>
                                                        <small>{{ $member->role === 'other' ? $member->custom_role : ucwords(str_replace('_', ' ', $member->role)) }}</small>
                                                        @if($member->notes)<div class="text-muted small mt-1">{{ $member->notes }}</div>@endif
                                                    </div>
                                                    @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
                                                    <button class="btn btn-sm btn-outline-danger" onclick="removeTeamMember({{ $member->id }})">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                    @endhasanyrole
                                                </div>
                                            @endforeach
                                        @endforeach
                                    @else
                                        <div class="empty-state" id="no-team-message">
                                            <i class="fa fa-users"></i>
                                            <p>No team members assigned yet</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Notes Timeline --}}
                        <div class="clinical-notes">
                            <div class="section-card">
                                <div class="section-card-header">
                                    <h5><i class="fa fa-sticky-note"></i> Procedure Notes</h5>
                                    @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE')
                                    <button class="btn btn-sm btn-primary" onclick="openAddNoteModal()">
                                        <i class="fa fa-plus mr-1"></i>Add Note
                                    </button>
                                    @endhasanyrole
                                </div>

                                {{-- Quick Nursing Note (nurses only) --}}
                                @hasanyrole('NURSE|SURGERY')
                                @if(!$isCompleted && !$isCancelled)
                                <div class="quick-note-bar">
                                    <div class="form-group mb-1">
                                        <textarea class="form-control form-control-sm" id="quick-note-text" rows="2" placeholder="Quick nursing note…"></textarea>
                                    </div>
                                    <button class="btn btn-sm btn-primary" onclick="submitQuickNote()">
                                        <i class="fa fa-paper-plane mr-1"></i>Add Nursing Note
                                    </button>
                                </div>
                                @endif
                                @endhasanyrole

                                {{-- Filter pills --}}
                                @php
                                    $noteTypePills = [
                                        'all'       => 'All',
                                        'pre_op'    => 'Pre-Op',
                                        'intra_op'  => 'Intra-Op',
                                        'post_op'   => 'Post-Op',
                                        'anesthesia'=> 'Anesthesia',
                                        'nursing'   => 'Nursing',
                                    ];
                                @endphp
                                <div class="notes-filter-pills">
                                    @foreach($noteTypePills as $key => $label)
                                        <span class="note-pill {{ $key === 'all' ? 'active' : '' }}" data-filter="{{ $key }}" onclick="filterNotes('{{ $key }}')">{{ $label }}</span>
                                    @endforeach
                                </div>

                                {{-- Timeline --}}
                                @php
                                    $allNotes = $procedure->notes->sortByDesc('created_at');
                                    $noteTypeColors = [
                                        'pre_op'    => '#1976d2',
                                        'intra_op'  => '#7b1fa2',
                                        'post_op'   => '#388e3c',
                                        'anesthesia'=> '#f57c00',
                                        'nursing'   => '#0097a7',
                                    ];
                                    $noteTypeNames = [
                                        'pre_op'    => 'Pre-Op',
                                        'intra_op'  => 'Intra-Op',
                                        'post_op'   => 'Post-Op',
                                        'anesthesia'=> 'Anesthesia',
                                        'nursing'   => 'Nursing',
                                    ];
                                @endphp
                                @if($allNotes->count() > 0)
                                    <div class="notes-timeline" id="notes-timeline-container">
                                        @foreach($allNotes as $note)
                                            <div class="note-timeline-item" id="note-{{ $note->id }}" data-note-type="{{ $note->note_type }}">
                                                <div class="note-timeline-left">
                                                    <div class="note-type-dot nt-{{ $note->note_type }}"></div>
                                                    @if(!$loop->last)<div class="note-type-line"></div>@endif
                                                </div>
                                                <div class="note-card nc-{{ $note->note_type }} flex-fill">
                                                    <div class="note-card-header">
                                                        <div>
                                                            <div class="note-card-title">{{ $note->title }}</div>
                                                            <div class="note-card-meta">
                                                                <span class="badge badge-pill badge-light" style="border: 1px solid {{ $noteTypeColors[$note->note_type] ?? '#999' }}; color: {{ $noteTypeColors[$note->note_type] ?? '#999' }}; font-size:.7rem;">
                                                                    {{ $noteTypeNames[$note->note_type] ?? $note->note_type }}
                                                                </span>
                                                                <span class="ml-1">{{ optional($note->createdBy)->name ?? 'Unknown' }}</span>
                                                                <span class="ml-1">· {{ $note->created_at->format('d M Y H:i') }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center" style="gap:4px; flex-shrink:0;">
                                                            @if(auth()->id() === $note->created_by || auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'DOCTOR']))
                                                            <button class="btn btn-sm btn-link text-primary p-0" onclick="editNote({{ $note->id }})" title="Edit"><i class="fa fa-edit"></i></button>
                                                            <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteNote({{ $note->id }})" title="Delete"><i class="fa fa-trash"></i></button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="note-card-body">
                                                        <div class="note-content-preview" id="note-preview-{{ $note->id }}">{!! $note->content !!}</div>
                                                        <span class="note-toggle" onclick="toggleNotePreview({{ $note->id }})" id="note-toggle-{{ $note->id }}" style="display:none;">
                                                            Show more <i class="fa fa-chevron-down"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="empty-state py-4" id="notes-empty-state">
                                        <i class="fa fa-sticky-note"></i>
                                        <p>No notes yet</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>{{-- end clinical-tab-flex --}}
                </div>

                {{-- ═══════════════════════════════════════════════
                     TAB 2 — ORDERS & HISTORY
                     ═══════════════════════════════════════════════ --}}
                <div class="tab-pane fade" id="tab-orders">
                    {{-- Summary bar --}}
                    <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                        <div class="text-muted small">
                            <span class="mr-3"><i class="fa fa-flask text-primary mr-1"></i>{{ $labCount }} labs</span>
                            <span class="mr-3"><i class="fa fa-x-ray mr-1" style="color:#9c27b0"></i>{{ $imagingCount }} imaging</span>
                            <span><i class="fa fa-pills text-success mr-1"></i>{{ $medsCount }} medications</span>
                        </div>
                        @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
                        <div class="btn-group">
                            <button class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-plus mr-1"></i>Add Item
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="javascript:void(0)" onclick="openAddItemModal('lab')"><i class="fa fa-flask mr-2 text-primary"></i>Lab Request</a>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="openAddItemModal('imaging')"><i class="fa fa-x-ray mr-2" style="color:#9c27b0"></i>Imaging Request</a>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="openAddItemModal('medication')"><i class="fa fa-pills mr-2 text-success"></i>Medication</a>
                            </div>
                        </div>
                        @endhasanyrole
                    </div>

                    <div class="section-card">
                        <div class="section-card-body p-0">
                            <ul class="nav nav-tabs notes-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#proc-orders-labs">
                                        <i class="fa fa-flask text-primary"></i> Labs
                                        <span class="badge badge-primary ml-1" id="proc-labs-count">{{ $labCount }}</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#proc-orders-imaging">
                                        <i class="fa fa-x-ray" style="color:#9c27b0"></i> Imaging
                                        <span class="badge badge-primary ml-1" id="proc-imaging-count">{{ $imagingCount }}</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#proc-orders-meds">
                                        <i class="fa fa-pills text-success"></i> Medications
                                        <span class="badge badge-primary ml-1" id="proc-meds-count">{{ $medsCount }}</span>
                                    </a>
                                </li>
                                @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#proc-orders-services" id="services-rendered-tab">
                                        <i class="fa fa-receipt text-warning"></i> Services Rendered
                                    </a>
                                </li>
                                @endhasanyrole
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="proc-orders-labs">
                                    <div class="history-table-wrapper p-2">
                                        <table class="table table-borderless" id="procedure_lab_history" style="width:100%">
                                            <thead><tr><th><i class="mdi mdi-test-tube"></i> Laboratory Requests</th></tr></thead>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="proc-orders-imaging">
                                    <div class="history-table-wrapper p-2">
                                        <table class="table table-borderless" id="procedure_imaging_history" style="width:100%">
                                            <thead><tr><th><i class="mdi mdi-radioactive"></i> Imaging Requests</th></tr></thead>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="proc-orders-meds">
                                    <div class="history-table-wrapper p-2">
                                        <table class="table table-borderless" id="procedure_meds_history" style="width:100%">
                                            <thead><tr><th><i class="mdi mdi-pill"></i> Medication Requests</th></tr></thead>
                                        </table>
                                    </div>
                                </div>
                                @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
                                <div class="tab-pane fade" id="proc-orders-services">
                                    <div id="billing-kit-root"></div>
                                </div>
                                @endhasanyrole
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════
                     TAB 3 — CONSENT & BILLING
                     ═══════════════════════════════════════════════ --}}
                <div class="tab-pane fade {{ $pageRole === 'nurse' ? 'show active' : '' }}" id="tab-consent-billing">

                    {{-- Consent Detail Card --}}
                    <div class="section-card mb-3">
                        <div class="section-card-header">
                            <h5><i class="fa fa-clipboard-check"></i> Consent Documentation</h5>
                            @if(!$isCompleted && !$isCancelled)
                                @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
                                <button class="btn btn-sm {{ $consentNeedsAction ? 'btn-primary' : 'btn-outline-secondary' }}" onclick="openConsentModal()">
                                    <i class="fa fa-{{ $cs ? 'sync' : 'clipboard-check' }} mr-1"></i>{{ $cs ? 'Change Status' : 'Record Consent' }}
                                </button>
                                @endhasanyrole
                            @endif
                        </div>
                        <div class="section-card-body">
                            @if($cs)
                                @php
                                    $cbClass = ($cs === 'obtained') ? 'cs-ok' : (($cs === 'pending') ? 'cs-warn' : 'cs-grey');
                                    $cbColor = $consentInfo['color'] ?? '#6c757d';
                                    $cbIcon  = $consentInfo['icon'] ?? 'fa-question-circle';
                                    $cbLabel = $consentInfo['label'] ?? ucfirst($cs);
                                @endphp
                                <div class="consent-big-card {{ $cbClass }}">
                                    <div class="consent-big-title" style="color:{{ $cbColor }}">
                                        <i class="fa {{ $cbIcon }} fa-lg"></i>
                                        {{ $cbLabel }}
                                    </div>
                                    <div class="consent-detail-grid">
                                        <div class="consent-detail-item">
                                            <label>Marked By</label>
                                            <span>{{ optional($procedure->consentMarkedBy)->name ?? '—' }}</span>
                                        </div>
                                        <div class="consent-detail-item">
                                            <label>Date &amp; Time</label>
                                            <span>{{ $procedure->consent_marked_at?->format('d M Y H:i') ?? '—' }}</span>
                                        </div>
                                        @if($procedure->consent_notes)
                                        <div class="consent-detail-item" style="grid-column:span 2;">
                                            <label>Notes / Reason</label>
                                            <span>{{ $procedure->consent_notes }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="consent-big-card cs-danger">
                                    <div class="consent-big-title" style="color:#dc3545;">
                                        <i class="fa fa-exclamation-triangle fa-lg"></i>
                                        Consent Not Recorded
                                    </div>
                                    <p class="text-muted mb-3">Patient consent has not been documented for this procedure.</p>
                                    @if(!$isCompleted && !$isCancelled)
                                        @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
                                        <button class="btn btn-primary" onclick="openConsentModal()">
                                            <i class="fa fa-clipboard-check mr-1"></i>Record Consent Now
                                        </button>
                                        @endhasanyrole
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Billing Status Card --}}
                    <div class="section-card">
                        <div class="section-card-header">
                            <h5><i class="fa fa-file-invoice-dollar"></i> Billing Status</h5>
                        </div>
                        <div class="section-card-body">
                            @if($procedure->productOrServiceRequest)
                                @php
                                    $billing = $procedure->productOrServiceRequest;
                                    $payment = $billing->payment;
                                @endphp
                                <div class="billing-grid">
                                    <div class="billing-box">
                                        <div class="billing-row">
                                            <span>Procedure Fee</span>
                                            <strong>₦{{ number_format(($billing->amount ?? 0) + ($billing->claims_amount ?? 0), 2) }}</strong>
                                        </div>
                                        @if(($billing->claims_amount ?? 0) > 0)
                                            <div class="billing-row">
                                                <span>Patient Pays</span>
                                                <span>₦{{ number_format($billing->amount ?? 0, 2) }}</span>
                                            </div>
                                            <div class="billing-row">
                                                <span>HMO Claims</span>
                                                <span>₦{{ number_format($billing->claims_amount ?? 0, 2) }}</span>
                                            </div>
                                        @endif
                                        <div class="billing-row total">
                                            <span>Payment</span>
                                            @if($payment)
                                                <span class="text-success"><i class="fa fa-check-circle"></i> PAID</span>
                                            @else
                                                <span class="text-warning"><i class="fa fa-clock"></i> UNPAID</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="billing-box">
                                        <div class="info-item">
                                            <span class="info-label">Coverage</span>
                                            <span class="info-value"><span class="badge badge-{{ ($billing->coverage_mode ?? '') === 'hmo' ? 'info' : 'secondary' }}">{{ strtoupper($billing->coverage_mode ?? 'CASH') }}</span></span>
                                        </div>
                                        @if(($billing->coverage_mode ?? '') === 'hmo' || ($billing->claims_amount ?? 0) > 0)
                                            <div class="info-item">
                                                <span class="info-label">Validation</span>
                                                <span class="info-value">
                                                    @php $vs = $billing->validation_status ?? 'pending'; @endphp
                                                    <span class="badge badge-{{ $vs === 'approved' ? 'success' : ($vs === 'rejected' ? 'danger' : 'warning') }}">{{ strtoupper($vs) }}</span>
                                                </span>
                                            </div>
                                            @if($billing->auth_code)
                                                <div class="info-item">
                                                    <span class="info-label">Auth Code</span>
                                                    <span class="info-value"><code>{{ $billing->auth_code }}</code></span>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info mb-0"><i class="fa fa-info-circle mr-1"></i>No billing entry found for this procedure.</div>
                            @endif
                        </div>
                    </div>

                </div>

                {{-- ═══════════════════════════════════════════════
                     TAB 4 — ATTACHMENTS
                     ═══════════════════════════════════════════════ --}}
                <div class="tab-pane fade" id="tab-attachments">
                    <div class="section-card">
                        <div class="section-card-header">
                            <h5><i class="fa fa-paperclip"></i> Attachments</h5>
                            <span class="badge badge-secondary" id="attachment-count">{{ $procedure->attachments->count() }}</span>
                        </div>
                        <div class="section-card-body">
                            <div id="attachments-list">
                                @forelse($procedure->attachments as $att)
                                    <div class="attachment-item" id="attachment-row-{{ $att->id }}">
                                        <div class="attachment-preview">
                                            @if(in_array(strtolower(pathinfo($att->original_name, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']))
                                                <img src="{{ route('patient-procedures.attachments.download', [$procedure->id, $att->id]) }}" alt="{{ $att->original_name }}">
                                            @else
                                                <div class="att-icon">
                                                    <i class="fa fa-{{ strtolower(pathinfo($att->original_name, PATHINFO_EXTENSION)) === 'pdf' ? 'file-pdf text-danger' : 'file-alt' }}"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="attachment-info">
                                            <div class="att-name">{{ $att->original_name }}</div>
                                            <div class="att-meta">
                                                @if($att->label)<span class="badge badge-info mr-1">{{ $att->label }}</span>@endif
                                                {{ $att->formattedSize() }} · {{ optional($att->uploadedBy)->name ?? 'Unknown' }} · {{ $att->created_at->format('d M Y H:i') }}
                                            </div>
                                        </div>
                                        <div class="d-flex flex-shrink-0" style="gap:4px;">
                                            <a href="{{ route('patient-procedures.attachments.download', [$procedure->id, $att->id]) }}" class="btn btn-sm btn-outline-primary" title="Download"><i class="fa fa-download"></i></a>
                                            @if(auth()->id() === $att->uploaded_by || auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'DOCTOR']))
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAttachment({{ $att->id }})" title="Delete"><i class="fa fa-trash"></i></button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small" id="no-attachments-msg">No attachments yet.</p>
                                @endforelse
                            </div>
                            @hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
                            <hr>
                            <form id="upload-attachment-form" enctype="multipart/form-data">
                                @csrf
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold">Upload File</label>
                                    <input type="file" class="form-control-file" id="attachment-file" accept=".pdf,.jpg,.jpeg,.png,.docx" required>
                                    <small class="text-muted">Max 10 MB — Accepted: PDF, JPG, PNG, DOCX</small>
                                </div>
                                <div class="form-group mb-2">
                                    <input type="text" class="form-control form-control-sm" id="attachment-label" placeholder="Label (optional)" maxlength="100">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-upload mr-1"></i>Upload</button>
                            </form>
                            @endhasanyrole
                        </div>
                    </div>
                </div>
            </div>{{-- end tab-content --}}
        </div>

        {{-- RIGHT — Action Sidebar (4 cols) --}}
        <div class="col-lg-4">

            {{-- Next Action Card --}}
            @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
            @if(!$isCancelled)
            <div class="next-action-card">
                <div class="next-action-header">
                    <i class="fa fa-cogs mr-2"></i>
                    @if($procedure->procedure_status === 'requested') Schedule Procedure
                    @elseif($procedure->procedure_status === 'scheduled') Start Procedure
                    @elseif($procedure->procedure_status === 'in_progress') Complete Procedure
                    @elseif($procedure->procedure_status === 'completed') Procedure Complete
                    @endif
                </div>
                <div class="next-action-body">
                    @if($procedure->procedure_status === 'requested')
                        <button class="btn btn-info btn-block mb-2" onclick="openScheduleModal()">
                            <i class="fa fa-calendar-check"></i> Schedule &amp; Set OR
                        </button>
                        <button class="btn btn-success btn-block mb-2" onclick="confirmAction('complete', 'Mark as Complete', 'Mark this procedure as completed?', 'Use only if the procedure is already done.', 'success', 'check-circle')">
                            <i class="fa fa-check-circle"></i> Mark as Complete
                        </button>
                    @elseif($procedure->procedure_status === 'scheduled')
                        @if($isSurgical && $consentNeedsAction)
                            <div class="alert alert-warning py-2 small mb-2">
                                <i class="fa fa-exclamation-triangle mr-1"></i><strong>Consent pending.</strong> Obtain consent before starting.
                            </div>
                        @endif
                        <button class="btn btn-warning btn-block mb-2" onclick="confirmAction('in_progress', 'Start Procedure', 'Start this procedure now?', 'The procedure will be marked as in progress.', 'warning', 'play-circle')">
                            <i class="fa fa-play-circle"></i> Start Procedure
                        </button>
                        <button class="btn btn-success btn-block mb-2" onclick="confirmAction('complete', 'Mark as Complete', 'Mark this procedure as completed?', '', 'success', 'check-circle')">
                            <i class="fa fa-check-circle"></i> Mark as Complete
                        </button>
                        <button class="btn btn-outline-secondary btn-block mb-2" onclick="confirmAction('requested', 'Revert to Requested', 'Revert this procedure to Requested?', 'This will unschedule the procedure.', 'secondary', 'undo')">
                            <i class="fa fa-undo"></i> Revert to Requested
                        </button>
                    @elseif($procedure->procedure_status === 'in_progress')
                        <button class="btn btn-success btn-block mb-2" onclick="confirmAction('complete', 'Mark as Complete', 'Mark this procedure as completed?', 'Ensure all notes and outcome are recorded.', 'success', 'check-circle')">
                            <i class="fa fa-check-circle"></i> Mark as Complete
                        </button>
                        <button class="btn btn-outline-secondary btn-block mb-2" onclick="confirmAction('scheduled', 'Revert to Scheduled', 'Revert back to Scheduled?', 'The procedure will no longer be marked as in progress.', 'secondary', 'undo')">
                            <i class="fa fa-undo"></i> Revert to Scheduled
                        </button>
                    @elseif($procedure->procedure_status === 'completed')
                        <div class="alert alert-success py-2 small mb-2">
                            <i class="fa fa-check-circle mr-1"></i>Procedure completed.
                            @if($procedure->actual_end_time)on {{ $procedure->actual_end_time->format('d M Y H:i') }}@endif
                        </div>
                        <button class="btn btn-outline-warning btn-block mb-2" onclick="confirmAction('in_progress', 'Reopen Procedure', 'Reopen this completed procedure?', 'Status will go back to In Progress.', 'warning', 'redo')">
                            <i class="fa fa-redo"></i> Reopen Procedure
                        </button>
                    @endif
                </div>
            </div>
            @endif
            @endhasanyrole

            {{-- Outcome Card --}}
            @if(in_array($procedure->procedure_status, ['in_progress', 'completed']))
            <div class="section-card">
                <div class="section-card-header">
                    <h5><i class="fa fa-flag-checkered"></i> Outcome</h5>
                    @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
                    @if($procedure->outcome)
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleOutcomeEdit()">
                            <i class="fa fa-pencil"></i> Edit
                        </button>
                    @endif
                    @endhasanyrole
                </div>
                <div class="section-card-body">
                    <div id="outcome-display" style="{{ $procedure->outcome ? '' : 'display:none;' }}">
                        @if($procedure->outcome)
                            @php
                                $outcomeColors = ['successful'=>'success','complications'=>'warning','aborted'=>'danger','converted'=>'info'];
                                $outcomeIcons  = ['successful'=>'check-circle','complications'=>'exclamation-triangle','aborted'=>'times-circle','converted'=>'exchange-alt'];
                                $oColor = $outcomeColors[$procedure->outcome] ?? 'secondary';
                                $oIcon  = $outcomeIcons[$procedure->outcome] ?? 'flag';
                            @endphp
                            <div style="border-left:4px solid; padding:14px; border-radius:6px; background:#f8f9fa;" class="border-{{ $oColor }}">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge badge-{{ $oColor }} px-3 py-2" style="font-size:.9rem;">
                                        <i class="fa fa-{{ $oIcon }} mr-1"></i>
                                        {{ \App\Models\Procedure::OUTCOMES[$procedure->outcome] ?? ucfirst($procedure->outcome) }}
                                    </span>
                                    <small class="text-muted ml-auto"><i class="fa fa-clock"></i> {{ $procedure->updated_at?->diffForHumans() }}</small>
                                </div>
                                @if($procedure->outcome_notes)
                                    <div class="mt-2 p-2 bg-white rounded border">
                                        <small class="text-muted d-block mb-1"><i class="fa fa-sticky-note"></i> Outcome Notes:</small>
                                        <p class="mb-0 small">{{ $procedure->outcome_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="empty-state py-2"><i class="fa fa-flag"></i><p>No outcome recorded</p></div>
                        @endif
                    </div>
                    @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
                    <div id="outcome-form-wrapper" style="{{ $procedure->outcome ? 'display:none;' : '' }}">
                        <form id="outcome-form">
                            <div class="form-group">
                                <label for="outcome">Outcome <span class="text-danger">*</span></label>
                                <select class="form-control" id="outcome" name="outcome" required>
                                    <option value="">-- Select Outcome --</option>
                                    @foreach(\App\Models\Procedure::OUTCOMES as $key => $label)
                                        <option value="{{ $key }}" {{ $procedure->outcome === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="outcome_notes">Outcome Notes</label>
                                <textarea class="form-control" id="outcome_notes" name="outcome_notes" rows="3" placeholder="Relevant notes about the outcome…">{{ $procedure->outcome_notes }}</textarea>
                            </div>
                            <div class="d-flex">
                                @if($procedure->outcome)
                                    <button type="button" class="btn btn-secondary mr-2" onclick="toggleOutcomeEdit()"><i class="fa fa-times"></i> Cancel</button>
                                @endif
                                <button type="submit" class="btn btn-success flex-grow-1"><i class="fa fa-save mr-1"></i>Save Outcome</button>
                            </div>
                        </form>
                    </div>
                    @else
                    @if(!$procedure->outcome)
                    <div class="empty-state py-2"><i class="fa fa-flag"></i><p>No outcome recorded<br><small>Only doctors can record outcomes</small></p></div>
                    @endif
                    @endhasanyrole
                </div>
            </div>
            @endif

            {{-- Quick Details --}}
            <div class="section-card">
                <div class="section-card-header">
                    <h5><i class="fa fa-info-circle"></i> Details</h5>
                </div>
                <div class="section-card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Requested By</span>
                            <span class="info-value">{{ optional($procedure->requestedByUser)->name ?? 'N/A' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Requested On</span>
                            <span class="info-value">{{ $procedure->requested_on ? $procedure->requested_on->format('d M Y H:i') : 'N/A' }}</span>
                        </div>
                        @if($procedure->scheduled_date)
                        <div class="info-item">
                            <span class="info-label">Scheduled</span>
                            <span class="info-value">{{ $procedure->scheduled_date->format('d M Y') }}@if($procedure->scheduled_time) · {{ $procedure->scheduled_time }}@endif</span>
                        </div>
                        @endif
                        @if($procedure->operating_room)
                        <div class="info-item">
                            <span class="info-label">OR Room</span>
                            <span class="info-value">{{ $procedure->operating_room }}</span>
                        </div>
                        @endif
                        @if($isSurgical)
                        <div class="info-item">
                            <span class="info-label">Type</span>
                            <span class="info-value"><span class="badge badge-danger">SURGICAL</span></span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cancel Danger Zone --}}
            @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
            @if(!$isCancelled && !$isCompleted)
            <div class="danger-zone">
                <button class="danger-zone-toggle" onclick="toggleDangerZone()">
                    <i class="fa fa-exclamation-triangle"></i> Danger Zone
                    <i class="fa fa-chevron-down ml-auto" id="danger-zone-chevron"></i>
                </button>
                <div class="danger-zone-body" id="danger-zone-body">
                    <button class="btn btn-danger btn-block" onclick="openCancelModal()">
                        <i class="fa fa-times-circle mr-1"></i>Cancel Procedure
                    </button>
                </div>
            </div>
            @endif
            @endhasanyrole

            {{-- Print button --}}
            <button class="btn btn-outline-secondary btn-block mb-3" onclick="openPrintSelectionModal()">
                <i class="fa fa-print mr-1"></i>Print Report
            </button>

        </div>
    </div>
</section>

{{-- ╔══════════════════════════════════════════════════════════╗
     ║  MODALS                                                  ║
     ╚══════════════════════════════════════════════════════════╝ --}}

{{-- Print Selection Modal --}}
<div class="modal fade" id="printSelectionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-print mr-2"></i>Print Procedure Report</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Select what to include in the printed report:</p>
                <div class="form-check print-option-item">
                    <input class="form-check-input print-option" type="checkbox" id="print-details" name="sections" value="details" checked>
                    <label class="form-check-label" for="print-details"><i class="fa fa-info-circle mr-1 text-primary"></i> Procedure Details</label>
                </div>
                <div class="form-check print-option-item">
                    <input class="form-check-input print-option" type="checkbox" id="print-team" name="sections" value="team">
                    <label class="form-check-label" for="print-team"><i class="fa fa-users mr-1 text-success"></i> Surgical Team</label>
                </div>
                <div class="form-check print-option-item">
                    <input class="form-check-input print-option" type="checkbox" id="print-notes" name="sections" value="notes">
                    <label class="form-check-label" for="print-notes"><i class="fa fa-sticky-note mr-1 text-warning"></i> Procedure Notes</label>
                </div>
                <div class="form-check print-option-item">
                    <input class="form-check-input print-option" type="checkbox" id="print-items" name="sections" value="items">
                    <label class="form-check-label" for="print-items"><i class="fa fa-clipboard-list mr-1 text-info"></i> Orders &amp; Items</label>
                </div>
                <div class="form-check print-option-item">
                    <input class="form-check-input print-option" type="checkbox" id="print-consent" name="sections" value="consent">
                    <label class="form-check-label" for="print-consent"><i class="fa fa-clipboard-check mr-1 text-danger"></i> Consent</label>
                </div>
                <div class="mt-2">
                    <button class="btn btn-link btn-sm p-0" onclick="selectAllPrintOptions()">Select All</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="executePrint()">
                    <i class="fa fa-print mr-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Schedule Procedure Modal (NEW) --}}
<div class="modal fade" id="scheduleProcedureModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-calendar-plus mr-2"></i>Schedule Procedure</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="scheduleProcedureForm">
                    <div class="form-group">
                        <label for="schedule_date">Scheduled Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="schedule_date" name="scheduled_date" value="{{ $procedure->scheduled_date?->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="schedule_time">Scheduled Time <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="schedule_time" name="scheduled_time" value="{{ $procedure->scheduled_time }}" required>
                    </div>
                    <div class="form-group">
                        <label for="operating_room">Operating Room <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="operating_room" name="operating_room" placeholder="e.g. Theatre 1" value="{{ $procedure->operating_room }}" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-submit-schedule">
                    <i class="fa fa-calendar-check mr-1"></i>Confirm Schedule
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Add Team Member Modal --}}
<div class="modal fade" id="addTeamModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-user-plus mr-2"></i>Add Team Member</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addTeamForm">
                    <div class="form-group">
                        <label for="team_user_id">Staff Member <span class="text-danger">*</span></label>
                        <select class="form-control chosen-select" id="team_user_id" name="user_id" required>
                            <option value="">-- Select Staff --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="team_role">Role <span class="text-danger">*</span></label>
                        <select class="form-control" id="team_role" name="role" required onchange="toggleCustomRoleField()">
                            <option value="">-- Select Role --</option>
                            @foreach(\App\Models\ProcedureTeamMember::ROLES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="custom-role-group" style="display:none;">
                        <label for="team_custom_role">Custom Role <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="team_custom_role" name="custom_role" placeholder="Specify role…" maxlength="100">
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="team_is_lead" name="is_lead" value="1">
                            <label class="custom-control-label" for="team_is_lead">Mark as Lead / Primary</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="team_notes">Notes <small class="text-muted">(optional)</small></label>
                        <input type="text" class="form-control" id="team_notes" name="notes" placeholder="Optional notes…" maxlength="255">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" form="addTeamForm" class="btn btn-primary">
                    <i class="fa fa-user-plus mr-1"></i>Add Member
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Add Note Modal --}}
<div class="modal fade" id="addNoteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="noteModalTitle"><i class="fa fa-sticky-note mr-2"></i>Add Procedure Note</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addNoteForm">
                    <input type="hidden" id="edit_note_id" name="_note_id" value="">
                    <div class="form-group">
                        <label for="note_type">Note Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="note_type" name="note_type" required>
                            <option value="pre_op">Pre-Op</option>
                            <option value="intra_op">Intra-Op</option>
                            <option value="post_op">Post-Op</option>
                            <option value="anesthesia">Anesthesia</option>
                            <option value="nursing">Nursing</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="note_title">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="note_title" name="title" placeholder="Note title…" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="note_content">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="note_content" name="content" rows="6" placeholder="Enter note content…"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" form="addNoteForm" class="btn btn-primary" id="noteSubmitBtn">
                    <i class="fa fa-save mr-1"></i>Save Note
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Add Item Modal --}}
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalTitle"><i class="fa fa-clipboard-list mr-2"></i>Add Item</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addItemForm">
                    <input type="hidden" id="item_type" name="item_type" value="">
                    {{-- Service section --}}
                    <div id="item-service-section" class="item-type-section" style="display:none;">
                        <div class="form-group">
                            <label>Service / Lab / Imaging <span class="text-danger">*</span></label>
                            <select class="form-control chosen-select" id="item_service_id" name="service_id">
                                <option value="">-- Type to search --</option>
                            </select>
                            <div id="item_service_preview" class="chosen-rich-preview d-none"></div>
                        </div>
                    </div>
                    {{-- Product section --}}
                    <div id="item-product-section" class="item-type-section" style="display:none;">
                        <div class="form-group">
                            <label>Product / Medication <span class="text-danger">*</span></label>
                            <select class="form-control chosen-select" id="item_product_id" name="product_id">
                                <option value="">-- Type to search --</option>
                            </select>
                            <div id="item_product_preview" class="chosen-rich-preview d-none"></div>
                        </div>
                        <div class="form-group">
                            <label>Batch <span class="text-danger">*</span></label>
                            <select class="form-control" id="item_batch_id" name="batch_id">
                                <option value="">-- Select product first --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="item_quantity" name="quantity" value="1" min="1" step="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="item_is_bundled" name="is_bundled" value="1">
                            <label class="custom-control-label" for="item_is_bundled">Bundled (included in procedure fee)</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="item_notes">Notes <small class="text-muted">(optional)</small></label>
                        <input type="text" class="form-control" id="item_notes" name="notes" placeholder="Optional notes…">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" form="addItemForm" class="btn btn-primary">
                    <i class="fa fa-plus mr-1"></i>Add
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Cancel Procedure Modal --}}
<div class="modal fade" id="cancelProcedureModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-ban mr-2"></i>Cancel Procedure</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    This action cannot easily be undone. Please confirm.
                </div>
                <form id="cancelProcedureForm">
                    <div class="form-group">
                        <label for="cancellation_reason">Reason for Cancellation <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" placeholder="Reason for cancellation…" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="refund_amount">Refund Amount (if any)</label>
                        <input type="number" class="form-control" id="refund_amount" name="refund_amount" value="0" min="0" step="0.01">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Keep Procedure</button>
                <button type="submit" form="cancelProcedureForm" class="btn btn-danger">
                    <i class="fa fa-ban mr-1"></i>Cancel Procedure
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Consent Modal --}}
<div class="modal fade" id="consentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-clipboard-check mr-2"></i>Record Consent</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Select the consent status for this procedure:</p>
                <form id="consentForm" method="POST" action="{{ route('patient-procedures.consent.update', $procedure->id) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" id="consent_status_value" name="consent_status" value="{{ $procedure->consent_status }}">
                    <div class="consent-option-grid mb-3">
                        @php
                            $consentOpts = [
                                'obtained'     => ['label'=>'Consent Obtained','sub'=>'Patient/guardian agreed','icon'=>'fa-check-circle','color'=>'#28a745'],
                                'pending'      => ['label'=>'Pending','sub'=>'Consent being obtained','icon'=>'fa-clock','color'=>'#fd7e14'],
                                'waived'       => ['label'=>'Waived','sub'=>'Patient declined consent','icon'=>'fa-minus-circle','color'=>'#ffc107'],
                                'not_required' => ['label'=>'Not Required','sub'=>'Consent not applicable','icon'=>'fa-ban','color'=>'#6c757d'],
                            ];
                        @endphp
                        @foreach($consentOpts as $optKey => $opt)
                            <div class="consent-option-btn {{ $procedure->consent_status === $optKey ? 'selected-'.$optKey : '' }}"
                                 onclick="selectConsentOption('{{ $optKey }}')" id="consent-opt-{{ $optKey }}">
                                <i class="fa {{ $opt['icon'] }} fa-lg" style="color:{{ $opt['color'] }};"></i>
                                <div>
                                    <span class="opt-label">{{ $opt['label'] }}</span>
                                    <span class="opt-sub">{{ $opt['sub'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-group">
                        <label for="consent_notes">Notes / Reason <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="consent_notes" name="consent_notes" rows="2" placeholder="Additional consent notes…">{{ $procedure->consent_notes }}</textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-consent-btn" onclick="executeConsentUpdate()">
                    <i class="fa fa-save mr-1"></i>Save Consent
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Confirm Action Modal --}}
<div class="modal fade" id="confirmActionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header" id="confirmActionHeader">
                <h5 class="modal-title" id="confirmActionTitle">Confirm</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionMessage" class="mb-1">Are you sure?</p>
                <p id="confirmActionSub" class="text-muted small mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
{{-- Chosen --}}
<script src="{{ asset('assets/js/chosen.jquery.min.js') }}"></script>
{{-- CKEditor --}}
<script src="{{ asset('js/ckeditor5/ckeditor.js') }}"></script>

{{-- BillingKit Config — must be emitted before billing-shared.js --}}
@hasanyrole('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY')
<script>
window.BILLING_KIT_CONFIG = {
    csrf:                  "{{ csrf_token() }}",
    addServiceRoute:       "{{ route("patient-procedures.items.service", $procedure->id) }}",
    addLabRoute:           "{{ route("patient-procedures.items.lab", $procedure->id) }}",
    addImagingRoute:       "{{ route("patient-procedures.items.imaging", $procedure->id) }}",
    addConsumableRoute:    "{{ route("patient-procedures.items.medication", $procedure->id) }}",
    removeBillBase:        "/patient-procedures/{{ $procedure->id }}/items",
    pendingBillsBase:      "/patient-procedures/{{ $procedure->id }}",
    searchServicesRoute:   "{{ route("nursing-workbench.search-services") }}",
    searchProductsRoute:   "{{ route("nursing-workbench.search-products") }}",
    productBatchesRoute:   "{{ route("nursing-workbench.product-batches") }}",
    resolvedStoreId:       "{{ $resolvedStore?->id ?? "" }}",
    resolvedStoreName:     "{{ $resolvedStore?->name ?? "" }}",
    accessibleStores:      {!! json_encode($accessibleStores ?? []) !!},
    showMedicationOption:  true,
    canBundle:             true,
    procedureId:           {{ $procedure->id }},
};
</script>
<script src="{{ asset('js/billing-shared.js') }}"></script>
@endhasanyrole

<script>
const procedureId = {{ $procedure->id }};
const patientId   = {{ $procedure->patient_id }};
const labCategoryId     = {{ appsettings('investigation_category_id') ?? 0 }};
const imagingCategoryId = {{ appsettings('imaging_category_id') ?? 0 }};
let noteEditorInstance = null;

/* ═══════════════ TIMERS ═══════════════ */
@if($procedure->procedure_status === 'in_progress' && $procedure->actual_start_time)
(function() {
    const startMs = {{ $procedure->actual_start_time->timestamp }} * 1000;
    function tick() {
        const elapsed = Math.floor((Date.now() - startMs) / 1000);
        const h = Math.floor(elapsed / 3600);
        const m = Math.floor((elapsed % 3600) / 60);
        const s = elapsed % 60;
        const el = document.getElementById('elapsed-timer');
        if (el) el.textContent = (h > 0 ? h + 'h ' : '') + String(m).padStart(2,'0') + 'm ' + String(s).padStart(2,'0') + 's';
    }
    tick();
    setInterval(tick, 1000);
})();
@endif

@if($procedure->procedure_status === 'scheduled' && $procedure->scheduled_date && $procedure->scheduled_time)
(function() {
    const targetMs = new Date('{{ $procedure->scheduled_date->format('Y-m-d') }}T{{ $procedure->scheduled_time }}:00').getTime();
    function tick() {
        const diff = targetMs - Date.now();
        const el = document.getElementById('countdown-display');
        if (!el) return;
        if (diff <= 0) { el.textContent = 'Now'; return; }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        el.textContent = (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm';
    }
    tick();
    setInterval(tick, 60000);
})();
@endif

/* ═══════════════ NOTE TOGGLE ═══════════════ */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.note-content-preview').forEach(function(el) {
        if (el.scrollHeight > 90) {
            const noteId = el.id.replace('note-preview-', '');
            const toggle = document.getElementById('note-toggle-' + noteId);
            if (toggle) toggle.style.display = 'inline';
        }
    });
});

function toggleNotePreview(noteId) {
    const el = document.getElementById('note-preview-' + noteId);
    const toggle = document.getElementById('note-toggle-' + noteId);
    if (!el) return;
    el.classList.toggle('expanded');
    toggle.innerHTML = el.classList.contains('expanded')
        ? 'Show less <i class="fa fa-chevron-up"></i>'
        : 'Show more <i class="fa fa-chevron-down"></i>';
}

/* ═══════════════ NOTE FILTER ═══════════════ */
function filterNotes(type) {
    document.querySelectorAll('.note-pill').forEach(p => {
        p.classList.toggle('active', p.dataset.filter === type);
    });
    document.querySelectorAll('.note-timeline-item').forEach(function(el) {
        if (type === 'all' || el.dataset.noteType === type) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });
}

/* ═══════════════ TABS — Default by role ═══════════════ */
document.addEventListener('DOMContentLoaded', function() {
    @if($pageRole === 'nurse')
        const nurseTab = document.getElementById('tab-consent-billing-link');
        if (nurseTab) $(nurseTab).tab('show');
    @else
        const docTab = document.getElementById('tab-clinical-link');
        if (docTab) $(docTab).tab('show');
    @endif
});

/* ═══════════════ BILLING KIT ═══════════════ */
$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    const target = $(e.target).attr('href');
    if (target === '#proc-orders-services' && window.BillingKit) {
        if (!window._billingKitInitialized) {
            BillingKit.init(window.BILLING_KIT_CONFIG);
            BillingKit.setPatient(patientId);
            window._billingKitInitialized = true;
        }
    }
});

/* ═══════════════ DANGER ZONE ═══════════════ */
function toggleDangerZone() {
    const body = document.getElementById('danger-zone-body');
    const chev = document.getElementById('danger-zone-chevron');
    if (!body) return;
    body.classList.toggle('open');
    if (chev) chev.classList.toggle('fa-chevron-down');
    if (chev) chev.classList.toggle('fa-chevron-up');
}

/* ═══════════════ TEAM MODAL ═══════════════ */
function openAddTeamModal() {
    $('#addTeamForm')[0].reset();
    $('#team_user_id').empty().append('<option value="">-- Select Staff --</option>').trigger('chosen:updated');
    $.get('/api/staff-search', function(data) {
        if (data && data.data) {
            data.data.forEach(function(u) {
                $('#team_user_id').append('<option value="' + u.id + '">' + u.name + '</option>');
            });
        }
        $('#team_user_id').trigger('chosen:updated');
    });
    $('#addTeamModal').modal('show');
    setTimeout(function() {
        if (!$('#team_user_id').hasClass('chosen-initialized')) {
            $('#team_user_id').chosen({ search_contains: true });
        }
    }, 300);
}

function toggleCustomRoleField() {
    const val = $('#team_role').val();
    if (val === 'other') {
        $('#custom-role-group').show();
        $('#team_custom_role').prop('required', true);
    } else {
        $('#custom-role-group').hide();
        $('#team_custom_role').prop('required', false);
    }
}

$('#addTeamForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $(this).find('button[type=submit]');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Adding…');
    const formData = {
        user_id: $('#team_user_id').val(),
        role: $('#team_role').val(),
        custom_role: $('#team_custom_role').val(),
        is_lead: $('#team_is_lead').is(':checked') ? 1 : 0,
        notes: $('#team_notes').val(),
        _token: $('meta[name="csrf-token"]').attr('content'),
    };
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/team',
        method: 'POST',
        data: formData,
        success: function() {
            $('#addTeamModal').modal('hide');
            toastr.success('Team member added.');
            location.reload();
        },
        error: function(xhr) {
            const msg = xhr.responseJSON?.message || 'Error adding team member.';
            toastr.error(msg);
            btn.prop('disabled', false).html('<i class="fa fa-user-plus mr-1"></i>Add Member');
        }
    });
});

function removeTeamMember(memberId) {
    if (!confirm('Remove this team member?')) return;
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/team/' + memberId,
        method: 'DELETE',
        data: { _token: $('meta[name="csrf-token"]').attr('content') },
        success: function() {
            $('#team-member-' + memberId).remove();
            toastr.success('Team member removed.');
        },
        error: function() { toastr.error('Error removing team member.'); }
    });
}

/* ═══════════════ NOTE MODAL ═══════════════ */
function openAddNoteModal(noteType) {
    $('#addNoteForm')[0].reset();
    $('#edit_note_id').val('');
    $('#noteModalTitle').html('<i class="fa fa-sticky-note mr-2"></i>Add Procedure Note');
    $('#noteSubmitBtn').html('<i class="fa fa-save mr-1"></i>Save Note');
    if (noteType) $('#note_type').val(noteType);
    $('#addNoteModal').modal('show');
    setTimeout(initializeNoteEditor, 400);
}

function initializeNoteEditor() {
    if (noteEditorInstance) {
        try { noteEditorInstance.destroy().then(function() { noteEditorInstance = null; createEditor(); }); }
        catch(e) { noteEditorInstance = null; createEditor(); }
    } else { createEditor(); }
}

function createEditor() {
    ClassicEditor.create(document.querySelector('#note_content'), {
        toolbar: ['heading','|','bold','italic','bulletedList','numberedList','|','blockQuote','|','undo','redo']
    }).then(function(editor) {
        noteEditorInstance = editor;
    }).catch(function(err) { console.error(err); });
}

$('#addNoteForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $('#noteSubmitBtn');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Saving…');
    const editId = $('#edit_note_id').val();
    const content = noteEditorInstance ? noteEditorInstance.getData() : $('#note_content').val();
    const formData = {
        note_type: $('#note_type').val(),
        title: $('#note_title').val(),
        content: content,
        _token: $('meta[name="csrf-token"]').attr('content'),
    };
    let url, method;
    if (editId) {
        url = '/patient-procedures/' + procedureId + '/notes/' + editId;
        method = 'PUT';
        formData._method = 'PUT';
    } else {
        url = '/patient-procedures/' + procedureId + '/notes';
        method = 'POST';
    }
    $.ajax({
        url, method: 'POST', data: formData,
        success: function() {
            $('#addNoteModal').modal('hide');
            toastr.success(editId ? 'Note updated.' : 'Note added.');
            location.reload();
        },
        error: function(xhr) {
            const msg = xhr.responseJSON?.message || 'Error saving note.';
            toastr.error(msg);
            btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Note');
        }
    });
});

function deleteNote(noteId) {
    if (!confirm('Delete this note?')) return;
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/notes/' + noteId,
        method: 'POST',
        data: { _token: $('meta[name="csrf-token"]').attr('content'), _method: 'DELETE' },
        success: function() {
            $('#note-' + noteId).remove();
            toastr.success('Note deleted.');
        },
        error: function() { toastr.error('Error deleting note.'); }
    });
}

function editNote(noteId) {
    $.get('/patient-procedures/' + procedureId + '/notes/' + noteId + '/edit', function(data) {
        $('#edit_note_id').val(noteId);
        $('#noteModalTitle').html('<i class="fa fa-edit mr-2"></i>Edit Note');
        $('#noteSubmitBtn').html('<i class="fa fa-save mr-1"></i>Update Note');
        $('#note_type').val(data.note_type);
        $('#note_title').val(data.title);
        $('#addNoteModal').modal('show');
        setTimeout(function() {
            initializeNoteEditor();
            setTimeout(function() {
                if (noteEditorInstance) noteEditorInstance.setData(data.content || '');
                else $('#note_content').val(data.content || '');
            }, 600);
        }, 400);
    }).fail(function() { toastr.error('Error loading note.'); });
}

/* ═══════════════ QUICK NURSING NOTE ═══════════════ */
function submitQuickNote() {
    const text = $('#quick-note-text').val().trim();
    if (!text) { toastr.warning('Please enter a note.'); return; }
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/notes',
        method: 'POST',
        data: {
            note_type: 'nursing',
            title: 'Nursing Note',
            content: text,
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success: function() {
            $('#quick-note-text').val('');
            toastr.success('Nursing note added.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error saving note.');
        }
    });
}

/* ═══════════════ ITEM MODAL ═══════════════ */
function openAddItemModal(type) {
    $('#addItemForm')[0].reset();
    $('#item_type').val(type);
    $('.item-type-section').hide();
    clearChosenPreview('#item_service_preview');
    clearChosenPreview('#item_product_preview');
    if (type === 'lab') {
        $('#addItemModalTitle').html('<i class="fa fa-flask mr-2 text-primary"></i>Add Lab Request');
        $('#item-service-section').show();
        loadServicesForChosen(labCategoryId, '-- Search Lab Service --');
    } else if (type === 'imaging') {
        $('#addItemModalTitle').html('<i class="fa fa-x-ray mr-2"></i>Add Imaging Request');
        $('#item-service-section').show();
        loadServicesForChosen(imagingCategoryId, '-- Search Imaging Service --');
    } else if (type === 'medication') {
        $('#addItemModalTitle').html('<i class="fa fa-pills mr-2 text-success"></i>Add Medication');
        $('#item-product-section').show();
        loadProductsForChosen();
    }
    $('#addItemModal').modal('show');
}

function _num(v, fallback) {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : (fallback || 0);
}

function clearChosenPreview(previewSelector) {
    $(previewSelector).addClass('d-none').html('');
}

function renderChosenPreview(previewSelector, payload) {
    if (!payload || !payload.name) {
        clearChosenPreview(previewSelector);
        return;
    }

    const base = _num(payload.base, 0);
    const payable = _num(payload.payable, base);
    const claims = _num(payload.claims, 0);
    const modeRaw = (payload.mode || 'cash').toString();
    const mode = modeRaw.toLowerCase();
    const modeClass = 'mode-' + mode.replace(/[^a-z0-9_-]/g, '');
    const code = payload.code ? '[' + payload.code + ']' : '';
    const stock = payload.stock !== undefined && payload.stock !== null ? `<span class="cr-chip">Stock: ${payload.stock}</span>` : '';

    const html =
        `<div class="cr-title">${payload.name} ${code}</div>` +
        `<div class="cr-meta">` +
            `<span class="cr-base">Base: NGN ${base.toLocaleString()}</span>` +
            `<span class="cr-pay">Pay: NGN ${payable.toLocaleString()}</span>` +
            `<span class="cr-claim">Claim: NGN ${claims.toLocaleString()}</span>` +
            `<span class="cr-mode ${modeClass}">${modeRaw.toUpperCase()}</span>` +
            stock +
        `</div>`;

    $(previewSelector).removeClass('d-none').html(html);
}

function bindChosenPreview(selectSelector, previewSelector, kind) {
    $(selectSelector).off('change.richPreview').on('change.richPreview', function() {
        const $opt = $(this).find('option:selected');
        if (!$opt.val()) {
            clearChosenPreview(previewSelector);
            return;
        }
        renderChosenPreview(previewSelector, {
            name: $opt.data('name'),
            code: $opt.data('code'),
            base: $opt.data('base'),
            payable: $opt.data('payable'),
            claims: $opt.data('claims'),
            mode: $opt.data('mode'),
            stock: kind === 'product' ? $opt.data('stock') : null
        });
    });
}

function loadServicesForChosen(categoryId, placeholder) {
    const $select = $('#item_service_id');
    $select.empty().append('<option value=""></option>');
    if ($select.data('chosen')) { $select.chosen('destroy'); }
    $.ajax({
        url: '{{ route("live-search-services") }}',
        dataType: 'json',
        data: { term: '', category_id: categoryId, patient_id: patientId },
        success: function(data) {
            data.forEach(function(service) {
                const price = service.price?.sale_price || 0;
                const payable = service.payable_amount ?? price;
                const claims = service.claims_amount ?? 0;
                const mode = (service.coverage_mode || 'cash').toString();
                const name = service.service_name || 'Unknown';
                const code = service.service_code || '';
                const text =
                    name +
                    (code ? ' [' + code + ']' : '') +
                    ' | Base NGN ' + formatMoney(price) +
                    ' | Pay NGN ' + formatMoney(payable) +
                    ' | Claim NGN ' + formatMoney(claims) +
                    ' | ' + mode.toUpperCase();

                $select.append($('<option>', {
                    value: service.id,
                    text: text,
                    'data-name': name,
                    'data-code': code,
                    'data-base': price,
                    'data-payable': payable,
                    'data-claims': claims,
                    'data-mode': mode
                }));
            });
            $select.chosen({ allow_single_deselect: true, search_contains: true, placeholder_text_single: placeholder, width: '100%' });
            bindChosenPreview('#item_service_id', '#item_service_preview', 'service');
        },
        error: function() {
            toastr.error('Failed to load services');
            $select.chosen({ placeholder_text_single: placeholder, width: '100%' });
            bindChosenPreview('#item_service_id', '#item_service_preview', 'service');
        }
    });
}

function loadProductsForChosen() {
    const $select = $('#item_product_id');
    $select.empty().append('<option value=""></option>');
    if ($select.data('chosen')) { $select.chosen('destroy'); }
    $.ajax({
        url: '{{ route("live-search-products") }}',
        dataType: 'json',
        data: { term: '', patient_id: patientId },
        success: function(data) {
            data.forEach(function(product) {
                const price = product.price?.sale_price ?? product.price?.initial_sale_price ?? 0;
                const payable = product.payable_amount ?? price;
                const claims = product.claims_amount ?? 0;
                const mode = (product.coverage_mode || 'cash').toString();
                const name = product.product_name || 'Unknown';
                const code = product.product_code || '';
                const stock = product.stock?.current_quantity ?? 0;
                const text =
                    name +
                    (code ? ' [' + code + ']' : '') +
                    ' (' + stock + ' avail.)' +
                    ' | Base NGN ' + formatMoney(price) +
                    ' | Pay NGN ' + formatMoney(payable) +
                    ' | Claim NGN ' + formatMoney(claims) +
                    ' | ' + mode.toUpperCase();

                $select.append($('<option>', {
                    value: product.id,
                    text: text,
                    'data-name': name,
                    'data-code': code,
                    'data-base': price,
                    'data-payable': payable,
                    'data-claims': claims,
                    'data-mode': mode,
                    'data-stock': stock
                }));
            });
            $select.chosen({ allow_single_deselect: true, search_contains: true, placeholder_text_single: '-- Search Product --', width: '100%' });
            bindChosenPreview('#item_product_id', '#item_product_preview', 'product');
        },
        error: function() {
            toastr.error('Failed to load products');
            $select.chosen({ placeholder_text_single: '-- Search Product --', width: '100%' });
            bindChosenPreview('#item_product_id', '#item_product_preview', 'product');
        }
    });
}

function formatMoney(amount) {
    return parseFloat(amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

$('#item_product_id').on('change', function() {
    const productId = $(this).val();
    if (!productId) { $('#item_batch_id').html('<option value="">-- Select product first --</option>'); return; }
    $.get('{{ route('nursing-workbench.product-batches') }}', { product_id: productId }, function(data) {
        $('#item_batch_id').html('<option value="">-- Select batch --</option>');
        if (data && data.batches) {
            data.batches.forEach(function(b) {
                $('#item_batch_id').append('<option value="' + b.id + '">' + b.batch_number + ' (Qty: ' + b.quantity + ')</option>');
            });
        }
    });
});

$('#addItemForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $(this).find('button[type=submit]');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Adding…');
    const type = $('#item_type').val();
    const formData = {
        item_type: type,
        is_bundled: $('#item_is_bundled').is(':checked') ? 1 : 0,
        notes: $('#item_notes').val(),
        _token: $('meta[name="csrf-token"]').attr('content'),
    };
    if (type === 'medication') {
        formData.product_id = $('#item_product_id').val();
        formData.batch_id   = $('#item_batch_id').val();
        formData.quantity   = $('#item_quantity').val();
    } else {
        formData.service_id = $('#item_service_id').val();
    }
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/items',
        method: 'POST',
        data: formData,
        success: function() {
            $('#addItemModal').modal('hide');
            toastr.success('Item added.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error adding item.');
            btn.prop('disabled', false).html('<i class="fa fa-plus mr-1"></i>Add');
        }
    });
});

function removeItem(itemId) {
    if (!confirm('Remove this item?')) return;
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/items/' + itemId,
        method: 'POST',
        data: { _token: $('meta[name="csrf-token"]').attr('content'), _method: 'DELETE' },
        success: function() { $('#item-row-' + itemId).remove(); toastr.success('Item removed.'); },
        error: function() { toastr.error('Error removing item.'); }
    });
}

/* ═══════════════ CANCEL PROCEDURE ═══════════════ */
function openCancelModal() {
    $('#cancelProcedureForm')[0].reset();
    $('#cancelProcedureModal').modal('show');
}

$('#cancelProcedureForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $(this).find('button[type=submit]');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Cancelling…');
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/cancel',
        method: 'POST',
        data: {
            cancellation_reason: $('#cancellation_reason').val(),
            refund_amount: $('#refund_amount').val(),
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success: function() {
            $('#cancelProcedureModal').modal('hide');
            toastr.success('Procedure cancelled.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error cancelling procedure.');
            btn.prop('disabled', false).html('<i class="fa fa-ban mr-1"></i>Cancel Procedure');
        }
    });
});

/* ═══════════════ OUTCOME ═══════════════ */
function toggleOutcomeEdit() {
    $('#outcome-display').toggle();
    $('#outcome-form-wrapper').toggle();
}

$('#outcome-form').on('submit', function(e) {
    e.preventDefault();
    const btn = $(this).find('button[type=submit]');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Saving…');
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/outcome',
        method: 'POST',
        data: {
            outcome: $('#outcome').val(),
            outcome_notes: $('#outcome_notes').val(),
            _method: 'PUT',
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success: function() {
            toastr.success('Outcome saved.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error saving outcome.');
            btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Outcome');
        }
    });
});

/* ═══════════════ STATUS ═══════════════ */
function updateStatus(status) {
    $.ajax({
        url: '/patient-procedures/' + procedureId,
        method: 'POST',
        data: {
            procedure_status: status,
            _method: 'PUT',
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success: function() {
            toastr.success('Status updated.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error updating status.');
        }
    });
}

function completeProcedure() {
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/complete',
        method: 'POST',
        data: { _token: $('meta[name="csrf-token"]').attr('content') },
        success: function() {
            toastr.success('Procedure marked as completed.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error completing procedure.');
        }
    });
}

function confirmAction(action, title, message, subText, colorClass, icon) {
    $('#confirmActionHeader').attr('class', 'modal-header bg-' + colorClass + (colorClass === 'warning' || colorClass === 'secondary' ? ' text-dark' : ' text-white'));
    $('#confirmActionTitle').html('<i class="fa fa-' + icon + ' mr-2"></i>' + title);
    $('#confirmActionMessage').text(message);
    $('#confirmActionSub').text(subText || '');
    $('#confirmActionBtn')
        .attr('class', 'btn btn-' + colorClass)
        .off('click')
        .on('click', function() {
            $('#confirmActionModal').modal('hide');
            if (action === 'complete') {
                completeProcedure();
            } else {
                updateStatus(action);
            }
        });
    $('#confirmActionModal').modal('show');
}

/* ═══════════════ SCHEDULE MODAL (NEW) ═══════════════ */
function openScheduleModal() {
    $('#scheduleProcedureModal').modal('show');
}

$('#btn-submit-schedule').on('click', function() {
    const btn = $(this);
    const scheduled_date = $('#schedule_date').val();
    const scheduled_time = $('#schedule_time').val();
    const operating_room = $('#operating_room').val();
    if (!scheduled_date || !scheduled_time || !operating_room) {
        toastr.warning('Please fill all required fields.');
        return;
    }
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Saving…');
    $.ajax({
        url: '/patient-procedures/' + procedureId,
        method: 'POST',
        data: {
            procedure_status: 'scheduled',
            scheduled_date: scheduled_date,
            scheduled_time: scheduled_time,
            operating_room: operating_room,
            _method: 'PUT',
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success: function() {
            $('#scheduleProcedureModal').modal('hide');
            toastr.success('Procedure scheduled.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error scheduling procedure.');
            btn.prop('disabled', false).html('<i class="fa fa-calendar-check mr-1"></i>Confirm Schedule');
        }
    });
});

/* ═══════════════ PRINT ═══════════════ */
function openPrintSelectionModal() {
    $('#printSelectionModal').modal('show');
}

function selectAllPrintOptions() {
    $('.print-option').prop('checked', true);
}

function executePrint() {
    const sections = [];
    $('.print-option:checked').each(function() { sections.push($(this).val()); });
    if (sections.length === 0) { toastr.warning('Select at least one section.'); return; }
    const params = new URLSearchParams();
    sections.forEach(s => params.append('sections[]', s));
    window.open('/patient-procedures/' + procedureId + '/print?' + params.toString(), '_blank');
    $('#printSelectionModal').modal('hide');
}

/* ═══════════════ DATATABLES ═══════════════ */
function initLabHistoryTable() {
    if ($.fn.DataTable.isDataTable('#procedure_lab_history')) return;
    $('#procedure_lab_history').DataTable({
        ajax: { url: '/patient-procedures/' + procedureId + '/lab-history', dataSrc: 'data' },
        columns: [{ data: null, render: function(d) {
            return '<div class="item-row">'
                + '<div class="item-icon lab"><i class="fa fa-flask"></i></div>'
                + '<div class="item-info">'
                + '<div class="item-name">' + (d.service_name || '—') + '</div>'
                + '<div class="item-code"><small>' + (d.service_code || '') + ' · ' + (d.requested_at || '') + '</small></div>'
                + '</div>'
                + '<span class="badge badge-' + (d.status === 'completed' ? 'success' : 'warning') + '">' + (d.status || '') + '</span>'
                + '</div>';
        }}],
        pageLength: 10,
        dom: '<"d-flex justify-content-between align-items-center px-3 pt-2"f>t<"d-flex justify-content-between align-items-center px-3"ip>',
        language: { search: '', searchPlaceholder: 'Search labs…', emptyTable: 'No lab requests.' },
    });
}

function initImagingHistoryTable() {
    if ($.fn.DataTable.isDataTable('#procedure_imaging_history')) return;
    $('#procedure_imaging_history').DataTable({
        ajax: { url: '/patient-procedures/' + procedureId + '/imaging-history', dataSrc: 'data' },
        columns: [{ data: null, render: function(d) {
            return '<div class="item-row">'
                + '<div class="item-icon imaging"><i class="fa fa-x-ray"></i></div>'
                + '<div class="item-info">'
                + '<div class="item-name">' + (d.service_name || '—') + '</div>'
                + '<div class="item-code"><small>' + (d.service_code || '') + ' · ' + (d.requested_at || '') + '</small></div>'
                + '</div>'
                + '<span class="badge badge-' + (d.status === 'completed' ? 'success' : 'warning') + '">' + (d.status || '') + '</span>'
                + '</div>';
        }}],
        pageLength: 10,
        dom: '<"d-flex justify-content-between align-items-center px-3 pt-2"f>t<"d-flex justify-content-between align-items-center px-3"ip>',
        language: { search: '', searchPlaceholder: 'Search imaging…', emptyTable: 'No imaging requests.' },
    });
}

function initMedsHistoryTable() {
    if ($.fn.DataTable.isDataTable('#procedure_meds_history')) return;
    $('#procedure_meds_history').DataTable({
        ajax: { url: '/patient-procedures/' + procedureId + '/medication-history', dataSrc: 'data' },
        columns: [{ data: null, render: function(d) {
            return '<div class="item-row">'
                + '<div class="item-icon product"><i class="fa fa-pills"></i></div>'
                + '<div class="item-info">'
                + '<div class="item-name">' + (d.product_name || '—') + '</div>'
                + '<div class="item-code"><small>Qty: ' + (d.quantity || 1) + ' · ' + (d.dispensed_at || '') + '</small></div>'
                + '</div>'
                + '<span class="badge badge-' + (d.status === 'dispensed' ? 'success' : 'warning') + '">' + (d.status || '') + '</span>'
                + '</div>';
        }}],
        pageLength: 10,
        dom: '<"d-flex justify-content-between align-items-center px-3 pt-2"f>t<"d-flex justify-content-between align-items-center px-3"ip>',
        language: { search: '', searchPlaceholder: 'Search meds…', emptyTable: 'No medication requests.' },
    });
}

$('#proc-orders-labs').on('shown.bs.tab', function() { initLabHistoryTable(); });
$('#tab-orders-link').on('shown.bs.tab', function() { initLabHistoryTable(); });

$(document).on('shown.bs.tab', 'a[href="#proc-orders-labs"]', function() { initLabHistoryTable(); });
$(document).on('shown.bs.tab', 'a[href="#proc-orders-imaging"]', function() { initImagingHistoryTable(); });
$(document).on('shown.bs.tab', 'a[href="#proc-orders-meds"]', function() { initMedsHistoryTable(); });
$(document).on('shown.bs.tab', 'a[href="#tab-orders"]', function() {
    setTimeout(function() { initLabHistoryTable(); }, 100);
});

/* ═══════════════ CONSENT ═══════════════ */
function openConsentModal() {
    $('#consentModal').modal('show');
}

function selectConsentOption(optionKey) {
    document.querySelectorAll('.consent-option-btn').forEach(function(el) {
        el.className = 'consent-option-btn';
    });
    const el = document.getElementById('consent-opt-' + optionKey);
    if (el) el.classList.add('selected-' + optionKey);
    $('#consent_status_value').val(optionKey);
}

function executeConsentUpdate() {
    const status = $('#consent_status_value').val();
    if (!status) { toastr.warning('Please select a consent status.'); return; }
    const btn = $('#save-consent-btn');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Saving…');
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/consent',
        method: 'POST',
        data: {
            consent_status: status,
            consent_notes: $('#consent_notes').val(),
            _method: 'PATCH',
            _token: $('meta[name="csrf-token"]').attr('content'),
        },
        success: function() {
            $('#consentModal').modal('hide');
            toastr.success('Consent status updated.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error updating consent.');
            btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Consent');
        }
    });
}

/* ═══════════════ ATTACHMENTS ═══════════════ */
$('#upload-attachment-form').on('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('attachment-file');
    if (!fileInput.files.length) { toastr.warning('Please select a file.'); return; }
    const btn = $(this).find('button[type=submit]');
    btn.prop('disabled', true).html('<i class="fa fa-spin fa-spinner mr-1"></i>Uploading…');
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('label', $('#attachment-label').val());
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/attachments',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function() {
            toastr.success('Attachment uploaded.');
            location.reload();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Upload failed.');
            btn.prop('disabled', false).html('<i class="fa fa-upload mr-1"></i>Upload');
        }
    });
});

function deleteAttachment(attId) {
    if (!confirm('Delete this attachment?')) return;
    executeDeleteAttachment(attId);
}

function executeDeleteAttachment(attId) {
    $.ajax({
        url: '/patient-procedures/' + procedureId + '/attachments/' + attId,
        method: 'POST',
        data: { _token: $('meta[name="csrf-token"]').attr('content'), _method: 'DELETE' },
        success: function() {
            $('#attachment-row-' + attId).remove();
            toastr.success('Attachment deleted.');
        },
        error: function() { toastr.error('Error deleting attachment.'); }
    });
}

</script>
@endsection
