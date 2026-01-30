<nav class="ch-navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top">
    <div class="navbar-menu-wrapper d-flex align-items-center justify-content-between w-100">
        <!-- Left: Sidebar Toggle -->
        <button class="sidebar-toggle-btn" type="button">
            <i class="mdi mdi-menu"></i>
        </button>

        <!-- Center: Global Search -->
        <div class="navbar-search-wrapper d-none d-md-flex" style="flex: 1; max-width: 500px; margin: 0 20px;">
            <div class="position-relative w-100">
                <input type="text"
                       id="globalSearchInput"
                       class="form-control global-search-input"
                       placeholder="Search pages, features, modules... (Ctrl+K)"
                       autocomplete="off">
                <i class="mdi mdi-magnify global-search-icon"></i>
                <kbd class="global-search-kbd d-none d-lg-inline">Ctrl+K</kbd>

                <!-- Search Results Dropdown -->
                <div id="globalSearchResults" class="global-search-results">
                    <div class="search-loading d-none">
                        <i class="mdi mdi-loading mdi-spin"></i> Searching...
                    </div>
                    <div class="search-results-list"></div>
                    <div class="search-no-results d-none">
                        <i class="mdi mdi-magnify-close"></i>
                        <span>No results found</span>
                    </div>
                    <a href="{{ route('search.index') }}" class="search-view-all d-none">
                        <i class="mdi mdi-arrow-right-circle mr-1"></i> View all results
                    </a>
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

    /* Global Search Styles */
    .global-search-input {
        background: var(--input-bg, #f3f4f6);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 8px;
        padding: 8px 40px 8px 36px;
        font-size: 14px;
        transition: all 0.2s ease;
        width: 100%;
    }

    .global-search-input:focus {
        background: var(--card-bg, #fff);
        border-color: var(--hos-color, #6366f1);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        outline: none;
    }

    .global-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 16px;
        pointer-events: none;
    }

    .global-search-kbd {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--border-color, #e5e7eb);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
        color: #6b7280;
        font-family: monospace;
    }

    .global-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        margin-top: 8px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1050;
        display: none;
    }

    .global-search-results.show {
        display: block;
    }

    .search-result-item {
        display: flex;
        align-items: flex-start;
        padding: 12px 16px;
        text-decoration: none;
        color: inherit;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
        transition: background 0.15s ease;
    }

    .search-result-item:hover, .search-result-item.active {
        background: var(--hover-bg, #f9fafb);
        text-decoration: none;
        color: inherit;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--hos-color, #6366f1) 0%, #4338ca 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
        margin-right: 12px;
        flex-shrink: 0;
    }

    .search-result-content {
        flex: 1;
        min-width: 0;
    }

    .search-result-title {
        font-weight: 600;
        font-size: 14px;
        color: var(--text-primary, #1f2937);
        margin-bottom: 2px;
    }

    .search-result-hierarchy {
        font-size: 12px;
        color: var(--hos-color, #6366f1);
    }

    .search-loading, .search-no-results {
        padding: 20px;
        text-align: center;
        color: #6b7280;
    }

    .search-view-all {
        display: block;
        padding: 12px 16px;
        text-align: center;
        background: var(--hover-bg, #f9fafb);
        color: var(--hos-color, #6366f1);
        font-weight: 500;
        text-decoration: none;
        border-top: 1px solid var(--border-color, #e5e7eb);
    }

    .search-view-all:hover {
        background: var(--border-color, #e5e7eb);
        text-decoration: none;
    }

    /* Mobile Search Modal */
    .mobile-search-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1060;
        display: none;
    }

    .mobile-search-overlay.show {
        display: block;
    }

    .mobile-search-container {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: var(--card-bg, #fff);
        padding: 16px;
        z-index: 1061;
        transform: translateY(-100%);
        transition: transform 0.3s ease;
    }

    .mobile-search-overlay.show .mobile-search-container {
        transform: translateY(0);
    }
</style>

<!-- Mobile Search Overlay -->
<div id="mobileSearchOverlay" class="mobile-search-overlay">
    <div class="mobile-search-container">
        <div class="d-flex align-items-center gap-2">
            <input type="text"
                   id="mobileSearchInput"
                   class="form-control global-search-input flex-grow-1"
                   placeholder="Search pages...">
            <button class="btn btn-light" id="closeMobileSearch">
                <i class="mdi mdi-close"></i>
            </button>
        </div>
        <div id="mobileSearchResults" class="mt-2" style="max-height: 60vh; overflow-y: auto;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    const resultsList = searchResults?.querySelector('.search-results-list');
    const loadingEl = searchResults?.querySelector('.search-loading');
    const noResultsEl = searchResults?.querySelector('.search-no-results');
    const viewAllEl = searchResults?.querySelector('.search-view-all');

    let searchTimeout = null;
    let currentIndex = -1;

    // Keyboard shortcut (Ctrl+K)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }

        // Escape to close
        if (e.key === 'Escape') {
            searchResults?.classList.remove('show');
            searchInput?.blur();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                searchResults.classList.remove('show');
                return;
            }

            loadingEl?.classList.remove('d-none');
            noResultsEl?.classList.add('d-none');
            viewAllEl?.classList.add('d-none');
            resultsList.innerHTML = '';
            searchResults.classList.add('show');

            searchTimeout = setTimeout(() => {
                fetch(`/search/api?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        loadingEl?.classList.add('d-none');

                        if (data.length === 0) {
                            noResultsEl?.classList.remove('d-none');
                            return;
                        }

                        viewAllEl?.classList.remove('d-none');
                        viewAllEl.href = `/search?q=${encodeURIComponent(query)}`;

                        resultsList.innerHTML = data.map((item, index) => `
                            <a href="${item.url}" class="search-result-item" data-index="${index}">
                                <div class="search-result-icon">
                                    <i class="mdi ${item.icon || 'mdi-link'}"></i>
                                </div>
                                <div class="search-result-content">
                                    <div class="search-result-title">${item.title}</div>
                                    <div class="search-result-hierarchy">${item.hierarchy}</div>
                                </div>
                            </a>
                        `).join('');

                        currentIndex = -1;
                    })
                    .catch(err => {
                        loadingEl?.classList.add('d-none');
                        noResultsEl?.classList.remove('d-none');
                    });
            }, 300);
        });

        // Keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            const items = resultsList?.querySelectorAll('.search-result-item') || [];

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentIndex = Math.min(currentIndex + 1, items.length - 1);
                updateActiveItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentIndex = Math.max(currentIndex - 1, -1);
                updateActiveItem(items);
            } else if (e.key === 'Enter' && currentIndex >= 0) {
                e.preventDefault();
                items[currentIndex]?.click();
            }
        });

        function updateActiveItem(items) {
            items.forEach((item, i) => {
                item.classList.toggle('active', i === currentIndex);
            });
            if (currentIndex >= 0) {
                items[currentIndex]?.scrollIntoView({ block: 'nearest' });
            }
        }

        // Close on click outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });

        // Focus shows results if query exists
        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 2 && resultsList.children.length > 0) {
                searchResults.classList.add('show');
            }
        });
    }

    // Mobile search
    const mobileToggle = document.getElementById('mobileSearchToggle');
    const mobileOverlay = document.getElementById('mobileSearchOverlay');
    const mobileClose = document.getElementById('closeMobileSearch');
    const mobileInput = document.getElementById('mobileSearchInput');
    const mobileResults = document.getElementById('mobileSearchResults');

    mobileToggle?.addEventListener('click', function() {
        mobileOverlay?.classList.add('show');
        setTimeout(() => mobileInput?.focus(), 300);
    });

    mobileClose?.addEventListener('click', function() {
        mobileOverlay?.classList.remove('show');
    });

    mobileOverlay?.addEventListener('click', function(e) {
        if (e.target === mobileOverlay) {
            mobileOverlay.classList.remove('show');
        }
    });

    mobileInput?.addEventListener('input', function() {
        const query = this.value.trim();

        if (query.length < 2) {
            mobileResults.innerHTML = '';
            return;
        }

        mobileResults.innerHTML = '<div class="text-center text-muted py-3"><i class="mdi mdi-loading mdi-spin"></i> Searching...</div>';

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetch(`/search/api?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        mobileResults.innerHTML = `
                            <div class="text-center text-muted py-3">
                                <i class="mdi mdi-magnify-close d-block mb-2" style="font-size: 2rem; opacity: 0.5;"></i>
                                No results found
                            </div>
                            <a href="/search?q=${encodeURIComponent(query)}" class="search-view-all" style="display: block; margin-top: 10px;">
                                <i class="mdi mdi-magnify mr-1"></i> Search in full page
                            </a>
                        `;
                        return;
                    }

                    mobileResults.innerHTML = data.map(item => `
                        <a href="${item.url}" class="search-result-item">
                            <div class="search-result-icon">
                                <i class="mdi ${item.icon || 'mdi-link'}"></i>
                            </div>
                            <div class="search-result-content">
                                <div class="search-result-title">${item.title}</div>
                                <div class="search-result-hierarchy">${item.hierarchy}</div>
                            </div>
                        </a>
                    `).join('') + `
                        <a href="/search?q=${encodeURIComponent(query)}" class="search-view-all" style="display: block;">
                            <i class="mdi mdi-arrow-right-circle mr-1"></i> View all results
                        </a>
                    `;
                })
                .catch(err => {
                    mobileResults.innerHTML = '<div class="text-center text-muted py-3">Error searching. Please try again.</div>';
                });
        }, 300);
    });
});
</script>
