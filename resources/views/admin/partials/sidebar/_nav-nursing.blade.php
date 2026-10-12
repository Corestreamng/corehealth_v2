{{-- ========================================
     NURSING SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|NURSE')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.nursing') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('nursing-workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('nursing-workbench.*') ? 'active' : '' }}" href="{{ route('nursing-workbench.index') }}" id="sidebar-nurse-workbench">
        <i class="mdi mdi-heart-pulse menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.nursing_workbench') }}</span>
    </a>
</li>
@hasanyrole('SUPERADMIN|ADMIN|MATERNITY')
<li class="nav-item {{ request()->routeIs('maternity-workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('maternity-workbench.*') ? 'active' : '' }}" href="{{ route('maternity-workbench.index') }}" id="sidebar-nurse-maternity-workbench">
        <i class="mdi mdi-mother-nurse menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.maternity_workbench') }}</span>
    </a>
</li>
@endhasanyrole
@hasanyrole('SUPERADMIN|ADMIN|SURGERY|DOCTOR|NURSE')
<li class="nav-item {{ request()->routeIs('surgery-workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('surgery-workbench.*') ? 'active' : '' }}" href="{{ route('surgery-workbench.index') }}" id="sidebar-surgery-workbench">
        <i class="mdi mdi-scalpel menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.surgery_workbench') }}</span>
    </a>
</li>
@endhasanyrole
<li class="nav-item">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-nurse-admissions" data-bs-target="#sidebar-nurse-admissions" aria-expanded="false" aria-controls="sidebar-nurse-admissions" id="sidebar-nurse-admissions-toggle">
        <i class="mdi mdi-hotel menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.admissions') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse" id="sidebar-nurse-admissions">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('beds.index') }}" id="sidebar-nurse-manage-beds">{{ __('sidebar.manage_beds') }}</a>
            </li>
        </ul>
    </div>
</li>
@endhasanyrole
