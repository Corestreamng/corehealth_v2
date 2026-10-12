{{-- ========================================
     MORGUE SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|MORGUE')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.morgue') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('morgue.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('morgue.workbench') ? 'active' : '' }}" href="{{ route('morgue.workbench') }}" id="sidebar-morgue-workbench">
        <i class="mdi mdi-emoticon-dead menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.morgue_workbench') }}</span>
    </a>
</li>
<li class="nav-item {{ (request()->routeIs('services.*') && request('category') == appsettings('morgue_category_id', 9)) ? 'active' : '' }}">
    <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('morgue_category_id', 9)]) }}" id="sidebar-morgue-services">
        <i class="mdi mdi-medical-bag menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.morgue_services') }}</span>
    </a>
</li>
@endhasanyrole
