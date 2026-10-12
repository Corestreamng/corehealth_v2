{{-- ========================================
     RADIOLOGIST SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|RADIOLOGIST')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.imaging_radiology') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('imaging.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('imaging.workbench') ? 'active' : '' }}" href="{{ route('imaging.workbench') }}" id="sidebar-radiology-workbench">
        <i class="mdi mdi-radioactive menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.imaging_workbench') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'active' : '' }}" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-radiology-services" data-bs-target="#sidebar-radiology-services" aria-expanded="{{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'true' : 'false' }}" aria-controls="sidebar-radiology-services" id="sidebar-radiology-services-toggle">
        <i class="mdi mdi-cog-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.services_management') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('services-category.*', 'services.*', 'procedure-categories.*') ? 'show' : '' }}" id="sidebar-radiology-services">
        <ul class="nav flex-column sub-menu">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('services.index', ['category' => appsettings('imaging_category_id', 6)]) }}" id="sidebar-radiology-imaging-services">Imaging Services</a>
            </li>
        </ul>
    </div>
</li>
@endhasanyrole
