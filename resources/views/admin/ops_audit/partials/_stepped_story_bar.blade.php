{{--
    CoreHealth v2 — Stepped Storyboard Ribbon Partial
    Progressive 4-Step Architecture:
    1. Time Horizon -> 2. Duty Window -> 3. Advanced Scoping -> 4. Audit Cockpit Actions
--}}
<form id="ops_audit_filter_form" class="ops-story-ribbon mb-3">
    <div class="row g-2 align-items-stretch">
        {{-- Step 1: Time Horizon (Audit Period) --}}
        <div class="col-xl-3 col-lg-3 col-md-6">
            <div class="ops-step-card">
                <div class="ops-step-header">
                    <span class="ops-step-title">
                        <span class="ops-step-num">1</span> Time Horizon
                    </span>
                    <span class="ops-step-subtext">Audit Period</span>
                </div>
                <div class="ops-date-chips">
                    <button type="button" class="ops-chip" data-preset="today">Today</button>
                    <button type="button" class="ops-chip" data-preset="yesterday">Yest</button>
                    <button type="button" class="ops-chip" data-preset="7d">7 Days</button>
                    <button type="button" class="ops-chip active" data-preset="30d">30 Days</button>
                    <button type="button" class="ops-chip" data-preset="month">This Month</button>
                </div>
                <div class="row g-1 mt-1">
                    <div class="col-6">
                        <label class="small text-muted mb-0">From</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate ?? now()->subDays(30)->format('Y-m-d') }}">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted mb-0">To</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate ?? now()->format('Y-m-d') }}">
                    </div>
                </div>
                <div class="text-muted text-truncate mt-1" style="font-size: 0.69rem;" id="opsHorizonContext">
                    <i class="mdi mdi-calendar-range me-1 text-primary"></i><span id="opsHorizonContextText">Audit Period (Calendar Span)</span>
                </div>
            </div>
        </div>

        {{-- Step 2: Duty Window (Shift & Stream) --}}
        <div class="col-xl-3 col-lg-3 col-md-6">
            <div class="ops-step-card">
                <div class="ops-step-header">
                    <span class="ops-step-title">
                        <span class="ops-step-num">2</span> Duty Window
                    </span>
                    <span class="ops-step-subtext" id="opsShiftRangeBadge">Full Day (24h)</span>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="small text-muted mb-0 fw-bold">Shift Preset</label>
                        <span class="text-primary font-monospace fw-bold" id="opsShiftTimeDisplay" style="font-size: 0.72rem;">00:00 - 23:59</span>
                    </div>
                    <div class="ops-shift-chips d-flex gap-1 flex-wrap mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary shift-btn active btn-primary" data-shift="all" title="Full Day (24 Hours)">All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary shift-btn" data-shift="morning" title="Morning Duty (00:00 - 12:59)"><i class="mdi mdi-weather-sunny"></i> AM <small class="text-muted ms-0.5">00-13h</small></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary shift-btn" data-shift="afternoon" title="Afternoon Duty (13:00 - 17:59)"><i class="mdi mdi-weather-partly-cloudy"></i> PM <small class="text-muted ms-0.5">13-18h</small></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary shift-btn" data-shift="night" title="Night Duty (18:00 - 07:59 overnight)"><i class="mdi mdi-weather-night"></i> Night <small class="text-muted ms-0.5">18-08h</small></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary shift-btn d-none" data-shift="custom" id="btnShiftCustom" title="Custom Time Window"><i class="mdi mdi-clock-edit-outline"></i> Custom</button>
                    </div>

                    {{-- Default Visible Custom Time Range Inputs --}}
                    <div class="row g-1 align-items-center mb-1 ops-time-range-row">
                        <div class="col-6">
                            <label class="small text-muted mb-0" style="font-size: 0.72rem;">Time From</label>
                            <input type="time" name="shift_start" id="ops_shift_start" class="form-control form-control-sm ops-time-input" value="{{ request('shift_start') }}" step="60" title="Custom start time (HH:MM)">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted mb-0" style="font-size: 0.72rem;">Time To</label>
                            <input type="time" name="shift_end" id="ops_shift_end" class="form-control form-control-sm ops-time-input" value="{{ request('shift_end') }}" step="60" title="Custom end time (HH:MM)">
                        </div>
                    </div>
                    <div class="text-muted text-truncate mb-1" style="font-size: 0.69rem;" id="opsShiftContext">
                        <i class="mdi mdi-clock-outline me-1 text-primary"></i><span id="opsShiftContextText">Applies to every day in period</span>
                    </div>
                    <input type="hidden" name="shift" id="ops_shift_input" value="{{ request('shift', 'all') }}">
                </div>
                @if($hasCareStream ?? true)
                <div class="mt-2 pt-1 border-top">
                    <label class="small text-muted d-block mb-1 fw-bold">Care Stream</label>
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" class="btn btn-primary care-stream-btn active" data-stream="all">All</button>
                        <button type="button" class="btn btn-outline-secondary care-stream-btn" data-stream="general">General</button>
                        <button type="button" class="btn btn-outline-secondary care-stream-btn" data-stream="inpatient">Inpatient</button>
                        <button type="button" class="btn btn-outline-secondary care-stream-btn" data-stream="nhis" title="Insured Patients Pool">Insured</button>
                    </div>
                    <input type="hidden" name="care_stream" value="all">
                </div>
                @endif
            </div>
        </div>

        {{-- Step 3: Advanced Scoping --}}
        <div class="col-xl-4 col-lg-4 col-md-6">
            <div class="ops-step-card">
                <div class="ops-step-header">
                    <span class="ops-step-title">
                        <span class="ops-step-num">3</span> Advanced Scoping
                    </span>
                    <span class="badge bg-primary bg-opacity-10 text-primary d-none" id="opsScopeCountBadge"></span>
                </div>
                <div class="row g-2 align-items-center">
                    {{ $slot ?? '' }}
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                    <button type="button" class="btn-clear-scopes d-none" id="btnOpsClearScopes" onclick="opsClearScopes()">
                        <i class="mdi mdi-close me-1"></i> Clear Scopes
                    </button>
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3 ms-auto" id="btnApplyFilters">
                        <i class="mdi mdi-filter me-1"></i> Apply
                    </button>
                </div>
            </div>
        </div>

        {{-- Step 4: Audit Cockpit Actions --}}
        <div class="col-xl-2 col-lg-2 col-md-6">
            <div class="ops-step-card bg-light">
                <div class="ops-step-header">
                    <span class="ops-step-title">
                        <span class="ops-step-num">4</span> Cockpit Flow
                    </span>
                    <span class="ops-step-subtext">Actions</span>
                </div>
                <div class="d-flex flex-column gap-1.5 justify-content-center h-100 py-1">
                    <button type="button" class="btn btn-sm btn-stamp-page w-100" id="btnStampVisiblePage" onclick="stampCurrentPage()" title="Stamp all unstamped records currently visible on this page">
                        <i class="mdi mdi-check-decagram"></i> Stamp Page (<span class="ops-page-count">0</span>)
                    </button>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-success font-weight-bold flex-fill" onclick="openUniversalStampModal('bulk')" title="Bulk stamp records matching current filter query across all pages">
                            <i class="mdi mdi-check-all me-1"></i> Bulk
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info font-weight-bold flex-fill" onclick="printCurrentTab()" title="Print current view">
                            <i class="mdi mdi-printer me-1"></i> Print
                        </button>
                    </div>
                    @if(isset($extraActions))
                        {!! $extraActions !!}
                    @endif
                </div>
            </div>
        </div>

        {{-- Live Audit Scope Narrative & Operational Context Banner --}}
        <div class="col-12 mt-2 pt-2 border-top">
            <div class="ops-context-banner d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="ops-context-badge">
                        <i class="mdi mdi-compass-outline me-1"></i> AUDIT CONTEXT
                    </span>
                    <span class="ops-context-narrative" id="opsScopeNarrative">
                        Initializing audit context...
                    </span>
                </div>
                <div class="ops-context-explainer d-none d-md-flex align-items-center gap-1" id="opsScopeExplainer">
                    <i class="mdi mdi-information-outline text-info"></i>
                    <span id="opsScopeExplainerText">Filtering records matching the selected intraday shift hours on every day of the audit period.</span>
                </div>
            </div>
        </div>
    </div>
</form>
