{{-- ==========================================================================
     Column 1: Operational Pipelines (28% Desktop Width)
     Extensive, Role-Scoped & Domain Driving Engine
     ========================================================================== --}}
@php
    $user = Auth::user();
    $userRoles = $user ? $user->roles->pluck('name')->toArray() : [];
    $isDoctor = in_array('DOCTOR', $userRoles);
    $isNurse = in_array('NURSE', $userRoles);
    $isPharm = in_array('PHARMACIST', $userRoles);
    $isLab = in_array('LAB SCIENTIST', $userRoles);
    $isStore = in_array('STORE', $userRoles);
    $isBiller = in_array('BILLER', $userRoles) || in_array('CASHIER', $userRoles) || in_array('ACCOUNTS', $userRoles);
    $isAdmin = $user && $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);
@endphp
<div class="dash-column dash-col-pipeline mobile-active">
    <div class="dash-card h-100">
        {{-- Card Header --}}
        <div class="dash-card-header">
            <div class="dash-card-title">
                <i class="mdi mdi-ray-start-arrow text-primary"></i>
                <span>Operational Pipelines</span>
            </div>
            <span class="dash-card-badge" id="pipe-total-label">Active Flow</span>
        </div>

        {{-- Card Body: Micro-Scrollable Categorized Queue List --}}
        <div class="dash-card-body dash-scrollable">
            <div class="dash-pipeline-list">

                {{-- ── Tier 0: Executive Overview ── --}}
                <div class="dash-pipe-section-head">
                    <span>Executive Overview</span>
                    <i class="mdi mdi-shield-crown-outline"></i>
                </div>

                {{-- Cockpit Overview --}}
                <div class="dash-pipeline-item pipe-cockpit {{ $isAdmin ? 'active-pipeline' : '' }}" data-domain="cockpit" title="Executive Cockpit: Hospital-Wide Overview">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-cockpit">
                            <i class="mdi mdi-view-dashboard-outline"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Hospital Cockpit
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Executive Telemetry • All Departments</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-tag">Global</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- ── Tier 1: Clinical & Patient Intake ── --}}
                <div class="dash-pipe-section-head">
                    <span>Clinical & Consultations</span>
                    <i class="mdi mdi-stethoscope"></i>
                </div>

                {{-- 1. Reception: Waiting Queue --}}
                <div class="dash-pipeline-item pipe-waiting" data-domain="receptionist" title="Reception: Waiting for Triage">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-reception">
                            <i class="mdi mdi-clock-outline"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Waiting for Triage
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Reception Check-in • Patient Records</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-waiting-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 2. Nursing: Vitals Queue --}}
                <div class="dash-pipeline-item pipe-vitals {{ $isNurse && !$isAdmin ? 'active-pipeline' : '' }}" data-domain="nursing" title="Nursing: Vitals & Triage">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-nursing">
                            <i class="mdi mdi-heart-pulse"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Vitals & Triage Station
                                @if($isNurse)<span class="dash-role-badge">MY ROLE</span>@endif
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Triage Station • Clinical Nursing</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-vitals-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 3. Doctor: Consult Queue --}}
                <div class="dash-pipeline-item pipe-consult {{ $isDoctor && !$isAdmin ? 'active-pipeline' : '' }}" data-domain="doctor" title="Doctor: Waiting Consultations">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-doctor">
                            <i class="mdi mdi-stethoscope"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Doctor Consultations
                                @if($isDoctor)<span class="dash-role-badge">MY SPECIALTY</span>@endif
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Consulting Clinics • OPD & Reviews</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-consult-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- ── Tier 2: Diagnostics & Investigations ── --}}
                <div class="dash-pipe-section-head">
                    <span>Diagnostics & Investigations</span>
                    <i class="mdi mdi-flask-outline"></i>
                </div>

                {{-- 4. Lab: Diagnostics Queue --}}
                <div class="dash-pipeline-item pipe-lab {{ $isLab && !$isAdmin ? 'active-pipeline' : '' }}" data-domain="lab" title="Laboratory: Sampling & Diagnostics">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-lab">
                            <i class="mdi mdi-flask-outline"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Medical Laboratory
                                @if($isLab)<span class="dash-role-badge">MY ROLE</span>@endif
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Specimens • Testing & Validations</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-lab-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 5. Radiology: Imaging Queue --}}
                <div class="dash-pipeline-item pipe-imaging" data-domain="imaging" title="Radiology: Medical Imaging & Scans">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-imaging">
                            <i class="mdi mdi-radioactive"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Radiology & Scans
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">X-Ray, Ultrasound, CT Scan</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-imaging-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- ── Tier 3: Pharmacy & Supply Chain ── --}}
                <div class="dash-pipe-section-head">
                    <span>Pharmacy & Supply Chain</span>
                    <i class="mdi mdi-pill"></i>
                </div>

                {{-- 6. Pharmacy: Dispense Queue --}}
                <div class="dash-pipeline-item pipe-dispense {{ $isPharm && !$isAdmin ? 'active-pipeline' : '' }}" data-domain="pharmacy" title="Pharmacy: Dispensary & Prescriptions">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-pharmacy">
                            <i class="mdi mdi-pill"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Pharmacy Dispensary
                                @if($isPharm)<span class="dash-role-badge">MY ROLE</span>@endif
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Prescriptions • Drugs & Dispensing</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-dispense-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 7. Store: Inventory Requisitions --}}
                <div class="dash-pipeline-item pipe-store {{ $isStore && !$isAdmin ? 'active-pipeline' : '' }}" data-domain="store" title="Store: Consumables & Requisitions">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-store">
                            <i class="mdi mdi-store-warehouse"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Central Store & Inventory
                                @if($isStore)<span class="dash-role-badge">MY ROLE</span>@endif
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Internal Requisitions • Stock POs</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-store-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- ── Tier 4: Inpatients & Surgical Care ── --}}
                <div class="dash-pipe-section-head">
                    <span>Inpatients & Surgical Care</span>
                    <i class="mdi mdi-hotel"></i>
                </div>

                {{-- 8. Inpatient Wards: Admitted Patients --}}
                <div class="dash-pipeline-item pipe-admitted" data-domain="inpatient" title="Wards: Admitted Patients & Beds">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-inpatient">
                            <i class="mdi mdi-hotel"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Ward Inpatients & Beds
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Ward Beds • Clinical Care Census</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-admitted-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 9. Surgery / Theatre Queue --}}
                <div class="dash-pipeline-item pipe-theatre" data-domain="theatre" title="Surgery: Operating Theatre">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-theatre">
                            <i class="mdi mdi-scalpel"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Operating Theatre
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Surgical Schedule • PACU Recovery</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-surgery-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 10. Maternity: Labour & ANC Queue --}}
                <div class="dash-pipeline-item pipe-maternity" data-domain="maternity" title="Maternity: Labour Ward & ANC">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-maternity">
                            <i class="mdi mdi-mother-nurse"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Maternity & ANC
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Antenatal Care • Labour Ward</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-maternity-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- ── Tier 5: Revenue & Governance ── --}}
                <div class="dash-pipe-section-head">
                    <span>Revenue & Governance</span>
                    <i class="mdi mdi-cash-multiple"></i>
                </div>

                {{-- 11. Billing: Unpaid Queue --}}
                <div class="dash-pipeline-item pipe-unpaid {{ $isBiller && !$isAdmin ? 'active-pipeline' : '' }}" data-domain="billing" title="Billing: Cashier & Payments">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon icon-billing">
                            <i class="mdi mdi-cash-multiple"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">
                                Cashier & Patient Billing
                                @if($isBiller)<span class="dash-role-badge">MY ROLE</span>@endif
                                <span class="active-dot"></span>
                            </div>
                            <div class="dash-pipe-sub">Payment Collection • Invoices</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-unpaid-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

            </div>
        </div>

        {{-- Card Footer: Quick Actions Dock --}}
        <div class="dash-card-footer">
            <div class="dash-pipe-actions">
                <a href="{{ route('appointments.list') }}" class="dash-pipe-action-btn" title="Clinic Appointments Roster">
                    <i class="mdi mdi-calendar-clock text-primary"></i>
                    <span>Appointments</span>
                </a>
                <a href="{{ route('patientVitalsQueue') }}" class="dash-pipe-action-btn" title="Fast Vitals Triage">
                    <i class="mdi mdi-heart-pulse text-danger"></i>
                    <span>Fast Triage</span>
                </a>
                <a href="{{ route('nursing-workbench.index') }}" class="dash-pipe-action-btn" title="Nursing Shifts & Handovers">
                    <i class="mdi mdi-account-clock text-info"></i>
                    <span>Shift Mgmt</span>
                </a>
            </div>
        </div>

    </div>
</div>
