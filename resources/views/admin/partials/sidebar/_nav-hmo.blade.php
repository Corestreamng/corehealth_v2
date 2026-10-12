{{-- ========================================
     HMO EXECUTIVE SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|HMO Executive')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.hmo_executive') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('hmo.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hmo.workbench') ? 'active' : '' }}" href="{{ route('hmo.workbench') }}" id="sidebar-hmo-workbench">
        <i class="mdi mdi-hospital-building menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.hmo_workbench') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('hmo.reports') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hmo.reports') ? 'active' : '' }}" href="{{ route('hmo.reports') }}" id="sidebar-hmo-reports">
        <i class="mdi mdi-file-chart menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.hmo_reports') }}</span>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hmo-patients" data-bs-target="#sidebar-hmo-patients" aria-expanded="false" aria-controls="sidebar-hmo-patients" id="sidebar-hmo-patients-toggle">
        <i class="mdi mdi-account-group menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.patients') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse" id="sidebar-hmo-patients">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('patient.index', ['hmo_only' => 1]) }}" id="sidebar-hmo-patient-hmo">HMO Patients</a>
            </li>
        </ul>
    </div>
</li>

{{-- More HMO Management - Available to ADMIN/SUPERADMIN or HMO Executive who is unit/dept head --}}
@php
    $isAdminUser = Auth::user()->hasAnyRole(['SUPERADMIN', 'ADMIN']);
    $isQualifiedHmoExecutive = Auth::user()->hasRole('HMO Executive') && Auth::user()->staff_profile && (Auth::user()->staff_profile->is_unit_head || Auth::user()->staff_profile->is_dept_head);
    $showHmoMgmt = $isAdminUser || $isQualifiedHmoExecutive;
@endphp
@if($showHmoMgmt)
<li class="nav-item {{ request()->routeIs('hmo-tariffs.*', 'hmo.index', 'organizations.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hmo-tariffs.*', 'hmo.index', 'organizations.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hmo-more-management" data-bs-target="#sidebar-hmo-more-management" aria-expanded="{{ request()->routeIs('hmo-tariffs.*', 'hmo.index', 'organizations.*') ? 'true' : 'false' }}" aria-controls="sidebar-hmo-more-management" id="sidebar-hmo-more-management-toggle">
        <i class="mdi mdi-medical-bag menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.more_management') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hmo-tariffs.*', 'hmo.index', 'organizations.*') ? 'show' : '' }}" id="sidebar-hmo-more-management">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hmo-tariffs.*') ? 'active' : '' }}" href="{{ route('hmo-tariffs.index') }}" id="sidebar-hmo-more-tariffs">Tariff Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hmo.index') ? 'active' : '' }}" href="{{ route('hmo.index') }}" id="sidebar-hmo-more-settings">HMO Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('organizations.*') ? 'active' : '' }}" href="{{ route('organizations.index') }}" id="sidebar-hmo-more-organizations">{{ __('sidebar.billing_organizations') }}</a>
            </li>
        </ul>
    </div>
</li>
@endif
@endhasanyrole
