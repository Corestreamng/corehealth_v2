{{-- Tab Navigation Partial — Encounter Intelligence Workbench --}}
<ul class="nav nav-tabs ewb-nav-tabs mb-0" id="ewb-tabs" role="tablist">

    <li class="nav-item" role="presentation">
        <button class="nav-link active ewb-tab-btn" id="ewb-tab-list"
            data-bs-toggle="tab" data-bs-target="#ewb-pane-list"
            type="button" role="tab" data-tab="list">
            <i class="mdi mdi-format-list-bulleted"></i>
            <span>Encounters</span>
        </button>
    </li>

    <li class="nav-item" role="presentation">
        <button class="nav-link ewb-tab-btn" id="ewb-tab-kpi"
            data-bs-toggle="tab" data-bs-target="#ewb-pane-kpi"
            type="button" role="tab" data-tab="kpi">
            <i class="mdi mdi-view-dashboard-outline"></i>
            <span>Overview</span>
        </button>
    </li>

    @if($canViewAnalytics)
    <li class="nav-item" role="presentation">
        <button class="nav-link ewb-tab-btn ewb-tab-analytics" id="ewb-tab-clinic"
            data-bs-toggle="tab" data-bs-target="#ewb-pane-clinic"
            type="button" role="tab" data-tab="clinic">
            <i class="mdi mdi-hospital-building"></i>
            <span>Clinic Analytics</span>
            <span class="ewb-admin-badge">Admin</span>
        </button>
    </li>
    @endif

    @if($canViewAnalytics || $isDoctor)
    <li class="nav-item" role="presentation">
        <button class="nav-link ewb-tab-btn" id="ewb-tab-doctor"
            data-bs-toggle="tab" data-bs-target="#ewb-pane-doctor"
            type="button" role="tab" data-tab="doctor">
            <i class="mdi mdi-doctor"></i>
            <span>{{ $isDoctor && !$canViewAnalytics ? 'My Productivity' : 'Doctor Productivity' }}</span>
            @if($canViewAnalytics)<span class="ewb-admin-badge">Admin</span>@endif
        </button>
    </li>
    @endif

    @if($canViewRevenue)
    <li class="nav-item" role="presentation">
        <button class="nav-link ewb-tab-btn" id="ewb-tab-revenue"
            data-bs-toggle="tab" data-bs-target="#ewb-pane-revenue"
            type="button" role="tab" data-tab="revenue">
            <i class="mdi mdi-cash-multiple"></i>
            <span>{{ $isDoctor && !$canViewAnalytics ? 'My Revenue' : 'Revenue & Billing' }}</span>
            @if($canViewAnalytics)<span class="ewb-admin-badge">Finance</span>@endif
        </button>
    </li>
    @endif

    @if($canViewAnalytics || $isDoctor)
    <li class="nav-item" role="presentation">
        <button class="nav-link ewb-tab-btn" id="ewb-tab-patients"
            data-bs-toggle="tab" data-bs-target="#ewb-pane-patients"
            type="button" role="tab" data-tab="patients">
            <i class="mdi mdi-account-group"></i>
            <span>Patient Insights</span>
            @if($canViewAnalytics)<span class="ewb-admin-badge">Admin</span>@endif
        </button>
    </li>
    @endif

</ul>
