{{-- ========================================
     STAFF WORKSPACE (ESS & Staff Supplies)
     Condensed Personal Hub for All Staff
     Combines HR Self-Service & Supplies
     ======================================== --}}
@auth
@php
    $sidebarUser = Auth::user();
    $hasStaffProfile = !empty($sidebarUser->staff_profile);
    $canAccessEss = $hasStaffProfile && $sidebarUser->can('ess.access');
    $isEssActive = request()->routeIs('hr.ess.*');
    $isRequisitionsActive = request()->routeIs('inventory.requisitions.*');
    $isWorkspaceActive = $isEssActive || $isRequisitionsActive;
@endphp

<li class="nav-item {{ $isWorkspaceActive ? 'active' : '' }}">
    <a class="nav-link {{ $isWorkspaceActive ? 'active' : '' }}"
       data-toggle="collapse"
       data-bs-toggle="collapse"
       href="javascript:void(0);"
       data-target="#sidebar-staff-workspace"
       data-bs-target="#sidebar-staff-workspace"
       aria-expanded="{{ $isWorkspaceActive ? 'true' : 'false' }}"
       aria-controls="sidebar-staff-workspace"
       id="sidebar-staff-workspace-toggle">
        <i class="mdi mdi-briefcase-account-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.staff_workspace') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ $isWorkspaceActive ? 'show' : '' }}" id="sidebar-staff-workspace">
        <ul class="nav flex-column sub-menu">
            @if($canAccessEss)
            <li class="ch-sidebar-sub-label">
                <span>{{ __('sidebar.hr_self_service') }}</span>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}"
                   href="{{ route('hr.ess.my-profile') }}"
                   id="sidebar-ess-my-profile"
                   data-alias="sidebar-global-profile">
                    <i class="mdi mdi-account-circle-outline mr-2"></i>
                    <span>{{ __('sidebar.my_profile') }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.index') ? 'active' : '' }}"
                   href="{{ route('hr.ess.index') }}"
                   id="sidebar-ess-dashboard">
                    <i class="mdi mdi-view-dashboard-outline mr-2"></i>
                    <span>{{ __('sidebar.ess_dashboard') }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-leave') ? 'active' : '' }}"
                   href="{{ route('hr.ess.my-leave') }}"
                   id="sidebar-ess-my-leave">
                    <i class="mdi mdi-calendar-check mr-2"></i>
                    <span>{{ __('sidebar.my_leave') }}</span>
                </a>
            </li>
            @can('ess.view-payslips')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-payslips') ? 'active' : '' }}"
                   href="{{ route('hr.ess.my-payslips') }}"
                   id="sidebar-ess-my-payslips">
                    <i class="mdi mdi-file-document-outline mr-2"></i>
                    <span>{{ __('sidebar.my_payslips') }}</span>
                </a>
            </li>
            @endcan
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.ess.my-disciplinary') ? 'active' : '' }}"
                   href="{{ route('hr.ess.my-disciplinary') }}"
                   id="sidebar-ess-my-disciplinary">
                    <i class="mdi mdi-alert-circle-outline mr-2"></i>
                    <span>{{ __('sidebar.my_disciplinary') }}</span>
                </a>
            </li>
            <li class="ch-sidebar-sub-divider"></li>
            @endif

            {{-- Staff Supplies & Department Stock --}}
            <li class="ch-sidebar-sub-label">
                <span>{{ __('sidebar.supplies_and_stock') }}</span>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.requisitions.*') && !request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}"
                   href="{{ route('inventory.requisitions.index') }}"
                   id="sidebar-global-requisitions">
                    <i class="mdi mdi-swap-horizontal mr-2"></i>
                    <span>{{ __('sidebar.requisitions') }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}"
                   href="{{ route('inventory.requisitions.my-stock') }}"
                   id="sidebar-global-my-stock">
                    <i class="mdi mdi-package-variant-closed mr-2"></i>
                    <span>{{ __('sidebar.my_stock') }}</span>
                </a>
            </li>
        </ul>
    </div>
</li>
@endauth
