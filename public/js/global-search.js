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
         * Execute global search request
         */
        function executeSearch(query, isMobile = false) {
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

            searchTimeout = setTimeout(() => {
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
                        }
                    })
                    .catch(() => {
                        if (isMobile && mobileResults) {
                            mobileResults.innerHTML = '<div class="text-center text-muted py-3">Error executing search.</div>';
                        } else if (searchResults) {
                            loadingEl?.classList.add('d-none');
                            noResultsEl?.classList.remove('d-none');
                        }
                    });
            }, 200);
        }

        /**
         * Render desktop dropdown results
         */
        function renderDesktopResults(data, query) {
            if (!searchResults || !resultsList) return;

            loadingEl?.classList.add('d-none');

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

            resultsList.innerHTML = data.map((item, index) => `
                <a href="${escapeHtml(item.url)}" class="search-result-item" data-index="${index}">
                    <div class="search-result-icon">
                        <i class="mdi ${escapeHtml(item.icon || 'mdi-link')}"></i>
                    </div>
                    <div class="search-result-content">
                        <div class="search-result-title">${escapeHtml(item.title)}</div>
                        <div class="search-result-hierarchy">${escapeHtml(item.hierarchy || item.section || '')}</div>
                    </div>
                    <i class="mdi mdi-chevron-right search-result-action"></i>
                </a>
            `).join('');

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

            mobileResults.innerHTML = data.map(item => `
                <a href="${escapeHtml(item.url)}" class="search-result-item">
                    <div class="search-result-icon">
                        <i class="mdi ${escapeHtml(item.icon || 'mdi-link')}"></i>
                    </div>
                    <div class="search-result-content">
                        <div class="search-result-title">${escapeHtml(item.title)}</div>
                        <div class="search-result-hierarchy">${escapeHtml(item.hierarchy || item.section || '')}</div>
                    </div>
                    <i class="mdi mdi-chevron-right search-result-action"></i>
                </a>
            `).join('') + `
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

            searchInput.addEventListener('focus', function () {
                if (this.value.trim().length >= 2 && resultsList && resultsList.children.length > 0) {
                    searchResults?.classList.add('show');
                }
            });

            // Arrow keys & Enter selection
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
                    if (currentIndex >= 0 && items[currentIndex]) {
                        e.preventDefault();
                        items[currentIndex].click();
                    } else if (this.value.trim().length >= 2) {
                        window.location.href = `/search?q=${encodeURIComponent(this.value.trim())}`;
                    }
                } else if (e.key === 'Escape') {
                    searchResults?.classList.remove('show');
                    this.blur();
                }
            });
        }

        // Close desktop dropdown on outside click
        document.addEventListener('click', function (e) {
            if (searchInput && searchResults) {
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

                const isDesktop = window.innerWidth >= 768;

                if (isDesktop && searchInput) {
                    searchInput.focus();
                    searchInput.select();
                    if (searchInput.value.trim().length >= 2) {
                        executeSearch(searchInput.value, false);
                    }
                } else if (mobileOverlay && mobileInput) {
                    mobileOverlay.classList.add('show');
                    setTimeout(() => {
                        mobileInput.focus();
                        mobileInput.select();
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

            if (isDesktop && searchInput) {
                searchInput.value = query || '';
                searchInput.focus();
                executeSearch(searchInput.value, false);
            } else if (mobileOverlay && mobileInput) {
                mobileOverlay.classList.add('show');
                mobileInput.value = query || '';
                setTimeout(() => {
                    mobileInput.focus();
                    executeSearch(mobileInput.value, true);
                }, 150);
            }
        };
    });
})(window, document);
