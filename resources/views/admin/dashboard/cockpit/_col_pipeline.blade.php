{{-- ==========================================================================
     Column 1: Operational Pipeline & Triage Queues (28% Desktop Width)
     Space-Maximizing Zero-Scroll Clinical Flow
     ========================================================================== --}}
<div class="dash-column dash-col-pipeline mobile-active">
    <div class="dash-card h-100">
        {{-- Card Header --}}
        <div class="dash-card-header">
            <div class="dash-card-title">
                <i class="mdi mdi-ray-start-arrow text-primary"></i>
                <span>Operational Pipeline</span>
            </div>
            <span class="dash-card-badge" id="pipe-total-label">Active Flow</span>
        </div>

        {{-- Card Body: Micro-Scrollable Queue List --}}
        <div class="dash-card-body dash-scrollable">
            <div class="dash-pipeline-list">

                {{-- 1. Reception: Waiting Queue --}}
                <div class="dash-pipeline-item pipe-waiting" data-role="receptionist" data-href="{{ route('reception.workbench', ['queue_filter' => 'waiting']) }}" title="Reception: Waiting for Triage">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-clock-outline"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Waiting for Triage</div>
                            <div class="dash-pipe-sub">Reception Check-in • Records</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-waiting-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 2. Nursing: Vitals Queue --}}
                <div class="dash-pipeline-item pipe-vitals" data-role="nursing" data-href="{{ route('nursing-workbench.index', ['queue_filter' => 'vitals']) }}" title="Nursing: Vitals Pending">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-heart-pulse"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Vitals Pending</div>
                            <div class="dash-pipe-sub">Triage Station • Nursing</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-vitals-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 3. Doctor: Consult Queue (Deep link with status=waiting) --}}
                <div class="dash-pipeline-item pipe-consult" data-role="doctor" data-href="{{ route('encounters.index', ['status' => 'waiting']) }}" title="Doctor: Waiting Consultation">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-stethoscope"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Doctor Consultation</div>
                            <div class="dash-pipe-sub">Waiting for Doctor • Clinics</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-consult-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 4. Pharmacy: Dispense Queue --}}
                <div class="dash-pipeline-item pipe-dispense" data-role="pharmacy" data-href="{{ route('pharmacy.workbench', ['queue_filter' => 'unbilled']) }}" title="Pharmacy: Unbilled Prescriptions">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-pill"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Pharmacy Dispense</div>
                            <div class="dash-pipe-sub">Prescriptions • Dispensary</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-dispense-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 5. Lab: Diagnostics Queue --}}
                <div class="dash-pipeline-item pipe-lab" data-role="lab" data-href="{{ route('lab.workbench', ['queue_filter' => 'sample']) }}" title="Laboratory: Pending Sampling">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-flask-outline"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Laboratory Orders</div>
                            <div class="dash-pipe-sub">Sampling & Diagnostics</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-lab-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 6. Radiology: Imaging Queue --}}
                <div class="dash-pipeline-item pipe-imaging" data-role="imaging" data-href="{{ route('imaging.workbench', ['queue_filter' => 'results']) }}" title="Radiology: Pending Results">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-radioactive"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Radiology & Scans</div>
                            <div class="dash-pipe-sub">X-Ray, Ultrasound, CT</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-imaging-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 7. Surgery / Theatre Queue --}}
                <div class="dash-pipeline-item pipe-theatre" data-role="theatre" data-href="{{ route('surgery-workbench.index') }}" title="Surgery: Theatre Procedures">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-scalpel"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Operating Theatre</div>
                            <div class="dash-pipe-sub">Procedures Scheduled / Pending</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-surgery-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 8. Maternity: Labour & ANC Queue --}}
                <div class="dash-pipeline-item pipe-maternity" data-role="maternity" data-href="{{ route('maternity-workbench.index') }}" title="Maternity: ANC & Labour">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-mother-nurse"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Maternity & ANC</div>
                            <div class="dash-pipe-sub">Antenatal & Labour Cases</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-maternity-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 9. Inpatient Wards: Admitted Patients --}}
                <div class="dash-pipeline-item pipe-admitted" data-role="inpatient" data-href="{{ route('beds.index') }}" title="Wards: Admitted Inpatients">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-hotel"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Admitted Inpatients</div>
                            <div class="dash-pipe-sub">Ward Beds • Clinical Care</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-admitted-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 10. Store: Inventory Requisitions --}}
                <div class="dash-pipeline-item pipe-store" data-role="store" data-href="{{ route('inventory.requisitions.index') }}" title="Store: Internal Requisitions">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-swap-horizontal"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Store Requisitions</div>
                            <div class="dash-pipe-sub">Internal Stock Requests</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-store-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 11. Billing: Unpaid Invoices --}}
                <div class="dash-pipeline-item pipe-billing" data-role="biller" data-href="{{ route('billing.workbench', ['queue_filter' => 'all']) }}" title="Billing: Unpaid Invoices">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-cash-register"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Unpaid Invoices</div>
                            <div class="dash-pipe-sub">Cashier • Pending Settlement</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-unpaid-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 12. HMO: Claims & Pre-Auth --}}
                <div class="dash-pipeline-item pipe-hmo" data-role="hmo" data-href="{{ route('hmo.workbench', ['queue_filter' => 'pre-auth']) }}" title="HMO: Claims & Pre-Auth">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-hospital-building"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">HMO Pre-Auth & Claims</div>
                            <div class="dash-pipe-sub">Managed Care Approvals</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-hmo-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 13. HR: Leave Approvals --}}
                <div class="dash-pipeline-item pipe-hr" data-role="hr" data-href="{{ route('hr.leave-requests.index') }}" title="HR: Leave Approvals">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-calendar-check"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Staff Leave Requests</div>
                            <div class="dash-pipe-sub">HR Approvals & Relief</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-leave-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

                {{-- 14. Morgue: Mortuary Admissions --}}
                <div class="dash-pipeline-item pipe-morgue" data-role="morgue" data-href="{{ route('morgue.workbench') }}" title="Morgue: Active Custody">
                    <div class="dash-pipe-left">
                        <div class="dash-pipe-icon">
                            <i class="mdi mdi-emoticon-dead"></i>
                        </div>
                        <div class="dash-pipe-text">
                            <div class="dash-pipe-name">Mortuary Admissions</div>
                            <div class="dash-pipe-sub">Active Morgue Custody</div>
                        </div>
                    </div>
                    <div class="dash-pipe-right">
                        <span class="dash-pipe-count badge-zero" id="pipe-morgue-count">0</span>
                        <i class="mdi mdi-chevron-right dash-pipe-arrow"></i>
                    </div>
                </div>

            </div>
        </div>

        {{-- Column Footer: Quick Actions Dock --}}
        <div class="dash-quick-dock">
            <a href="{{ route('reception.workbench', ['tab' => 'appointments']) }}" class="dash-dock-btn" title="View & Book Appointments">
                <i class="mdi mdi-calendar-plus text-primary"></i> + Book Appt
            </a>
            <a href="{{ route('nursing-workbench.index', ['queue_filter' => 'vitals']) }}" class="dash-dock-btn" title="Fast Nursing Triage Station">
                <i class="mdi mdi-heart-pulse text-info"></i> Fast Triage
            </a>
            <a href="{{ route('billing.workbench', ['action' => 'shift']) }}" class="dash-dock-btn" title="Shift & Cashier Management">
                <i class="mdi mdi-cash-register text-success"></i> Shift Mgmt
            </a>
        </div>
    </div>
</div>
