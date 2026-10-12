{{-- ========================================
     LAB SCIENTIST SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|LAB SCIENTIST')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.medical_laboratory') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('lab.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('lab.workbench') ? 'active' : '' }}" href="{{ route('lab.workbench') }}" id="sidebar-lab-workbench">
        <i class="mdi mdi-flask-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.lab_workbench') }}</span>
    </a>
</li>

<li class="nav-item {{ request()->routeIs('services.index') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('services.index') ? 'active' : '' }}" href="{{ route('services.index', ['category' => appsettings('lab_category_id', 2)]) }}" id="sidebar-lab-workbench">
        <i class="mdi mdi-flask-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.lab_services') }}</span>
    </a>
</li>
@endhasanyrole
