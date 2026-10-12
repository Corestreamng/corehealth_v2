<nav class="ch-navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top">
    <div class="navbar-menu-wrapper d-flex align-items-center justify-content-between w-100">
        <!-- Left: Sidebar Toggle -->
        <button class="sidebar-toggle-btn" type="button">
            <i class="mdi mdi-menu"></i>
        </button>

        <!-- Center: Global Search -->
        <div class="navbar-search-wrapper d-none d-md-flex" style="flex: 1; max-width: 500px; margin: 0 20px;">
            <div class="navbar-search-box position-relative w-100">
                <input type="text"
                       id="globalSearchInput"
                       class="form-control global-search-input"
                       placeholder="{{ __('front.search_placeholder') }}"
                       autocomplete="off">
                <i class="mdi mdi-magnify global-search-icon"></i>
                <kbd class="global-search-kbd d-none d-lg-inline">Ctrl+K</kbd>

                <!-- Search Results Dropdown -->
                <div id="globalSearchResults" class="global-search-results">
                    <div class="search-loading d-none">
                        <i class="mdi mdi-loading mdi-spin"></i> {{ __('front.searching') }}
                    </div>
                    <div class="search-results-list"></div>
                    <div class="search-no-results d-none">
                        <i class="mdi mdi-magnify-close"></i>
                        <span>{{ __('front.no_results_found') }}</span>
                    </div>
                    <a href="{{ route('search.index') }}" class="search-view-all d-none">
                        <i class="mdi mdi-arrow-right-circle mr-1"></i> {{ __('front.view_all_results') }}
                    </a>
                    <div class="global-search-footer">
                        <div class="global-search-hints">
                            <span><kbd>↑</kbd><kbd>↓</kbd> Select</span>
                            <span><kbd>↵</kbd> Open</span>
                            <span><kbd>Alt+↵</kbd> Locate</span>
                            <span><kbd>Esc</kbd> Close</span>
                        </div>
                        <button type="button" class="global-search-switch-btn" id="globalSearchSwitchToSidebar" title="Filter sidebar menu with current query">
                            <i class="mdi mdi-page-layout-sidebar-left mr-1"></i> Menu (<kbd>/</kbd>)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Search Toggle -->
        <button class="nav-link d-md-none" id="mobileSearchToggle" style="width: 36px; height: 36px; border: none; background: transparent; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
            <i class="mdi mdi-magnify" style="font-size: 18px; color: #666;"></i>
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
                        <a class="dropdown-item" href="{{ route('hr.ess.my-profile') }}">
                            <i class="mdi mdi-account-outline mr-2"></i> {{ __('front.profile') }}
                        </a>
                        <div class="dropdown-divider" style="margin: 4px 0;"></div>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form-navbar').submit();">
                            <i class="mdi mdi-logout mr-2"></i> {{ __('front.sign_out') }}
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

<!-- Mobile Search Overlay -->
<div id="mobileSearchOverlay" class="mobile-search-overlay">
    <div class="mobile-search-container">
        <div class="d-flex align-items-center gap-2">
            <input type="text"
                   id="mobileSearchInput"
                   class="form-control global-search-input flex-grow-1"
                   placeholder="{{ __('front.search_pages') }}">
            <button class="btn btn-light" id="closeMobileSearch" type="button" aria-label="Close search">
                <i class="mdi mdi-close"></i>
            </button>
        </div>
        <div id="mobileSearchResults" class="mt-2" style="max-height: 60vh; overflow-y: auto;"></div>
    </div>
</div>

