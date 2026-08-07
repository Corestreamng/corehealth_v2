{{-- Treatment Plans Tab — Full Patient-Scoped Plan Management (Phase 7) --}}
{{-- Positioned after Clinical Notes tab in the encounter view --}}
<style>
    /* ═══ Treatment Plans Tab Accent ═══ */
    .tp-tab-accent {
        background: linear-gradient(135deg, #e0f2f1, #b2dfdb) !important;
        border: 2px solid #00897b !important;
        border-radius: 8px 8px 0 0 !important;
        font-weight: 600 !important;
        color: #00695c !important;
        position: relative;
    }
    .tp-tab-accent.active {
        background: #fff !important;
        border-bottom-color: #fff !important;
        color: #00796b !important;
        box-shadow: 0 -2px 8px rgba(0, 121, 107, 0.15);
    }
    .tp-tab-pulse {
        width: 8px; height: 8px;
        background: #00e676;
        border-radius: 50%;
        display: inline-block;
        margin-left: 6px;
        animation: tp-pulse 2s ease-in-out infinite;
    }
    @keyframes tp-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(1.4); }
    }
    /* ═══ Active Plan Context Bar ═══ */
    .tp-active-context-bar { margin-bottom: 8px; transition: all 0.3s ease; }
    .tp-plan-active-border { border-left: 4px solid #00897b !important; box-shadow: -4px 0 12px rgba(0, 137, 123, 0.1) !important; transition: all 0.3s ease; }
    /* ═══ Plan Links in Datatables ═══ */
    .tp-view-link { color: #00796b !important; text-decoration: none; font-weight: 500; font-size: 0.82rem; white-space: nowrap; transition: all 0.15s ease; }
    .tp-view-link:hover { color: #004d40 !important; text-decoration: underline; }
    /* ═══ Notes Plan Widget ═══ */
    .tp-notes-context-widget .form-select:focus { border-color: #00897b; box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25); }
    /* ═══ Teal Utilities ═══ */
    .bg-teal { background-color: #00897b !important; }
    .text-teal { color: #00796b !important; }
    .btn-teal { background-color: #00796b; border-color: #00796b; color: #fff; }
    .btn-teal:hover { background-color: #00695c; border-color: #00695c; color: #fff; }
    .btn-outline-teal { color: #00796b; border-color: #00796b; background: transparent; }
    .btn-outline-teal:hover { background-color: #00796b; color: #fff; }
    /* ═══ Notes sub-tab link to Treatment Plans ═══ */
    .tp-tab-link-accent { color: #00796b !important; font-weight: 500; border-bottom: 2px dashed #80cbc4 !important; }
    .tp-tab-link-accent:hover { background-color: #e0f2f1 !important; }
    /* ═══ Plan Card Styles ═══ */
    .tp-plan-card {
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        background: #fff;
        transition: all 0.25s ease;
        overflow: hidden;
    }
    .tp-plan-card:hover { border-color: #80cbc4; box-shadow: 0 4px 16px rgba(0,121,107,0.08); }
    .tp-plan-card.tp-active-card { border-color: #00897b; border-width: 2px; box-shadow: 0 4px 16px rgba(0,121,107,0.15); }
    .tp-plan-card-header {
        padding: 12px 16px;
        background: linear-gradient(135deg, #f5fffe 0%, #e0f7fa 100%);
        border-bottom: 1px solid #e0f2f1;
        cursor: pointer;
    }
    .tp-plan-card-body { padding: 16px; }
    .tp-plan-card .tp-item-group { margin-bottom: 8px; }
    .tp-item-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 500;
        background: #f5f5f5; color: #333; transition: background 0.2s;
    }
    .tp-item-badge:hover { background: #e8f5e9; }
    .tp-item-badge.completed { background: #e8f5e9; color: #2e7d32; text-decoration: line-through; }
    .tp-item-badge.pending { background: #fff3e0; color: #e65100; }
    .tp-item-badge.active-item { background: #e3f2fd; color: #1565c0; }
    .tp-priority-badge {
        font-size: 0.65rem; padding: 2px 8px; border-radius: 4px; font-weight: 600; text-transform: uppercase;
    }
    .tp-priority-low { background: #e8f5e9; color: #388e3c; }
    .tp-priority-medium { background: #fff3e0; color: #f57c00; }
    .tp-priority-high { background: #fce4ec; color: #c62828; }
    .tp-priority-urgent { background: #c62828; color: #fff; }
    .tp-quick-action-panel { position: sticky; top: 0; }
    .tp-quick-btn {
        display: flex; align-items: center; gap: 8px;
        width: 100%; padding: 8px 12px; margin-bottom: 6px;
        border: 1px solid #e0e0e0; border-radius: 8px; background: #fff;
        font-size: 0.8rem; font-weight: 500; color: #424242;
        transition: all 0.15s ease; cursor: pointer; text-align: left;
    }
    .tp-quick-btn:hover { border-color: #00897b; color: #00796b; background: #e0f7fa; transform: translateX(3px); }
    .tp-create-modal .modal-header { background: linear-gradient(135deg, #00796b, #004d40); }
    .tp-progress-ring {
        width: 44px; height: 44px; border-radius: 50%;
        background: conic-gradient(#00897b var(--progress, 0%), #e0e0e0 var(--progress, 0%));
        display: flex; align-items: center; justify-content: center;
    }
    .tp-progress-ring-inner {
        width: 34px; height: 34px; border-radius: 50%; background: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.6rem; font-weight: 700; color: #00796b;
    }

    /* ═══ Priority Chips ═══ */
    .tp-priority-chip { background-color: #f1f5f9; color: #64748b; transition: all 0.2s ease; }
    .tp-priority-chip[data-priority="low"].active { background-color: #059669 !important; color: #ffffff !important; box-shadow: 0 2px 6px rgba(5,150,105,0.3); }
    .tp-priority-chip[data-priority="medium"].active { background-color: #d97706 !important; color: #ffffff !important; box-shadow: 0 2px 6px rgba(217,119,6,0.3); }
    .tp-priority-chip[data-priority="high"].active { background-color: #ea580c !important; color: #ffffff !important; box-shadow: 0 2px 6px rgba(234,88,12,0.3); }
    .tp-priority-chip[data-priority="urgent"].active { background-color: #dc2626 !important; color: #ffffff !important; box-shadow: 0 2px 6px rgba(220,38,38,0.3); }

    /* ═══ Department Chips ═══ */
    .tp-dept-chip { background-color: #ffffff; color: #475569; border: 1px solid #cbd5e1; transition: all 0.2s ease; }
    .tp-dept-chip:hover { background-color: #f8fafc; border-color: #94a3b8; }
    .tp-dept-chip.active { background-color: #00796b !important; color: #ffffff !important; border-color: #00796b !important; box-shadow: 0 2px 6px rgba(0,121,107,0.25); }
    .tp-dept-chip[data-dept="all"].active { background-color: #0288d1 !important; border-color: #0288d1 !important; box-shadow: 0 2px 6px rgba(2,136,209,0.25); }
</style>

<div class="card-modern mt-2">
    <div class="card-body">

        {{-- Onboarding Guide --}}
        <div class="alert alert-info d-flex align-items-center shadow-sm mb-4" style="border-radius: 8px; border-left: 4px solid #0dcaf0; background-color: #f0f9ff;" role="alert">
            <i class="fa fa-info-circle fa-2x me-3 text-info"></i>
            <div>
                <h6 class="mb-1 text-dark fw-bold">Welcome to Treatment Plans</h6>
                <p class="mb-0 text-dark small" style="opacity: 0.9;">
                    Treatment Plans help you organize and track patient care goals across encounters.
                    <strong>Click on any plan below to activate its context.</strong> Once activated, a banner will appear page-wide, and any new clinical orders (labs, meds, imaging, etc.) will be automatically linked to that plan.
                </p>
            </div>
        </div>

        {{-- Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0" style="color: #00796b;">
                    <i class="fa fa-clipboard-list me-2"></i>Patient Treatment Plans
                    <span class="badge bg-teal rounded-pill ms-2" id="tp-plan-total-badge" style="font-size: 0.7rem;">0</span>
                </h5>
                <small class="text-muted">Patient-scoped care goals & order tracking across encounters</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                {{-- Status Filter Tabs --}}
                <div class="btn-group btn-group-sm me-2" role="group" style="background: #e2e8f0; padding: 3px; border-radius: 10px;">
                    <button type="button" class="btn btn-sm tp-status-tab-btn active" id="tp-tab-active" onclick="TreatmentPlansTab.setFilterTab('active')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                        <i class="fa fa-heartbeat me-1"></i> Active Plans (<span id="tp-count-active">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm tp-status-tab-btn text-muted" id="tp-tab-retired" onclick="TreatmentPlansTab.setFilterTab('retired')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                        <i class="fa fa-archive me-1"></i> Retired / History (<span id="tp-count-retired">0</span>)
                    </button>
                </div>

                <button class="btn btn-teal btn-sm shadow-sm" onclick="TreatmentPlansTab.openCreateModal()" style="border-radius: 8px;">
                    <i class="fa fa-plus me-1"></i> Create New Plan
                </button>
            </div>
        </div>

        <div class="row">
            {{-- Plan Cards Column --}}
            <div class="col-md-9" id="tp-plans-container">
                <div class="tp-container pb-5">
                    @php $tpRequired = appsettings('require_treatment_plan_in_consult', false); @endphp
                    <div class="text-center py-5" id="tp-plans-loading">
                        <div class="spinner-border text-teal" role="status" style="width: 2rem; height: 2rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2 mb-0" style="font-size: 0.85rem;">Loading treatment plans...</p>
                    </div>
                </div>
                <div id="tp-plans-empty" class="text-center py-5" style="display: none;">
                    <i class="fa fa-clipboard-list" style="font-size: 3rem; color: #b2dfdb;"></i>
                    <p class="text-muted mt-3 mb-1" style="font-size: 0.95rem;">No treatment plans yet</p>
                    <p class="text-muted mb-3" style="font-size: 0.8rem;">Create a plan to start tracking this patient's care goals and orders</p>
                    <button class="btn btn-teal btn-sm shadow-sm" onclick="TreatmentPlansTab.openCreateModal()" style="border-radius: 8px;">
                        <i class="fa fa-plus me-1"></i> Create First Plan
                    </button>
                </div>

                {{-- Toolbar: Search & Per Page Selector --}}
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" id="tp-toolbar" style="display: none !important;">
                    <div class="input-group input-group-sm flex-grow-1" style="max-width: 360px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
                        <input type="text" id="tp-plan-search-input" class="form-control border-start-0 ps-0" placeholder="Search by name, diagnosis, doctor, ICD..." style="border-radius: 0 8px 8px 0;" oninput="TreatmentPlansTab.onSearchInput(this.value)">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <small class="text-muted fw-semibold">Show:</small>
                        <select id="tp-per-page-select" class="form-select form-select-sm" style="width: 75px; border-radius: 6px;" onchange="TreatmentPlansTab.onPerPageChange(this.value)">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                {{-- Plan List Container --}}
                <div id="tp-plans-list" style="display: none;"></div>

                {{-- Pagination Container --}}
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 pt-2 border-top" id="tp-pagination-container" style="display: none !important;">
                    <small class="text-muted" id="tp-pagination-info">Showing 1 to 5 of 12 plans</small>
                    <nav aria-label="Treatment plans pagination">
                        <ul class="pagination pagination-sm mb-0" id="tp-pagination-links"></ul>
                    </nav>
                </div>
            </div>

            {{-- Quick Action Panel --}}
            <div class="col-md-3">
                <div class="tp-quick-action-panel p-3 rounded-3" style="background: #fafffe; border: 1px solid #e0f2f1;">
                    <h6 class="text-teal mb-3" style="font-size: 0.82rem; font-weight: 600;">
                        <i class="fa fa-bolt me-1"></i> Quick Actions
                    </h6>
                    <div id="tp-quick-actions-content">
                        <p class="text-muted mb-3" style="font-size: 0.75rem;">
                            <i class="fa fa-info-circle me-1"></i>Select a plan to enable quick actions
                        </p>
                    </div>
                    <hr class="my-2">
                    <button class="tp-quick-btn" onclick="switch_tab(event, 'laboratory_services_tab')">
                        <i class="fa fa-flask text-primary"></i> Order Lab Test
                    </button>
                    <button class="tp-quick-btn" onclick="switch_tab(event, 'imaging_services_tab')">
                        <i class="fa fa-x-ray text-info"></i> Order Imaging
                    </button>
                    <button class="tp-quick-btn" onclick="switch_tab(event, 'medications_tab')">
                        <i class="fa fa-pills text-warning"></i> Prescribe Medication
                    </button>
                    <button class="tp-quick-btn" onclick="switch_tab(event, 'procedures_tab')">
                        <i class="fa fa-user-md text-danger"></i> Schedule Procedure
                    </button>
                    <button class="tp-quick-btn" onclick="switch_tab(event, 'non_pharm_tab')">
                        <i class="fa fa-heartbeat text-success"></i> Care Plan Item
                    </button>
                    <button class="tp-quick-btn" onclick="switch_tab(event, 'referrals_tab')">
                        <i class="mdi mdi-account-switch text-purple"></i> Referral
                    </button>
                </div>
            </div>
        </div>

        {{-- Footer info --}}
        <div class="mt-3 px-3 py-2 rounded-2" style="background: #f5fffe; border: 1px dashed #b2dfdb;">
            <small class="text-muted">
                <i class="fa fa-info-circle text-teal me-1"></i>
                Items from this plan appear across all workbenches with
                <i class="fa fa-clipboard-list text-teal mx-1"></i> plan association links.
                Select a plan to set it as your active working context across Labs, Imaging, Meds tabs.
            </small>
        </div>
    </div>
</div>

{{-- Navigation Prompt Modal when leaving Treatment Plans without selecting active plan --}}
<div class="modal fade" id="tpNoActivePlanPromptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 text-white" style="background: linear-gradient(135deg, #00796b, #004d40);">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="fa fa-exclamation-triangle text-warning me-2"></i> No Active Treatment Plan Selected
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                @if($tpRequired)
                <p class="text-danger mb-2 fw-semibold" style="font-size: 0.95rem;">
                    Hospital policy requires that you create or select a treatment plan to work under.
                </p>
                <p class="text-muted small mb-0" style="font-size: 0.82rem;">
                    You cannot proceed with this consultation until an active plan is selected.
                </p>
                @else
                <p class="text-dark mb-2 fw-semibold" style="font-size: 0.95rem;">
                    You are navigating away without setting an active treatment plan context.
                </p>
                <p class="text-muted small mb-0" style="font-size: 0.82rem;">
                    Setting an active plan automatically links any labs, medications, imaging, and care procedures ordered during this encounter to that plan's goals and progress tracker.
                </p>
                @endif
            </div>
            <div class="modal-footer bg-light border-0 px-4 py-3 d-flex justify-content-between">
                @if(!$tpRequired)
                <button type="button" class="btn btn-outline-secondary btn-sm" id="tp-prompt-proceed-btn" style="border-radius: 8px;">
                    Proceed Without Active Plan
                </button>
                @else
                <div></div> <!-- spacer -->
                @endif
                <button type="button" class="btn btn-teal btn-sm shadow-sm" data-bs-dismiss="modal" style="border-radius: 8px;">
                    <i class="fa fa-clipboard-list me-1"></i> Choose Active Plan Now
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Unified Create / Edit Plan Modal --}}
<div class="modal fade tp-create-modal" id="tpCreatePlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden; background: #f8fafc;">
            <!-- Header -->
            <div class="modal-header border-0 text-white px-4 py-3" style="background: linear-gradient(135deg, #00796b 0%, #004d40 100%);">
                <h5 class="modal-title fs-5 fw-bold d-flex align-items-center gap-2" id="tp_form_modal_title">
                    <i class="fa fa-clipboard-list"></i> Create Treatment Plan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4" style="max-height: 80vh; overflow-y: auto;">
                <form id="tpCreatePlanForm">
                    <input type="hidden" id="tp_form_plan_id" value="">
                    <input type="hidden" name="tp_priority" id="tp_create_priority" value="medium">

                    <!-- Section 1: Core Info & Priority -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; background: #ffffff;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center gap-2 mb-3 text-teal">
                                <i class="mdi mdi-bookmark-outline fs-5"></i>
                                <h6 class="mb-0 fw-bold text-teal" style="letter-spacing: 0.3px;">1. General Plan Identification</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold text-dark small mb-1">Plan Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-lg border" name="tp_name" id="tp_create_name" placeholder="e.g. Type II Diabetes & Hypertension Care" required style="border-radius: 10px; font-size: 0.95rem;">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold text-dark small mb-1">Clinical Priority</label>
                                    <div class="d-flex flex-wrap gap-1 p-1 bg-light rounded border align-items-center justify-content-between" style="border-radius: 10px !important;">
                                        <button type="button" class="btn btn-sm tp-priority-chip flex-fill px-2 py-1 border-0" data-priority="low" onclick="TreatmentPlansTab.setPriority('low')" style="border-radius: 8px; font-size: 0.75rem; font-weight: 600;">Low</button>
                                        <button type="button" class="btn btn-sm tp-priority-chip flex-fill px-2 py-1 border-0 active" data-priority="medium" onclick="TreatmentPlansTab.setPriority('medium')" style="border-radius: 8px; font-size: 0.75rem; font-weight: 600;">Medium</button>
                                        <button type="button" class="btn btn-sm tp-priority-chip flex-fill px-2 py-1 border-0" data-priority="high" onclick="TreatmentPlansTab.setPriority('high')" style="border-radius: 8px; font-size: 0.75rem; font-weight: 600;">High</button>
                                        <button type="button" class="btn btn-sm tp-priority-chip flex-fill px-2 py-1 border-0" data-priority="urgent" onclick="TreatmentPlansTab.setPriority('urgent')" style="border-radius: 8px; font-size: 0.75rem; font-weight: 600;">Urgent</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Clinical Diagnoses (ICPC-2) -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; background: #ffffff;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-2 text-teal">
                                    <i class="mdi mdi-stethoscope fs-5"></i>
                                    <h6 class="mb-0 fw-bold text-teal" style="letter-spacing: 0.3px;">2. Primary Clinical Diagnoses (ICPC-2)</h6>
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-teal shadow-sm text-nowrap" id="tp_import_encounter_btn" onclick="TreatmentPlansTab.importEncounterDiagnoses()" style="border-radius: 8px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="mdi mdi-flash me-1"></i> Import Encounter Diagnoses
                                </button>
                            </div>

                            <div class="position-relative mb-2">
                                <div class="input-group input-group-merge shadow-none">
                                    <span class="input-group-text bg-light border-end-0" style="border-radius: 10px 0 0 10px;"><i class="mdi mdi-magnify text-muted fs-5"></i></span>
                                    <input type="text"
                                        class="form-control border-start-0"
                                        id="tp_reasons_for_encounter_search"
                                        placeholder="Search ICPC-2 codes or type clinical problem... (e.g. 'Hypertension', 'A03', 'Fever')"
                                        autocomplete="off" style="border-radius: 0 10px 10px 0; font-size: 0.9rem;">
                                </div>
                                <ul class="list-group shadow-lg position-absolute w-100" id="tp_reasons_search_results" style="display: none; max-height: 230px; overflow-y: auto; z-index: 1050; top: 100%; border-radius: 10px;"></ul>
                            </div>
                            <small class="text-muted d-block mb-3" style="font-size: 0.75rem;">
                                <i class="mdi mdi-information-outline me-1"></i> Type at least 2 characters to search. Select diagnosis to assign status & course.
                            </small>

                            <!-- Selected Diagnoses Table Container -->
                            <div id="tp_selected_reasons_container" class="rounded p-2" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                                <div id="tp_selected_reasons_list">
                                    <div class="text-center py-3 text-muted" style="font-size: 0.85rem;">
                                        <i class="mdi mdi-file-document-outline d-block text-slate-400 mb-1" style="font-size: 1.5rem;"></i>
                                        <span>No diagnoses assigned to this treatment plan yet.</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="tp_diagnosis_data" id="tp_diagnosis_data" value="[]">
                        </div>
                    </div>

                    <!-- Section 3: Goal & Clinical Context -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px; background: #ffffff;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center gap-2 mb-3 text-teal">
                                <i class="mdi mdi-target fs-5"></i>
                                <h6 class="mb-0 fw-bold text-teal" style="letter-spacing: 0.3px;">3. Target Goal & Clinical Instructions</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark small mb-1">Target Goal (SMART)</label>
                                    <textarea class="form-control border" name="tp_goal" id="tp_create_goal" rows="2" placeholder="e.g. Achieve Blood Pressure < 130/80 mmHg & HbA1c < 7% within 3 months" style="border-radius: 10px; font-size: 0.88rem;"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark small mb-1">Description / Clinical Notes</label>
                                    <textarea class="form-control border" name="tp_description" id="tp_create_description" rows="2" placeholder="Optional clinical instructions, dietary guidelines, or team notes..." style="border-radius: 10px; font-size: 0.88rem;"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Department Access Control -->
                    <div class="card border-0 shadow-sm mb-1" style="border-radius: 12px; background: #ffffff;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 text-teal">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-shield-eye-outline fs-5"></i>
                                    <h6 class="mb-0 fw-bold text-teal" style="letter-spacing: 0.3px;">4. Departmental Access Controls</h6>
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">Select workbenches authorized to view badges & progress</small>
                            </div>

                            <div class="d-flex flex-wrap gap-2 p-2 rounded" style="background: #f1f5f9;">
                                <!-- Hidden checkboxes for form compatibility -->
                                <input class="d-none tp-vis-check" type="checkbox" id="tp_vis_all" value="all" checked>
                                <input class="d-none tp-vis-check tp-vis-dept" type="checkbox" id="tp_vis_doctors" value="doctors">
                                <input class="d-none tp-vis-check tp-vis-dept" type="checkbox" id="tp_vis_nursing" value="nursing">
                                <input class="d-none tp-vis-check tp-vis-dept" type="checkbox" id="tp_vis_pharmacy" value="pharmacy">
                                <input class="d-none tp-vis-check tp-vis-dept" type="checkbox" id="tp_vis_lab" value="lab">
                                <input class="d-none tp-vis-check tp-vis-dept" type="checkbox" id="tp_vis_imaging" value="imaging">
                                <input class="d-none tp-vis-check tp-vis-dept" type="checkbox" id="tp_vis_billing" value="billing">

                                <!-- Visual Icon Chips -->
                                <button type="button" class="btn btn-sm tp-dept-chip active" data-dept="all" onclick="TreatmentPlansTab.toggleDeptChip('all')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa fa-globe me-1"></i> All Departments
                                </button>
                                <button type="button" class="btn btn-sm tp-dept-chip" data-dept="doctors" onclick="TreatmentPlansTab.toggleDeptChip('doctors')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa fa-user-md me-1"></i> Doctors
                                </button>
                                <button type="button" class="btn btn-sm tp-dept-chip" data-dept="nursing" onclick="TreatmentPlansTab.toggleDeptChip('nursing')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa fa-user-nurse me-1"></i> Nursing
                                </button>
                                <button type="button" class="btn btn-sm tp-dept-chip" data-dept="pharmacy" onclick="TreatmentPlansTab.toggleDeptChip('pharmacy')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa fa-pills me-1"></i> Pharmacy
                                </button>
                                <button type="button" class="btn btn-sm tp-dept-chip" data-dept="lab" onclick="TreatmentPlansTab.toggleDeptChip('lab')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa fa-flask me-1"></i> Lab
                                </button>
                                <button type="button" class="btn btn-sm tp-dept-chip" data-dept="imaging" onclick="TreatmentPlansTab.toggleDeptChip('imaging')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa fa-x-ray me-1"></i> Imaging
                                </button>
                                <button type="button" class="btn btn-sm tp-dept-chip" data-dept="billing" onclick="TreatmentPlansTab.toggleDeptChip('billing')" style="border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                                    <i class="fa fa-file-invoice-dollar me-1"></i> Billing
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-white border-top px-4 py-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Cancel</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-teal shadow-sm px-4" onclick="TreatmentPlansTab.submitPlanForm()" id="tp_create_submit_btn" style="border-radius: 10px; font-weight: 600;">
                        <i class="fa fa-check me-1" id="tp_form_submit_icon"></i> <span id="tp_form_submit_btn_text">Create Plan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Retire Treatment Plan Modal --}}
<div class="modal fade" id="tpRetirePlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 text-white" style="background: linear-gradient(135deg, #475569 0%, #1e293b 100%);">
                <h5 class="modal-title fs-5 fw-bold d-flex align-items-center gap-2">
                    <i class="fa fa-archive"></i> Retire / Complete Treatment Plan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="tpRetirePlanForm">
                    <input type="hidden" id="tp_retire_plan_id" value="">
                    
                    <div class="alert alert-info border-0 mb-3" style="border-radius: 10px; background-color: #f0f9ff; color: #0369a1;">
                        <i class="fa fa-info-circle me-1"></i> Retiring a plan archives its active status while preserving all linked diagnostic orders & clinical history for AI & story timeline audit.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Select Primary Reason for Retirement <span class="text-danger">*</span></label>
                        <div class="d-flex flex-column gap-2">
                            <label class="d-flex align-items-center gap-2 p-2 rounded border bg-white shadow-xs cursor-pointer" style="border-radius: 8px;">
                                <input type="radio" name="tp_retire_reason" id="tp_reason_goal_achieved" value="goal_achieved" checked class="form-check-input mt-0">
                                <div>
                                    <span class="fw-bold text-dark d-block" style="font-size: 0.88rem;">🟢 Goal Achieved / Care Complete</span>
                                    <small class="text-muted">Target therapeutic goals and linked clinical orders have been fully met.</small>
                                </div>
                            </label>

                            <label class="d-flex align-items-center gap-2 p-2 rounded border bg-white shadow-xs cursor-pointer" style="border-radius: 8px;">
                                <input type="radio" name="tp_retire_reason" id="tp_reason_superseded" value="superseded" class="form-check-input mt-0">
                                <div>
                                    <span class="fw-bold text-dark d-block" style="font-size: 0.88rem;">🔄 Superseded / Revised Strategy</span>
                                    <span class="text-muted small">Care plan is replaced or upgraded by a new treatment plan.</span>
                                </div>
                            </label>

                            <label class="d-flex align-items-center gap-2 p-2 rounded border bg-white shadow-xs cursor-pointer" style="border-radius: 8px;">
                                <input type="radio" name="tp_retire_reason" id="tp_reason_discontinued" value="discontinued" class="form-check-input mt-0">
                                <div>
                                    <span class="fw-bold text-dark d-block" style="font-size: 0.88rem;">🛑 Discontinued (Clinical Decision)</span>
                                    <span class="text-muted small">Treatment stopped due to adverse effect, non-compliance, or clinical directive.</span>
                                </div>
                            </label>

                            <label class="d-flex align-items-center gap-2 p-2 rounded border bg-white shadow-xs cursor-pointer" style="border-radius: 8px;">
                                <input type="radio" name="tp_retire_reason" id="tp_reason_transferred" value="transferred_discharged" class="form-check-input mt-0">
                                <div>
                                    <span class="fw-bold text-dark d-block" style="font-size: 0.88rem;">🚪 Patient Discharged / Transferred</span>
                                    <span class="text-muted small">Episode concluded due to facility discharge, transfer, or referral.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark small mb-1">Clinical Rationale / Notes (Optional)</label>
                        <textarea class="form-control" id="tp_retire_notes" rows="2" placeholder="Provide any additional signoff context or instructions..." style="border-radius: 8px; font-size: 0.88rem;"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-top px-4 py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 8px; font-weight: 600;">Cancel</button>
                <button type="button" class="btn text-white shadow-sm" onclick="TreatmentPlansTab.submitRetirePlan()" id="tp_retire_submit_btn" style="background-color: #334155; border-radius: 8px; font-weight: 600;">
                    <i class="fa fa-archive me-1"></i> Retire & Archive Plan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Tab Navigation -->
        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <div>
                <a href="{{ route('encounters.index') }}" onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')" class="btn btn-light" style="border-radius: 8px; font-weight: 600;">Exit</a>
            </div>
            <div>
                <button type="button" class="btn btn-primary shadow-sm" onclick="switch_tab(event, 'clinical_story_tab')" style="border-radius: 8px; font-weight: 600;">
                    Next (Clinical Story) <i class="fa fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
/**
 * TreatmentPlansTab — JS module for the Treatment Plans dedicated tab.
 * Manages loading, creating, searching, paginating, and interacting with patient-scoped plans.
 */
window.TreatmentPlansTab = (function($) {
    'use strict';

    var patientId = {{ $patient->id ?? 'null' }};
    var encounterId = {{ $encounter->id ?? 'null' }};
    var clinicId = {{ $encounter->clinic_id ?? ($clinic_id ?? 'null') }};
    var plans = [];
    var activeFilterTab = 'active';
    var currentPage = 1;
    var perPage = 10;
    var searchQuery = '';

    var tpSearchDebounceTimer = null;

    /* ── Initialization (called on tab show) ── */
    function init() {
        if (!patientId) return;
        loadPatientPlans();
    }

    /* ── Filter Tab Switching ── */
    function setFilterTab(tab) {
        activeFilterTab = tab;
        if (tab === 'active') {
            $('#tp-tab-active').addClass('active').removeClass('text-muted');
            $('#tp-tab-retired').removeClass('active').addClass('text-muted');
        } else {
            $('#tp-tab-retired').addClass('active').removeClass('text-muted');
            $('#tp-tab-active').removeClass('active').addClass('text-muted');
        }
        currentPage = 1;
        renderView();
    }

    /* ── Load Plans ── */
    function loadPatientPlans() {
        $('#tp-plans-loading').show();
        $('#tp-plans-empty, #tp-plans-list, #tp-pagination-container').hide();

        var url = '/patients/' + patientId + '/treatment-plans';
        if (searchQuery) {
            url += '?search=' + encodeURIComponent(searchQuery);
        }

        $.get(url, function(response) {
            $('#tp-plans-loading').hide();
            
            var fetchedPlans = response.plans || [];
            if (!searchQuery && fetchedPlans.length > 0) {
                window._fullPlansList = fetchedPlans.slice();
            }

            plans = fetchedPlans;
            sortActivePlanToTop();

            // Populate tab counts
            var fullSet = window._fullPlansList || plans;
            var activeCount = fullSet.filter(function(p) { return p.status === 'active'; }).length;
            var retiredCount = fullSet.filter(function(p) { return p.status !== 'active'; }).length;
            $('#tp-count-active').text(activeCount);
            $('#tp-count-retired').text(retiredCount);

            if (!plans.length) {
                if (searchQuery) {
                    $('#tp-plans-list').html('<div class="text-center py-5 text-muted"><i class="fa fa-search me-2" style="font-size: 1.5rem;"></i><br>No treatment plans match your search query in the database</div>').show();
                    $('#tp-toolbar').attr('style', 'display: flex !important;');
                    $('#tp-pagination-container').attr('style', 'display: none !important;');
                } else {
                    $('#tp-plans-empty').show();
                    $('#tp-plan-total-badge').text('0');
                }
                return;
            }

            if (!searchQuery) {
                $('#tp-plan-total-badge').text(plans.length);
            }
            $('#tp-toolbar').attr('style', 'display: flex !important;');

            currentPage = 1;
            renderView();

            // Update tab badge
            var badge = document.getElementById('tp-plan-count-badge');
            if (badge) { badge.textContent = activeCount; badge.style.display = 'inline'; }

            var pulse = document.getElementById('tp-tab-pulse');
            if (pulse && activeCount > 0) pulse.style.display = 'inline-block';

        }).fail(function() {
            $('#tp-plans-loading').hide();
            $('#tp-plans-list').html('<div class="alert alert-warning">Failed to load treatment plans</div>').show();
        });
    }

    /* ── Sort Active Plan to Top (Even if not in search results) ── */
    function sortActivePlanToTop() {
        var activeId = window._activeTreatmentPlan ? window._activeTreatmentPlan.id : null;
        if (!activeId && plans && plans.length) {
            var dbActive = plans.find(function(p) { return p.is_active; });
            if (dbActive) activeId = dbActive.id;
        }

        if (activeId) {
            var activeIdx = plans.findIndex(function(p) { return p.id === activeId; });
            if (activeIdx > 0) {
                var activePlan = plans.splice(activeIdx, 1)[0];
                plans.unshift(activePlan);
            } else if (activeIdx === -1 && window._fullPlansList) {
                var fullActiveObj = window._fullPlansList.find(function(p) { return p.id === activeId; });
                if (fullActiveObj) {
                    plans.unshift(fullActiveObj);
                }
            }
        }
    }

    /* ── Render View (Pagination + Cards) ── */
    function renderView() {
        var displayPlans = plans.filter(function(p) {
            return activeFilterTab === 'active' ? (p.status === 'active') : (p.status !== 'active');
        });

        var total = displayPlans.length;
        if (total === 0) {
            var emptyMsg = activeFilterTab === 'active' 
                ? 'No active treatment plans found' 
                : 'No retired or historical treatment plans found';
            $('#tp-plans-list').html('<div class="text-center py-5 text-muted"><i class="fa fa-clipboard-check me-2" style="font-size: 1.8rem; color: #cbd5e1;"></i><br>' + emptyMsg + '</div>').show();
            $('#tp-pagination-container').attr('style', 'display: none !important;');
            return;
        }

        var maxPages = Math.ceil(total / perPage);
        if (currentPage > maxPages) currentPage = maxPages;
        if (currentPage < 1) currentPage = 1;

        var startIdx = (currentPage - 1) * perPage;
        var endIdx = Math.min(startIdx + perPage, total);
        var pageItems = displayPlans.slice(startIdx, endIdx);

        renderPlanCards(pageItems);
        $('#tp-plans-list').show();

        // Render Pagination Controls
        if (total > perPage) {
            $('#tp-pagination-info').text('Showing ' + (startIdx + 1) + ' to ' + endIdx + ' of ' + total + ' plans');
            
            var pagHtml = '';
            pagHtml += '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="TreatmentPlansTab.goToPage(' + (currentPage - 1) + '); return false;">Prev</a></li>';
            for (var i = 1; i <= maxPages; i++) {
                pagHtml += '<li class="page-item ' + (i === currentPage ? 'active' : '') + '"><a class="page-link" href="#" onclick="TreatmentPlansTab.goToPage(' + i + '); return false;">' + i + '</a></li>';
            }
            pagHtml += '<li class="page-item ' + (currentPage === maxPages ? 'disabled' : '') + '"><a class="page-link" href="#" onclick="TreatmentPlansTab.goToPage(' + (currentPage + 1) + '); return false;">Next</a></li>';
            
            $('#tp-pagination-links').html(pagHtml);
            $('#tp-pagination-container').attr('style', 'display: flex !important;');
        } else {
            $('#tp-pagination-container').attr('style', 'display: none !important;');
        }
    }

    /* ── Render Plan Cards ── */
    function renderPlanCards(pageItems) {
        var html = '';
        pageItems.forEach(function(plan, idx) {
            var isActive = window._activeTreatmentPlan && window._activeTreatmentPlan.id === plan.id;
            var priorityClass = 'tp-priority-' + (plan.priority || 'medium');
            var creatorName = plan.creator
                ? ((plan.creator.surname || '') + ' ' + (plan.creator.firstname || '')).trim()
                : 'Unknown';
            var clinicName = plan.clinic ? plan.clinic.name : 'N/A';
            var progress = plan.progress_percent || 0;
            var isPlanActive = (plan.status === 'active');

            var isExpanded = false;
            if (window._activeTreatmentPlan) {
                isExpanded = isActive;
            } else {
                isExpanded = (idx === 0);
            }

            // Action buttons HTML
            var actionBtnsHtml = '<div class="d-flex flex-wrap gap-2">';
            if (isPlanActive) {
                if (isActive) {
                    actionBtnsHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); ClinicalOrdersKit.clearActivePlan()" style="border-radius: 8px; font-size: 0.75rem;"><i class="fa fa-times me-1"></i>Clear Active</button>';
                } else {
                    actionBtnsHtml += '<button class="btn btn-sm btn-teal" onclick="event.stopPropagation(); TreatmentPlansTab.setActive(' + plan.id + ')" style="border-radius: 8px; font-size: 0.75rem;"><i class="fa fa-check-circle me-1"></i>Set as Active</button>';
                }
                actionBtnsHtml += '<button class="btn btn-sm btn-outline-teal" onclick="event.stopPropagation(); TreatmentPlansTab.showPlanDetails(' + plan.id + ')" style="border-radius: 8px; font-size: 0.75rem;"><i class="fa fa-eye me-1"></i>View Details</button>';
                actionBtnsHtml += '<button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); TreatmentPlansTab.openEditModal(' + plan.id + ')" style="border-radius: 8px; font-size: 0.75rem;"><i class="fa fa-edit me-1"></i>Edit Plan</button>';
                actionBtnsHtml += '<button class="btn btn-sm btn-outline-dark" onclick="event.stopPropagation(); TreatmentPlansTab.openRetireModal(' + plan.id + ')" style="border-radius: 8px; font-size: 0.75rem;"><i class="fa fa-archive me-1"></i>Retire Plan</button>';
            } else {
                actionBtnsHtml += '<button class="btn btn-sm btn-outline-teal" onclick="event.stopPropagation(); TreatmentPlansTab.showPlanDetails(' + plan.id + ')" style="border-radius: 8px; font-size: 0.75rem;"><i class="fa fa-eye me-1"></i>View Full History & Details</button>';
                actionBtnsHtml += '<span class="badge bg-secondary p-2 ms-auto" style="font-size: 0.72rem;"><i class="fa fa-archive me-1"></i>RETIRED / ' + (plan.status || '').toUpperCase() + '</span>';
            }
            actionBtnsHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); TreatmentPlansTab.refreshProgress(' + plan.id + ')" style="border-radius: 8px; font-size: 0.75rem;"><i class="fa fa-sync-alt me-1"></i>Refresh</button>';
            actionBtnsHtml += '</div>';

            html += '<div class="tp-plan-card mb-3 ' + (isActive ? 'tp-active-card' : '') + '" data-plan-id="' + plan.id + '">';

            // Card header
            html += '<div class="tp-plan-card-header" onclick="TreatmentPlansTab.toggleCard(' + idx + ')">';
            
            // Header Row 1
            html += '<div class="d-flex justify-content-between align-items-center">';
            html += '<div class="d-flex align-items-center gap-2">';
            html += '<span class="tp-priority-badge ' + priorityClass + '">' + (plan.priority || 'medium') + '</span>';
            html += '<strong style="font-size: 0.95rem;">' + escapeHtml(plan.name) + '</strong>';
            if (plan.icd_code) {
                html += ' <span class="badge bg-light text-dark" style="font-size: 0.65rem;">(' + escapeHtml(plan.icd_code) + ')</span>';
            }
            if (isActive) {
                html += ' <span class="badge bg-teal rounded-pill text-white" style="font-size: 0.65rem;"><i class="fa fa-star me-1"></i>ACTIVE PLAN</span>';
            }
            html += '</div>';
            html += '<div class="d-flex align-items-center gap-3">';
            // Progress ring
            html += '<div class="tp-progress-ring" style="--progress: ' + progress + '%;"><div class="tp-progress-ring-inner">' + progress + '%</div></div>';
            var dateStr = plan.created_at ? new Date(plan.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
            var locationStr = plan.clinic ? plan.clinic.name : '';
            var detailsArr = ['Dr. ' + escapeHtml(creatorName)];
            if (locationStr) detailsArr.push(escapeHtml(locationStr));
            if (dateStr) detailsArr.push(dateStr);

            html += '<small class="text-muted" style="font-size: 0.72rem;">' + detailsArr.join(' &bull; ') + '</small>';
            html += '<i class="fa ' + (isExpanded ? 'fa-chevron-up' : 'fa-chevron-down') + ' text-muted tp-card-chevron" id="tp-chevron-' + idx + '" style="transition: transform 0.2s;"></i>';
            html += '</div>';
            html += '</div>'; // End Header Row 1

            // Header Row 2 (Action buttons displayed when card is NOT expanded)
            html += '<div class="tp-header-actions mt-2 pt-2" id="tp-header-actions-' + idx + '" style="border-top: 1px solid #e0f2f1; ' + (isExpanded ? 'display:none;' : 'display:block;') + '" onclick="event.stopPropagation();">';
            html += actionBtnsHtml;
            html += '</div>';

            html += '</div>'; // End tp-plan-card-header

            // Card body (EXPANDED if active/first card, COLLAPSED otherwise)
            html += '<div class="tp-plan-card-body" id="tp-card-body-' + idx + '" style="' + (isExpanded ? 'display:block;' : 'display:none;') + '">';

            // Departmental Visibility Badges inside card body
            var visBadgesHtml = '';
            if (plan.visibility && Array.isArray(plan.visibility) && plan.visibility.length > 0 && !plan.visibility.includes('all')) {
                plan.visibility.forEach(function(v) {
                    visBadgesHtml += '<span class="badge me-1" style="background-color: #00796b; color: #ffffff; font-weight: 600; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 8px; border-radius: 6px;"><i class="mdi mdi-shield-account me-1"></i>' + escapeHtml(v) + '</span>';
                });
            } else {
                visBadgesHtml = '<span class="badge me-1" style="background-color: #0288d1; color: #ffffff; font-weight: 600; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 8px; border-radius: 6px;"><i class="mdi mdi-earth me-1"></i>ALL DEPARTMENTS</span>';
            }
            html += '<div class="d-flex align-items-center mb-2 pb-2 border-bottom" style="border-color: #e0f2f1 !important;">';
            html += '<small class="text-muted me-2 fw-bold" style="font-size: 0.72rem;"><i class="mdi mdi-eye-outline me-1"></i>Department Visibility:</small>';
            html += '<div class="d-flex flex-wrap gap-1">' + visBadgesHtml + '</div>';
            html += '</div>';

            // Goal
            if (plan.goal) {
                html += '<div class="mb-2 p-2 rounded" style="background: #f5fffe; border: 1px solid #e0f2f1;">';
                html += '<small class="fw-semibold text-teal"><i class="fa fa-bullseye me-1"></i>Goal:</small> ';
                html += '<small>' + escapeHtml(plan.goal) + '</small>';
                html += '</div>';
            }

            // Diagnosis Data rendering
            if (plan.diagnosis_data && plan.diagnosis_data.length > 0) {
                var diagnoses = [];
                try {
                    diagnoses = typeof plan.diagnosis_data === 'string' ? JSON.parse(plan.diagnosis_data) : plan.diagnosis_data;
                } catch(e) {}
                
                if (diagnoses.length > 0) {
                    html += '<div class="mb-2"><small class="text-muted"><i class="fa fa-stethoscope me-1"></i><strong>Diagnoses:</strong></small>';
                    html += '<ul class="mb-1" style="font-size: 0.8rem; padding-left: 20px; list-style-type: disc;">';
                    diagnoses.forEach(function(d) {
                        html += '<li>' + escapeHtml(d.display);
                        if (d.comment_1 && d.comment_1 !== 'NA') {
                            html += ' <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;">' + escapeHtml(d.comment_1) + '</span>';
                        }
                        if (d.comment_2 && d.comment_2 !== 'NA') {
                            html += ' <span class="badge bg-info ms-1" style="font-size: 0.65rem;">' + escapeHtml(d.comment_2) + '</span>';
                        }
                        html += '</li>';
                    });
                    html += '</ul></div>';
                }
            } else if (plan.problem_text) {
                html += '<div class="mb-2"><small class="text-muted"><i class="fa fa-stethoscope me-1"></i>' + escapeHtml(plan.problem_text) + '</small></div>';
            }

            // Linked items summary
            if (plan.linked_counts) {
                html += '<div class="tp-item-group d-flex flex-wrap gap-1 mb-2">';
                var typeIcons = {
                    labs: {icon: 'fa-flask', label: 'Labs', color: '#1976d2'},
                    imaging: {icon: 'fa-x-ray', label: 'Imaging', color: '#0097a7'},
                    medications: {icon: 'fa-pills', label: 'Meds', color: '#f57c00'},
                    procedures: {icon: 'fa-user-md', label: 'Procedures', color: '#c62828'},
                    non_pharm: {icon: 'fa-heartbeat', label: 'Care', color: '#388e3c'},
                    referrals: {icon: 'mdi mdi-account-switch', label: 'Referrals', color: '#7b1fa2'},
                    admissions: {icon: 'fa-bed', label: 'Admissions', color: '#5d4037'},
                    notes: {icon: 'fa-sticky-note', label: 'Notes', color: '#455a64'}
                };
                $.each(plan.linked_counts, function(type, count) {
                    if (count > 0 && typeIcons[type]) {
                        var t = typeIcons[type];
                        html += '<span class="tp-item-badge"><i class="' + t.icon + '" style="color:' + t.color + ';font-size:0.7rem;"></i> ' + t.label + ' (' + count + ')</span>';
                    }
                });
                if (plan.total_linked === 0) {
                    html += '<small class="text-muted"><i class="fa fa-info-circle me-1"></i>No items linked yet</small>';
                }
                html += '</div>';
            }

            // Action buttons (in body when expanded)
            html += '<div class="mt-2 pt-2" style="border-top: 1px solid #f0f0f0;">';
            html += actionBtnsHtml;
            html += '</div>';

            html += '</div>'; // card-body
            html += '</div>'; // plan-card
        });

        $('#tp-plans-list').html(html);
    }

    /* ── Event Handlers: Search, Per-Page, Pagination ── */
    function onSearchInput(val) {
        searchQuery = val || '';
        clearTimeout(tpSearchDebounceTimer);
        tpSearchDebounceTimer = setTimeout(function() {
            currentPage = 1;
            loadPatientPlans();
        }, 300);
    }

    function onPerPageChange(val) {
        perPage = parseInt(val, 10) || 10;
        currentPage = 1;
        renderView();
    }

    function goToPage(page) {
        currentPage = page;
        renderView();
    }

    /* ── Toggle card expansion ── */
    function toggleCard(idx) {
        var $body = $('#tp-card-body-' + idx);
        var $headerActions = $('#tp-header-actions-' + idx);
        var $chevron = $('#tp-chevron-' + idx);
        $body.slideToggle(200);
        $headerActions.slideToggle(200);
        $chevron.toggleClass('fa-chevron-down fa-chevron-up');
    }

    /* ── Set Active Plan ── */
    function setActive(planId) {
        var plan = plans.find(function(p) { return p.id === planId; });
        if (!plan) return;

        var creatorName = plan.creator
            ? ((plan.creator.surname || '') + ' ' + (plan.creator.firstname || '')).trim()
            : '';
        var clinicName = plan.clinic ? plan.clinic.clinic_name : '';

        window._activeTreatmentPlan = {
            id: plan.id,
            name: plan.name,
            progress: plan.progress_percent || 0,
            priority: plan.priority || 'medium',
            doctor: creatorName,
            clinic: clinicName,
            diagnosis_data: plan.diagnosis_data,
            problem_text: plan.problem_text
        };

        // Re-sort active plan to top and reset to page 1
        sortActivePlanToTop();
        currentPage = 1;

        ClinicalOrdersKit.renderActivePlanContextBar();
        renderView();
        toastr.success('Plan "' + plan.name + '" set as active context', 'Active Plan', {timeOut: 3000});
    }

    /* ── Priority Chips Controller ── */
    function setPriority(val) {
        val = val || 'medium';
        $('#tp_create_priority').val(val);
        $('.tp-priority-chip').removeClass('active');
        $('.tp-priority-chip[data-priority="' + val + '"]').addClass('active');
    }

    /* ── Department Chips Controller ── */
    function toggleDeptChip(deptVal) {
        if (deptVal === 'all') {
            $('.tp-dept-chip').removeClass('active');
            $('.tp-dept-chip[data-dept="all"]').addClass('active');
            $('.tp-vis-check').prop('checked', false);
            $('#tp_vis_all').prop('checked', true);
        } else {
            // Always uncheck & deactivate 'All Departments' when any specific department is selected
            $('.tp-dept-chip[data-dept="all"]').removeClass('active');
            $('#tp_vis_all').prop('checked', false);

            var $btn = $('.tp-dept-chip[data-dept="' + deptVal + '"]');
            var $chk = $('#tp_vis_' + deptVal);
            
            $btn.toggleClass('active');
            $chk.prop('checked', $btn.hasClass('active'));
        }
    }

    function syncDeptChipsFromCheckboxes(visArr) {
        $('.tp-dept-chip').removeClass('active');
        $('.tp-vis-check').prop('checked', false);

        if (!visArr || !Array.isArray(visArr) || visArr.includes('all') || visArr.length === 0) {
            $('.tp-dept-chip[data-dept="all"]').addClass('active');
            $('#tp_vis_all').prop('checked', true);
        } else {
            visArr.forEach(function(v) {
                $('.tp-dept-chip[data-dept="' + v + '"]').addClass('active');
                $('#tp_vis_' + v).prop('checked', true);
            });
        }
    }

    /* ── Import Encounter Diagnoses ── */
    function importEncounterDiagnoses() {
        var encounterReasons = window.encounterReasons || window._encounterReasons || [];
        if (!encounterReasons || encounterReasons.length === 0) {
            if (typeof selectedReasons !== 'undefined' && Array.isArray(selectedReasons) && selectedReasons.length > 0) {
                encounterReasons = selectedReasons;
            }
        }

        if (!encounterReasons || encounterReasons.length === 0) {
            toastr.info('No recorded diagnoses found in current encounter.', 'Import Diagnoses');
            return;
        }

        var importedCount = 0;
        encounterReasons.forEach(function(item) {
            var val = item.value || (item.code + '-' + item.name);
            var display = item.display || (item.code + ' - ' + item.name);
            if (!tpSelectedReasons.some(function(r) { return r.value === val; })) {
                tpSelectedReasons.push({
                    value: val,
                    display: display,
                    code: item.code || 'NA',
                    name: item.name || display,
                    comment_1: item.comment_1 || 'NA',
                    comment_2: item.comment_2 || 'NA'
                });
                importedCount++;
            }
        });

        if (importedCount > 0) {
            tpUpdateSelectedReasonsDisplay();
            toastr.success('Imported ' + importedCount + ' diagnosis code(s) from encounter.', 'Imported');
        } else {
            toastr.info('All encounter diagnoses are already assigned to this plan.', 'Import Diagnoses');
        }
    }

    /* ── Open Create Modal ── */
    function openCreateModal() {
        $('#tp_form_plan_id').val('');
        $('#tp_form_modal_title').html('<i class="fa fa-clipboard-list me-2"></i> Create Treatment Plan');
        $('#tp_form_submit_icon').attr('class', 'fa fa-check me-1');
        $('#tp_form_submit_btn_text').text('Create Plan');

        $('#tpCreatePlanForm')[0].reset();
        setPriority('medium');
        syncDeptChipsFromCheckboxes(['all']);
        tpSelectedReasons = [];
        tpUpdateSelectedReasonsDisplay();

        $('#tpCreatePlanModal').modal('show');
    }

    /* ── Open Edit Modal (reuses Create Modal) ── */
    function openEditModal(planId) {
        var planList = window._fullPlansList || plans || [];
        var plan = planList.find(function(p) { return p.id == planId; });
        
        function populateAndShow(p) {
            $('#tp_form_plan_id').val(p.id);
            $('#tp_form_modal_title').html('<i class="fa fa-edit me-2"></i> Edit Treatment Plan');
            $('#tp_form_submit_icon').attr('class', 'fa fa-save me-1');
            $('#tp_form_submit_btn_text').text('Save Changes');

            $('#tp_create_name').val(p.name || '');
            setPriority(p.priority || 'medium');
            $('#tp_create_goal').val(p.goal || '');
            $('#tp_create_description').val(p.description || '');

            var vis = p.visibility;
            if (typeof vis === 'string') {
                try { vis = JSON.parse(vis); } catch(e) { vis = []; }
            }
            syncDeptChipsFromCheckboxes(vis);

            tpSelectedReasons = [];
            if (p.diagnosis_data) {
                try {
                    tpSelectedReasons = typeof p.diagnosis_data === 'string' ? JSON.parse(p.diagnosis_data) : p.diagnosis_data;
                    if (!Array.isArray(tpSelectedReasons)) tpSelectedReasons = [];
                } catch(e) { tpSelectedReasons = []; }
            }
            tpUpdateSelectedReasonsDisplay();

            $('#tpCreatePlanModal').modal('show');
        }

        if (plan) {
            populateAndShow(plan);
        } else {
            $.get('/treatment-plans/' + planId, function(res) {
                if (res.success && res.plan) {
                    populateAndShow(res.plan);
                } else {
                    toastr.error('Could not load plan details for editing');
                }
            }).fail(function() {
                toastr.error('Error fetching plan details');
            });
        }
    }

    /* ── Submit Plan Form (Create or Update) ── */
    function submitPlanForm() {
        var planId = $('#tp_form_plan_id').val();
        var name = $('#tp_create_name').val().trim();
        if (!name) {
            toastr.warning('Please enter a plan name');
            return;
        }

        var vis = [];
        if ($('#tp_vis_all').is(':checked')) {
            vis = ['all'];
        } else {
            $('.tp-vis-dept:checked').each(function() {
                vis.push($(this).val());
            });
        }

        var isEdit = !!planId;
        var url = isEdit ? ('/treatment-plans/' + planId) : ('/patients/' + patientId + '/treatment-plans');
        var method = isEdit ? 'PUT' : 'POST';

        var $btn = $('#tp_create_submit_btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> ' + (isEdit ? 'Saving...' : 'Creating...'));

        var payload = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            name: name,
            description: $('#tp_create_description').val(),
            diagnosis_data: $('#tp_diagnosis_data').val(),
            goal: $('#tp_create_goal').val(),
            priority: $('#tp_create_priority').val(),
            visibility: vis
        };

        if (!isEdit) {
            payload.encounter_id = encounterId;
            payload.clinic_id = clinicId;
        }

        $.ajax({
            url: url,
            method: method,
            data: payload,
            success: function(response) {
                $btn.prop('disabled', false).html('<i class="' + (isEdit ? 'fa fa-save' : 'fa fa-check') + ' me-1"></i> ' + (isEdit ? 'Save Changes' : 'Create Plan'));
                if (response.success) {
                    $('#tpCreatePlanModal').modal('hide');
                    toastr.success(response.message || (isEdit ? 'Plan updated' : 'Plan created'), 'Success');
                    loadPatientPlans();
                } else {
                    toastr.error(response.message || 'Failed to save plan');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="' + (isEdit ? 'fa fa-save' : 'fa fa-check') + ' me-1"></i> ' + (isEdit ? 'Save Changes' : 'Create Plan'));
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error';
                toastr.error(msg, 'Error');
            }
        });
    }

    /* ── Refresh Progress ── */
    function refreshProgress(planId) {
        $.ajax({
            url: '/treatment-plans/' + planId + '/progress',
            method: 'PUT',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success: function(r) {
                if (r.success) {
                    toastr.info('Progress: ' + r.progress_percent + '%', 'Updated');
                    loadPatientPlans();
                    if (window._activeTreatmentPlan && window._activeTreatmentPlan.id === planId) {
                        window._activeTreatmentPlan.progress = r.progress_percent;
                        ClinicalOrdersKit.renderActivePlanContextBar();
                    }
                }
            }
        });
    }

    /* ── Open Retire Modal ── */
    function openRetireModal(planId) {
        var planList = window._fullPlansList || plans || [];
        var plan = planList.find(function(p) { return p.id == planId; });

        function populateAndShowRetire(p) {
            $('#tp_retire_plan_id').val(p.id);
            $('#tp_retire_notes').val('');

            if ((p.progress_percent || 0) >= 100) {
                $('#tp_reason_goal_achieved').prop('checked', true);
            } else {
                $('#tp_reason_discontinued').prop('checked', true);
            }

            $('#tpRetirePlanModal').modal('show');
        }

        if (plan) {
            populateAndShowRetire(plan);
        } else {
            $.get('/treatment-plans/' + planId, function(res) {
                if (res.success && res.plan) {
                    populateAndShowRetire(res.plan);
                } else {
                    toastr.error('Could not load plan details for retirement');
                }
            }).fail(function() {
                toastr.error('Error fetching plan details');
            });
        }
    }

    /* ── Submit Retire Plan ── */
    function submitRetirePlan() {
        var planId = $('#tp_retire_plan_id').val();
        var reason = $('input[name="tp_retire_reason"]:checked').val();
        var notes = $('#tp_retire_notes').val();

        if (!planId || !reason) {
            toastr.warning('Please select a retirement reason');
            return;
        }

        var $btn = $('#tp_retire_submit_btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Retiring...');

        $.ajax({
            url: '/treatment-plans/' + planId + '/retire',
            method: 'PUT',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                retirement_reason: reason,
                retirement_notes: notes
            },
            success: function(response) {
                $btn.prop('disabled', false).html('<i class="fa fa-archive me-1"></i> Retire & Archive Plan');
                if (response.success) {
                    $('#tpRetirePlanModal').modal('hide');
                    toastr.success(response.message || 'Treatment plan retired successfully', 'Success');

                    // Auto de-select if plan is set as active context bar
                    if (window._activeTreatmentPlan && window._activeTreatmentPlan.id == planId) {
                        ClinicalOrdersKit.clearActivePlan();
                    }

                    loadPatientPlans();
                } else {
                    toastr.error(response.message || 'Failed to retire plan');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-archive me-1"></i> Retire & Archive Plan');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Server error';
                toastr.error(msg, 'Error');
            }
        });
    }

    /* ── Show Shared Plan Details Modal ── */
    function showPlanDetails(planId) {
        if (window.ClinicalOrdersKit && typeof window.ClinicalOrdersKit.viewTreatmentPlan === 'function') {
            window.ClinicalOrdersKit.viewTreatmentPlan(planId);
        } else {
            toastr.info('Opening plan details...');
        }
    }

    /* ── Utility ── */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    return {
        init: init,
        loadPatientPlans: loadPatientPlans,
        setFilterTab: setFilterTab,
        toggleCard: toggleCard,
        setActive: setActive,
        setPriority: setPriority,
        toggleDeptChip: toggleDeptChip,
        importEncounterDiagnoses: importEncounterDiagnoses,
        openCreateModal: openCreateModal,
        openEditModal: openEditModal,
        openRetireModal: openRetireModal,
        submitRetirePlan: submitRetirePlan,
        showPlanDetails: showPlanDetails,
        submitPlanForm: submitPlanForm,
        refreshProgress: refreshProgress,
        onSearchInput: onSearchInput,
        onPerPageChange: onPerPageChange,
        goToPage: goToPage
    };

})(jQuery);

// Navigation Prompt Interceptor (prompts once per page refresh when navigating away without active plan)
window._hasShownNoActivePlanPrompt = false;
var tpPendingNavClick = null;

function isTreatmentPlansTabActive() {
    return $('#treatment_plans_tab').hasClass('active') ||
           $('#treatment_plans').hasClass('active') ||
           $('#treatment_plans').hasClass('show');
}

$(document).ready(function() {
    var tabEl = document.getElementById('treatment_plans_tab');
    if (tabEl) {
        if ($(tabEl).hasClass('active')) {
            TreatmentPlansTab.init();
        }
        tabEl.addEventListener('shown.bs.tab', function() {
            TreatmentPlansTab.init();
        });
    }

    // Intercept clicks on any tab links or switch_tab buttons when leaving Treatment Plans tab
    $(document).on('click', '.nav-link, [data-toggle="tab"], [data-bs-toggle="tab"], [onclick*="switch_tab"]', function(e) {
        var targetId = $(this).attr('id');
        if (targetId === 'treatment_plans_tab') return;

        var tpReq = (typeof _PI_TP_REQUIRED !== 'undefined' && _PI_TP_REQUIRED) || {{ $tpRequired ? 'true' : 'false' }};
        var needsPrompt = tpReq ? !window._activeTreatmentPlan : (!window._activeTreatmentPlan && !window._hasShownNoActivePlanPrompt);

        if (isTreatmentPlansTabActive() && needsPrompt) {
            e.preventDefault();
            e.stopPropagation();
            tpPendingNavClick = this;
            $('#tpNoActivePlanPromptModal').modal('show');
            return false;
        }
    });

    // Intercept Bootstrap native tab hide event for treatment_plans_tab
    $(document).on('hide.bs.tab', '#treatment_plans_tab', function(e) {
        var tpReq = (typeof _PI_TP_REQUIRED !== 'undefined' && _PI_TP_REQUIRED) || {{ $tpRequired ? 'true' : 'false' }};
        var needsPrompt = tpReq ? !window._activeTreatmentPlan : (!window._activeTreatmentPlan && !window._hasShownNoActivePlanPrompt);

        if (needsPrompt) {
            e.preventDefault();
            tpPendingNavClick = e.relatedTarget || null;
            $('#tpNoActivePlanPromptModal').modal('show');
            return false;
        }
    });

    $('#tp-prompt-proceed-btn').on('click', function() {
        window._hasShownNoActivePlanPrompt = true;
        $('#tpNoActivePlanPromptModal').modal('hide');
        if (tpPendingNavClick) {
            var target = tpPendingNavClick;
            tpPendingNavClick = null;
            if (target instanceof HTMLElement || (target.jquery && target.length)) {
                $(target).trigger('click');
            } else if (typeof target === 'string') {
                $('#' + target).trigger('click');
            }
        }
    });

    // Initialize Diagnosis Widget in Create Plan Modal
    tpUpdateSelectedReasonsDisplay();
});

// --- Diagnosis Widget Logic for Treatment Plan Modal ---
var tpSelectedReasons = [];
var tpSearchTimeout;

$('#tp_reasons_for_encounter_search').on('input', function() {
    clearTimeout(tpSearchTimeout);
    const query = $(this).val();
    tpSearchTimeout = setTimeout(() => tpSearchReasons(query), 300);
});

// Hide results when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#tp_reasons_for_encounter_search, #tp_reasons_search_results').length) {
        $('#tp_reasons_search_results').hide();
    }
});

function tpSearchReasons(query) {
    if (query.length < 2) {
        $('#tp_reasons_search_results').hide();
        return;
    }
    $.ajax({
        url: '/live-search-reasons',
        method: 'GET',
        data: { q: query },
        success: function(data) {
            const resultsContainer = $('#tp_reasons_search_results');
            resultsContainer.empty();
            if (data.length === 0) {
                resultsContainer.append(`
                    <li class="list-group-item custom-reason-option" onclick="tpAddReason('custom:${query}', 'Custom: ${query}', 'CUSTOM', '${query}')" style="cursor: pointer;">
                        <i class="mdi mdi-plus-circle text-success"></i> <strong>Add custom reason:</strong> "${query}"
                    </li>
                `);
            } else {
                data.forEach(reason => {
                    const display = `${reason.code} - ${reason.name}`;
                    const value = `${reason.code}-${reason.name}`;
                    resultsContainer.append(`
                        <li class="list-group-item" onclick="tpAddReason('${value}', '${display}', '${reason.code}', '${reason.name}')" style="cursor: pointer;">
                            <strong>${reason.code}</strong> ${reason.name}
                            <br><small class="text-muted">${reason.category} › ${reason.sub_category}</small>
                        </li>
                    `);
                });
            }
            resultsContainer.show();
        }
    });
}

function tpAddReason(value, display, code, name) {
    if (tpSelectedReasons.some(r => r.value === value)) return;
    tpSelectedReasons.push({value, display, code, name, comment_1: 'NA', comment_2: 'NA'});
    tpUpdateSelectedReasonsDisplay();
    $('#tp_reasons_for_encounter_search').val('');
    $('#tp_reasons_search_results').hide();
}

function tpRemoveReason(value) {
    tpSelectedReasons = tpSelectedReasons.filter(r => r.value !== value);
    tpUpdateSelectedReasonsDisplay();
}

function tpUpdateReasonComment(value, field, newVal) {
    const reason = tpSelectedReasons.find(r => r.value === value);
    if (reason) {
        reason[field] = newVal;
        $('#tp_diagnosis_data').val(JSON.stringify(tpSelectedReasons));
    }
}

function tpUpdateSelectedReasonsDisplay() {
    const container = $('#tp_selected_reasons_list');
    if (tpSelectedReasons.length === 0) {
        container.html('<span class="text-muted"><i>No diagnoses selected yet</i></span>');
    } else {
        let html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">';
        html += '<thead class="table-light"><tr><th>Code</th><th>Diagnosis</th><th>Status</th><th>Course</th><th style="width:40px;"></th></tr></thead><tbody>';
        tpSelectedReasons.forEach(reason => {
            const escVal = (reason.value || '').replace(/'/g, "\\'");
            html += `<tr>
                <td><span class="badge bg-primary">${reason.code}</span></td>
                <td>${reason.name || reason.display}</td>
                <td>
                    <select class="form-select form-select-sm" onchange="tpUpdateReasonComment('${escVal}', 'comment_1', this.value)">
                        <option value="NA" ${reason.comment_1 === 'NA' ? 'selected' : ''}>N/A</option>
                        <option value="QUERY" ${reason.comment_1 === 'QUERY' ? 'selected' : ''}>Query</option>
                        <option value="DIFFRENTIAL" ${reason.comment_1 === 'DIFFRENTIAL' ? 'selected' : ''}>Differential</option>
                        <option value="CONFIRMED" ${reason.comment_1 === 'CONFIRMED' ? 'selected' : ''}>Confirmed</option>
                        <option value="ACTIVE" ${reason.comment_1 === 'ACTIVE' ? 'selected' : ''}>Active</option>
                        <option value="RESOLVED" ${reason.comment_1 === 'RESOLVED' ? 'selected' : ''}>Resolved</option>
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm" onchange="tpUpdateReasonComment('${escVal}', 'comment_2', this.value)">
                        <option value="NA" ${reason.comment_2 === 'NA' ? 'selected' : ''}>N/A</option>
                        <option value="ACUTE" ${reason.comment_2 === 'ACUTE' ? 'selected' : ''}>Acute</option>
                        <option value="CHRONIC" ${reason.comment_2 === 'CHRONIC' ? 'selected' : ''}>Chronic</option>
                        <option value="RECURRENT" ${reason.comment_2 === 'RECURRENT' ? 'selected' : ''}>Recurrent</option>
                        <option value="IMPROVING" ${reason.comment_2 === 'IMPROVING' ? 'selected' : ''}>Improving</option>
                        <option value="STABLE" ${reason.comment_2 === 'STABLE' ? 'selected' : ''}>Stable</option>
                        <option value="WORSENING" ${reason.comment_2 === 'WORSENING' ? 'selected' : ''}>Worsening</option>
                    </select>
                </td>
                <td class="align-middle text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill" onclick="tpRemoveReason('${escVal}')"><i class="fa fa-times"></i></button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.html(html);
    }
    $('#tp_diagnosis_data').val(JSON.stringify(tpSelectedReasons));
}

// Ensure modal reset when opened
$('#tpCreatePlanModal').on('show.bs.modal', function () {
    if (!$('#tp_form_plan_id').val()) {
        tpSelectedReasons = [];
        tpUpdateSelectedReasonsDisplay();
        $('#tp_create_name').val('');
        $('#tp_create_goal').val('');
        $('#tp_create_description').val('');
    }
});

</script>
@endpush
