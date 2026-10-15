{{-- ==========================================================================
     Cockpit Header Ribbon — Live Status, Perspective Switcher & Fast Intake
     ========================================================================== --}}
@php
    $user = Auth::user();
    $roles = $user->roles->pluck('name')->toArray();
    $isAdmin = $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);
@endphp
<div class="dash-ribbon">
    {{-- Left: Pulse Status & Live WAT Clock --}}
    <div class="dash-ribbon-left">
        <div class="dash-pulse-badge">
            <span class="dash-pulse-dot"></span>
            <span>LIVE OPS</span>
        </div>
        <div class="dash-shift-badge">
            <i class="mdi mdi-calendar-clock me-1"></i>
            <span>Active Shift</span>
        </div>
        <div class="dash-clock" id="dash-live-clock">
            <i class="mdi mdi-clock-outline me-1"></i> --:--:--
        </div>
    </div>

    {{-- Center: Role Perspective Switcher Bar --}}
    <div class="dash-perspective-bar" id="dash-perspective-bar">
        <button type="button" class="dash-perspective-pill active" data-perspective="cockpit">
            <i class="mdi mdi-view-dashboard-outline me-1"></i> Cockpit
        </button>

        @if($isAdmin || $user->hasAnyRole(['RECEPTIONIST', 'Receptionist']))
            <button type="button" class="dash-perspective-pill" data-perspective="receptionist">
                <i class="mdi mdi-account-tie me-1"></i> Reception
            </button>
        @endif

        @if($isAdmin || $user->hasRole('DOCTOR'))
            <button type="button" class="dash-perspective-pill" data-perspective="doctor">
                <i class="mdi mdi-stethoscope me-1"></i> Doctor
            </button>
        @endif

        @if($isAdmin || $user->hasRole('NURSE'))
            <button type="button" class="dash-perspective-pill" data-perspective="nursing">
                <i class="mdi mdi-heart-pulse me-1"></i> Nursing
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['NURSE', 'DOCTOR']))
            <button type="button" class="dash-perspective-pill" data-perspective="inpatient">
                <i class="mdi mdi-hotel me-1"></i> Inpatient
            </button>
        @endif

        @if($isAdmin || $user->hasRole('PHARMACIST'))
            <button type="button" class="dash-perspective-pill" data-perspective="pharmacy">
                <i class="mdi mdi-pill me-1"></i> Pharmacy
            </button>
        @endif

        @if($isAdmin || $user->hasRole('LAB SCIENTIST'))
            <button type="button" class="dash-perspective-pill" data-perspective="lab">
                <i class="mdi mdi-flask-outline me-1"></i> Lab
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['RADIOLOGIST', 'Scanning Room']))
            <button type="button" class="dash-perspective-pill" data-perspective="imaging">
                <i class="mdi mdi-radioactive me-1"></i> Radiology
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['SURGERY', 'SURGEON', 'THEATRE-NURSE', 'DOCTOR', 'NURSE']))
            <button type="button" class="dash-perspective-pill" data-perspective="theatre">
                <i class="mdi mdi-scalpel me-1"></i> Theatre
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['MATERNITY', 'NURSE', 'DOCTOR']))
            <button type="button" class="dash-perspective-pill" data-perspective="maternity">
                <i class="mdi mdi-mother-nurse me-1"></i> Maternity
            </button>
        @endif

        @if($isAdmin || $user->hasRole('STORE'))
            <button type="button" class="dash-perspective-pill" data-perspective="store">
                <i class="mdi mdi-store me-1"></i> Store
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['BILLER', 'CASHIER', 'ACCOUNTS']))
            <button type="button" class="dash-perspective-pill" data-perspective="biller">
                <i class="mdi mdi-cash-register me-1"></i> Billing
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['HMO', 'HMO Executive']))
            <button type="button" class="dash-perspective-pill" data-perspective="hmo">
                <i class="mdi mdi-hospital-building me-1"></i> HMO
            </button>
        @endif

        @if($isAdmin || $user->hasRole('ACCOUNTS'))
            <button type="button" class="dash-perspective-pill" data-perspective="accounts">
                <i class="mdi mdi-calculator me-1"></i> Accounts
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['AUDITOR', 'AUDIT']))
            <button type="button" class="dash-perspective-pill" data-perspective="audit">
                <i class="mdi mdi-shield-check-outline me-1"></i> Audit
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['HR MANAGER', 'PAYROLL APPROVER']))
            <button type="button" class="dash-perspective-pill" data-perspective="hr">
                <i class="mdi mdi-account-group me-1"></i> HR
            </button>
        @endif

        @if($user->staff_profile && Auth::user()->can('ess.access'))
            <button type="button" class="dash-perspective-pill" data-perspective="ess">
                <i class="mdi mdi-account-circle-outline me-1"></i> My Portal
            </button>
        @endif

        @if($isAdmin || $user->hasRole('MORGUE'))
            <button type="button" class="dash-perspective-pill" data-perspective="morgue">
                <i class="mdi mdi-emoticon-dead me-1"></i> Morgue
            </button>
        @endif

        @if($isAdmin)
            <button type="button" class="dash-perspective-pill" data-perspective="admin">
                <i class="mdi mdi-cogs me-1"></i> Admin
            </button>
        @endif
    </div>

    {{-- Right: Quick Action Buttons & Refresh --}}
    <div class="dash-ribbon-right">
        @if($isAdmin || $user->hasAnyRole(['RECEPTIONIST', 'Receptionist', 'NURSE', 'DOCTOR']))
            <button type="button" class="dash-btn-emergency" data-bs-toggle="modal" data-bs-target="#emergencyIntakeModal" data-toggle="modal" data-target="#emergencyIntakeModal" onclick="if(typeof showEmergencyIntakeModal==='function'){showEmergencyIntakeModal();}" title="Open Emergency Walk-In Intake">
                <i class="mdi mdi-ambulance me-1"></i> Emergency Intake
            </button>
        @endif

        @if($isAdmin || $user->hasAnyRole(['RECEPTIONIST', 'Receptionist']))
            <button type="button" class="dash-btn-primary" data-bs-toggle="modal" data-bs-target="#patientFormModal" data-toggle="modal" data-target="#patientFormModal" onclick="if(typeof showPatientFormModal==='function'){showPatientFormModal('create');}" title="Register New Patient">
                <i class="mdi mdi-account-plus me-1"></i> New Patient
            </button>
        @endif

        <span class="dash-sync-badge" id="dash-sync-indicator" title="Live Auto-Sync Status">
            <span class="dash-sync-dot"></span> Live
        </span>

        <button type="button" class="dash-btn-refresh" id="dash-btn-refresh" title="Refresh Live Cockpit">
            <i class="mdi mdi-refresh"></i>
        </button>
    </div>
</div>
