<style>
    @media (max-width: 767.98px) {
        .tp-ctx-plan-name-wrap { max-width: 160px; }
        .tp-ctx-diagnosis-wrap { max-width: 200px; }
    }
</style>
<div class="tp-active-context-bar d-none" id="tp-active-context-bar">
    <div class="d-flex flex-wrap align-items-center justify-content-between px-2 px-md-3 py-1 py-md-2 shadow-sm"
         style="background: linear-gradient(135deg, var(--hos-color) 0%, color-mix(in srgb, var(--hos-color) 50%, black) 100%); color: #ffffff; border-top: 1px solid rgba(255,255,255,0.1);">
        
        <div class="d-flex align-items-center gap-2 gap-md-3 mb-1 mb-md-0 w-100 flex-md-grow-1" style="min-width: 0; flex-basis: 0;">
            <div class="bg-white rounded-circle align-items-center justify-content-center shadow-sm d-none d-md-flex" style="width: 36px; height: 36px; flex-shrink: 0;">
                <i class="fa fa-clipboard-list" style="color: var(--hos-color); font-size: 1.1rem;"></i>
            </div>
            
            <div class="flex-grow-1" style="min-width: 0;">
                {{-- Row 1: Plan Name + Priority + Mobile Actions --}}
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-1 gap-md-2 text-truncate">
                        <span class="d-none d-md-block" style="font-size: 0.75rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Working On:</span>
                        <i class="fa fa-clipboard-list d-md-none me-1" style="font-size: 0.85rem; opacity: 0.8;"></i>
                        <span class="fw-bold tp-ctx-plan-name text-truncate tp-ctx-plan-name-wrap" style="font-size: 0.95rem; text-shadow: 0 1px 2px rgba(0,0,0,0.2);"></span>
                        <span class="badge tp-ctx-priority-badge shadow-sm px-1 py-0 ms-1" style="font-size: 0.55rem; background-color: #ffd54f !important; color: #3e2723 !important;"></span>
                    </div>
                    
                    {{-- Mobile Only Action: View & Close in top right --}}
                    <div class="d-flex align-items-center gap-2 d-md-none ms-2">
                        <button type="button" class="btn btn-sm btn-light rounded-pill px-2 py-0 shadow-sm" onclick="switch_tab(event, 'treatment_plans_tab')" style="color: var(--hos-color); font-size: 0.7rem; line-height: 1.5;">
                            View
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-white p-0 m-0" onclick="ClinicalOrdersKit.clearActivePlan()" style="line-height: 1;">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>

                {{-- Row 2: Diagnosis + Mobile Progress --}}
                <div class="d-flex align-items-center justify-content-between mt-1">
                    <span class="badge bg-light text-dark fw-semibold shadow-sm tp-ctx-diagnosis text-truncate text-start tp-ctx-diagnosis-wrap" style="font-size: 0.65rem; white-space: nowrap;">
                        <i class="fa fa-stethoscope text-primary me-1"></i> <span></span>
                    </span>
                    
                    {{-- Mobile Only Progress --}}
                    <div class="d-flex align-items-center gap-1 d-md-none ms-2">
                        <small class="fw-bold tp-ctx-progress-text" style="font-size: 0.7rem;"></small>
                        <div class="progress shadow-inner" style="width: 40px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                            <div class="progress-bar tp-ctx-progress-bar bg-white" style="width: 0%; transition: width 0.5s ease; border-radius: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Desktop Only Actions --}}
        <div class="d-none d-md-flex align-items-center gap-3 w-auto justify-content-end flex-shrink-0 ms-3">
            <div class="d-flex flex-column align-items-end">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span style="font-size: 0.75rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px;">Progress</span>
                    <small class="fw-bold tp-ctx-progress-text" style="font-size: 0.8rem;"></small>
                </div>
                <div class="progress shadow-inner" style="width: 100px; height: 6px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                    <div class="progress-bar tp-ctx-progress-bar bg-white" style="width: 0%; transition: width 0.5s ease; border-radius: 10px; box-shadow: 0 0 5px rgba(255,255,255,0.8);"></div>
                </div>
            </div>
            
            <div style="border-left: 1px solid rgba(255,255,255,0.2); height: 35px;"></div>
            
            <div class="d-flex align-items-center gap-2">
                <small class="tp-ctx-doctor text-truncate" style="font-size: 0.75rem; opacity: 0.9; max-width: 120px;"><i class="fa fa-user-md me-1"></i></small>
                <button type="button" class="btn btn-sm btn-light rounded-pill fw-bold px-3 py-1 tp-ctx-view-link shadow-sm d-flex align-items-center transition-all" onclick="switch_tab(event, 'treatment_plans_tab')" style="color: var(--hos-color); font-size: 0.75rem;">
                    <i class="fa fa-external-link-alt me-2"></i> View
                </button>
                <button type="button" class="btn btn-sm btn-outline-light rounded-circle p-0 ms-1 d-flex align-items-center justify-content-center transition-all hover-danger" onclick="ClinicalOrdersKit.clearActivePlan()" title="Clear active plan" style="width: 30px; height: 30px; border-width: 2px;" onmouseover="this.classList.add('bg-danger', 'text-white', 'border-danger');" onmouseout="this.classList.remove('bg-danger', 'text-white', 'border-danger');">
                    <i class="fa fa-times" style="font-size: 0.9rem;"></i>
                </button>
            </div>
        </div>
    </div>
</div>
