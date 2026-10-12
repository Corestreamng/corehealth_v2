{{-- ========================================
     GLOBAL SECTION (All Roles)
     ======================================== --}}
<li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" id="sidebar-global-dashboard">
        <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.dashboard') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.ess.my-profile') ? 'active' : '' }}" href="{{ route('hr.ess.my-profile') }}" id="sidebar-global-profile">
        <i class="mdi mdi-account-circle menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.profile') }}</span>
    </a>
</li>
@if(appsettings('chat_enabled') ?? true)
<li class="nav-item">
    <a class="nav-link" href="{{ route('chat.index') }}" target="_blank" id="sidebar-global-messenger">
        <i class="mdi mdi-message-text-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.messenger') }}</span>
        @include('messenger.unread-count')
    </a>
</li>
@endif
<li class="nav-item {{ request()->routeIs('inventory.requisitions.*') && !request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('inventory.requisitions.*') && !request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}" href="{{ route('inventory.requisitions.index') }}" id="sidebar-global-requisitions">
        <i class="mdi mdi-swap-horizontal menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.requisitions') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('inventory.requisitions.my-stock') ? 'active' : '' }}" href="{{ route('inventory.requisitions.my-stock') }}" id="sidebar-global-my-stock">
        <i class="mdi mdi-package-variant-closed menu-icon"></i>
        <span class="menu-title">My Stock</span>
    </a>
</li>
