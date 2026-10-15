{{-- ==========================================================================
     Column 1: Operational Pipelines (28% Desktop Width)
     Extensive, Role-Scoped & Domain Driving Engine
     Protected & Scoped character-for-character to Match Sidebar Authority
     ========================================================================== --}}
@php
    $user = Auth::user();
    $userRoles = $user ? $user->roles->pluck('name')->toArray() : [];
    $isAdmin = $user && $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);
    $isDoctor = in_array('DOCTOR', $userRoles);
    $isNurse = in_array('NURSE', $userRoles);
    $isPharm = in_array('PHARMACIST', $userRoles);
    $isLab = in_array('LAB SCIENTIST', $userRoles);
    $isImaging = in_array('RADIOLOGIST', $userRoles) || in_array('Scanning Room', $userRoles);
    $isStore = in_array('STORE', $userRoles);
    $isTheatre = in_array('SURGEON', $userRoles) || in_array('THEATRE-NURSE', $userRoles) || $isDoctor || $isNurse;
    $isMaternity = in_array('MATERNITY', $userRoles) || $isNurse || $isDoctor;
    $isBiller = in_array('BILLER', $userRoles) || in_array('CASHIER', $userRoles) || in_array('ACCOUNTS', $userRoles);
    $isHmo = in_array('HMO Executive', $userRoles) || in_array('HMO', $userRoles);
    $isAccounts = in_array('ACCOUNTS', $userRoles);
    $isHr = in_array('HR MANAGER', $userRoles) || in_array('PAYROLL APPROVER', $userRoles);
    $isAudit = in_array('AUDITOR', $userRoles) || in_array('AUDIT', $userRoles);
    $isMorgue = in_array('MORGUE', $userRoles);
    $isRecep = in_array('RECEPTIONIST', $userRoles) || in_array('Receptionist', $userRoles);
    $isEss = $user && $user->staff_profile && $user->can('ess.access');
