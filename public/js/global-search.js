/**
 * CoreHealth v2 - Global Search Interactive Module
 *
 * Provides real-time AJAX route search, keyboard navigation,
 * and synergy with sidebar navigation.
 */
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('globalSearchInput');
        const searchResults = document.getElementById('globalSearchResults');
        const resultsList = searchResults ? searchResults.querySelector('.search-results-list') : null;
        const loadingEl = searchResults ? searchResults.querySelector('.search-loading') : null;
        const noResultsEl = searchResults ? searchResults.querySelector('.search-no-results') : null;
        const viewAllEl = searchResults ? searchResults.querySelector('.search-view-all') : null;

        // Mobile search elements
        const mobileToggle = document.getElementById('mobileSearchToggle');
        const mobileOverlay = document.getElementById('mobileSearchOverlay');
        const mobileClose = document.getElementById('closeMobileSearch');
        const mobileInput = document.getElementById('mobileSearchInput');
        const mobileResults = document.getElementById('mobileSearchResults');

        let searchTimeout = null;
        let currentIndex = -1;

        /**
         * Escape HTML string helper
         */
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /**
         * Check if a given URL exists in the current sidebar navigation
         */
        function isLinkInSidebar(url) {
            if (!url) return false;
            try {
                const path = url.replace(/^https?:\/\/[^\/]+/, '');
                const exactMatch = document.querySelector(`.ch-sidebar a[href="${url}"], .ch-sidebar a[href="${path}"]`);
                if (exactMatch) return true;
                if (path && path.length > 1) {
                    const relMatch = document.querySelector(`.ch-sidebar a[href$="${path}"]`);
                    if (relMatch) return true;
                }
                return false;
            } catch (e) {
                return false;
            }
        }

        /**
         * Execute global search request
         */
        function executeSearch(query, isMobile = false, immediate = false) {
            clearTimeout(searchTimeout);

            const trimmedQuery = (query || '').trim();

            if (trimmedQuery.length < 2) {
                if (isMobile) {
                    if (mobileResults) mobileResults.innerHTML = '';
                } else if (searchResults) {
                    searchResults.classList.remove('show');
                }
                return;
            }

            if (!isMobile && searchResults) {
                loadingEl?.classList.remove('d-none');
                noResultsEl?.classList.add('d-none');
                viewAllEl?.classList.add('d-none');
                if (resultsList) resultsList.innerHTML = '';
                searchResults.classList.add('show');
            } else if (isMobile && mobileResults) {
                mobileResults.innerHTML = '<div class="text-center text-muted py-3"><i class="mdi mdi-loading mdi-spin"></i> Searching...</div>';
            }

            const doFetch = () => {
                fetch(`/search/api?q=${encodeURIComponent(trimmedQuery)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Search request failed');
                        return response.json();
                    })
                    .then(data => {
                        if (isMobile) {
                            renderMobileResults(data, trimmedQuery);
                        } else {
                            renderDesktopResults(data, trimmedQuery);
                            if (searchResults) {
                                searchResults.classList.add('show');
                            }
                        }
                    })
                    .catch(() => {
                        if (isMobile && mobileResults) {
                            mobileResults.innerHTML = '<div class="text-center text-muted py-3">Error executing search.</div>';
                        } else if (searchResults) {
                            loadingEl?.classList.add('d-none');
                            noResultsEl?.classList.remove('d-none');
                            searchResults.classList.add('show');
                        }
                    });
            };

            if (immediate) {
                doFetch();
            } else {
                searchTimeout = setTimeout(doFetch, 200);
            }
        }

        /**
         * Render desktop dropdown results
         */
        function renderDesktopResults(data, query) {
            if (!searchResults || !resultsList) return;

            loadingEl?.classList.add('d-none');
            searchResults.classList.add('show');

            if (!Array.isArray(data) || data.length === 0) {
                noResultsEl?.classList.remove('d-none');
                resultsList.innerHTML = '';
                currentIndex = -1;
                return;
            }

            noResultsEl?.classList.add('d-none');

            if (viewAllEl) {
                viewAllEl.classList.remove('d-none');
                viewAllEl.href = `/search?q=${encodeURIComponent(query)}`;
            }

            resultsList.innerHTML = data.map((item, index) => {
                const inSidebar = isLinkInSidebar(item.url);
                return `
                <a href="${escapeHtml(item.url)}" class="search-result-item" data-index="${index}" data-url="${escapeHtml(item.url)}">
                    <div class="search-result-icon">
                        <i class="mdi ${escapeHtml(item.icon || 'mdi-link')}"></i>
                    </div>
                    <div class="search-result-content">
                        <div class="search-result-title">
                            ${escapeHtml(item.title)}
                            ${inSidebar ? `<span class="search-result-in-menu" title="Available in sidebar navigation"><i class="mdi mdi-dock-left mr-1"></i>In menu</span>` : ''}
                        </div>
                        <div class="search-result-hierarchy">${escapeHtml(item.hierarchy || item.section || '')}</div>
                    </div>
                    ${inSidebar ? `
                    <button type="button" class="search-result-locate-btn" title="Locate in sidebar menu (Alt+Enter)" data-url="${escapeHtml(item.url)}" tabindex="-1">
                        <i class="mdi mdi-crosshairs-gps"></i>
                    </button>` : ''}
                    <i class="mdi mdi-chevron-right search-result-action"></i>
                </a>
            `}).join('');

            currentIndex = -1;
        }

        /**
         * Render mobile results list
         */
        function renderMobileResults(data, query) {
            if (!mobileResults) return;

            if (!Array.isArray(data) || data.length === 0) {
                mobileResults.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="mdi mdi-magnify-close d-block mb-2" style="font-size: 2rem; opacity: 0.5;"></i>
                        No results found for "${escapeHtml(query)}"
                    </div>
                    <a href="/search?q=${encodeURIComponent(query)}" class="search-view-all">
                        <i class="mdi mdi-magnify mr-1"></i> Search in full page
                    </a>
                `;
                return;
            }

            mobileResults.innerHTML = data.map(item => {
                const inSidebar = isLinkInSidebar(item.url);
                return `
                <a href="${escapeHtml(item.url)}" class="search-result-item" data-url="${escapeHtml(item.url)}">
                    <div class="search-result-icon">
                        <i class="mdi ${escapeHtml(item.icon || 'mdi-link')}"></i>
                    </div>
                    <div class="search-result-content">
                        <div class="search-result-title">
                            ${escapeHtml(item.title)}
                            ${inSidebar ? `<span class="search-result-in-menu" title="Available in sidebar menu"><i class="mdi mdi-dock-left mr-1"></i>In menu</span>` : ''}
                        </div>
                        <div class="search-result-hierarchy">${escapeHtml(item.hierarchy || item.section || '')}</div>
                    </div>
                    ${inSidebar ? `
                    <button type="button" class="search-result-locate-btn" title="Locate in sidebar menu" data-url="${escapeHtml(item.url)}" tabindex="-1">
                        <i class="mdi mdi-crosshairs-gps"></i>
                    </button>` : ''}
                    <i class="mdi mdi-chevron-right search-result-action"></i>
                </a>
            `}).join('') + `
                <a href="/search?q=${encodeURIComponent(query)}" class="search-view-all">
                    <i class="mdi mdi-arrow-right-circle mr-1"></i> View all results
                </a>
            `;
        }

        /**
         * Keyboard selection update
         */
        function updateActiveItem(items) {
            items.forEach((item, i) => {
                item.classList.toggle('active', i === currentIndex);
            });
            if (currentIndex >= 0 && items[currentIndex]) {
                items[currentIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        /**
         * Desktop Input Event Handlers
         */
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                executeSearch(this.value, false);
            });

            searchInput.addEventListener('paste', function () {
                setTimeout(() => {
                    executeSearch(this.value, false, true);
                }, 10);
            });

            searchInput.addEventListener('focus', function () {
                if (this.value.trim().length >= 2) {
                    if (searchResults) searchResults.classList.add('show');
                    if (!resultsList || resultsList.children.length === 0) {
                        executeSearch(this.value, false, true);
                    }
                }
            });

            // Arrow keys & Enter selection & Alt+Enter & / switch
            searchInput.addEventListener('keydown', function (e) {
                const items = resultsList ? resultsList.querySelectorAll('.search-result-item') : [];

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (items.length > 0) {
                        currentIndex = Math.min(currentIndex + 1, items.length - 1);
                        updateActiveItem(items);
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (items.length > 0) {
                        currentIndex = Math.max(currentIndex - 1, -1);
                        updateActiveItem(items);
                    }
                } else if (e.key === 'Enter') {
                    // Alt+Enter: Locate active result in sidebar
                    if (e.altKey) {
                        e.preventDefault();
                        const targetItem = currentIndex >= 0 ? items[currentIndex] : items[0];
                        if (targetItem) {
                            const url = targetItem.getAttribute('data-url') || targetItem.getAttribute('href');
                            if (url && typeof window.revealInSidebar === 'function') {
                                searchResults?.classList.remove('show');
                                searchInput.blur();
                                window.revealInSidebar(url);
                                return;
                            }
                        }
                    }

                    if (currentIndex >= 0 && items[currentIndex]) {
                        e.preventDefault();
                        items[currentIndex].click();
                    } else if (this.value.trim().length >= 2) {
                        window.location.href = `/search?q=${encodeURIComponent(this.value.trim())}`;
                    }
                } else if (e.key === '/' && (this.value.trim().length === 0 || e.ctrlKey || e.altKey)) {
                    // Switch to sidebar filter
                    e.preventDefault();
                    searchResults?.classList.remove('show');
                    this.blur();
                    if (typeof window.triggerSidebarSearch === 'function') {
                        window.triggerSidebarSearch(this.value.trim());
                    }
                } else if (e.key === 'Escape') {
                    if (searchResults?.classList.contains('show')) {
                        searchResults.classList.remove('show');
                    } else {
                        this.blur();
                    }
                }
            });
        }

        // Locate in sidebar button or switch button clicks
        document.addEventListener('click', function (e) {
            const locateBtn = e.target.closest('.search-result-locate-btn');
            if (locateBtn) {
                e.preventDefault();
                e.stopPropagation();
                const url = locateBtn.getAttribute('data-url');
                if (url && typeof window.revealInSidebar === 'function') {
                    searchResults?.classList.remove('show');
                    mobileOverlay?.classList.remove('show');
                    searchInput?.blur();
                    window.revealInSidebar(url);
                }
                return;
            }

            const switchBtn = e.target.closest('#globalSearchSwitchToSidebar');
            if (switchBtn) {
                e.preventDefault();
                e.stopPropagation();
                const query = searchInput ? searchInput.value.trim() : '';
                searchResults?.classList.remove('show');
                searchInput?.blur();
                if (typeof window.triggerSidebarSearch === 'function') {
                    window.triggerSidebarSearch(query);
                }
                return;
            }

            if (searchInput && searchResults) {
                // Ignore clicks originating from sidebar bridge buttons that trigger global search
                if (e.target.closest('#ch-sidebar-global-bridge-btn, #ch-sidebar-bridge-global, .ch-sidebar-search-all-btn, .ch-sidebar-bridge-btn')) {
                    return;
                }
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.remove('show');
                }
            }
        });

        /**
         * Global Keyboard Shortcut: Ctrl+K / Cmd+K
         */
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();

                // If currently inside sidebar search, capture any query
                const sidebarSearch = document.getElementById('ch-sidebar-search');
                let initialQuery = '';
                if (sidebarSearch && document.activeElement === sidebarSearch && sidebarSearch.value.trim()) {
                    initialQuery = sidebarSearch.value.trim();
                }

                const isDesktop = window.innerWidth >= 768;

                if (isDesktop && searchInput) {
                    if (initialQuery) {
                        searchInput.value = initialQuery;
                    }
                    searchInput.focus();
                    searchInput.select();
                    if (searchInput.value.trim().length >= 2) {
                        executeSearch(searchInput.value, false, true);
                    }
                } else if (mobileOverlay && mobileInput) {
                    if (initialQuery) {
                        mobileInput.value = initialQuery;
                    }
                    mobileOverlay.classList.add('show');
                    setTimeout(() => {
                        mobileInput.focus();
                        mobileInput.select();
                        if (mobileInput.value.trim().length >= 2) {
                            executeSearch(mobileInput.value, true, true);
                        }
                    }, 150);
                }
            } else if (e.key === 'Escape') {
                if (mobileOverlay && mobileOverlay.classList.contains('show')) {
                    mobileOverlay.classList.remove('show');
                }
            }
        });

        /**
         * Mobile Overlay Listeners
         */
        mobileToggle?.addEventListener('click', function (e) {
            e.preventDefault();
            mobileOverlay?.classList.add('show');
            setTimeout(() => mobileInput?.focus(), 200);
        });

        mobileClose?.addEventListener('click', function () {
            mobileOverlay?.classList.remove('show');
        });

        mobileOverlay?.addEventListener('click', function (e) {
            if (e.target === mobileOverlay) {
                mobileOverlay.classList.remove('show');
            }
        });

        mobileInput?.addEventListener('input', function () {
            executeSearch(this.value, true);
        });

        /**
         * Global API Function: triggerGlobalSearch(query)
         * Enables sidebar search or other widgets to open global search with a prefilled query.
         */
        window.triggerGlobalSearch = function (query) {
            const isDesktop = window.innerWidth >= 768;
            const term = (query || '').trim();

            if (isDesktop && searchInput) {
                searchInput.value = term;
                searchInput.focus();

                // Open search results dropdown immediately with loading state
                if (searchResults) {
                    loadingEl?.classList.remove('d-none');
                    noResultsEl?.classList.add('d-none');
                    viewAllEl?.classList.add('d-none');
                    if (resultsList) resultsList.innerHTML = '';
                    searchResults.classList.add('show');
                }

                if (term.length >= 2) {
                    // Trigger immediate search without waiting for debounce
                    executeSearch(term, false, true);
                } else {
                    searchInput.select();
                }
            } else if (mobileOverlay && mobileInput) {
                mobileOverlay.classList.add('show');
                mobileInput.value = term;
                if (mobileResults) {
                    mobileResults.innerHTML = '<div class="text-center text-muted py-3"><i class="mdi mdi-loading mdi-spin"></i> Searching...</div>';
                }
                setTimeout(() => {
                    mobileInput.focus();
                    if (term.length >= 2) {
                        executeSearch(term, true, true);
                    } else {
                        mobileInput.select();
                    }
                }, 100);
            }
        };
    });
})(window, document);
