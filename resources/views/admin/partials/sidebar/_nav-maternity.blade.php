{{-- ========================================
     MATERNITY SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|MATERNITY')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.maternity') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('maternity-workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('maternity-workbench.*') ? 'active' : '' }}" href="{{ route('maternity-workbench.index') }}" id="sidebar-maternity-workbench">
        <i class="mdi mdi-mother-nurse menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.maternity_workbench') }}</span>
    </a>
</li>
@endhasanyrole
