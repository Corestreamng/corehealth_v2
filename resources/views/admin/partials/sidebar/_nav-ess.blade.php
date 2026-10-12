{{-- ========================================
     EMPLOYEE SELF-SERVICE (ESS) - My HR Portal
     Available to all staff with HR profiles
     ======================================== --}}
@auth
@if(Auth::user()->staff_profile)
@can('ess.access')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.my_hr_portal') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('hr.ess.index') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.ess.index') ? 'active' : '' }}" href="{{ route('hr.ess.index') }}" id="sidebar-ess-dashboard">
        <i class="mdi mdi-account-circle-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.ess_dashboard') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('hr.ess.my-leave') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.ess.my-leave') ? 'active' : '' }}" href="{{ route('hr.ess.my-leave') }}" id="sidebar-ess-my-leave">
        <i class="mdi mdi-calendar-check menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.my_leave') }}</span>
    </a>
</li>
@can('ess.view-payslips')
<li class="nav-item {{ request()->routeIs('hr.ess.my-payslips') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.ess.my-payslips') ? 'active' : '' }}" href="{{ route('hr.ess.my-payslips') }}" id="sidebar-ess-my-payslips">
        <i class="mdi mdi-file-document-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.my_payslips') }}</span>
    </a>
</li>
@endcan
<li class="nav-item {{ request()->routeIs('hr.ess.my-disciplinary') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.ess.my-disciplinary') ? 'active' : '' }}" href="{{ route('hr.ess.my-disciplinary') }}" id="sidebar-ess-my-disciplinary">
        <i class="mdi mdi-alert-circle-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.my_disciplinary') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}" href="{{ route('hr.ess.my-profile') }}" id="sidebar-ess-my-profile">
        <i class="mdi mdi-account-edit-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.my_profile') }}</span>
    </a>
</li>
@endcan
@endif
@endauth
