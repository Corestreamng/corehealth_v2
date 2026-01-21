@extends('admin.layouts.app')
@section('title', 'Procedure Details - ' . ($procedure->service->service_name ?? 'Procedure'))
@section('page_name', 'Procedures')
@section('subpage_name', 'Procedure Details')

@php
    $hosColor = appsettings('hos_color') ?? '#0066cc';
@endphp

@section('content')
{{-- Chosen CSS --}}
<link rel="stylesheet" href="{{ asset('assets/css/chosen.min.css') }}">
<style>
    /* ===== Procedure Details Page Styles ===== */
    .procedure-page { font-family: 'Inter', -apple-system, sans-serif; }

    /* Chosen Select Customization */
    .chosen-container { width: 100% !important; }
    .chosen-container-single .chosen-single {
        height: 38px;
        line-height: 36px;
        border-radius: 4px;
        border: 1px solid #ced4da;
        background: #fff;
        box-shadow: none;
    }
    .chosen-container-active.chosen-with-drop .chosen-single {
        border-color: {{ $hosColor }};
        box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    }
    .chosen-container .chosen-drop {
        border-color: {{ $hosColor }};
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .chosen-container .chosen-results li.highlighted {
        background: {{ $hosColor }};
    }
    .chosen-container-single .chosen-search input[type="text"] {
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 8px 10px;
    }

    /* Header Card */
    .procedure-header-card {
        background: linear-gradient(135deg, {{ $hosColor }} 0%, #5a9fd4 100%);
        border-radius: 12px;
        padding: 24px;
        color: white;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .procedure-header-card h1 { font-size: 1.75rem; font-weight: 700; margin: 0 0 8px 0; }
    .procedure-header-card .procedure-meta { opacity: 0.9; font-size: 0.9rem; }
    .procedure-header-card .procedure-meta span { margin-right: 12px; }

    /* Status & Priority Badges */
    .badge-status { padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
    .badge-requested { background: #e9ecef; color: #495057; }
    .badge-scheduled { background: #cce5ff; color: #004085; }
    .badge-in_progress { background: #fff3cd; color: #856404; }
    .badge-completed { background: #d4edda; color: #155724; }
    .badge-cancelled { background: #f8d7da; color: #721c24; }
    .badge-priority { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-left: 8px; }
    .badge-routine { background: #d4edda; color: #155724; }
    .badge-urgent { background: #fff3cd; color: #856404; }
    .badge-emergency { background: #f8d7da; color: #721c24; }

    /* Patient Info Bar */
    .patient-info-bar {
        background: white;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border-left: 4px solid {{ $hosColor }};
    }
    .patient-info-bar h5 { margin: 0 0 4px 0; font-weight: 600; color: #333; }
    .patient-info-bar .patient-details { color: #6c757d; font-size: 0.9rem; }
    .patient-info-bar .patient-details i { margin-right: 4px; }

    /* Section Card */
    .section-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .section-card-header {
        background: #f8f9fa;
        padding: 16px 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .section-card-header h5 { margin: 0; font-size: 1rem; font-weight: 600; color: #333; }
    .section-card-header h5 i { color: {{ $hosColor }}; margin-right: 8px; }
    .section-card-body { padding: 20px; }
    .section-card-body.p-0 { padding: 0; }

    /* Info Grid */
    .info-grid { display: grid; gap: 12px; }
    .info-item { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .info-item:last-child { border-bottom: none; }
    .info-label { font-weight: 600; color: #6c757d; min-width: 140px; font-size: 0.9rem; }
    .info-value { color: #333; flex: 1; }

    /* Billing Summary */
    .billing-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 768px) { .billing-grid { grid-template-columns: 1fr; } }
    .billing-box { background: #f8f9fa; border-radius: 8px; padding: 16px; }
    .billing-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #dee2e6; }
    .billing-row:last-child { border-bottom: none; font-weight: 600; }
    .billing-row.total { font-size: 1.1rem; color: {{ $hosColor }}; }

    /* Team Member Card */
    .team-member-item {
        display: flex;
        align-items: center;
        padding: 14px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 10px;
        transition: all 0.2s;
    }
    .team-member-item:hover { background: #f0f0f0; }
    .team-member-item:last-child { margin-bottom: 0; }
    .team-avatar {
        width: 44px;
        height: 44px;
        background: {{ $hosColor }};
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
        margin-right: 14px;
        flex-shrink: 0;
    }
    .team-info { flex: 1; }
    .team-info h6 { margin: 0 0 2px 0; font-weight: 600; font-size: 0.95rem; }
    .team-info small { color: #6c757d; }
    .lead-tag { background: #ffc107; color: #212529; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; margin-left: 6px; font-weight: 600; }

    /* Notes Tabs */
    .notes-tabs { border-bottom: 1px solid #e9ecef; padding: 0 20px; background: #fafafa; }
    .notes-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
        padding: 14px 16px;
        font-size: 0.9rem;
        border-bottom: 3px solid transparent;
        margin-bottom: -1px;
    }
    .notes-tabs .nav-link.active { color: {{ $hosColor }}; border-bottom-color: {{ $hosColor }}; background: transparent; }
    .notes-tabs .nav-link:hover { color: {{ $hosColor }}; }

    /* Note Card */
    .note-item {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 12px;
        overflow: hidden;
    }
    .note-item:last-child { margin-bottom: 0; }
    .note-item-header {
        background: #f8f9fa;
        padding: 12px 16px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .note-item-header h6 { margin: 0; font-weight: 600; font-size: 0.95rem; }
    .note-item-header .note-meta { font-size: 0.8rem; color: #6c757d; }
    .note-item-body { padding: 16px; }

    /* Action Buttons */
    .action-btn-group .btn { margin-bottom: 10px; }
    .action-btn-group .btn-block { display: block; width: 100%; }

    /* Empty State */
    .empty-state { text-align: center; padding: 40px 20px; color: #6c757d; }
    .empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.5; }
    .empty-state p { margin: 0; }

    /* Alert Custom */
    .alert-custom { border-radius: 8px; border: none; }

    /* Items List */
    .item-row {
        display: flex;
        align-items: center;
        padding: 14px 20px;
        border-bottom: 1px solid #f0f0f0;
    }
    .item-row:last-child { border-bottom: none; }
    .item-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 14px;
        font-size: 1.1rem;
    }
    .item-icon.lab { background: #e3f2fd; color: #1976d2; }
    .item-icon.imaging { background: #f3e5f5; color: #7b1fa2; }
    .item-icon.product { background: #e8f5e9; color: #388e3c; }
    .item-info { flex: 1; }
    .item-name { font-weight: 600; color: #333; margin-bottom: 2px; }
    .item-code { font-size: 0.8rem; color: #6c757d; }
    .bundled-tag { font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; margin-left: 8px; }
    .bundled-yes { background: #e3f2fd; color: #1565c0; }
    .bundled-no { background: #fce4ec; color: #c62828; }
    .delivery-tag { font-size: 0.75rem; padding: 4px 10px; border-radius: 4px; margin-right: 12px; }
    .delivery-pending { background: #fff3e0; color: #e65100; }
    .delivery-completed { background: #e8f5e9; color: #2e7d32; }
    .delivery-cancelled { background: #ffebee; color: #c62828; }

    /* Right Sidebar Actions Card */
    .actions-card .btn { font-weight: 500; }
    .actions-card .btn i { margin-right: 6px; }

    /* Card Modern (Workbench History Style) */
    .card-modern {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        margin-bottom: 12px;
        overflow: hidden;
    }
    .card-modern .card-body {
        padding: 16px;
    }
    .history-table-wrapper {
        max-height: 500px;
        overflow-y: auto;
    }
    .history-table-wrapper .dataTables_wrapper {
        padding: 0;
    }
    .history-table-wrapper table.dataTable {
        margin: 0 !important;
    }
    .history-table-wrapper table.dataTable thead {
        display: none;
    }
    .history-table-wrapper table.dataTable tbody td {
        padding: 0;
        border: none;
    }
    .history-table-wrapper .dataTables_info,
    .history-table-wrapper .dataTables_paginate {
        padding: 10px 16px;
    }

    /* Print Selection Modal */
    .print-option-item {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .print-option-item:last-child {
        border-bottom: none;
    }
</style>

<section class="container-fluid procedure-page">
    {{-- Procedure Header --}}
    <div class="procedure-header-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1>{{ $procedure->service->service_name ?? 'Procedure' }}</h1>
                <div class="procedure-meta">
                    <span><i class="fa fa-hashtag"></i> {{ $procedure->service->service_code ?? 'N/A' }}</span>
                    @if($procedure->procedureDefinition && $procedure->procedureDefinition->procedureCategory)
                        <span><i class="fa fa-folder"></i> {{ $procedure->procedureDefinition->procedureCategory->name }}</span>
                    @endif
                    @if($procedure->procedureDefinition && $procedure->procedureDefinition->is_surgical)
                        <span><i class="fa fa-cut"></i> Surgical</span>
                    @endif
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <span class="badge-status badge-{{ $procedure->procedure_status }}">
                    {{ \App\Models\Procedure::STATUSES[$procedure->procedure_status] ?? ucfirst(str_replace('_', ' ', $procedure->procedure_status)) }}
                </span>
                <span class="badge-priority badge-{{ $procedure->priority }}">
                    {{ ucfirst($procedure->priority) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Patient Info Bar --}}
    <div class="patient-info-bar">
        <div>
            <h5>{{ userfullname($procedure->patient->user_id) }}</h5>
            <div class="patient-details">
                <span><i class="fa fa-id-card"></i> {{ $procedure->patient->file_no ?? 'N/A' }}</span>
                <span class="mx-2">|</span>
                <span><i class="fa fa-calendar"></i> {{ $procedure->patient->dob ? \Carbon\Carbon::parse($procedure->patient->dob)->age . ' yrs' : 'N/A' }}</span>
                @if($procedure->patient->hmo)
                    <span class="mx-2">|</span>
                    <span><i class="fa fa-hospital"></i> {{ $procedure->patient->hmo->name ?? 'HMO' }}</span>
                @endif
            </div>
        </div>
        <a href="{{ route('patient.show', $procedure->patient_id) }}" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="fa fa-external-link-alt"></i> View Patient
        </a>
    </div>

    <div class="row">
        {{-- Left Column (8 cols) --}}
        <div class="col-lg-8">
            {{-- Billing Status --}}
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
                                    <span>Payment Status</span>
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
                                    <span class="info-value">
                                        <span class="badge badge-{{ ($billing->coverage_mode ?? '') === 'hmo' ? 'info' : 'secondary' }}">
                                            {{ strtoupper($billing->coverage_mode ?? 'CASH') }}
                                        </span>
                                    </span>
                                </div>
                                @if(($billing->coverage_mode ?? '') === 'hmo' || ($billing->claims_amount ?? 0) > 0)
                                    <div class="info-item">
                                        <span class="info-label">Validation</span>
                                        <span class="info-value">
                                            @php $vs = $billing->validation_status ?? 'pending'; @endphp
                                            <span class="badge badge-{{ $vs === 'approved' ? 'success' : ($vs === 'rejected' ? 'danger' : 'warning') }}">
                                                {{ strtoupper($vs) }}
                                            </span>
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
                        <div class="alert alert-info alert-custom mb-0">
                            <i class="fa fa-info-circle"></i> No billing entry found for this procedure.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Surgical Team --}}
            <div class="section-card">
                <div class="section-card-header">
                    <h5><i class="fa fa-users"></i> Surgical Team</h5>
                    <button class="btn btn-sm btn-primary" onclick="openAddTeamModal()">
                        <i class="fa fa-plus"></i> Add Member
                    </button>
                </div>
                <div class="section-card-body" id="team-members-container">
                    @forelse($procedure->teamMembers as $member)
                        <div class="team-member-item" id="team-member-{{ $member->id }}">
                            <div class="team-avatar">
                                {{ strtoupper(substr(optional($member->user)->surname ?? 'U', 0, 1)) }}{{ strtoupper(substr(optional($member->user)->firstname ?? '', 0, 1)) }}
                            </div>
                            <div class="team-info">
                                <h6>
                                    {{ optional($member->user)->name ?? 'Unknown' }}
                                    @if($member->is_lead)<span class="lead-tag">LEAD</span>@endif
                                </h6>
                                <small>{{ $member->role === 'other' ? $member->custom_role : ucwords(str_replace('_', ' ', $member->role)) }}</small>
                                @if($member->notes)
                                    <div class="text-muted small mt-1">{{ $member->notes }}</div>
                                @endif
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeTeamMember({{ $member->id }})">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    @empty
                        <div class="empty-state" id="no-team-message">
                            <i class="fa fa-users"></i>
                            <p>No team members assigned yet</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Procedure Notes --}}
            <div class="section-card">
                <div class="section-card-header">
                    <h5><i class="fa fa-sticky-note"></i> Procedure Notes</h5>
                    <button class="btn btn-sm btn-primary" onclick="openAddNoteModal()">
                        <i class="fa fa-plus"></i> Add Note
                    </button>
                </div>
                <div class="section-card-body p-0">
                    <ul class="nav nav-tabs notes-tabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#notes-pre_op">Pre-Op</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#notes-intra_op">Intra-Op</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#notes-post_op">Post-Op</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#notes-anesthesia">Anesthesia</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#notes-nursing">Nursing</a></li>
                    </ul>
                    <div class="tab-content p-3">
                        @foreach(['pre_op', 'intra_op', 'post_op', 'anesthesia', 'nursing'] as $noteType)
                            <div class="tab-pane fade {{ $noteType === 'pre_op' ? 'show active' : '' }}" id="notes-{{ $noteType }}">
                                @php $typeNotes = $procedure->notes->where('note_type', $noteType); @endphp
                                @forelse($typeNotes as $note)
                                    <div class="note-item" id="note-{{ $note->id }}">
                                        <div class="note-item-header">
                                            <h6>{{ $note->title }}</h6>
                                            <div>
                                                <span class="note-meta">{{ optional($note->createdBy)->name ?? 'Unknown' }} • {{ $note->created_at->format('d M Y H:i') }}</span>
                                                <button class="btn btn-sm btn-link text-primary p-0 ml-2" onclick="editNote({{ $note->id }})"><i class="fa fa-edit"></i></button>
                                                <button class="btn btn-sm btn-link text-danger p-0 ml-1" onclick="deleteNote({{ $note->id }})"><i class="fa fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <div class="note-item-body">{!! $note->content !!}</div>
                                    </div>
                                @empty
                                    <div class="empty-state">
                                        <i class="fa fa-sticky-note"></i>
                                        <p>No {{ str_replace('_', '-', $noteType) }} notes yet</p>
                                    </div>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Procedure Orders & History (Workbench-style) --}}
            <div class="section-card">
                <div class="section-card-header">
                    <h5><i class="fa fa-clipboard-list"></i> Procedure Orders & History</h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown">
                            <i class="fa fa-plus"></i> Add Item
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="javascript:void(0)" onclick="openAddItemModal('lab')"><i class="fa fa-flask mr-2 text-primary"></i> Lab Request</a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="openAddItemModal('imaging')"><i class="fa fa-x-ray mr-2 text-purple"></i> Imaging Request</a>
                            <a class="dropdown-item" href="javascript:void(0)" onclick="openAddItemModal('medication')"><i class="fa fa-pills mr-2 text-success"></i> Medication</a>
                        </div>
                    </div>
                </div>
                <div class="section-card-body p-0">
                    <ul class="nav nav-tabs notes-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#proc-orders-labs">
                                <i class="fa fa-flask text-primary"></i> Labs
                                <span class="badge badge-primary ml-1" id="proc-labs-count">{{ $procedure->items->filter(fn($i) => $i->lab_service_request_id !== null)->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#proc-orders-imaging">
                                <i class="fa fa-x-ray" style="color:#9c27b0"></i> Imaging
                                <span class="badge badge-primary ml-1" id="proc-imaging-count">{{ $procedure->items->filter(fn($i) => $i->imaging_service_request_id !== null)->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#proc-orders-meds">
                                <i class="fa fa-pills text-success"></i> Medications
                                <span class="badge badge-primary ml-1" id="proc-meds-count">{{ $procedure->items->filter(fn($i) => $i->product_request_id !== null)->count() }}</span>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        {{-- Labs History Tab --}}
                        <div class="tab-pane fade show active" id="proc-orders-labs">
                            <div class="history-table-wrapper p-2" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-borderless" id="procedure_lab_history" style="width:100%">
                                    <thead><tr><th><i class="mdi mdi-test-tube"></i> Laboratory Requests</th></tr></thead>
                                </table>
                            </div>
                        </div>
                        {{-- Imaging History Tab --}}
                        <div class="tab-pane fade" id="proc-orders-imaging">
                            <div class="history-table-wrapper p-2" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-borderless" id="procedure_imaging_history" style="width:100%">
                                    <thead><tr><th><i class="mdi mdi-radioactive"></i> Imaging Requests</th></tr></thead>
                                </table>
                            </div>
                        </div>
                        {{-- Medications History Tab --}}
                        <div class="tab-pane fade" id="proc-orders-meds">
                            <div class="history-table-wrapper p-2" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-borderless" id="procedure_meds_history" style="width:100%">
                                    <thead><tr><th><i class="mdi mdi-pill"></i> Medication Requests</th></tr></thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column (4 cols) --}}
        <div class="col-lg-4">
            {{-- Details Card --}}
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
                            <span class="info-label">Scheduled Date</span>
                            <span class="info-value">{{ $procedure->scheduled_date->format('d M Y') }}</span>
                        </div>
                        @endif
                        @if($procedure->scheduled_time)
                        <div class="info-item">
                            <span class="info-label">Scheduled Time</span>
                            <span class="info-value">{{ $procedure->scheduled_time }}</span>
                        </div>
                        @endif
                        @if($procedure->operating_room)
                        <div class="info-item">
                            <span class="info-label">Operating Room</span>
                            <span class="info-value">{{ $procedure->operating_room }}</span>
                        </div>
                        @endif
                        @if($procedure->actual_start_time)
                        <div class="info-item">
                            <span class="info-label">Started At</span>
                            <span class="info-value">{{ $procedure->actual_start_time->format('d M Y H:i') }}</span>
                        </div>
                        @endif
                        @if($procedure->actual_end_time)
                        <div class="info-item">
                            <span class="info-label">Ended At</span>
                            <span class="info-value">{{ $procedure->actual_end_time->format('d M Y H:i') }}</span>
                        </div>
                        @endif
                        @if($procedure->procedureDefinition && $procedure->procedureDefinition->is_surgical)
                        <div class="info-item">
                            <span class="info-label">Type</span>
                            <span class="info-value"><span class="badge badge-danger">SURGICAL</span></span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Outcome (if in_progress or completed) --}}
            @if(in_array($procedure->procedure_status, ['in_progress', 'completed']))
            <div class="section-card">
                <div class="section-card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-flag-checkered"></i> Outcome</h5>
                    @if($procedure->outcome)
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleOutcomeEdit()">
                            <i class="fa fa-pencil"></i> Edit
                        </button>
                    @endif
                </div>
                <div class="section-card-body">
                    {{-- Display View (shown when outcome exists) --}}
                    <div id="outcome-display" style="{{ $procedure->outcome ? '' : 'display:none;' }}">
                        @if($procedure->outcome)
                            @php
                                $outcomeColors = [
                                    'successful' => 'success',
                                    'complications' => 'warning',
                                    'aborted' => 'danger',
                                    'converted' => 'info',
                                ];
                                $outcomeIcons = [
                                    'successful' => 'check-circle',
                                    'complications' => 'exclamation-triangle',
                                    'aborted' => 'times-circle',
                                    'converted' => 'exchange-alt',
                                ];
                                $color = $outcomeColors[$procedure->outcome] ?? 'secondary';
                                $icon = $outcomeIcons[$procedure->outcome] ?? 'flag';
                            @endphp
                            <div class="outcome-display-card border-{{ $color }}" style="border-left: 4px solid; padding: 15px; border-radius: 6px; background: #f8f9fa;">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge badge-{{ $color }} px-3 py-2" style="font-size: 0.95rem;">
                                        <i class="fa fa-{{ $icon }} mr-1"></i>
                                        {{ \App\Models\Procedure::OUTCOMES[$procedure->outcome] ?? ucfirst($procedure->outcome) }}
                                    </span>
                                    <small class="text-muted ml-auto">
                                        <i class="fa fa-clock"></i>
                                        Recorded {{ $procedure->updated_at ? $procedure->updated_at->diffForHumans() : '' }}
                                    </small>
                                </div>
                                @if($procedure->outcome_notes)
                                    <div class="mt-3 p-3 bg-white rounded border">
                                        <small class="text-muted d-block mb-1"><i class="fa fa-sticky-note"></i> Outcome Notes:</small>
                                        <p class="mb-0">{{ $procedure->outcome_notes }}</p>
                                    </div>
                                @else
                                    <small class="text-muted"><i class="fa fa-info-circle"></i> No outcome notes recorded</small>
                                @endif
                            </div>
                        @else
                            <div class="text-center text-muted py-3">
                                <i class="fa fa-flag fa-2x mb-2 d-block"></i>
                                <p>No outcome recorded yet</p>
                            </div>
                        @endif
                    </div>

                    {{-- Edit Form (hidden when outcome exists, shown on edit click) --}}
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
                                <textarea class="form-control" id="outcome_notes" name="outcome_notes" rows="3" placeholder="Add any relevant notes about the procedure outcome...">{{ $procedure->outcome_notes }}</textarea>
                            </div>
                            <div class="d-flex gap-2">
                                @if($procedure->outcome)
                                    <button type="button" class="btn btn-secondary mr-2" onclick="toggleOutcomeEdit()">
                                        <i class="fa fa-times"></i> Cancel
                                    </button>
                                @endif
                                <button type="submit" class="btn btn-success flex-grow-1">
                                    <i class="fa fa-save"></i> Save Outcome
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            {{-- Cancellation Info (if cancelled) --}}
            @if($procedure->procedure_status === 'cancelled')
            <div class="section-card">
                <div class="section-card-header bg-danger text-white">
                    <h5 class="text-white"><i class="fa fa-ban"></i> Cancellation Info</h5>
                </div>
                <div class="section-card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Cancelled By</span>
                            <span class="info-value">{{ optional($procedure->cancelledByUser)->name ?? 'N/A' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Cancelled At</span>
                            <span class="info-value">{{ $procedure->cancelled_at ? $procedure->cancelled_at->format('d M Y H:i') : 'N/A' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Reason</span>
                            <span class="info-value">{{ $procedure->cancellation_reason ?? 'N/A' }}</span>
                        </div>
                        @if(($procedure->refund_amount ?? 0) > 0)
                        <div class="info-item">
                            <span class="info-label">Refund</span>
                            <span class="info-value text-success font-weight-bold">₦{{ number_format($procedure->refund_amount, 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Actions --}}
            <div class="section-card actions-card">
                <div class="section-card-header">
                    <h5><i class="fa fa-cogs"></i> Actions</h5>
                </div>
                <div class="section-card-body action-btn-group">
                    @if(!in_array($procedure->procedure_status, ['completed', 'cancelled']))
                        {{-- Forward Actions --}}
                        <button class="btn btn-success btn-block" onclick="confirmAction('complete', 'Mark as Complete', 'Are you sure you want to mark this procedure as completed?', 'This action indicates the procedure has been successfully finished.', 'success', 'check-circle')">
                            <i class="fa fa-check-circle"></i> Mark as Complete
                        </button>
                        @if($procedure->procedure_status === 'requested')
                            <button class="btn btn-info btn-block" onclick="confirmAction('scheduled', 'Mark as Scheduled', 'Schedule this procedure?', 'The procedure will be marked as scheduled and ready for the operating room.', 'info', 'calendar-check')">
                                <i class="fa fa-calendar-check"></i> Mark as Scheduled
                            </button>
                        @endif
                        @if($procedure->procedure_status === 'scheduled')
                            <button class="btn btn-warning btn-block" onclick="confirmAction('in_progress', 'Start Procedure', 'Start this procedure now?', 'The procedure will be marked as in progress. Make sure all preparations are complete.', 'warning', 'play-circle')">
                                <i class="fa fa-play-circle"></i> Start Procedure
                            </button>
                        @endif

                        {{-- Backtrack Actions --}}
                        @if($procedure->procedure_status === 'scheduled')
                            <button class="btn btn-outline-secondary btn-block" onclick="confirmAction('requested', 'Revert to Requested', 'Revert this procedure back to Requested status?', 'This will unschedule the procedure.', 'secondary', 'undo')">
                                <i class="fa fa-undo"></i> Revert to Requested
                            </button>
                        @endif
                        @if($procedure->procedure_status === 'in_progress')
                            <button class="btn btn-outline-secondary btn-block" onclick="confirmAction('scheduled', 'Revert to Scheduled', 'Revert this procedure back to Scheduled status?', 'This will mark the procedure as not yet started.', 'secondary', 'undo')">
                                <i class="fa fa-undo"></i> Revert to Scheduled
                            </button>
                        @endif

                        <hr class="my-2">
                        <button class="btn btn-danger btn-block" onclick="openCancelModal()">
                            <i class="fa fa-times-circle"></i> Cancel Procedure
                        </button>
                    @endif

                    {{-- Reopen completed procedure --}}
                    @if($procedure->procedure_status === 'completed')
                        <button class="btn btn-outline-warning btn-block" onclick="confirmAction('in_progress', 'Reopen Procedure', 'Reopen this completed procedure?', 'This will set the procedure back to In Progress status. Use this if additional work is needed.', 'warning', 'redo')">
                            <i class="fa fa-redo"></i> Reopen Procedure
                        </button>
                    @endif

                    <button class="btn btn-secondary btn-block" onclick="openPrintSelectionModal()">
                        <i class="fa fa-print"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Print Selection Modal --}}
<div class="modal fade" id="printSelectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title"><i class="fa fa-print"></i> Print Options</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Select sections to include in the printout:</p>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_patient_info" value="patient_info" checked>
                        <label class="custom-control-label" for="print_patient_info">
                            <i class="fa fa-user text-primary mr-2"></i> Patient Information
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_procedure_details" value="procedure_details" checked>
                        <label class="custom-control-label" for="print_procedure_details">
                            <i class="fa fa-procedures text-info mr-2"></i> Procedure Details
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_team" value="team" checked>
                        <label class="custom-control-label" for="print_team">
                            <i class="fa fa-users text-success mr-2"></i> Surgical Team
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_notes" value="notes" checked>
                        <label class="custom-control-label" for="print_notes">
                            <i class="fa fa-sticky-note text-warning mr-2"></i> Procedure Notes
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_labs" value="labs" checked>
                        <label class="custom-control-label" for="print_labs">
                            <i class="fa fa-flask text-primary mr-2"></i> Lab Results
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_imaging" value="imaging" checked>
                        <label class="custom-control-label" for="print_imaging">
                            <i class="fa fa-x-ray mr-2" style="color:#9c27b0"></i> Imaging Results
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_meds" value="meds" checked>
                        <label class="custom-control-label" for="print_meds">
                            <i class="fa fa-pills text-success mr-2"></i> Medications
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_outcome" value="outcome" checked>
                        <label class="custom-control-label" for="print_outcome">
                            <i class="fa fa-flag-checkered text-dark mr-2"></i> Procedure Outcome
                        </label>
                    </div>
                </div>

                <div class="print-option-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input print-section-check" id="print_billing" value="billing" checked>
                        <label class="custom-control-label" for="print_billing">
                            <i class="fa fa-file-invoice-dollar text-success mr-2"></i> Billing Summary
                        </label>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="selectAllPrintOptions(true)">
                        <i class="fa fa-check-square"></i> Select All
                    </button>
                    <span class="mx-2">|</span>
                    <button type="button" class="btn btn-sm btn-link p-0" onclick="selectAllPrintOptions(false)">
                        <i class="fa fa-square"></i> Deselect All
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="executePrint()">
                    <i class="fa fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Add Team Member Modal --}}
<div class="modal fade" id="addTeamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title"><i class="fa fa-user-plus"></i> Add Team Member</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="addTeamForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Staff Member <span class="text-danger">*</span></label>
                        <select class="form-control" id="team_user_id" name="user_id" required>
                            <option value="">-- Select Staff --</option>
                            @php
                                $staffUsers = \App\Models\User::with(['category', 'staff_profile.specialization'])
                                    ->where('status', 1)
                                    ->where('is_admin', '!=', 19)
                                    ->orderBy('surname')->orderBy('firstname')->get();
                            @endphp
                            @foreach($staffUsers as $user)
                                @php
                                    $cat = $user->category->name ?? 'Staff';
                                    $spec = $user->staff_profile->specialization->name ?? null;
                                    $lbl = userfullname($user->id) . ' - ' . $cat;
                                    if($spec) $lbl .= ' (' . $spec . ')';
                                @endphp
                                <option value="{{ $user->id }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Role <span class="text-danger">*</span></label>
                        <select class="form-control" id="team_role" name="role" required onchange="toggleCustomRoleField()">
                            <option value="">-- Select Role --</option>
                            @foreach(\App\Models\ProcedureTeamMember::ROLES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="custom_role_group" style="display: none;">
                        <label>Custom Role <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="team_custom_role" name="custom_role" placeholder="Enter custom role">
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="team_is_lead" name="is_lead" value="1">
                            <label class="custom-control-label" for="team_is_lead">Lead/Primary for this role</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" id="team_notes" name="notes" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Team Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Note Modal --}}
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="noteModalTitle"><i class="fa fa-sticky-note"></i> Add Note</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="addNoteForm">
                <input type="hidden" id="note_id" name="note_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Note Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="note_type" name="note_type" required>
                                    @foreach(\App\Models\ProcedureNote::NOTE_TYPES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="note_title" name="title" required placeholder="Note title">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="note_content" name="content" rows="8"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="noteSubmitBtn">Save Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Item Modal --}}
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="addItemModalTitle"><i class="fa fa-plus"></i> Add Item</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="addItemForm">
                <input type="hidden" id="item_type" name="item_type" value="lab">
                <div class="modal-body">
                    <div class="form-group" id="service_select_group">
                        <label>Service <span class="text-danger">*</span></label>
                        <select class="form-control chosen-service" id="item_service_id" name="service_id" data-placeholder="-- Search Service --">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="form-group" id="product_select_group" style="display: none;">
                        <label>Product <span class="text-danger">*</span></label>
                        <select class="form-control chosen-product" id="item_product_id" name="product_id" data-placeholder="-- Search Product --">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="form-group" id="qty_group" style="display: none;">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="item_qty" name="qty" value="1" min="1">
                    </div>
                    <div class="form-group" id="dose_group" style="display: none;">
                        <label>Dose/Instructions</label>
                        <input type="text" class="form-control" id="item_dose" name="dose" placeholder="e.g., 500mg twice daily">
                    </div>
                    <div class="form-group" id="note_group">
                        <label>Note</label>
                        <textarea class="form-control" id="item_note" name="note" rows="2" placeholder="Additional notes"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Billing Option <span class="text-danger">*</span></label>
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" class="custom-control-input" id="bundled_yes" name="is_bundled" value="1" checked>
                            <label class="custom-control-label" for="bundled_yes"><strong>Bundle with Procedure Fee</strong><br><small class="text-muted">Included in package price</small></label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" class="custom-control-input" id="bundled_no" name="is_bundled" value="0">
                            <label class="custom-control-label" for="bundled_no"><strong>Bill Separately</strong><br><small class="text-muted">Normal billing flow</small></label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Procedure</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Cancel Procedure Modal --}}
<div class="modal fade" id="cancelProcedureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Cancel Procedure</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="cancelProcedureForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong><i class="fa fa-exclamation-circle"></i> Warning!</strong>
                        <p class="mb-0">You are about to cancel this procedure. This action cannot be undone.</p>
                    </div>
                    <div class="mb-3"><strong>Procedure:</strong> {{ $procedure->service->service_name ?? 'Procedure' }}</div>
                    @if($procedure->productOrServiceRequest && $procedure->productOrServiceRequest->payment_id)
                        <div class="form-group">
                            <label>Amount Paid</label>
                            <div class="alert alert-info py-2"><strong>₦{{ number_format($procedure->productOrServiceRequest->amount ?? 0, 2) }}</strong></div>
                        </div>
                        <div class="form-group">
                            <label>Refund Amount</label>
                            <input type="number" class="form-control" id="refund_amount" name="refund_amount" value="{{ $procedure->productOrServiceRequest->amount ?? 0 }}" min="0" max="{{ $procedure->productOrServiceRequest->amount ?? 0 }}" step="0.01">
                            <small class="text-muted">Enter 0 for no refund</small>
                        </div>
                    @endif
                    <div class="form-group">
                        <label>Cancellation Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required placeholder="Please provide a reason"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Keep Procedure</button>
                    <button type="submit" class="btn btn-danger">Cancel & Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Generic Confirmation Modal --}}
<div class="modal fade" id="confirmActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="confirmModalHeader" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="confirmModalTitle"><i class="fa fa-question-circle"></i> Confirm Action</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body text-center py-4">
                <div id="confirmModalIcon" class="mb-3">
                    <i class="fa fa-question-circle fa-4x text-primary"></i>
                </div>
                <h5 id="confirmModalMessage">Are you sure you want to proceed?</h5>
                <p id="confirmModalSubtext" class="text-muted mb-0"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary px-4" id="confirmModalBtn" onclick="">
                    <i class="fa fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('assets/js/chosen.jquery.min.js') }}"></script>
<script>
const procedureId = {{ $procedure->id }};
const patientId = {{ $procedure->patient_id }};
const labCategoryId = {{ appsettings('investigation_category_id') ?? 0 }};
const imagingCategoryId = {{ appsettings('imaging_category_id') ?? 0 }};
let noteEditorInstance = null;

// Modal openers
function openAddTeamModal() { $('#addTeamModal').modal('show'); }
function openAddNoteModal() {
    // Reset form for add mode
    $('#note_id').val('');
    $('#note_type').val('pre_op').prop('disabled', false);
    $('#note_title').val('');
    $('#noteModalTitle').html('<i class="fa fa-sticky-note"></i> Add Note');
    $('#noteSubmitBtn').text('Save Note');

    $('#addNoteModal').modal('show');
    // Initialize CKEditor after modal is shown
    setTimeout(function() {
        initializeNoteEditor();
        if (noteEditorInstance) {
            noteEditorInstance.setData('');
        }
    }, 300);
}
function openCancelModal() { $('#cancelProcedureModal').modal('show'); }

// Initialize CKEditor for notes
function initializeNoteEditor() {
    // Destroy existing instance if any
    if (noteEditorInstance) {
        noteEditorInstance.destroy().catch(err => console.log('Error destroying editor:', err));
        noteEditorInstance = null;
    }

    const editorElement = document.querySelector('#note_content');
    if (!editorElement) return;

    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(editorElement, {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic', 'underline', 'strikethrough',
                        '|', 'bulletedList', 'numberedList',
                        '|', 'link', 'insertTable', 'blockQuote',
                        '|', 'outdent', 'indent'
                    ]
                },
                placeholder: 'Enter note content here...'
            })
            .then(editor => {
                noteEditorInstance = editor;
            })
            .catch(error => {
                console.error('Error initializing CKEditor:', error);
            });
    }
}
function toggleCustomRoleField() {
    const role = $('#team_role').val();
    if (role === 'other') {
        $('#custom_role_group').show();
        $('#team_custom_role').prop('required', true);
    } else {
        $('#custom_role_group').hide();
        $('#team_custom_role').prop('required', false).val('');
    }
}

// Add Team Member
$('#addTeamForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    $.ajax({
        url: `/patient-procedures/${procedureId}/team`,
        type: 'POST',
        data: formData,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Team member added');
                $('#addTeamModal').modal('hide');
                $('#addTeamForm')[0].reset();
                location.reload();
            } else {
                toastr.error(response.message || 'Error adding team member');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error adding team member'); }
    });
});

// Remove Team Member
function removeTeamMember(memberId) {
    if (!confirm('Remove this team member?')) return;
    $.ajax({
        url: `/patient-procedures/${procedureId}/team/${memberId}`,
        type: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success('Team member removed');
                $(`#team-member-${memberId}`).fadeOut(300, function() { $(this).remove(); });
            } else {
                toastr.error(response.message || 'Error');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error'); }
    });
}

// Add/Edit Note Form Submit
$('#addNoteForm').on('submit', function(e) {
    e.preventDefault();

    // Get content from CKEditor if available
    let content = '';
    if (noteEditorInstance) {
        content = noteEditorInstance.getData();
    } else {
        content = $('#note_content').val();
    }

    if (!content || content.trim() === '') {
        toastr.error('Please enter note content');
        return;
    }

    const noteId = $('#note_id').val();
    const isEdit = noteId && noteId !== '';

    const formData = {
        note_type: $('#note_type').val(),
        title: $('#note_title').val(),
        content: content
    };

    const url = isEdit
        ? `/patient-procedures/${procedureId}/notes/${noteId}`
        : `/patient-procedures/${procedureId}/notes`;
    const method = isEdit ? 'PUT' : 'POST';

    $.ajax({
        url: url,
        type: method,
        data: formData,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || (isEdit ? 'Note updated' : 'Note added'));
                $('#addNoteModal').modal('hide');
                // Reset form and destroy editor
                $('#addNoteForm')[0].reset();
                if (noteEditorInstance) {
                    noteEditorInstance.setData('');
                }
                location.reload();
            } else {
                toastr.error(response.message || 'Error saving note');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error saving note'); }
    });
});

// Delete Note
function deleteNote(noteId) {
    confirmDelete('note', noteId, 'Delete Note', 'Are you sure you want to delete this note?', 'This action cannot be undone.');
}

function executeDeleteNote(noteId) {
    $.ajax({
        url: `/patient-procedures/${procedureId}/notes/${noteId}`,
        type: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success('Note deleted');
                $(`#note-${noteId}`).fadeOut(300, function() { $(this).remove(); });
                $('#confirmActionModal').modal('hide');
            } else {
                toastr.error(response.message || 'Error');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error'); }
    });
}

// Edit Note
function editNote(noteId) {
    // Fetch note data
    $.ajax({
        url: `/patient-procedures/${procedureId}/notes/${noteId}/edit`,
        type: 'GET',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success && response.note) {
                const note = response.note;

                // Set form to edit mode
                $('#note_id').val(note.id);
                $('#note_type').val(note.note_type).prop('disabled', true); // Can't change type on edit
                $('#note_title').val(note.title);
                $('#noteModalTitle').html('<i class="fa fa-edit"></i> Edit Note');
                $('#noteSubmitBtn').text('Update Note');

                $('#addNoteModal').modal('show');

                // Initialize CKEditor and set content
                setTimeout(function() {
                    initializeNoteEditor();
                    setTimeout(function() {
                        if (noteEditorInstance) {
                            noteEditorInstance.setData(note.content || '');
                        }
                    }, 200);
                }, 300);
            } else {
                toastr.error(response.message || 'Error loading note');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error loading note');
        }
    });
}

// Add Item Modal
let currentItemType = null;

function openAddItemModal(type) {
    $('#item_type').val(type);
    currentItemType = type;

    // Reset selects
    $('#item_service_id').val('').trigger('chosen:updated');
    $('#item_product_id').val('').trigger('chosen:updated');

    if (type === 'lab') {
        $('#addItemModalTitle').html('<i class="fa fa-flask"></i> Add Lab Request');
        $('#service_select_group').show();
        $('#product_select_group, #qty_group, #dose_group').hide();
        loadServicesForChosen(labCategoryId, '-- Search Lab Service --');

    } else if (type === 'imaging') {
        $('#addItemModalTitle').html('<i class="fa fa-x-ray"></i> Add Imaging Request');
        $('#service_select_group').show();
        $('#product_select_group, #qty_group, #dose_group').hide();
        loadServicesForChosen(imagingCategoryId, '-- Search Imaging Service --');

    } else if (type === 'medication') {
        $('#addItemModalTitle').html('<i class="fa fa-pills"></i> Add Medication');
        $('#service_select_group').hide();
        $('#product_select_group, #qty_group, #dose_group').show();
        loadProductsForChosen();
    }

    $('#addItemModal').modal('show');
}

// Load services and populate Chosen dropdown
function loadServicesForChosen(categoryId, placeholder) {
    const $select = $('#item_service_id');
    $select.empty().append('<option value=""></option>');

    // Destroy and reinitialize Chosen
    if ($select.data('chosen')) {
        $select.chosen('destroy');
    }

    $.ajax({
        url: '{{ route("live-search-services") }}',
        dataType: 'json',
        data: {
            term: '',
            category_id: categoryId,
            patient_id: patientId
        },
        success: function(data) {
            data.forEach(function(service) {
                const price = service.price?.sale_price || 0;
                const text = service.service_name + ' [' + service.service_code + '] - ₦' + formatMoney(price);
                $select.append($('<option>', { value: service.id, text: text }));
            });
            $select.chosen({
                allow_single_deselect: true,
                search_contains: true,
                placeholder_text_single: placeholder,
                width: '100%'
            });
        },
        error: function() {
            toastr.error('Failed to load services');
            $select.chosen({ placeholder_text_single: placeholder, width: '100%' });
        }
    });
}

// Load products and populate Chosen dropdown
function loadProductsForChosen() {
    const $select = $('#item_product_id');
    $select.empty().append('<option value=""></option>');

    // Destroy and reinitialize Chosen
    if ($select.data('chosen')) {
        $select.chosen('destroy');
    }

    $.ajax({
        url: '{{ route("live-search-products") }}',
        dataType: 'json',
        data: {
            term: '',
            patient_id: patientId
        },
        success: function(data) {
            data.forEach(function(product) {
                const price = product.price?.sale_price || 0;
                const text = product.product_name + ' [' + product.product_code + '] - ₦' + formatMoney(price);
                $select.append($('<option>', { value: product.id, text: text }));
            });
            $select.chosen({
                allow_single_deselect: true,
                search_contains: true,
                placeholder_text_single: '-- Search Product --',
                width: '100%'
            });
        },
        error: function() {
            toastr.error('Failed to load products');
            $select.chosen({ placeholder_text_single: '-- Search Product --', width: '100%' });
        }
    });
}

// Format money helper
function formatMoney(amount) {
    return parseFloat(amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Add Item Form
$('#addItemForm').on('submit', function(e) {
    e.preventDefault();
    const type = $('#item_type').val();
    let url = `/patient-procedures/${procedureId}/items/${type}`;
    if (type === 'medication') url = `/patient-procedures/${procedureId}/items/medication`;
    $.ajax({
        url: url,
        type: 'POST',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Item added');
                $('#addItemModal').modal('hide');
                location.reload();
            } else {
                toastr.error(response.message || 'Error');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error'); }
    });
});

// Remove Item
function removeItem(itemId) {
    confirmDelete('item', itemId, 'Remove Item', 'Are you sure you want to remove this item?', 'This will unlink the item from this procedure.');
}

function executeRemoveItem(itemId) {
    $.ajax({
        url: `/patient-procedures/${procedureId}/items/${itemId}`,
        type: 'DELETE',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success('Item removed');
                $(`#item-${itemId}`).fadeOut(300, function() { $(this).remove(); });
                $('#confirmActionModal').modal('hide');
            } else {
                toastr.error(response.message || 'Error');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error'); }
    });
}

// Confirm Delete Helper
function confirmDelete(type, id, title, message, subtext) {
    $('#confirmModalHeader').css('background', '#dc3545').css('color', 'white');
    $('#confirmModalTitle').html(`<i class="fa fa-trash"></i> ${title}`);
    $('#confirmModalIcon').html('<i class="fa fa-exclamation-triangle fa-4x text-danger"></i>');
    $('#confirmModalMessage').text(message);
    $('#confirmModalSubtext').text(subtext || '');

    const btn = $('#confirmModalBtn');
    btn.removeClass('btn-primary btn-success btn-info btn-warning')
       .addClass('btn-danger')
       .html('<i class="fa fa-trash"></i> Delete')
       .prop('disabled', false);

    if (type === 'note') {
        btn.attr('onclick', `executeDeleteNote(${id})`);
    } else if (type === 'item') {
        btn.attr('onclick', `executeRemoveItem(${id})`);
    }

    $('#confirmActionModal').modal('show');
}

// Cancel Procedure
$('#cancelProcedureForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: `/patient-procedures/${procedureId}/cancel`,
        type: 'POST',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Procedure cancelled');
                $('#cancelProcedureModal').modal('hide');
                location.reload();
            } else {
                toastr.error(response.message || 'Error');
            }
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error'); }
    });
});

// Toggle Outcome Edit
function toggleOutcomeEdit() {
    const display = document.getElementById('outcome-display');
    const form = document.getElementById('outcome-form-wrapper');

    if (form.style.display === 'none') {
        display.style.display = 'none';
        form.style.display = 'block';
    } else {
        display.style.display = 'block';
        form.style.display = 'none';
    }
}

// Update Outcome
$('#outcome-form').on('submit', function(e) {
    e.preventDefault();
    const btn = $(this).find('button[type="submit"]');
    const originalText = btn.html();
    btn.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);

    $.ajax({
        url: `/patient-procedures/${procedureId}/outcome`,
        type: 'PUT',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success('Outcome saved successfully');
                // Reload page to show updated outcome display
                location.reload();
            } else {
                toastr.error(response.message || 'Error');
                btn.html(originalText).prop('disabled', false);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error');
            btn.html(originalText).prop('disabled', false);
        }
    });
});

// Update Status
function updateStatus(status) {
    const btn = $('#confirmModalBtn');
    const originalText = btn.html();
    btn.html('<i class="fa fa-spinner fa-spin"></i> Processing...').prop('disabled', true);

    $.ajax({
        url: `/patient-procedures/${procedureId}`,
        type: 'PUT',
        data: { procedure_status: status },
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success('Status updated successfully');
                location.reload();
            } else {
                toastr.error(response.message || 'Error');
                btn.html(originalText).prop('disabled', false);
                $('#confirmActionModal').modal('hide');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error');
            btn.html(originalText).prop('disabled', false);
            $('#confirmActionModal').modal('hide');
        }
    });
}

// Complete Procedure
function completeProcedure() {
    const btn = $('#confirmModalBtn');
    const originalText = btn.html();
    btn.html('<i class="fa fa-spinner fa-spin"></i> Processing...').prop('disabled', true);

    $.ajax({
        url: `/patient-procedures/${procedureId}/complete`,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                toastr.success('Procedure marked as completed');
                location.reload();
            } else {
                toastr.error(response.message || 'Error');
                btn.html(originalText).prop('disabled', false);
                $('#confirmActionModal').modal('hide');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error');
            btn.html(originalText).prop('disabled', false);
            $('#confirmActionModal').modal('hide');
        }
    });
}

// Confirm Action Modal
function confirmAction(action, title, message, subtext, colorClass, icon) {
    const colors = {
        'success': '#28a745',
        'info': '#17a2b8',
        'warning': '#ffc107',
        'danger': '#dc3545',
        'primary': '{{ $hosColor }}'
    };

    const textColors = {
        'warning': '#212529'
    };

    $('#confirmModalHeader').css('background', colors[colorClass] || colors['primary']);
    $('#confirmModalHeader').css('color', textColors[colorClass] || 'white');
    $('#confirmModalTitle').html(`<i class="fa fa-${icon}"></i> ${title}`);
    $('#confirmModalIcon').html(`<i class="fa fa-${icon} fa-4x text-${colorClass}"></i>`);
    $('#confirmModalMessage').text(message);
    $('#confirmModalSubtext').text(subtext || '');

    const btn = $('#confirmModalBtn');
    btn.removeClass('btn-primary btn-success btn-info btn-warning btn-danger')
       .addClass(`btn-${colorClass}`)
       .html(`<i class="fa fa-check"></i> Confirm`)
       .prop('disabled', false);

    // Set the action
    if (action === 'complete') {
        btn.attr('onclick', 'completeProcedure()');
    } else {
        btn.attr('onclick', `updateStatus('${action}')`);
    }

    $('#confirmActionModal').modal('show');
}

// =========================================================================
// PRINT FUNCTIONS
// =========================================================================

// Open print selection modal
function openPrintSelectionModal() {
    $('#printSelectionModal').modal('show');
}

// Select/deselect all print options
function selectAllPrintOptions(select) {
    $('.print-section-check').prop('checked', select);
}

// Execute print with selected options
function executePrint() {
    const sections = [];
    $('.print-section-check:checked').each(function() {
        sections.push($(this).val());
    });

    if (sections.length === 0) {
        toastr.warning('Please select at least one section to print');
        return;
    }

    // Build query string
    const queryParams = sections.map(s => `${s}=1`).join('&');
    const printUrl = `/patient-procedures/${procedureId}/print?${queryParams}`;

    $('#printSelectionModal').modal('hide');
    window.open(printUrl, '_blank', 'width=900,height=700');
}

// Legacy print function (direct print without selection)
function printProcedure(procedureId) {
    openPrintSelectionModal();
}

// =========================================================================
// PROCEDURE ORDERS HISTORY DATATABLES
// =========================================================================

let labHistoryTable = null;
let imagingHistoryTable = null;
let medsHistoryTable = null;

// Initialize history DataTables on document ready
$(document).ready(function() {
    // Initialize Lab History DataTable after a short delay to ensure everything is loaded
    setTimeout(function() {
        if (typeof $.fn.DataTable !== 'undefined' && $('#procedure_lab_history').length) {
            initLabHistoryTable();
        }
    }, 300);

    // Initialize other tables when their tabs are shown
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        if (target === '#proc-orders-imaging' && !imagingHistoryTable) {
            initImagingHistoryTable();
        } else if (target === '#proc-orders-meds' && !medsHistoryTable) {
            initMedsHistoryTable();
        }
    });
});

function initLabHistoryTable() {
    if (typeof $.fn.DataTable === 'undefined' || !$('#procedure_lab_history').length) return;

    if ($.fn.DataTable.isDataTable('#procedure_lab_history')) {
        $('#procedure_lab_history').DataTable().destroy();
    }

    labHistoryTable = $('#procedure_lab_history').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: `/patient-procedures/${procedureId}/lab-history`,
            type: 'GET'
        },
        columns: [
            { data: "info", name: "info", orderable: false, searchable: true }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[5, 10, 25], [5, 10, 25]],
        language: {
            emptyTable: "<div class='text-center text-muted py-4'><i class='fa fa-flask fa-2x mb-2 d-block'></i>No lab requests for this procedure</div>",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        }
    });
}

function initImagingHistoryTable() {
    if (typeof $.fn.DataTable === 'undefined' || !$('#procedure_imaging_history').length) return;

    if ($.fn.DataTable.isDataTable('#procedure_imaging_history')) {
        $('#procedure_imaging_history').DataTable().destroy();
    }

    imagingHistoryTable = $('#procedure_imaging_history').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: `/patient-procedures/${procedureId}/imaging-history`,
            type: 'GET'
        },
        columns: [
            { data: "info", name: "info", orderable: false, searchable: true }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[5, 10, 25], [5, 10, 25]],
        language: {
            emptyTable: "<div class='text-center text-muted py-4'><i class='fa fa-x-ray fa-2x mb-2 d-block'></i>No imaging requests for this procedure</div>",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        }
    });
}

function initMedsHistoryTable() {
    if (typeof $.fn.DataTable === 'undefined' || !$('#procedure_meds_history').length) return;

    if ($.fn.DataTable.isDataTable('#procedure_meds_history')) {
        $('#procedure_meds_history').DataTable().destroy();
    }

    medsHistoryTable = $('#procedure_meds_history').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: `/patient-procedures/${procedureId}/medication-history`,
            type: 'GET'
        },
        columns: [
            { data: "info", name: "info", orderable: false, searchable: true }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[5, 10, 25], [5, 10, 25]],
        language: {
            emptyTable: "<div class='text-center text-muted py-4'><i class='fa fa-pills fa-2x mb-2 d-block'></i>No medications for this procedure</div>",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        }
    });
}

// View Lab Result (opens modal or redirects)
function viewLabResult(requestId) {
    window.open(`/service-requests/${requestId}`, '_blank');
}

// View Imaging Result
function viewImagingResult(requestId) {
    window.open(`/imaging-requests/${requestId}`, '_blank');
}
</script>
@endsection
