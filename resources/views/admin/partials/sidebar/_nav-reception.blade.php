{{-- ========================================
     RECEPTIONIST SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|RECEPTIONIST')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.health_records') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('reception.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('reception.workbench') ? 'active' : '' }}" href="{{ route('reception.workbench') }}" id="sidebar-receptionist-workbench">
        <i class="mdi mdi-desktop-mac-dashboard menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.health_records_workbench') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" href="{{ route('allPrevEncounters') }}" id="sidebar-receptionist-encounters">
        <i class="mdi mdi-stethoscope menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.all_encounters') }}</span>
    </a>
</li>
@php
    $sidebarUser = Auth::user();
    $sidebarStaff = $sidebarUser ? $sidebarUser->staff_profile : null;
    $isQualifiedReceptionist = $sidebarUser && $sidebarUser->hasAnyRole(['RECEPTIONIST', 'Receptionist']) && $sidebarStaff && ($sidebarStaff->is_unit_head || $sidebarStaff->is_dept_head);
    $showNhmisReport = $sidebarUser && ($sidebarUser->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) || $isQualifiedReceptionist);
@endphp
@if($showNhmisReport)
<li class="nav-item {{ request()->routeIs('nhmis.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('nhmis.*') ? 'active' : '' }}" href="{{ route('nhmis.workbench') }}" id="sidebar-receptionist-nhmis-report">
        <i class="mdi mdi-file-chart menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.nhmis_monthly_report') }}</span>
    </a>
</li>
@endif
<li class="nav-item {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-receptionist-consultations" data-bs-target="#sidebar-receptionist-consultations" aria-expanded="{{ request()->routeIs('allPrevEncounters') ? 'true' : 'false' }}" aria-controls="sidebar-receptionist-consultations" id="sidebar-receptionist-consultations-toggle">
        <i class="mdi mdi-stethoscope menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.consultations') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('allPrevEncounters') ? 'show' : '' }}" id="sidebar-receptionist-consultations">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('allPrevEncounters') ? 'active' : '' }}" href="{{ route('allPrevEncounters') }}" id="sidebar-receptionist-prev-consultations">All Previous Consultations</a>
            </li>
        </ul>
    </div>
</li>
@endhasanyrole
