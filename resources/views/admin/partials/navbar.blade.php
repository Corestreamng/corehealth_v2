<nav class="ch-navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top">
    <div class="navbar-menu-wrapper d-flex align-items-center justify-content-between w-100">
        <!-- Left: Sidebar Toggle -->
        <button class="sidebar-toggle-btn" type="button">
            <i class="mdi mdi-menu"></i>
        </button>

        <!-- Right: User Menu -->
        <ul class="navbar-nav navbar-nav-right d-flex flex-row align-items-center ml-auto" style="gap: 15px; margin: 0; list-style: none;">
            <!-- Dark/Light Mode Toggle -->
            <li class="nav-item">
                <button id="darkModeToggle" class="nav-link" style="width: 36px; height: 36px; border: none; background: transparent; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s;">
                    <i class="mdi mdi-weather-night" id="darkModeIcon" style="font-size: 18px; color: #666;"></i>
                </button>
            </li>

            <!-- Messages -->
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="popMessengerWindow()" style="width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 4px; transition: background 0.2s;">
                    <i class="mdi mdi-email-outline" style="font-size: 18px; color: #666;"></i>
                    @include('messenger.unread-count')
                </a>
            </li>

            <!-- Notifications -->
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="dropdown" style="width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 4px; transition: background 0.2s;">
                    <i class="mdi mdi-bell-outline" style="font-size: 18px; color: #666;"></i>
                    <span class="notification-badge" style="position: absolute; top: 6px; right: 6px; background: #dc3545; color: white; border-radius: 50%; width: 8px; height: 8px; display: none;"></span>
                </a>
            </li>

            <!-- User Dropdown -->
            @auth
                <li class="nav-item dropdown">
                    <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="padding: 0; display: flex; align-items: center;">
                        <x-user-avatar :user="Auth::user()" width="32px" height="32px" />
                    </a>

                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="{{ route('my-profile') }}">
                            <i class="mdi mdi-account-outline mr-2"></i> Profile
                        </a>
                        <div class="dropdown-divider" style="margin: 4px 0;"></div>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form-navbar').submit();">
                            <i class="mdi mdi-logout mr-2"></i> Sign out
                        </a>

                        <form id="logout-form-navbar" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </li>
            @endauth
        </ul>

        <!-- Mobile Sidebar Toggle -->
        <button class="sidebar-mobile-toggle-btn d-lg-none" type="button">
            <i class="mdi mdi-menu"></i>
        </button>
    </div>
</nav>

<style>
    /* Message count badge styling */
    .ch-navbar .count {
        position: absolute;
        top: 4px;
        right: 4px;
        background: #dc3545;
        color: white;
        font-size: 9px;
        padding: 2px 4px;
        border-radius: 8px;
        font-weight: 600;
        min-width: 14px;
        text-align: center;
        line-height: 1;
    }
</style>
