{{-- ==========================================================================
     Column 3: Real-Time Activity Stream & Workbenches Dock (28% Desktop Width)
     Live Clinical Ledger Table & Persistent 18-Role Launchpad Dock
     ========================================================================== --}}
@php
    $user = Auth::user();
    $isAdmin = $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);
@endphp
<div class="dash-column dash-col-ledger">
    <div class="dash-card h-100 d-flex flex-column">

        {{-- Top Section: Live Activity Ledger --}}
        <div class="dash-card-header flex-shrink-0">
            <div class="dash-card-title">
                <i class="mdi mdi-history text-success"></i>
                <span>Live Activity Stream</span>
            </div>
            <span class="dash-card-badge">Multi-Dept</span>
        </div>

        <div class="dash-card-body dash-scrollable flex-grow-1 p-0">
            <div class="table-responsive h-100 mb-0">
                <table class="table dash-ledger-table mb-0">
                    <thead>
                        <tr>
                            <th class="col-patient">Patient</th>
                            <th class="col-dept">Dept</th>
                            <th class="col-event">Event</th>
                            <th class="col-status text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody id="dash-ledger-tbody">
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted small">
                                <i class="mdi mdi-loading mdi-spin me-1"></i> Loading live clinical stream...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Bottom Section: Department Workbenches Rapid Launch Dock --}}
        <div class="dash-dock-container flex-shrink-0">
            <div class="dash-dock-header">
                <span class="dash-dock-title">
                    <i class="mdi mdi-apps text-primary me-1"></i> Quick Workbenches
                </span>
                <span class="text-muted small">18 Roles</span>
            </div>

            <div class="dash-role-dock">

                {{-- 1. Reception --}}
                @if($isAdmin || $user->hasAnyRole(['RECEPTIONIST', 'Receptionist']))
                    <a href="{{ route('reception.workbench') }}" class="dash-dock-tile dock-reception" title="Reception Workbench">
                        <i class="mdi mdi-desktop-mac-dashboard"></i>
                        <span>Reception</span>
                    </a>
                @endif

                {{-- 2. Doctor --}}
                @if($isAdmin || $user->hasRole('DOCTOR'))
                    <a href="{{ route('encounters.index') }}" class="dash-dock-tile dock-doctor" title="Doctor Consultations">
                        <i class="mdi mdi-stethoscope"></i>
                        <span>Doctor</span>
                    </a>
                @endif

                {{-- 3. Nursing --}}
                @if($isAdmin || $user->hasRole('NURSE'))
                    <a href="{{ route('nursing-workbench.index') }}" class="dash-dock-tile dock-nursing" title="Nursing Workbench">
                        <i class="mdi mdi-heart-pulse"></i>
                        <span>Nursing</span>
                    </a>
                @endif

                {{-- 4. Inpatient Wards --}}
                @if($isAdmin || $user->hasAnyRole(['NURSE', 'DOCTOR']))
                    <a href="{{ route('beds.index') }}" class="dash-dock-tile dock-inpatient" title="Inpatient Wards & Beds">
                        <i class="mdi mdi-hotel"></i>
                        <span>Wards</span>
                    </a>
                @endif

                {{-- 5. Pharmacy --}}
                @if($isAdmin || $user->hasRole('PHARMACIST'))
                    <a href="{{ route('pharmacy.workbench') }}" class="dash-dock-tile dock-pharmacy" title="Pharmacy Workbench">
                        <i class="mdi mdi-pill"></i>
                        <span>Pharmacy</span>
                    </a>
                @endif

                {{-- 6. Lab --}}
                @if($isAdmin || $user->hasRole('LAB SCIENTIST'))
                    <a href="{{ route('lab.workbench') }}" class="dash-dock-tile dock-lab" title="Laboratory Workbench">
                        <i class="mdi mdi-flask-outline"></i>
                        <span>Lab</span>
                    </a>
                @endif

                {{-- 7. Radiology --}}
                @if($isAdmin || $user->hasAnyRole(['RADIOLOGIST', 'Scanning Room']))
                    <a href="{{ route('imaging.workbench') }}" class="dash-dock-tile dock-imaging" title="Imaging Workbench">
                        <i class="mdi mdi-radioactive"></i>
                        <span>Imaging</span>
                    </a>
                @endif

                {{-- 8. Theatre / Surgery --}}
                @if($isAdmin || $user->hasAnyRole(['SURGERY', 'SURGEON', 'THEATRE-NURSE', 'DOCTOR', 'NURSE']))
                    <a href="{{ route('surgery-workbench.index') }}" class="dash-dock-tile dock-theatre" title="Surgery & Theatre Workbench">
                        <i class="mdi mdi-scalpel"></i>
                        <span>Theatre</span>
                    </a>
                @endif

                {{-- 9. Maternity --}}
                @if($isAdmin || $user->hasRole('MATERNITY'))
                    <a href="{{ route('maternity-workbench.index') }}" class="dash-dock-tile dock-maternity" title="Maternity Workbench">
                        <i class="mdi mdi-mother-nurse"></i>
                        <span>Maternity</span>
                    </a>
                @endif

                {{-- 10. Store --}}
                @if($isAdmin || $user->hasRole('STORE'))
                    <a href="{{ route('inventory.store-workbench.index') }}" class="dash-dock-tile dock-store" title="Store & Inventory Workbench">
                        <i class="mdi mdi-store"></i>
                        <span>Store</span>
                    </a>
                @endif

                {{-- 11. Billing --}}
                @if($isAdmin || $user->hasAnyRole(['BILLER', 'ACCOUNTS']))
                    <a href="{{ route('billing.workbench') }}" class="dash-dock-tile dock-billing" title="Billing & Cashier Workbench">
                        <i class="mdi mdi-cash-register"></i>
                        <span>Billing</span>
                    </a>
                @endif

                {{-- 12. HMO --}}
                @if($isAdmin || $user->hasRole('HMO Executive'))
                    <a href="{{ route('hmo.workbench') }}" class="dash-dock-tile dock-hmo" title="HMO & Managed Care Workbench">
                        <i class="mdi mdi-hospital-building"></i>
                        <span>HMO</span>
                    </a>
                @endif

                {{-- 13. Accounts --}}
                @if($isAdmin || $user->hasRole('ACCOUNTS'))
                    <a href="{{ route('accounting.dashboard') }}" class="dash-dock-tile dock-accounts" title="Accounting Dashboard">
                        <i class="mdi mdi-calculator"></i>
                        <span>Accounts</span>
                    </a>
                @endif

                {{-- 14. Audit --}}
                @if($isAdmin || $user->hasAnyRole(['AUDITOR', 'AUDIT']))
                    <a href="/audit-workbench" class="dash-dock-tile dock-audit" title="Audit Workbench">
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>Audit</span>
                    </a>
                @endif

                {{-- 15. HR --}}
                @if($isAdmin || $user->hasAnyRole(['HR MANAGER', 'PAYROLL APPROVER']))
                    <a href="{{ route('hr.workbench.index') }}" class="dash-dock-tile dock-hr" title="HR Workbench">
                        <i class="mdi mdi-account-group"></i>
                        <span>HR</span>
                    </a>
                @endif

                {{-- 16. ESS / My Portal --}}
                @if($user->staff_profile)
                    <a href="{{ route('hr.ess.index') }}" class="dash-dock-tile dock-ess" title="Employee Self-Service">
                        <i class="mdi mdi-account-circle-outline"></i>
                        <span>Portal</span>
                    </a>
                @endif

                {{-- 17. Morgue --}}
                @if($isAdmin || $user->hasRole('MORGUE'))
                    <a href="{{ route('morgue.workbench') }}" class="dash-dock-tile dock-morgue" title="Morgue Workbench">
                        <i class="mdi mdi-emoticon-dead"></i>
                        <span>Morgue</span>
                    </a>
                @endif

                {{-- 18. Admin Config --}}
                @if($isAdmin)
                    <a href="{{ route('hospital-config.index') }}" class="dash-dock-tile dock-admin" title="Hospital Configuration">
                        <i class="mdi mdi-cogs"></i>
                        <span>Config</span>
                    </a>
                @endif

            </div>
        </div>

    </div>
</div>
