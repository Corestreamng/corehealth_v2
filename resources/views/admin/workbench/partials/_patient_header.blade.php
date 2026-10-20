{{--
    CoreHealth v2 — Universal Workbench Active Patient Header
    Compact space-maximizing banner displaying patient demographics, HMO coverage,
    account balance, clinical alerts, allergies, and expand/switch controls in signature hospital branding.
--}}
<div class="patient-header workbench-patient-header" id="patient-header" style="display: none;">
    <div class="patient-header-top">
        <div class="patient-header-main d-flex align-items-center gap-3" style="flex: 1; min-width: 0;">
            <div class="patient-avatar-wrapper" id="patient-avatar-wrapper">
                <div class="patient-avatar-circle" id="patient-avatar">
                    <i class="mdi mdi-account"></i>
                </div>
            </div>
            <div class="patient-info-summary" style="flex: 1; min-width: 0;">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <h5 class="patient-name mb-0" id="patient-name"></h5>
                    <button type="button" class="btn btn-sm btn-danger btn-manage-alerts" id="btn-manage-alerts" style="display:none;" title="Clinical Alerts">
                        <i class="mdi mdi-alert-octagon"></i> <span>Alerts</span>
                    </button>
                    <span class="patient-hmo-badge" id="patient-hmo-badge" style="display:none;"></span>
                </div>
                <div class="patient-meta" id="patient-meta"></div>
                <div class="sticky-header-alerts mt-1" style="max-height: 48px; overflow-y: auto;"></div>
                <div class="patient-allergies mt-1" id="patient-allergies" style="display: none;">
                    <span class="allergy-alert-badge"><i class="mdi mdi-alert"></i> Allergies: <span id="allergy-list"></span></span>
                </div>
            </div>
        </div>

        <div class="patient-header-controls d-flex align-items-center gap-2 flex-shrink-0">
            @if($showBalance ?? true)
            <div class="patient-account-balance" id="patient-header-balance" style="display: none;">
                <div class="balance-label">Account Balance</div>
                <div class="balance-value" id="header-balance-amount">₦0.00</div>
            </div>
            @endif

            <div class="patient-actions-group d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-header-action" id="btn-edit-patient" title="Edit Patient" style="display: none;">
                    <i class="mdi mdi-pencil"></i> <span class="d-none d-md-inline">Edit</span>
                </button>
                <button type="button" class="btn-expand-patient d-inline-flex" id="btn-expand-patient" title="Show more biodata details">
                    <span class="btn-expand-text">more biodata</span>
                    <i class="mdi mdi-chevron-down ms-1"></i>
                </button>
                <button type="button" class="btn btn-sm btn-header-action btn-header-close" id="btn-switch-patient" onclick="if(typeof WorkbenchShell!=='undefined'&&WorkbenchShell.clearPatient){WorkbenchShell.clearPatient();}else if(typeof clearPatient==='function'){clearPatient();}" title="Close / Switch Patient">
                    <i class="mdi mdi-close"></i> <span class="d-none d-md-inline">Close</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Expandable Biodata Drawer -->
    <div class="patient-details-expanded" id="patient-details-expanded" style="display: none;">
        <div class="patient-details-grid" id="patient-details-grid"></div>
    </div>
</div>
