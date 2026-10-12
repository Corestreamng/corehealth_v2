{{-- ========================================
     BILLER / ACCOUNTS SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS|BILLER')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.billing') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('billing.workbench') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('billing.workbench') ? 'active' : '' }}" href="{{ route('billing.workbench') }}" id="sidebar-biller-workbench">
        <i class="mdi mdi-cash-register menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.billing_workbench') }}</span>
    </a>
</li>
@endhasanyrole
