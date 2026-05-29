
        </div>

        <!-- Bottom User Profile Section -->
        <li class="nav-item nav-profile pt-3 mt-auto border-top" style="border-color: rgba(255,255,255,0.1) !important;">
            <div class="px-3 pb-3">
                <div class="d-flex align-items-center mb-3">
                    <x-user-avatar :user="Auth::user()" width="40px" height="40px" class="border border-light" />
                    <div class="ml-3 overflow-hidden">
                        <div class="font-weight-bold text-white text-truncate" style="font-size: 0.95rem;">
                            {{ Auth::user()->firstname }} {{ Auth::user()->surname }}
                        </div>
                        <div class="text-muted small text-truncate" style="opacity: 0.7;">{{ Auth::user()->category->name ?? '' }}</div>
                    </div>
                </div>

                <!-- Leadership Role Badges -->
                @php
                    $staffProfile = Auth::user()->staff_profile;
                @endphp
                @if($staffProfile && ($staffProfile->is_unit_head || $staffProfile->is_dept_head))
                <div class="mb-3">
                    <div class="d-flex flex-wrap justify-content-center" style="gap: 0.5rem;">
                        @if($staffProfile->is_dept_head)
                            <span class="badge d-flex align-items-center" style="background: linear-gradient(135deg, #f6ad55, #ed8936); color: white; padding: 0.4rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                <i class="mdi mdi-shield-crown mr-1"></i> Dept Head
                            </span>
                        @endif
                        @if($staffProfile->is_unit_head)
                            <span class="badge d-flex align-items-center" style="background: linear-gradient(135deg, #63b3ed, #4299e1); color: white; padding: 0.4rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                <i class="mdi mdi-shield-account mr-1"></i> Unit Head
                            </span>
                        @endif
                    </div>
                </div>
                @endif

                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                   class="btn btn-block d-flex align-items-center justify-content-center py-2"
                   style="background: rgba(255, 50, 50, 0.15); border: 1px solid rgba(255, 50, 50, 0.2); color: #ff6b6b; transition: all 0.3s;"
                   id="sidebar-bottom-logout">
                    <i class="mdi mdi-logout mr-2"></i>
                    <span style="font-weight: 500;">Logout</span>
                </a>
            </div>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </li>
    </ul>
</nav>
