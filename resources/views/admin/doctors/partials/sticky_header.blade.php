@push('styles')
<style>
    :root {
        --sch-color: {{ appsettings('hos_color', '#007bff') }};
    }

    .sticky-consultation-header {
        position: sticky;
        top: 60px;
        z-index: 990;
        background: #ffffff;
        border-bottom: 2px solid var(--sch-color);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 0.75rem;
        border-radius: 0.5rem;
        overflow: hidden;
    }

    @media (max-width: 991px) {
        .sticky-consultation-header {
            top: 55px;
            z-index: 990;
        }
    }

    /* ═══ COMPACT BAR (always visible) ═══ */
    .sch-compact-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        background: linear-gradient(135deg, var(--sch-color) 0%, color-mix(in srgb, var(--sch-color) 70%, teal) 100%);
        color: #fff;
    }

    .sch-compact-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255,255,255,0.7);
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        flex-shrink: 0;
    }

    .sch-compact-avatar-placeholder {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .sch-compact-identity {
        flex: 1;
        min-width: 0;
    }

    .sch-compact-name {
        font-size: 1.05rem;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }

    .sch-compact-name .file-no {
        font-weight: 600;
        font-size: 0.82rem;
        opacity: 0.85;
        margin-left: 6px;
    }

    .sch-compact-sub {
        font-size: 0.78rem;
        opacity: 0.9;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 1px;
    }

    /* Compact bar — icon-only action buttons (shown when COLLAPSED on desktop) */
    .sch-compact-actions {
        display: flex;
        align-items: center;
        gap: 5px;
        flex-shrink: 0;
    }

    .sch-compact-actions .btn {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        border: none;
        letter-spacing: 0.2px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        transition: transform 0.15s ease, filter 0.15s ease;
    }

    .sch-compact-actions .btn:hover {
        transform: translateY(-1px);
        filter: brightness(1.1);
    }

    .sch-expand-toggle {
        background: rgba(255,255,255,0.18);
        border: 1px solid rgba(255,255,255,0.45);
        border-radius: 4px;
        color: #fff;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 3px 8px;
        cursor: pointer;
        transition: all 0.25s ease;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .sch-expand-toggle:hover {
        background: rgba(255,255,255,0.3);
        border-color: #fff;
    }

    .sch-expand-toggle .fa-chevron-down {
        font-size: 0.85rem;
        transition: transform 0.25s ease;
    }

    .sch-expand-toggle.expanded .fa-chevron-down {
        transform: rotate(180deg);
    }

    /* ═══ EXPANDED DETAILS PANEL ═══ */
    .sch-details-panel {
        display: none;
        background: #fff;
        padding: 10px 14px 10px;
        border-top: 1px solid rgba(0,0,0,0.07);
    }

    .sch-details-panel.open {
        display: block;
        animation: schSlideDown 0.2s ease;
    }

    @keyframes schSlideDown {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .sch-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px 16px;
        margin-bottom: 10px;
    }

    .sch-details-section-title {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--sch-color);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* Demographics badges */
    .sch-badge-compact {
        font-size: 10pt;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 4px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        color: #495057;
        line-height: 1.2;
    }
    .sch-badge-compact i { font-size: 10pt; }
    .sch-badge-compact.hos-accent {
        border-color: var(--sch-color);
        background: color-mix(in srgb, var(--sch-color) 8%, white);
        color: var(--sch-color);
    }

    .sch-demographics-compact {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
    }

    /* Vitals */
    .sch-vitals-row {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .sch-vital-pill {
        font-size: 10pt;
        font-weight: 600;
        color: #343a40;
        padding: 2px 6px;
        background: #f1f3f5;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        border: 1px solid #e9ecef;
    }

    /* Allergies */
    .sch-allergies-list {
        display: flex;
        gap: 3px;
        flex-wrap: wrap;
    }

    /* Admin lines */
    .sch-admin-line {
        margin: 0;
        line-height: 1.4;
        font-size: 10pt;
        color: #495057;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sch-admin-line i {
        color: var(--sch-color);
        width: 14px;
        text-align: center;
    }

    /* Alerts row */
    .sch-row-alerts {
        padding: 6px 14px;
        border-top: 1px solid #f8d7da;
        background: #fff9f9;
        font-size: 0.8rem;
    }
    .sch-row-alerts:empty { display: none; }

    /* Full action buttons row inside expanded panel */
    .sch-expanded-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        padding: 8px 0 2px;
        border-top: 1px solid #e9ecef;
    }

    .sch-expanded-actions .btn {
        font-size: 10pt;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    @media (max-width: 767.98px) {
        .sch-compact-bar { padding: 8px 12px; }
        .sch-compact-actions { display: none !important; }
        .sch-details-grid { grid-template-columns: 1fr; }
        .sch-expanded-actions { gap: 4px; }
        .sch-expanded-actions .btn { font-size: 9pt; padding: 3px 7px; }
    }
</style>
@endpush

@php
    $hosColor = appsettings('hos_color', '#007bff');
    $dob      = \Carbon\Carbon::parse($patient->dob);
    $ageDiff  = $dob->diff(now());
    $ageString = $ageDiff->format('%y Yrs %m Mos %d Days');
    $ageShort  = $ageDiff->y . 'y';
    $allergies = [];
    if (!empty($patient->allergies)) {
        $allergies = is_string($patient->allergies)
            ? (json_decode($patient->allergies, true) ?? explode(',', $patient->allergies))
            : $patient->allergies;
    }
@endphp

<div class="sticky-consultation-header" style="--sch-color: {{ $hosColor }}; --hos-color: {{ $hosColor }};">

    {{-- ═══ ALWAYS-VISIBLE COMPACT BAR ═══ --}}
    <div class="sch-compact-bar">

        {{-- Avatar --}}
        @if($patient->user->filename)
            <img src="{!! url('storage/image/user/' . $patient->user->filename) !!}" class="sch-compact-avatar" alt="Patient" />
        @else
            <div class="sch-compact-avatar-placeholder"><i class="fa fa-user"></i></div>
        @endif

        {{-- Identity --}}
        <div class="sch-compact-identity">
            <div class="sch-compact-name">
                {{ userfullname($patient->user->id) }}
                <span class="file-no">#{{ $patient->file_no }}</span>
            </div>
            <div class="sch-compact-sub">
                <span>{{ $ageShort }}</span>
                <span class="mx-1">·</span>
                <span>{{ ucfirst($patient->gender ?? 'N/A') }}</span>
                <span class="mx-1">·</span>
                <span>{{ $patient->blood_group ?? 'N/A' }}/{{ $patient->genotype ?? 'N/A' }}</span>
                <span class="mx-1">·</span>
                <span>{{ $patient->hmo->name ?? 'Private' }}</span>
            </div>
        </div>

        {{-- Compact timer (synced from main timer, shown when header is collapsed) --}}
        <div id="sch-compact-timer" style="font-size:0.68rem; font-family:monospace; font-weight:700; opacity:0.9; display:none; align-items:center; gap:3px;">
            <i class="mdi mdi-timer-outline"></i><span id="sch-compact-timer-display">00:00</span>
        </div>

        {{-- Compact action buttons — ONLY shown when panel is COLLAPSED --}}
        <div class="sch-compact-actions" id="sch-compact-actions">
            @if (isset($admission_request))
                @if(!$admission_request->discharged && $admission_request->admission_status !== 'discharge_requested')
                    <button type="button" class="btn btn-warning text-dark" onclick="openDischargeModal()" title="Discharge">
                        <i class="fa fa-sign-out-alt"></i><span>Discharge</span>
                    </button>
                @endif
            @else
                <button type="button" class="btn btn-info text-white" onclick="openAdmitModal()" title="Admit">
                    <i class="fa fa-bed"></i><span>Admit</span>
                </button>
            @endif
            <button type="button" class="btn btn-danger text-white btn-manage-alerts" data-patient-id="{{ $patient->id }}" title="Alerts">
                <i class="mdi mdi-alert-octagon"></i><span>Alerts</span>
            </button>
            <button type="button" class="btn btn-secondary text-white" onclick="openReportBuilder()" title="Report">
                <i class="mdi mdi-file-document"></i><span>Report</span>
            </button>
            <button type="button" class="btn btn-primary text-white" onclick="switch_tab(event, 'referrals_tab'); setTimeout(function(){ if($('#referral-form-card').hasClass('d-none')) $('#toggle-referral-form-btn').click(); }, 300);" title="Refer">
                <i class="mdi mdi-account-switch"></i><span>Refer</span>
            </button>
            <button type="button" class="btn btn-success text-white" onclick="$('#concludeEncounterModal').modal('show')" title="Conclude">
                <i class="fa fa-check-circle"></i><span>Conclude</span>
            </button>
        </div>

        {{-- Expand/Collapse toggle --}}
        <button class="sch-expand-toggle" id="sch-expand-btn" onclick="toggleSchDetails()" title="Show / hide details">
            <span id="sch-expand-text">Details</span>
            <i class="fa fa-chevron-down"></i>
        </button>
    </div>

    {{-- ═══ COLLAPSIBLE DETAILS PANEL ═══ --}}
    <div class="sch-details-panel" id="sch-details-panel">

        <div class="sch-details-grid">

            {{-- Patient Info --}}
            <div>
                <div class="sch-details-section-title"><i class="fa fa-id-card"></i> Patient Info</div>
                <div class="sch-demographics-compact">
                    <span class="sch-badge-compact hos-accent" title="Date of Birth & Age">
                        <i class="fa fa-birthday-cake"></i>
                        {{ $dob->format('M j, Y') }} ({{ $ageString }})
                    </span>
                    <span class="sch-badge-compact" title="Gender">
                        <i class="fa fa-venus-mars text-primary"></i>
                        {{ ucfirst($patient->gender ?? 'N/A') }}
                    </span>
                    <span class="sch-badge-compact" title="Blood Group / Genotype">
                        <i class="fa fa-tint text-danger"></i>
                        {{ $patient->blood_group ?? 'N/A' }} / {{ $patient->genotype ?? 'N/A' }}
                    </span>
                    <span class="sch-badge-compact" title="HMO/Insurance">
                        <i class="fa fa-shield-alt text-success"></i>
                        {{ $patient->hmo->name ?? 'Private' }}
                    </span>
                </div>
            </div>

            {{-- Vitals --}}
            <div>
                <div class="sch-details-section-title"><i class="fa fa-heartbeat"></i> Vitals</div>
                <div class="sch-vitals-row" id="sticky-vitals-container">
                    <span class="sch-vital-pill" id="sticky-vital-temp" title="Temperature"><i class="fa fa-thermometer-half text-danger"></i> --</span>
                    <span class="sch-vital-pill" id="sticky-vital-bp" title="Blood Pressure"><i class="fa fa-heart text-danger"></i> --</span>
                    <span class="sch-vital-pill" id="sticky-vital-wt" title="Weight"><i class="fa fa-weight text-primary"></i> --</span>
                    <span class="sch-vital-pill" id="sticky-vital-hr" title="Heart Rate"><i class="fa fa-stethoscope text-success"></i> --</span>
                </div>
            </div>

            {{-- Allergies --}}
            <div>
                <div class="sch-details-section-title">
                    <i class="fa fa-allergies"></i> Allergies
                    <button class="btn btn-sm btn-link p-0 ms-1" style="font-size:0.65rem;color:var(--sch-color);line-height:1;" onclick="promptAddAllergy({{ $patient->id }})" title="Add Allergy"><i class="fa fa-plus-circle"></i></button>
                </div>
                <div class="sch-allergies-list" id="sticky-allergies-container">
                    @if(count($allergies) > 0)
                        @foreach($allergies as $allergy)
                            <span class="badge bg-danger" style="font-size:10pt; padding:2px 5px;">{{ trim($allergy) }}</span>
                        @endforeach
                    @else
                        <span class="text-muted" style="font-size:10pt;">None known</span>
                    @endif
                </div>
            </div>

            {{-- NOK & Admission --}}
            <div>
                <div class="sch-details-section-title"><i class="fa fa-users"></i> NOK &amp; Admission</div>
                <p class="sch-admin-line" title="Next of Kin">
                    <i class="fa fa-users"></i> <strong>{{ $patient->next_of_kin_name ?? 'N/A' }}</strong>&nbsp;{{ $patient->next_of_kin_phone ?? 'N/A' }}
                </p>
                <p class="sch-admin-line" title="Admission Status">
                    <i class="fa fa-bed"></i>
                    @if (isset($admission_request))
                        @if($admission_request->admission_status === 'pending_checklist')
                            <span class="badge bg-info text-dark" style="font-size:10pt;">Pending Checklist</span>
                        @elseif($admission_request->admission_status === 'checklist_complete')
                            <span class="badge bg-primary" style="font-size:10pt;">Awaiting Bed</span>
                        @elseif($admission_request->admission_status === 'admitted')
                            <span class="badge bg-success" style="font-size:10pt;">Admitted ({{ $admission_request->bed ? $admission_request->bed->name : 'No Bed' }})</span>
                        @elseif($admission_request->admission_status === 'discharge_requested')
                            <span class="badge bg-warning text-dark" style="font-size:10pt;">Discharge Req</span>
                        @elseif($admission_request->discharged)
                            <span class="badge bg-secondary" style="font-size:10pt;">Discharged</span>
                        @else
                            <span class="badge bg-secondary" style="font-size:10pt;">{{ ucfirst(str_replace('_', ' ', $admission_request->admission_status)) }}</span>
                        @endif
                    @else
                        <span class="text-muted" style="font-size:10pt;">Not Admitted</span>
                    @endif
                </p>
                <p class="sch-admin-line">
                    @if ($patient->user->old_records)
                        <a href="{!! url('storage/image/user/old_records/' . $patient->user->old_records) !!}" target="_blank" style="font-size:10pt; text-decoration:none; color:var(--sch-color);">
                            <i class="fa fa-file-pdf"></i> View Old Records
                        </a>
                    @else
                        <span class="text-muted" style="font-size:10pt;"><i class="fa fa-file-excel"></i> No Old Records</span>
                    @endif
                </p>
            </div>

        </div>{{-- /.sch-details-grid --}}

        {{-- Full action buttons row (moved in from compact bar when expanded) --}}
        <div class="sch-expanded-actions">
            {{-- Timer --}}
            @include('admin.doctors.partials.consultation_timer')

            {{-- All buttons with original Bootstrap styling --}}
            @if (isset($admission_request))
                @if(!$admission_request->discharged && $admission_request->admission_status !== 'discharge_requested')
                    <button type="button" class="btn btn-warning d-flex align-items-center shadow-sm" onclick="openDischargeModal()">
                        <i class="fa fa-sign-out-alt me-1"></i> Discharge
                    </button>
                @endif
            @else
                <button type="button" class="btn btn-info text-white d-flex align-items-center shadow-sm" onclick="openAdmitModal()">
                    <i class="fa fa-bed me-1"></i> Admit
                </button>
            @endif

            <button type="button" class="btn btn-danger text-white d-flex align-items-center shadow-sm btn-manage-alerts" data-patient-id="{{ $patient->id }}">
                <i class="mdi mdi-alert-octagon me-1"></i> Alerts
            </button>
            <button type="button" class="btn btn-secondary text-white d-flex align-items-center shadow-sm" onclick="openReportBuilder()">
                <i class="mdi mdi-file-document me-1"></i> Report
            </button>
            <button type="button" class="btn btn-primary text-white d-flex align-items-center shadow-sm" onclick="switch_tab(event, 'referrals_tab'); setTimeout(function(){ if($('#referral-form-card').hasClass('d-none')) $('#toggle-referral-form-btn').click(); }, 300);">
                <i class="mdi mdi-account-switch me-1"></i> Refer
            </button>
            <button type="button" class="btn btn-success text-white d-flex align-items-center shadow-sm" onclick="$('#concludeEncounterModal').modal('show')">
                <i class="fa fa-check-circle me-1"></i> Conclude
            </button>
        </div>

    </div>{{-- /.sch-details-panel --}}

    {{-- ═══ ALERTS ROW ═══ --}}
    <div class="sch-row-alerts sticky-header-alerts">
        <!-- JS will inject alerts here -->
    </div>

    {{-- ═══ ACTIVE PLAN CONTEXT BAR ═══ --}}
    @include('admin.partials.active_plan_context_bar')

</div>

<script>
    function toggleSchDetails() {
        var panel       = document.getElementById('sch-details-panel');
        var btn         = document.getElementById('sch-expand-btn');
        var textEl      = document.getElementById('sch-expand-text');
        var compactBtns = document.getElementById('sch-compact-actions');
        var isOpen      = panel.classList.contains('open');

        panel.classList.toggle('open', !isOpen);
        btn.classList.toggle('expanded', !isOpen);

        if (textEl) {
            textEl.textContent = !isOpen ? 'Less' : 'Details';
        }

        // Hide compact action buttons on desktop when expanded (they live in the panel instead)
        // Show them again when collapsed (desktop only)
        if (compactBtns && window.innerWidth >= 768) {
            compactBtns.style.display = !isOpen ? 'none' : 'flex';
        }

        try { localStorage.setItem('schDetailsOpen', !isOpen ? '1' : '0'); } catch(e){}
    }

    document.addEventListener('DOMContentLoaded', function() {
        var saved       = localStorage.getItem('schDetailsOpen');
        var defaultOpen = window.innerWidth >= 768; // open on desktop, closed on mobile
        var shouldOpen  = saved !== null ? saved === '1' : defaultOpen;

        if (shouldOpen) {
            var panel       = document.getElementById('sch-details-panel');
            var btn         = document.getElementById('sch-expand-btn');
            var textEl      = document.getElementById('sch-expand-text');
            var compactBtns = document.getElementById('sch-compact-actions');
            if (panel)       panel.classList.add('open');
            if (btn)         btn.classList.add('expanded');
            if (textEl)      textEl.textContent = 'Less';
            if (compactBtns && window.innerWidth >= 768) compactBtns.style.display = 'none';
        }

        // Mirror compact timer display
        setInterval(function() {
            var mainDisplay = document.getElementById('timer-display');
            var compactContainer = document.getElementById('sch-compact-timer');
            var compactDisplay   = document.getElementById('sch-compact-timer-display');
            if (mainDisplay && compactDisplay) {
                var text = mainDisplay.textContent;
                if (text && text !== '00:00:00') {
                    compactDisplay.textContent = text;
                    if (compactContainer) compactContainer.style.display = 'inline-flex';
                }
            }
        }, 1000);
    });
</script>
