{{--
    Reusable Clinical Procedure Booking Configurator Card
    Used across Doctor Encounter, Nursing, Maternity, and Surgery workbenches.
    Maintains the unified Search -> Select -> Customize -> Add inline flow.

    @param string $prefix        ID/Name prefix (e.g. 'proc_', 'cr_proc_', 'sw_proc_', 'mco_proc_')
    @param string $cancelHandler JS call on cancel (defaults to ClinicalOrdersKit.cancelProcedureConfig('$prefix'))
    @param string $submitHandler JS call on submit (defaults to ClinicalOrdersKit.submitProcedureConfig('$prefix'))
    @param string $submitLabel   Action button label (default: 'Add Procedure')
--}}
@php
    $prefix = $prefix ?? 'proc_';
    $cancelHandler = $cancelHandler ?? "ClinicalOrdersKit.cancelProcedureConfig('{$prefix}')";
    $submitHandler = $submitHandler ?? "ClinicalOrdersKit.submitProcedureConfig('{$prefix}')";
    $submitLabel = $submitLabel ?? 'Add Procedure';
    $customPriceEnabled = (bool) (appsettings('allow_doctor_set_procedure_price') ?? 0);
@endphp

<div id="{{ $prefix }}config_card" class="proc-config-card mb-4 shadow-sm border rounded bg-white" style="display: none;">
    {{-- 1. Header with Procedure Badges & Close Button --}}
    <div class="proc-config-header p-3 bg-light border-bottom d-flex justify-content-between align-items-center rounded-top">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-primary text-uppercase" id="{{ $prefix }}config_category">Procedure</span>
            <span id="{{ $prefix }}config_surgical_badge" class="badge bg-danger" style="display: none;">
                <i class="fa fa-cut me-1"></i> Surgical (OR)
            </span>
            <span id="{{ $prefix }}config_clinical_badge" class="badge bg-info text-dark" style="display: none;">
                <i class="fa fa-stethoscope me-1"></i> Bedside / Minor
            </span>
            <h5 class="mb-0 fw-bold text-dark" id="{{ $prefix }}config_title">Selected Procedure</h5>
            <small class="text-muted fw-normal" id="{{ $prefix }}config_code"></small>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($customPriceEnabled)
                <span class="badge bg-warning text-dark border border-warning-subtle">
                    <i class="fa fa-clock me-1"></i> Custom Pricing Mode
                </span>
            @endif
            <button type="button" class="btn-close ms-2" aria-label="Close" onclick="{{ $cancelHandler }}"></button>
        </div>
    </div>

    <div class="p-3">
        {{-- 2. Clean Billing Status Notice (No Convoluted Pricing Inputs) --}}
        @if($customPriceEnabled)
            <div class="alert alert-info py-2 px-3 mb-3 small rounded d-flex align-items-center justify-content-between">
                <div>
                    <i class="fa fa-info-circle me-2 text-info"></i>
                    <strong>Base Fee Billing Deferred:</strong> Custom procedure pricing is active for this facility. Procedure base fee and consumable itemizations will be billed in the <strong>Procedure Workbench</strong> upon execution.
                </div>
                <span class="badge bg-warning text-dark"><i class="fa fa-clock me-1"></i> Deferred Billing</span>
            </div>
            <input type="hidden" id="{{ $prefix }}defer_billing" value="1">
        @else
            <div class="alert alert-light py-2 px-3 mb-3 small rounded border d-flex align-items-center justify-content-between">
                <div>
                    <i class="fa fa-tag me-2 text-secondary"></i>
                    <strong>Standard Tariff Billing:</strong> Procedure will be billed according to the standard hospital catalog / HMO tariff.
                </div>
                <span class="badge bg-secondary"><i class="fa fa-receipt me-1"></i> Standard Tariff</span>
            </div>
            <input type="hidden" id="{{ $prefix }}defer_billing" value="0">
        @endif

        {{-- Hidden fallback fields for custom price --}}
        <input type="hidden" id="{{ $prefix }}total_price" value="0">
        <input type="hidden" id="{{ $prefix }}coverage_mode" value="cash">
        <input type="hidden" id="{{ $prefix }}payable_amount" value="0">
        <input type="hidden" id="{{ $prefix }}claims_amount" value="0">

        {{-- 3. Clinical Scheduling & Notes (Clean 2-Column Layout) --}}
        <div class="row g-3 mb-3">
            {{-- Left: Scheduling --}}
            <div class="col-md-6">
                <div class="border rounded p-3 h-100 bg-light-subtle">
                    <div class="fw-bold small text-secondary mb-2">
                        <i class="fa fa-calendar-check text-primary me-1"></i> Scheduling &amp; Location
                    </div>
                    <div class="row g-2">
                        <div class="col-12 mb-2">
                            <label for="{{ $prefix }}priority" class="form-label small fw-bold mb-1">Priority</label>
                            <select class="form-select form-select-sm" id="{{ $prefix }}priority">
                                <option value="routine">Routine</option>
                                <option value="urgent">Urgent</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="{{ $prefix }}scheduled_date" class="form-label small fw-semibold mb-1">Date (Optional)</label>
                            <input type="date" class="form-control form-control-sm" id="{{ $prefix }}scheduled_date">
                        </div>
                        <div class="col-6">
                            <label for="{{ $prefix }}scheduled_time" class="form-label small fw-semibold mb-1">Time (Optional)</label>
                            <input type="time" class="form-control form-control-sm" id="{{ $prefix }}scheduled_time">
                        </div>
                        <div class="col-12 mt-2">
                            <label for="{{ $prefix }}operating_room" class="form-label small fw-semibold mb-1" id="{{ $prefix }}operating_room_label">
                                Theatre / Room (Optional)
                            </label>
                            <input type="text" class="form-control form-control-sm" id="{{ $prefix }}operating_room"
                                placeholder="e.g. Main OR 1 / Minor Procedure Room / Ward Bedside">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Clinical Indications --}}
            <div class="col-md-6">
                <div class="border rounded p-3 h-100 bg-light-subtle d-flex flex-column">
                    <div class="fw-bold small text-secondary mb-2">
                        <i class="fa fa-notes-medical text-info me-1"></i> Clinical Indications &amp; Notes
                    </div>
                    <textarea class="form-control form-control-sm flex-grow-1" id="{{ $prefix }}pre_notes" rows="5"
                        placeholder="Clinical indications, procedure notes, diagnostic findings, special patient instructions..."></textarea>
                </div>
            </div>
        </div>

        {{-- 4. Clinical Preparation & Readiness Protocol (Dynamic Surgical vs Bedside) --}}
        {{-- 4A. Surgical Protocol Controls --}}
        <div id="{{ $prefix }}surgical_prep_box" class="border border-danger-subtle bg-danger-subtle bg-opacity-10 rounded p-3 mb-3" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-danger-subtle">
                <div class="fw-bold small text-danger">
                    <i class="fa fa-cut me-1"></i> Surgical Preparation &amp; Anesthesia Plan (Pre-Op)
                </div>
                <span class="badge bg-danger">Theatre Protocol</span>
            </div>
            <div class="row g-2">
                <div class="col-md-3">
                    <label for="{{ $prefix }}npo_status" class="form-label small fw-bold mb-1">Fasting (NPO) Status</label>
                    <select class="form-select form-select-sm" id="{{ $prefix }}npo_status">
                        <option value="npo_midnight">NPO from Midnight (Standard)</option>
                        <option value="6_hours_fast">Fasting 6h Pre-Op</option>
                        <option value="clear_fluids_2h">Clear Fluids Up to 2h</option>
                        <option value="emergency_none">Emergency (No Fasting)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="{{ $prefix }}anesthesia_type" class="form-label small fw-bold mb-1">Anesthesia Plan</label>
                    <select class="form-select form-select-sm" id="{{ $prefix }}anesthesia_type">
                        <option value="general">General Anesthesia (GA)</option>
                        <option value="spinal">Spinal / Subarachnoid Block</option>
                        <option value="epidural">Epidural Anesthesia</option>
                        <option value="regional_block">Regional / Nerve Block</option>
                        <option value="sedation_local">Local Anesthesia + IV Sedation</option>
                        <option value="local_only">Local Anesthesia Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="{{ $prefix }}surgical_consent" class="form-label small fw-bold mb-1">Surgical Consent</label>
                    <select class="form-select form-select-sm" id="{{ $prefix }}surgical_consent">
                        <option value="required">Required (Form to be Signed)</option>
                        <option value="already_signed">Consent Form Signed &amp; Attached</option>
                        <option value="emergency_implied">Emergency Implied Consent</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-center pt-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="{{ $prefix }}blood_required" style="cursor: pointer;">
                        <label class="form-check-label small fw-bold text-danger" for="{{ $prefix }}blood_required" style="cursor: pointer;">
                            <i class="fa fa-tint me-1"></i> G&amp;X / Blood on Standby
                        </label>
                    </div>
                </div>
                <div class="col-12 mt-2">
                    <input type="text" class="form-control form-control-sm" id="{{ $prefix }}surgical_prep_notes"
                        placeholder="Pre-Op Instructions: e.g. Pre-medication, surgical site prep, prophylactic antibiotics, special implants/staplers...">
                </div>
            </div>
        </div>

        {{-- 4B. Bedside / Minor Clinical Preparation Controls --}}
        <div id="{{ $prefix }}clinical_prep_box" class="border border-info-subtle bg-info-subtle bg-opacity-10 rounded p-3 mb-3" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-info-subtle">
                <div class="fw-bold small text-info text-dark">
                    <i class="fa fa-stethoscope me-1"></i> Bedside Preparation &amp; Clinical Consumables
                </div>
                <span class="badge bg-info text-dark">Bedside Protocol</span>
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label for="{{ $prefix }}clinical_pack" class="form-label small fw-bold mb-1">Procedure Pack / Kit</label>
                    <select class="form-select form-select-sm" id="{{ $prefix }}clinical_pack">
                        <option value="routine_pack">Standard Treatment Pack</option>
                        <option value="sterile_dressing_kit">Sterile Dressing Kit</option>
                        <option value="biopsy_pack">Biopsy Pack &amp; Formalin Container</option>
                        <option value="catheter_kit">Catheterization Kit</option>
                        <option value="suture_pack">Suture Pack &amp; Instrument Tray</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="{{ $prefix }}clinical_consent" class="form-label small fw-bold mb-1">Informed Consent</label>
                    <select class="form-select form-select-sm" id="{{ $prefix }}clinical_consent">
                        <option value="routine_explained">Routine Clinical Explanation Given</option>
                        <option value="written_form">Written Minor Consent Form</option>
                        <option value="not_required">Not Required</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="{{ $prefix }}observation_plan" class="form-label small fw-bold mb-1">Post-Procedure Observation</label>
                    <select class="form-select form-select-sm" id="{{ $prefix }}observation_plan">
                        <option value="immediate">Immediate Outpatient Discharge</option>
                        <option value="30_min">30 Minutes Bedside Observation</option>
                        <option value="extended">Extended Observation / Ward Transfer</option>
                    </select>
                </div>
                <div class="col-12 mt-2">
                    <input type="text" class="form-control form-control-sm" id="{{ $prefix }}clinical_prep_notes"
                        placeholder="Specific consumables or wound instructions (e.g. Chlorhexidine scrub, 1% Lignocaine local, Aquacel dressing)...">
                </div>
            </div>
        </div>

        {{-- 5. Footer Action Bar --}}
        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <div class="text-muted small" id="{{ $prefix }}summary_text">
                Ready to add procedure
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary btn-sm" onclick="{{ $cancelHandler }}">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="{{ $prefix }}add_btn" onclick="{{ $submitHandler }}">
                    <i class="fa fa-plus-circle"></i> {{ $submitLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