@endphp
<div class="dash-column dash-col-pipeline mobile-active">
    <div class="dash-card h-100 d-flex flex-column">
        {{-- Card Header --}}
        <div class="dash-card-header flex-shrink-0">
            <div class="dash-card-title">
                <i class="mdi mdi-ray-start-arrow text-primary"></i>
                <span>Operational Pipelines</span>
            </div>
            <span class="dash-card-badge" id="pipe-total-label">Active Flow</span>
        </div>

        {{-- Card Body: Micro-Scrollable Categorized Queue List --}}
        <div class="dash-card-body dash-scrollable flex-grow-1">
            <div class="dash-pipeline-list">

                {{-- ── Tier 0: Executive Overview (Superadmin & Admin) ── --}}
                @if($isAdmin)
                    <div class="dash-pipe-section-head">
                        <span>Executive Overview</span>
                        <i class="mdi mdi-shield-crown-outline"></i>
                    </div>

                    {{-- Cockpit Overview --}}
                    <div class="dash-pipeline-item pipe-cockpit active-pipeline" data-domain="cockpit" title="Executive Cockpit: Hospital-Wide Overview">
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

                    {{-- System Governance --}}
                    <div class="dash-pipeline-item pipe-admin" data-domain="admin" title="System Administration & Hospital Governance">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon">
                                <i class="mdi mdi-cogs"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    Hospital Governance
                                    <span class="active-dot"></span>
                                </div>
                                <div class="dash-pipe-sub">Backups • Audit Logs • Preferences</div>
                            </div>
                        </div>
                        <div class="dash-pipe-right">
                            <span class="dash-pipe-tag">System</span>
                            <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                        </div>
                    </div>
                @endif

                {{-- ── Tier 1: Reception & Patient Intake ── --}}
                @if($isAdmin || $isRecep)
                    <div class="dash-pipe-section-head">
                        <span>Reception & Intake</span>
                        <i class="mdi mdi-account-tie"></i>
                    </div>

                    {{-- Reception: Waiting for Triage --}}
                    <div class="dash-pipeline-item pipe-waiting {{ !$isAdmin && $isRecep ? 'active-pipeline' : '' }}" data-domain="receptionist" title="Reception: Waiting for Triage">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon icon-reception">
                                <i class="mdi mdi-clock-outline"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    Waiting for Triage
                                    @if($isRecep && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
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
                @endif

                {{-- ── Tier 2: Clinical Nursing & Inpatients ── --}}
                @if($isAdmin || $isNurse)
                    <div class="dash-pipe-section-head">
                        <span>Nursing & Inpatients</span>
                        <i class="mdi mdi-heart-pulse"></i>
                    </div>

                    {{-- Nursing: Vitals Station --}}
                    <div class="dash-pipeline-item pipe-vitals {{ !$isAdmin && $isNurse ? 'active-pipeline' : '' }}" data-domain="nursing" title="Nursing: Vitals & Triage">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon icon-nursing">
                                <i class="mdi mdi-heart-pulse"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    Vitals & Triage Station
                                    @if($isNurse && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
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

                    {{-- Inpatients Wards & Beds --}}
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
                @endif

                {{-- ── Tier 3: Doctor & Consultations ── --}}
                @if($isAdmin || $isDoctor)
                    <div class="dash-pipe-section-head">
                        <span>Clinical Consultations</span>
                        <i class="mdi mdi-stethoscope"></i>
                    </div>

                    {{-- Doctor Consultations --}}
                    <div class="dash-pipeline-item pipe-consult {{ !$isAdmin && $isDoctor ? 'active-pipeline' : '' }}" data-domain="doctor" title="Doctor: Waiting Consultations">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon icon-doctor">
                                <i class="mdi mdi-stethoscope"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    Doctor Consultations
                                    @if($isDoctor && !$isAdmin)<span class="dash-role-badge">MY SPECIALTY</span>@endif
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
                @endif

                {{-- ── Tier 4: Diagnostics & Investigations ── --}}
                @if($isAdmin || $isLab || $isImaging)
                    <div class="dash-pipe-section-head">
                        <span>Diagnostics & Investigations</span>
                        <i class="mdi mdi-flask-outline"></i>
                    </div>

                    @if($isAdmin || $isLab)
                        {{-- Lab Diagnostics --}}
                        <div class="dash-pipeline-item pipe-lab {{ !$isAdmin && $isLab ? 'active-pipeline' : '' }}" data-domain="lab" title="Laboratory: Sampling & Diagnostics">
                            <div class="dash-pipe-left">
                                <div class="dash-pipe-icon icon-lab">
                                    <i class="mdi mdi-flask-outline"></i>
                                </div>
                                <div class="dash-pipe-text">
                                    <div class="dash-pipe-name">
                                        Medical Laboratory
                                        @if($isLab && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
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
                    @endif

                    @if($isAdmin || $isImaging)
                        {{-- Radiology / Scans --}}
                        <div class="dash-pipeline-item pipe-imaging {{ !$isAdmin && $isImaging ? 'active-pipeline' : '' }}" data-domain="imaging" title="Radiology: Medical Imaging & Scans">
                            <div class="dash-pipe-left">
                                <div class="dash-pipe-icon icon-imaging">
                                    <i class="mdi mdi-radioactive"></i>
                                </div>
                                <div class="dash-pipe-text">
                                    <div class="dash-pipe-name">
                                        Radiology & Scans
                                        @if($isImaging && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
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
                    @endif
                @endif

                {{-- ── Tier 5: Pharmacy & Supply Chain ── --}}
                @if($isAdmin || $isPharm || $isStore)
                    <div class="dash-pipe-section-head">
                        <span>Pharmacy & Supply Chain</span>
                        <i class="mdi mdi-pill"></i>
                    </div>

                    @if($isAdmin || $isPharm)
                        {{-- Pharmacy Dispensary --}}
                        <div class="dash-pipeline-item pipe-dispense {{ !$isAdmin && $isPharm ? 'active-pipeline' : '' }}" data-domain="pharmacy" title="Pharmacy: Dispensary & Prescriptions">
                            <div class="dash-pipe-left">
                                <div class="dash-pipe-icon icon-pharmacy">
                                    <i class="mdi mdi-pill"></i>
                                </div>
                                <div class="dash-pipe-text">
                                    <div class="dash-pipe-name">
                                        Pharmacy Dispensary
                                        @if($isPharm && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
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
                    @endif

                    @if($isAdmin || $isStore)
                        {{-- Central Store --}}
                        <div class="dash-pipeline-item pipe-store {{ !$isAdmin && $isStore ? 'active-pipeline' : '' }}" data-domain="store" title="Store: Consumables & Requisitions">
                            <div class="dash-pipe-left">
                                <div class="dash-pipe-icon icon-store">
                                    <i class="mdi mdi-store-warehouse"></i>
                                </div>
                                <div class="dash-pipe-text">
                                    <div class="dash-pipe-name">
                                        Central Store & Inventory
                                        @if($isStore && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
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
                    @endif
                @endif

                {{-- ── Tier 6: Theatre & Maternity Care ── --}}
                @if($isAdmin || $isTheatre || $isMaternity)
                    <div class="dash-pipe-section-head">
                        <span>Theatre & Maternity</span>
                        <i class="mdi mdi-scalpel"></i>
                    </div>

                    @if($isAdmin || $isTheatre)
                        {{-- Operating Theatre --}}
                        <div class="dash-pipeline-item pipe-theatre {{ !$isAdmin && in_array('SURGEON', $userRoles) ? 'active-pipeline' : '' }}" data-domain="theatre" title="Surgery: Operating Theatre">
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
                    @endif

                    @if($isAdmin || $isMaternity)
                        {{-- Maternity & ANC --}}
                        <div class="dash-pipeline-item pipe-maternity {{ !$isAdmin && in_array('MATERNITY', $userRoles) ? 'active-pipeline' : '' }}" data-domain="maternity" title="Maternity: Labour Ward & ANC">
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
                    @endif
                @endif

                {{-- ── Tier 7: Revenue, Billing & HMO ── --}}
                @if($isAdmin || $isBiller || $isHmo)
                    <div class="dash-pipe-section-head">
                        <span>Revenue & Insurance</span>
                        <i class="mdi mdi-cash-multiple"></i>
                    </div>

                    @if($isAdmin || $isBiller)
                        {{-- Cashier & Patient Billing --}}
                        <div class="dash-pipeline-item pipe-billing {{ !$isAdmin && ($isBiller && !$isAccounts) ? 'active-pipeline' : '' }}" data-domain="billing" title="Billing: Cashier & Payments">
                            <div class="dash-pipe-left">
                                <div class="dash-pipe-icon icon-billing">
                                    <i class="mdi mdi-cash-multiple"></i>
                                </div>
                                <div class="dash-pipe-text">
                                    <div class="dash-pipe-name">
                                        Cashier & Patient Billing
                                        @if($isBiller && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
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
                    @endif

                    @if($isAdmin || $isHmo)
                        {{-- HMO Claims & Authorizations --}}
                        <div class="dash-pipeline-item pipe-hmo {{ !$isAdmin && $isHmo ? 'active-pipeline' : '' }}" data-domain="hmo" title="HMO: Claims & Pre-Authorizations">
                            <div class="dash-pipe-left">
                                <div class="dash-pipe-icon">
                                    <i class="mdi mdi-shield-check"></i>
                                </div>
                                <div class="dash-pipe-text">
                                    <div class="dash-pipe-name">
                                        HMO Claims & Vetting
                                        @if($isHmo && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
                                        <span class="active-dot"></span>
                                    </div>
                                    <div class="dash-pipe-sub">Pre-Authorizations • Tariffs • Claims</div>
                                </div>
                            </div>
                            <div class="dash-pipe-right">
                                <span class="dash-pipe-count badge-zero" id="pipe-hmo-count">0</span>
                                <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- ── Tier 8: Financial Accounting & Treasury ── --}}
                @if($isAdmin || $isAccounts)
                    <div class="dash-pipe-section-head">
                        <span>Finance & Treasury</span>
                        <i class="mdi mdi-calculator"></i>
                    </div>

                    {{-- General Ledger & Accounts --}}
                    <div class="dash-pipeline-item pipe-accounts {{ !$isAdmin && $isAccounts ? 'active-pipeline' : '' }}" data-domain="accounts" title="Accounting: General Ledger & Payables">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon">
                                <i class="mdi mdi-calculator"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    General Ledger & Payables
                                    @if($isAccounts && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
                                    <span class="active-dot"></span>
                                </div>
                                <div class="dash-pipe-sub">Journal Entries • AP • Chart of Accounts</div>
                            </div>
                        </div>
                        <div class="dash-pipe-right">
                            <span class="dash-pipe-count badge-zero" id="pipe-accounts-count">0</span>
                            <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                        </div>
                    </div>
                @endif

                {{-- ── Tier 9: Human Resources & Workforce ── --}}
                @if($isAdmin || $isHr)
                    <div class="dash-pipe-section-head">
                        <span>Human Resources</span>
                        <i class="mdi mdi-account-group"></i>
                    </div>

                    {{-- HR & Leave Roster --}}
                    <div class="dash-pipeline-item pipe-hr {{ !$isAdmin && $isHr ? 'active-pipeline' : '' }}" data-domain="hr" title="HR: Personnel, Leave & Shifts">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon">
                                <i class="mdi mdi-account-group"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    Workforce & Leave Roster
                                    @if($isHr && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
                                    <span class="active-dot"></span>
                                </div>
                                <div class="dash-pipe-sub">Leave Requests • Staff Registry • Payroll</div>
                            </div>
                        </div>
                        <div class="dash-pipe-right">
                            <span class="dash-pipe-count badge-zero" id="pipe-leave-count">0</span>
                            <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                        </div>
                    </div>
                @endif

                {{-- ── Tier 10: Clinical Governance & Internal Audit ── --}}
                @if($isAdmin || $isAudit)
                    <div class="dash-pipe-section-head">
                        <span>Internal Audit & Governance</span>
                        <i class="mdi mdi-shield-check-outline"></i>
                    </div>

                    {{-- Audit Exceptions Ledger --}}
                    <div class="dash-pipeline-item pipe-audit {{ !$isAdmin && $isAudit ? 'active-pipeline' : '' }}" data-domain="audit" title="Audit: Compliance & Exceptions">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon">
                                <i class="mdi mdi-shield-check-outline"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    Audit & Ops Compliance
                                    @if($isAudit && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
                                    <span class="active-dot"></span>
                                </div>
                                <div class="dash-pipe-sub">Ops Audit • Cash Remittances • Census</div>
                            </div>
                        </div>
                        <div class="dash-pipe-right">
                            <span class="dash-pipe-count badge-zero" id="pipe-audit-count">0</span>
                            <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                        </div>
                    </div>
                @endif

                {{-- ── Tier 11: Mortuary Services ── --}}
                @if($isAdmin || $isMorgue)
                    <div class="dash-pipe-section-head">
                        <span>Mortuary Services</span>
                        <i class="mdi mdi-emoticon-dead"></i>
                    </div>

                    {{-- Morgue Census --}}
                    <div class="dash-pipeline-item pipe-morgue {{ !$isAdmin && $isMorgue ? 'active-pipeline' : '' }}" data-domain="morgue" title="Morgue: Deceased Intake & Census">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon">
                                <i class="mdi mdi-emoticon-dead"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    Mortuary Census
                                    @if($isMorgue && !$isAdmin)<span class="dash-role-badge">MY ROLE</span>@endif
                                    <span class="active-dot"></span>
                                </div>
                                <div class="dash-pipe-sub">Admissions • Cold Storage • Releases</div>
                            </div>
                        </div>
                        <div class="dash-pipe-right">
                            <span class="dash-pipe-count badge-zero" id="pipe-morgue-count">0</span>
                            <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                        </div>
                    </div>
                @endif

                {{-- ── Tier 12: Staff Self-Service (ESS) ── --}}
                @if($isEss)
                    <div class="dash-pipe-section-head">
                        <span>Employee Self-Service</span>
                        <i class="mdi mdi-account-circle-outline"></i>
                    </div>

                    {{-- ESS Portal --}}
                    <div class="dash-pipeline-item pipe-ess" data-domain="ess" title="My Workstation: Shifts, Leave & Biodata">
                        <div class="dash-pipe-left">
                            <div class="dash-pipe-icon">
                                <i class="mdi mdi-account-circle-outline"></i>
                            </div>
                            <div class="dash-pipe-text">
                                <div class="dash-pipe-name">
                                    My Staff Portal
                                    <span class="active-dot"></span>
                                </div>
                                <div class="dash-pipe-sub">My Shift • Annual Leave • My Payslips</div>
                            </div>
                        </div>
                        <div class="dash-pipe-right">
                            <span class="dash-pipe-tag">Personal</span>
                            <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                        </div>
                    </div>
                @endif

            </div>
        </div>

        {{-- Card Footer: Dynamic Contextual Actions Dock (Strictly Gated by Sidebar Authority) --}}
        <div class="dash-card-footer flex-shrink-0">
            <div class="dash-pipe-actions">
                @if($isNurse && !$isAdmin)
                    <a href="{{ route('patientVitalsQueue') }}" class="dash-pipe-action-btn" title="Fast Vitals Triage Station">
                        <i class="mdi mdi-heart-pulse text-danger"></i>
                        <span>Fast Triage</span>
                    </a>
                    <a href="{{ route('nursing-workbench.index') }}" class="dash-pipe-action-btn" title="Nursing Shifts & Handovers">
                        <i class="mdi mdi-account-clock text-info"></i>
                        <span>Shift Mgmt</span>
                    </a>
                    <a href="{{ route('beds.index') }}" class="dash-pipe-action-btn" title="Ward Beds Census">
                        <i class="mdi mdi-hotel text-primary"></i>
                        <span>Bed Census</span>
                    </a>
                @elseif($isDoctor && !$isAdmin)
                    <a href="{{ route('encounters.create') }}" class="dash-pipe-action-btn" title="Start New Patient Consultation">
                        <i class="mdi mdi-stethoscope text-primary"></i>
                        <span>+ Consult</span>
                    </a>
                    <a href="{{ route('encounters.index') }}" class="dash-pipe-action-btn" title="Doctor Clinic Queues">
                        <i class="mdi mdi-clipboard-text-clock text-warning"></i>
                        <span>My Queues</span>
                    </a>
                    <a href="{{ route('surgery-workbench.index') }}" class="dash-pipe-action-btn" title="Theatre Schedule">
                        <i class="mdi mdi-scalpel text-danger"></i>
                        <span>Theatre</span>
                    </a>
                @elseif($isPharm && !$isAdmin)
                    <a href="{{ route('pharmacy.workbench') }}" class="dash-pipe-action-btn" title="Pharmacy Dispensary">
                        <i class="mdi mdi-pill text-purple"></i>
                        <span>Dispensary</span>
                    </a>
                    <a href="{{ route('products.index') }}" class="dash-pipe-action-btn" title="Drug Formulary">
                        <i class="mdi mdi-format-list-bulleted text-info"></i>
                        <span>Drug List</span>
                    </a>
                    <a href="{{ route('stores.index') }}" class="dash-pipe-action-btn" title="Dispensing Stores">
                        <i class="mdi mdi-store text-success"></i>
                        <span>Stores</span>
                    </a>
                @elseif($isStore && !$isAdmin)
                    <a href="{{ route('inventory.store-workbench.index') }}" class="dash-pipe-action-btn" title="Store Console">
                        <i class="mdi mdi-store-warehouse text-warning"></i>
                        <span>Store Console</span>
                    </a>
                    <a href="{{ route('inventory.requisitions.index') }}" class="dash-pipe-action-btn" title="Store Requisitions">
                        <i class="mdi mdi-clipboard-arrow-right text-primary"></i>
                        <span>Requisitions</span>
                    </a>
                    <a href="{{ route('inventory.purchase-orders.index') }}" class="dash-pipe-action-btn" title="Purchase Orders">
                        <i class="mdi mdi-truck-delivery text-success"></i>
                        <span>POs</span>
                    </a>
                @elseif($isAccounts && !$isAdmin)
                    <a href="{{ route('accounting.dashboard') }}" class="dash-pipe-action-btn" title="Accounting Dashboard">
                        <i class="mdi mdi-calculator text-success"></i>
                        <span>Accounts Hub</span>
                    </a>
                    <a href="{{ route('accounting.journal-entries.index') }}" class="dash-pipe-action-btn" title="Journal Entries">
                        <i class="mdi mdi-book-open-page-variant text-primary"></i>
                        <span>Journal</span>
                    </a>
                    <a href="{{ route('inventory.purchase-orders.accounts-payable') }}" class="dash-pipe-action-btn" title="Accounts Payable">
                        <i class="mdi mdi-currency-ngn text-danger"></i>
                        <span>Payables</span>
                    </a>
                @elseif($isHr && !$isAdmin)
                    <a href="{{ route('hr.workbench.index') }}" class="dash-pipe-action-btn" title="HR Workbench">
                        <i class="mdi mdi-account-group text-primary"></i>
                        <span>HR Console</span>
                    </a>
                    <a href="{{ route('hr.leave-requests.index') }}" class="dash-pipe-action-btn" title="Leave Applications">
                        <i class="mdi mdi-calendar-clock text-warning"></i>
                        <span>Leave Requests</span>
                    </a>
                    <a href="{{ route('hr.staff-registry.index') }}" class="dash-pipe-action-btn" title="Staff Personnel Registry">
                        <i class="mdi mdi-clipboard-list-outline text-purple"></i>
                        <span>Staff Registry</span>
                    </a>
                @elseif($isAudit && !$isAdmin)
                    <a href="{{ url('/audit-workbench') }}" class="dash-pipe-action-btn" title="Audit Workbench">
                        <i class="mdi mdi-shield-check-outline text-primary"></i>
                        <span>Audit Hub</span>
                    </a>
                    <a href="{{ route('ops-audit.reception') }}" class="dash-pipe-action-btn" title="Ops Audit Hub">
                        <i class="mdi mdi-clipboard-check-multiple text-teal"></i>
                        <span>Ops Audit</span>
                    </a>
                @elseif($isMorgue && !$isAdmin)
                    <a href="{{ route('morgue.workbench') }}" class="dash-pipe-action-btn" title="Morgue Workbench">
                        <i class="mdi mdi-emoticon-dead text-danger"></i>
                        <span>Morgue Console</span>
                    </a>
                    <a href="{{ route('services.index', ['category' => appsettings('morgue_category_id')]) }}" class="dash-pipe-action-btn" title="Morgue Services">
                        <i class="mdi mdi-cogs text-info"></i>
                        <span>Services</span>
                    </a>
                @elseif($isHmo && !$isAdmin)
                    <a href="{{ route('hmo.workbench') }}" class="dash-pipe-action-btn" title="HMO Claims Workbench">
                        <i class="mdi mdi-shield-check text-primary"></i>
                        <span>HMO Hub</span>
                    </a>
                    <a href="{{ route('hmo.reports') }}" class="dash-pipe-action-btn" title="HMO Claims Reports">
                        <i class="mdi mdi-file-chart text-teal"></i>
                        <span>Reports</span>
                    </a>
                    <a href="{{ route('patient.index', ['hmo_only' => 1]) }}" class="dash-pipe-action-btn" title="HMO Enrollees">
                        <i class="mdi mdi-account-group text-purple"></i>
                        <span>Enrollees</span>
                    </a>
                @elseif($isRecep && !$isAdmin)
                    <a href="{{ route('patient.create') }}" class="dash-pipe-action-btn" title="Register New Patient">
                        <i class="mdi mdi-account-plus text-success"></i>
                        <span>New Patient</span>
                    </a>
                    <a href="{{ route('appointments.list') }}" class="dash-pipe-action-btn" title="Clinic Appointments Roster">
                        <i class="mdi mdi-calendar-clock text-primary"></i>
                        <span>Appointments</span>
                    </a>
                    <a href="{{ route('add-to-queue') }}" class="dash-pipe-action-btn" title="Add Patient to Doctor Queue">
                        <i class="mdi mdi-clipboard-arrow-right text-warning"></i>
                        <span>Queue Intake</span>
                    </a>
                @elseif($isLab && !$isAdmin)
                    <a href="{{ route('lab.workbench') }}" class="dash-pipe-action-btn" title="Laboratory Diagnostics Workbench">
                        <i class="mdi mdi-flask text-teal"></i>
                        <span>Lab Hub</span>
                    </a>
                    <a href="{{ url('lab-workbench?tab=sample') }}" class="dash-pipe-action-btn" title="Specimen Collection Queue">
                        <i class="mdi mdi-test-tube text-info"></i>
                        <span>Phlebotomy</span>
                    </a>
                    <a href="{{ url('lab-workbench?tab=results') }}" class="dash-pipe-action-btn" title="Result Entry & Analysis">
                        <i class="mdi mdi-clipboard-edit text-warning"></i>
                        <span>Results</span>
                    </a>
                @elseif($isImaging && !$isAdmin)
                    <a href="{{ route('imaging.workbench') }}" class="dash-pipe-action-btn" title="Imaging & Radiology Workbench">
                        <i class="mdi mdi-radiology-box text-teal"></i>
                        <span>Imaging Hub</span>
                    </a>
                    <a href="{{ route('imaging.workbench') }}" class="dash-pipe-action-btn" title="Scan Requests">
                        <i class="mdi mdi-file-document-box-outline text-primary"></i>
                        <span>Scans</span>
                    </a>
                @elseif($isBiller && !$isAdmin)
                    <a href="{{ route('billing.workbench') }}" class="dash-pipe-action-btn" title="Billing & POS Workbench">
                        <i class="mdi mdi-cash-multiple text-success"></i>
                        <span>Billing Hub</span>
                    </a>
                    <a href="{{ route('billing.workbench') }}?tab=unpaid" class="dash-pipe-action-btn" title="Unpaid Invoices">
                        <i class="mdi mdi-cash-clock text-danger"></i>
                        <span>Unpaid Bills</span>
                    </a>
                    <a href="{{ route('billing.workbench') }}?tab=deposits" class="dash-pipe-action-btn" title="Patient Deposits">
                        <i class="mdi mdi-wallet-outline text-primary"></i>
                        <span>Deposits</span>
                    </a>
                @elseif($isEss && !$isAdmin)
                    <a href="{{ route('hr.ess.index') }}" class="dash-pipe-action-btn" title="Employee Self-Service Profile">
                        <i class="mdi mdi-account-circle-outline text-primary"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="{{ route('hr.ess.my-leave') }}" class="dash-pipe-action-btn" title="My Leave Applications">
                        <i class="mdi mdi-calendar-clock text-warning"></i>
                        <span>My Leave</span>
                    </a>
                    <a href="{{ route('hr.ess.my-roster') }}" class="dash-pipe-action-btn" title="My Duty Roster">
                        <i class="mdi mdi-clock-check-outline text-success"></i>
                        <span>My Shifts</span>
                    </a>
                @else
                    {{-- Default / Superadmin / Admin Context --}}
                    <a href="{{ route('hospital-config.index') }}" class="dash-pipe-action-btn" title="Hospital Configuration">
                        <i class="mdi mdi-cogs text-primary"></i>
                        <span>Hospital Config</span>
                    </a>
                    <a href="{{ route('roles.index') }}" class="dash-pipe-action-btn" title="Access Control & Roles">
                        <i class="mdi mdi-shield-account text-warning"></i>
                        <span>Access Control</span>
                    </a>
                    <a href="{{ route('ops-audit.reception') }}" class="dash-pipe-action-btn" title="Clinical Governance & Audit">
                        <i class="mdi mdi-shield-check text-teal"></i>
                        <span>Ops Audit</span>
                    </a>
                @endif
            </div>
        </div>

    </div>
</div>

