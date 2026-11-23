{{-- ========================================
     SURGERY SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|SURGERY|DOCTOR|NURSE')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.surgery') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('surgery-workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('surgery-workbench.*') ? 'active' : '' }}" href="{{ route('surgery-workbench.index') }}" id="sidebar-surgery-workbench">
        <i class="mdi mdi-scalpel menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.surgery_workbench') }}</span>
    </a>
</li>
@endhasanyrole
