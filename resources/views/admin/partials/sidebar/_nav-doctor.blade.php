{{-- ========================================
     DOCTOR SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.doctors') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('encounters.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('encounters.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-doctor-consultations" data-bs-target="#sidebar-doctor-consultations" aria-expanded="{{ request()->routeIs('encounters.*') ? 'true' : 'false' }}" aria-controls="sidebar-doctor-consultations" id="sidebar-doctor-consultations-toggle">
        <i class="mdi mdi-doctor menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.consultations') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('encounters.*') ? 'show' : '' }}" id="sidebar-doctor-consultations">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('encounters.index') }}" id="sidebar-doctor-encounters">{{ __('sidebar.all_encounters') }}</a>
            </li>
        </ul>
    </div>
</li>
@hasanyrole('SUPERADMIN|ADMIN|MATERNITY')
<li class="nav-item {{ request()->routeIs('maternity-workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('maternity-workbench.*') ? 'active' : '' }}" href="{{ route('maternity-workbench.index') }}" id="sidebar-doctor-maternity-workbench">
        <i class="mdi mdi-mother-nurse menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.maternity_workbench') }}</span>
    </a>
</li>
@endhasanyrole
@endhasanyrole
