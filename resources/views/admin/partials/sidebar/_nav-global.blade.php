{{-- ========================================
     GLOBAL PRIMARY NAVIGATION (All Roles)
     - Dashboard (Operational Cockpit)
     - Hospital Messenger (Internal Comms)
     ======================================== --}}
<li class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" id="sidebar-global-dashboard">
        <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.dashboard') }}</span>
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
