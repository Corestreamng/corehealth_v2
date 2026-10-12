{{-- Bottom User Profile Section --}}
<li class="nav-item nav-profile pt-3 mt-auto border-top ch-sidebar-user-section">
    <div class="px-3 pb-3">
        <div class="d-flex align-items-center mb-3">
            <x-user-avatar :user="Auth::user()" width="40px" height="40px" class="border border-light" />
            <div class="ml-3 overflow-hidden">
                <div class="font-weight-bold text-white text-truncate" style="font-size: 0.95rem;">
                    {{ Auth::user()->surname }} {{ Auth::user()->firstname }}{{ Auth::user()->othername ? ' ' . Auth::user()->othername : '' }}
                </div>
                <div class="small text-truncate" style="color: rgba(255,255,255,0.8) !important;">{{ Auth::user()->category->name ?? '' }}</div>
            </div>
        </div>

        {{-- Leadership Role Badges --}}
        @php
            $sidebarUser = Auth::user();
            $staffProfile = $sidebarUser ? ($sidebarUser->staff_profile ?? $sidebarUser->staff) : null;
            $isDeptHead = $staffProfile && !empty($staffProfile->is_dept_head);
            $isUnitHead = $staffProfile && !empty($staffProfile->is_unit_head);
        @endphp
        @if($isDeptHead || $isUnitHead)
        <div class="mb-3 ch-sidebar-leadership-wrap">
            <div class="d-flex flex-wrap justify-content-center ch-sidebar-leadership-container" style="gap: 0.4rem;">
                @if($isDeptHead)
                    <span class="badge d-flex align-items-center ch-sidebar-badge-dept-head" title="Department Head">
                        <i class="mdi mdi-shield-crown mr-1"></i> <span class="badge-text">Dept Head</span>
                    </span>
                @endif
                @if($isUnitHead)
                    <span class="badge d-flex align-items-center ch-sidebar-badge-unit-head" title="Unit Head">
                        <i class="mdi mdi-shield-account mr-1"></i> <span class="badge-text">Unit Head</span>
                    </span>
                @endif
            </div>
        </div>
        @endif

        <a href="{{ route('logout') }}"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
           class="btn btn-block d-flex align-items-center justify-content-center py-2 ch-sidebar-logout-btn"
           id="sidebar-bottom-logout">
            <i class="mdi mdi-logout mr-2"></i>
            <span>{{ __('sidebar.logout') }}</span>
        </a>
    </div>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>
</li>
