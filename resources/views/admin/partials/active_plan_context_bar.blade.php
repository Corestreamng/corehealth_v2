<div class="tp-active-context-bar d-none" id="tp-active-context-bar">
    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 shadow-sm"
         style="background: linear-gradient(135deg, var(--hos-color) 0%, color-mix(in srgb, var(--hos-color) 50%, black) 100%); color: #ffffff; border-top: 1px solid rgba(255,255,255,0.1);">
        
        <div class="d-flex align-items-center gap-2 gap-md-3 mb-2 mb-md-0 w-100 w-md-auto">
            <div class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm d-none d-md-flex" style="width: 36px; height: 36px; flex-shrink: 0;">
                <i class="fa fa-clipboard-list" style="color: var(--hos-color); font-size: 1.1rem;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between w-100 d-md-none mb-1">
                    <span style="font-size: 0.7rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;"><i class="fa fa-clipboard-list me-1"></i> Active Plan</span>
                    <button type="button" class="btn btn-sm btn-link text-white p-0" onclick="ClinicalOrdersKit.clearActivePlan()">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="d-none d-md-block" style="font-size: 0.75rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600;">Working On:</span>
                    <span class="fw-bold tp-ctx-plan-name text-truncate" style="font-size: 1.05rem; text-shadow: 0 1px 2px rgba(0,0,0,0.2); max-width: 220px;"></span>
                    <span class="badge tp-ctx-priority-badge ms-1 shadow-sm px-2 py-1" style="font-size: 0.65rem; background-color: #ffd54f !important; color: #3e2723 !important;"></span>
                </div>
                <div class="d-flex align-items-center mt-1">
                    <span class="badge bg-light text-dark fw-semibold shadow-sm tp-ctx-diagnosis text-truncate text-start" style="font-size: 0.65rem; max-width: 300px; white-space: normal;">
                        <i class="fa fa-stethoscope text-primary me-1"></i> <span></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3 w-100 w-md-auto justify-content-between justify-content-md-end">
            <div class="d-flex flex-column align-items-start align-items-md-end">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="d-none d-md-inline" style="font-size: 0.75rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px;">Progress</span>
                    <small class="fw-bold tp-ctx-progress-text" style="font-size: 0.8rem;"></small>
                </div>
                <div class="progress shadow-inner" style="width: 100px; height: 6px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                    <div class="progress-bar tp-ctx-progress-bar bg-white" style="width: 0%; transition: width 0.5s ease; border-radius: 10px; box-shadow: 0 0 5px rgba(255,255,255,0.8);"></div>
                </div>
            </div>
            
            <div class="d-none d-md-block" style="border-left: 1px solid rgba(255,255,255,0.2); height: 35px;"></div>
            
            <div class="d-flex align-items-center gap-2">
                <small class="tp-ctx-doctor d-none d-lg-inline text-truncate" style="font-size: 0.75rem; opacity: 0.9; max-width: 120px;"><i class="fa fa-user-md me-1"></i></small>
                <button type="button" class="btn btn-sm btn-light rounded-pill fw-bold px-3 py-1 tp-ctx-view-link shadow-sm d-flex align-items-center transition-all" onclick="switch_tab(event, 'treatment_plans_tab')" style="color: var(--hos-color); font-size: 0.75rem;">
                    <i class="fa fa-external-link-alt me-1 me-md-2"></i> <span class="d-none d-md-inline">View</span><span class="d-inline d-md-none">View</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-light rounded-circle p-0 ms-1 d-none d-md-flex align-items-center justify-content-center transition-all hover-danger" onclick="ClinicalOrdersKit.clearActivePlan()" title="Clear active plan" style="width: 30px; height: 30px; border-width: 2px;" onmouseover="this.classList.add('bg-danger', 'text-white', 'border-danger');" onmouseout="this.classList.remove('bg-danger', 'text-white', 'border-danger');">
                    <i class="fa fa-times" style="font-size: 0.9rem;"></i>
                </button>
            </div>
        </div>
    </div>
</div>
