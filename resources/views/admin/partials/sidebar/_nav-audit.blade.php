{{-- ========================================
     INTERNAL AUDIT SECTION
     ======================================== --}}
@if(Auth::user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin', 'AUDITOR', 'AUDIT']))
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.internal_audit') }}</span>
</li>
<li class="nav-item {{ request()->routeIs('audit.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}" href="/audit-workbench" id="sidebar-audit-workbench">
        <i class="mdi mdi-shield-check-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.audit_workbench') }}</span>
    </a>
</li>
<li class="nav-item {{ request()->routeIs('ops-audit.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('ops-audit.*') ? 'active' : '' }}" href="{{ route('ops-audit.reception') }}" id="sidebar-ops-audit">
        <i class="mdi mdi-clipboard-check-multiple menu-icon"></i>
        <span class="menu-title">Ops Audit</span>
    </a>
</li>
@endif
