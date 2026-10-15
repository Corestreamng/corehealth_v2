{{-- ==========================================================================
     Column 3: Domain Queue, Shortcuts & Live Stream (28% Desktop Width)
     Driven by Active Operational Pipeline Stage
     ========================================================================== --}}
@php
    $user = Auth::user();
    $isAdmin = $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);
@endphp
<div class="dash-column dash-col-ledger">
    <div class="dash-card h-100 d-flex flex-column">

        {{-- Header Section: Live Queue Title & Badge --}}
        <div class="dash-card-header flex-shrink-0">
            <div class="dash-card-title">
                <i class="mdi mdi-format-list-checks text-success" id="col3-queue-icon"></i>
                <span id="col3-queue-title">Live Activity Stream & Queue</span>
            </div>
            <div class="dash-card-header-actions">
                <span class="dash-card-badge" id="col3-queue-badge">Live Stream</span>
                <button type="button" class="dash-view-toggle-btn" id="btn-toggle-queue-view" title="Toggle Queue Cards / Table Grid">
                    <i class="mdi mdi-view-headline" id="icon-queue-view-toggle"></i>
                </button>
            </div>
        </div>

        {{-- Top Section: Contextual Quick Shortcuts Dock --}}
        <div class="dash-shortcuts-dock-wrap flex-shrink-0 p-2 border-bottom" id="dash-shortcuts-dock">
            <div class="dash-dock-header mb-1">
                <span class="dash-dock-title">Quick Workbenches</span>
            </div>
            {{-- Default Cockpit: 18-role Workbench Launch Dock --}}
            <div class="dash-role-dock">
                @if($isAdmin || $user->hasAnyRole(['RECEPTIONIST', 'Receptionist']))
                    <a href="{{ route('reception.workbench') }}" class="dash-dock-tile dock-reception" title="Reception Workbench">
                        <i class="mdi mdi-desktop-mac-dashboard"></i>
                        <span>Reception</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('DOCTOR'))
                    <a href="{{ route('encounters.index') }}" class="dash-dock-tile dock-doctor" title="Doctor Dashboard (My Queues)">
                        <i class="mdi mdi-stethoscope"></i>
                        <span>Doctor</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('NURSE'))
                    <a href="{{ route('nursing-workbench.index') }}" class="dash-dock-tile dock-nursing" title="Nursing Workbench">
                        <i class="mdi mdi-heart-pulse"></i>
                        <span>Nursing</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasAnyRole(['NURSE', 'DOCTOR']))
                    <a href="{{ route('beds.index') }}" class="dash-dock-tile dock-inpatient" title="Inpatient Wards & Beds">
                        <i class="mdi mdi-hotel"></i>
                        <span>Wards</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('PHARMACIST'))
                    <a href="{{ route('pharmacy.workbench') }}" class="dash-dock-tile dock-pharmacy" title="Pharmacy Workbench">
                        <i class="mdi mdi-pill"></i>
                        <span>Pharmacy</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('LAB SCIENTIST'))
                    <a href="{{ route('lab.workbench') }}" class="dash-dock-tile dock-lab" title="Laboratory Workbench">
                        <i class="mdi mdi-flask-outline"></i>
                        <span>Lab</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('RADIOLOGIST'))
                    <a href="{{ route('imaging.workbench') }}" class="dash-dock-tile dock-imaging" title="Imaging Workbench">
                        <i class="mdi mdi-radioactive"></i>
                        <span>Imaging</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasAnyRole(['SURGEON', 'THEATRE-NURSE']))
                    <a href="{{ route('surgery-workbench.index') }}" class="dash-dock-tile dock-theatre" title="Theatre Workbench">
                        <i class="mdi mdi-scalpel"></i>
                        <span>Theatre</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('MATERNITY'))
                    <a href="{{ route('maternity-workbench.index') }}" class="dash-dock-tile dock-maternity" title="Maternity Workbench">
                        <i class="mdi mdi-mother-nurse"></i>
                        <span>Maternity</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('STORE'))
                    <a href="{{ route('inventory.store-workbench.index') }}" class="dash-dock-tile dock-store" title="Store Workbench">
                        <i class="mdi mdi-store-warehouse"></i>
                        <span>Store</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasAnyRole(['BILLER', 'CASHIER', 'ACCOUNTS']))
                    <a href="{{ route('billing.workbench') }}" class="dash-dock-tile dock-billing" title="Billing Workbench">
                        <i class="mdi mdi-cash-multiple"></i>
                        <span>Billing</span>
                    </a>
                @endif

                @if($isAdmin || $user->hasRole('HMO'))
                    <a href="{{ route('hmo.workbench') }}" class="dash-dock-tile dock-hmo" title="HMO Claims Workbench">
                        <i class="mdi mdi-shield-check"></i>
                        <span>HMO</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Bottom Section: Live Domain Queue & Activity Stream / Table --}}
        <div class="dash-card-body dash-scrollable flex-grow-1 p-0" id="col3-scroll-container">
            {{-- Mode A: High-Density Queue Stream Cards (Default, Fluid, Never Truncates Badly) --}}
            <div class="dash-queue-stream" id="dash-queue-stream">
                <div class="text-center py-4 text-muted small">
                    <i class="mdi mdi-loading mdi-spin me-1"></i> Synchronizing operational stream...
                </div>
            </div>

            {{-- Mode B: Modern Responsive Table (Optional Grid View) --}}
            <div class="table-responsive h-100 mb-0 d-none" id="dash-ledger-table-wrap">
                <table class="table dash-ledger-table mb-0" id="dash-ledger-table">
                    <thead>
                        <tr>
                            <th class="col-patient">Patient / Entity</th>
                            <th class="col-dept" id="col3-th-dept">Clinic & Details</th>
                            <th class="col-status text-right">Status</th>
                            <th class="col-action text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="dash-ledger-tbody">
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted small">
                                <i class="mdi mdi-loading mdi-spin me-1"></i> Synchronizing operational stream...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
