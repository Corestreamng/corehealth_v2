/**
 * CoreHealth v2 — Sidebar Module
 * ================================
 * Handles all sidebar interactive behavior:
 * - Desktop: Full sidebar with icon-only toggle (persisted)
 * - Tablet:  Icon rail with flyout panels
 * - Mobile:  Off-canvas drawer with backdrop, swipe, scroll lock
 * - Search:  Ctrl+K / Cmd+K fuzzy filter
 * - Keyboard: Tab, Arrow, Escape navigation
 * - Section collapse memory (localStorage)
 * - Active route detection
 *
 * Consumes window.SIDEBAR_CONFIG (optional) for server-injected data.
 * Dependencies: jQuery (already loaded globally)
 */
(function ($) {
    'use strict';

    /* ── Constants ── */
    var BREAKPOINT_MOBILE  = 768;
    var BREAKPOINT_TABLET  = 1200;
    var TRANSITION_MS      = 300;
    var SWIPE_THRESHOLD    = 50;
    var STORAGE_KEY_STATE  = 'ch_sidebar_state';       // 'full' | 'icon-only'
    var STORAGE_KEY_SECTIONS = 'ch_sidebar_sections';  // collapsed section IDs
    var STORAGE_KEY_SEARCH = 'ch_sidebar_search';      // last search term

    /* ── State ── */
    var sidebar, backdrop, searchInput, searchClear;
    var currentWidth = window.innerWidth;

    /* ──────────────────────────────────────
       INITIALIZATION
       ────────────────────────────────────── */
    $(document).ready(function () {
        sidebar     = $('.ch-sidebar');
        searchInput = $('#ch-sidebar-search');
        searchClear = $('#ch-sidebar-search-clear');

        if (!sidebar.length) return;

        createBackdrop();
        restoreSidebarState();
        setupDesktopToggle();
        setupMobileToggle();
        setupBackdropClose();
        setupSwipeGesture();
        setupSearch();
        setupSectionCollapse();
        setupKeyboardNavigation();
        setupActiveRouteDetection();
        setupFlyoutTriggers();
        setupResizeHandler();
        addAriaAttributes();
        addDataTitleAttributes();
        setupGroupAttributes();

        // Remove loading state
        sidebar.addClass('ch-sidebar--ready');
    });

    /* ──────────────────────────────────────
       BACKDROP OVERLAY (Mobile)
       ────────────────────────────────────── */
    function createBackdrop() {
        if ($('#ch-sidebar-backdrop').length) return;
        backdrop = $('<div id="ch-sidebar-backdrop" class="ch-sidebar-backdrop" aria-hidden="true"></div>');
        $('body').append(backdrop);
    }

    function showBackdrop() {
        backdrop.addClass('ch-sidebar-backdrop--visible');
        backdrop.attr('aria-hidden', 'false');
    }

    function hideBackdrop() {
        backdrop.removeClass('ch-sidebar-backdrop--visible');
        backdrop.attr('aria-hidden', 'true');
    }

    /* ──────────────────────────────────────
       BODY SCROLL LOCK
       ────────────────────────────────────── */
    function lockBodyScroll() {
        var scrollY = window.scrollY;
        $('body').css({
            'overflow': 'hidden',
            'position': 'fixed',
            'width': '100%',
            'top': -scrollY + 'px'
        });
        $('body').data('scroll-position', scrollY);
    }

    function unlockBodyScroll() {
        var scrollY = $('body').data('scroll-position') || 0;
        $('body').css({
            'overflow': '',
            'position': '',
            'width': '',
            'top': ''
        });
        window.scrollTo(0, scrollY);
    }

    /* ──────────────────────────────────────
       SIDEBAR STATE PERSISTENCE
       ────────────────────────────────────── */
    function restoreSidebarState() {
        var saved = localStorage.getItem(STORAGE_KEY_STATE);
        if (saved === 'icon-only' && getViewportTier() === 'desktop') {
            $('body').addClass('sidebar-icon-only');
        } else if (getViewportTier() !== 'desktop') {
            $('body').removeClass('sidebar-icon-only');
        }
    }

    function saveSidebarState(state) {
        try { localStorage.setItem(STORAGE_KEY_STATE, state); } catch (e) { /* quota */ }
    }

    function getViewportTier() {
        var w = window.innerWidth;
        if (w < BREAKPOINT_MOBILE)  return 'mobile';
        if (w < BREAKPOINT_TABLET)  return 'tablet';
        return 'desktop';
    }

    /* ──────────────────────────────────────
       DESKTOP TOGGLE (Icon-Only Mode)
       ────────────────────────────────────── */
    function setupDesktopToggle() {
        $(document).on('click', '.sidebar-toggle-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var isIconOnly = $('body').toggleClass('sidebar-icon-only').hasClass('sidebar-icon-only');
            saveSidebarState(isIconOnly ? 'icon-only' : 'full');

            // Collapse all open submenus when switching to icon-only
            if (isIconOnly) {
                sidebar.find('.collapse.show').removeClass('show');
                sidebar.find('.nav-link[aria-expanded="true"]').attr('aria-expanded', 'false');
            }
        });
    }

    /* ──────────────────────────────────────
       MOBILE TOGGLE (Off-Canvas Drawer)
       ────────────────────────────────────── */
    function setupMobileToggle() {
        $(document).on('click', '.sidebar-mobile-toggle-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openMobileDrawer({ focusSearch: false });
        });

        // Close button inside brand header on mobile
        $(document).on('click', '#ch-sidebar-close-btn', function (e) {
            e.preventDefault();
            closeMobileDrawer();
        });

        // Close drawer when a nav link is clicked (mobile UX)
        sidebar.on('click', '.nav-link:not([data-toggle="collapse"]):not([data-bs-toggle="collapse"])', function () {
            if (getViewportTier() !== 'desktop') {
                closeMobileDrawer();
            }
        });
    }

    function openMobileDrawer(options) {
        options = options || {};
        sidebar.addClass('active');
        showBackdrop();
        lockBodyScroll();
        sidebar.attr('aria-hidden', 'false');

        // Never auto-focus search on touch devices.
        // Calling .focus() on touchscreens brings up the on-screen virtual keyboard,
        // which covers 50-70% of the screen, locks scrolling, and interrupts navigation.
        // Users on touchscreens should explicitly tap the search input to begin typing.
        var isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
        if (options.focusSearch && !isTouchDevice) {
            setTimeout(function () {
                if (searchInput.length && searchInput.is(':visible')) {
                    searchInput.focus();
                }
            }, TRANSITION_MS);
        }
    }

    function closeMobileDrawer() {
        sidebar.removeClass('active');
        hideBackdrop();
        unlockBodyScroll();
        sidebar.attr('aria-hidden', 'true');

        // Ensure search input loses focus and virtual keyboard dismisses
        if (searchInput && searchInput.length && document.activeElement === searchInput[0]) {
            searchInput.blur();
        }
    }

    /* ──────────────────────────────────────
       BACKDROP CLOSE
       ────────────────────────────────────── */
    function setupBackdropClose() {
        $(document).on('click', '#ch-sidebar-backdrop', function () {
            closeMobileDrawer();
        });

        // Also close on outside click (fallback)
        $(document).on('click', function (e) {
            if (getViewportTier() !== 'desktop' && sidebar.hasClass('active')) {
                if (!$(e.target).closest('.ch-sidebar, .sidebar-mobile-toggle-btn').length) {
                    closeMobileDrawer();
                }
            }
        });
    }

    /* ──────────────────────────────────────
       SWIPE GESTURE (Mobile Only: < 768px)
       ────────────────────────────────────── */
    function setupSwipeGesture() {
        var sidebarEl = sidebar[0];
        if (!sidebarEl) return;

        // Dedicated state for closing the open sidebar drawer
        var closeTouchStartX = -1;
        var closeTouchStartY = -1;
        var closeTouchDeltaX = 0;
        var isCloseSwiping = false;

        // 1. Swipe-to-close on the active sidebar drawer
        sidebarEl.addEventListener('touchstart', function (e) {
            if (!sidebar.hasClass('active')) {
                closeTouchStartX = -1;
                return;
            }
            if (e.touches && e.touches.length === 1) {
                closeTouchStartX = e.touches[0].clientX;
                closeTouchStartY = e.touches[0].clientY;
                closeTouchDeltaX = 0;
                isCloseSwiping = false;
            }
        }, { passive: true });

        sidebarEl.addEventListener('touchmove', function (e) {
            if (!sidebar.hasClass('active') || closeTouchStartX < 0) return;
            if (e.touches && e.touches.length === 1) {
                closeTouchDeltaX = e.touches[0].clientX - closeTouchStartX;
                var deltaY = Math.abs(e.touches[0].clientY - closeTouchStartY);

                // Only treat as horizontal swipe if horizontal movement clearly dominates vertical scrolling
                if (Math.abs(closeTouchDeltaX) > deltaY * 1.5 && Math.abs(closeTouchDeltaX) > 15) {
                    isCloseSwiping = true;
                }
            }
        }, { passive: true });

        sidebarEl.addEventListener('touchend', function () {
            if (sidebar.hasClass('active') && isCloseSwiping && closeTouchDeltaX < -SWIPE_THRESHOLD) {
                closeMobileDrawer();
            }
            closeTouchStartX = -1;
            closeTouchStartY = -1;
            closeTouchDeltaX = 0;
            isCloseSwiping = false;
        }, { passive: true });

        // 2. Swipe-to-open from left edge of screen
        // STRICT CONSTRAINTS:
        // - Mobile ONLY (< 768px). Never on tablets (>= 768px) or desktop!
        // - Touch MUST originate strictly within 15px of screen edge (clientX <= 15).
        // - Any touch anywhere else on the screen (clientX > 15) IMMEDIATELY clears state.
        // - If vertical movement (deltaY) occurs during scroll, IMMEDIATELY abort gesture.
        var edgeTouchStartX = -1;
        var edgeTouchStartY = -1;
        var edgeTouchDeltaX = 0;
        var isEdgeSwipeCandidate = false;

        document.addEventListener('touchstart', function (e) {
            // Reset state on EVERY touchstart to prevent stale or 0 values
            isEdgeSwipeCandidate = false;
            edgeTouchStartX = -1;
            edgeTouchStartY = -1;
            edgeTouchDeltaX = 0;

            // Only mobile tier (< 768px) and only when drawer is currently closed
            if (getViewportTier() !== 'mobile' || sidebar.hasClass('active')) {
                return;
            }

            if (e.touches && e.touches.length === 1 && e.touches[0].clientX <= 15) {
                edgeTouchStartX = e.touches[0].clientX;
                edgeTouchStartY = e.touches[0].clientY;
                isEdgeSwipeCandidate = true;
            }
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!isEdgeSwipeCandidate || edgeTouchStartX < 0 || getViewportTier() !== 'mobile' || sidebar.hasClass('active')) {
                return;
            }

            if (e.touches && e.touches.length === 1) {
                edgeTouchDeltaX = e.touches[0].clientX - edgeTouchStartX;
                var deltaY = Math.abs(e.touches[0].clientY - edgeTouchStartY);

                // If user is scrolling vertically anywhere on the page, cancel edge swipe immediately
                if (deltaY > Math.abs(edgeTouchDeltaX) || deltaY > 15) {
                    isEdgeSwipeCandidate = false;
                    edgeTouchStartX = -1;
                    return;
                }
            }
        }, { passive: true });

        document.addEventListener('touchend', function () {
            if (isEdgeSwipeCandidate && edgeTouchStartX >= 0 && edgeTouchDeltaX > (SWIPE_THRESHOLD * 1.5) && !sidebar.hasClass('active')) {
                if (getViewportTier() === 'mobile') {
                    openMobileDrawer({ focusSearch: false });
                }
            }
            isEdgeSwipeCandidate = false;
            edgeTouchStartX = -1;
            edgeTouchStartY = -1;
            edgeTouchDeltaX = 0;
        }, { passive: true });
    }

    /* ──────────────────────────────────────
       SEARCH / FILTER (Ctrl+K / Cmd+K)
       ────────────────────────────────────── */
    function setupSearch() {
        if (!searchInput.length) return;

        // Keyboard shortcut: '/' (forward slash) to quickly filter sidebar menu
        $(document).on('keydown', function (e) {
            var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
            if (isTouch) return; // Never intercept slash on touch devices

            var tag = (e.target.tagName || '').toLowerCase();
            var isEditing = tag === 'input' || tag === 'textarea' || e.target.isContentEditable;
            if (e.key === '/' && !isEditing && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                if (getViewportTier() !== 'desktop' && !sidebar.hasClass('active')) {
                    openMobileDrawer({ focusSearch: true });
                } else {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });

        // Keyboard shortcut: Ctrl+K or Cmd+K fallback for mobile/sidebar drawer
        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                var globalInput = document.getElementById('globalSearchInput');
                // On desktop when global topbar search is present, let global-search.js take priority
                if (getViewportTier() === 'desktop' && globalInput) {
                    return;
                }

                e.preventDefault();
                if (getViewportTier() !== 'desktop' && !sidebar.hasClass('active')) {
                    openMobileDrawer({ focusSearch: true });
                } else {
                    searchInput.focus();
                }
            }
        });

        // Filter on input
        searchInput.on('input', debounce(function () {
            var term = $(this).val().toLowerCase().trim();
            filterSidebar(term);
            toggleSearchClear(term.length > 0);
        }, 150));

        // Clear button
        searchClear.on('click', function () {
            searchInput.val('').trigger('input').focus();
        });

        // Search input keyboard interactions: Enter, ArrowDown, Ctrl+K, Escape
        searchInput.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var val = $(this).val().trim();
                var visibleLinks = sidebar.find('.ch-sidebar-nav-scrollable .nav-link:visible').filter(function () {
                    var href = $(this).attr('href');
                    return !$(this).is('[data-toggle="collapse"], [data-bs-toggle="collapse"]') && href && href !== '#' && href !== 'javascript:void(0);';
                });

                if (visibleLinks.length === 1) {
                    var targetHref = visibleLinks.first().attr('href');
                    if (targetHref) {
                        window.location.href = targetHref;
                        return;
                    }
                }

                // If 0 matches or multiple matches, hand off to global omnibar search
                if (typeof window.triggerGlobalSearch === 'function') {
                    window.triggerGlobalSearch(val);
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var focusableLinks = sidebar.find('.ch-sidebar-nav-scrollable .nav-link:visible');
                if (focusableLinks.length) {
                    focusableLinks.first().focus();
                }
                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                var currentQuery = $(this).val().trim();
                if (typeof window.triggerGlobalSearch === 'function') {
                    window.triggerGlobalSearch(currentQuery);
                }
                return;
            }

            if (e.key === 'Escape') {
                if ($(this).val().length > 0) {
                    $(this).val('').trigger('input');
                    e.stopPropagation();
                } else {
                    $(this).blur();
                    if (getViewportTier() !== 'desktop') {
                        closeMobileDrawer();
                    }
                }
            }
        });
    }

    function filterSidebar(term) {
        sidebar.find('.ch-sidebar-no-results, .ch-sidebar-search-bridge-bar').remove();

        if (!term) {
            // Restore all items and section headers
            sidebar.find('.ch-sidebar-nav-scrollable li, .ch-sidebar-nav-scrollable .nav-item')
                .removeClass('ch-sidebar-search-hidden')
                .removeAttr('style');
            sidebar.find('.ch-sidebar-nav-scrollable .collapse').each(function () {
                var collapseEl = $(this);
                var toggle = collapseEl.siblings('.nav-link[data-toggle="collapse"], .nav-link[data-bs-toggle="collapse"]');
                if (toggle.attr('aria-expanded') !== 'true') {
                    collapseEl.removeClass('show');
                }
            });
            return;
        }

        var anyMatch = false;
        var matchCount = 0;

        // Reset search-hidden state
        sidebar.find('.ch-sidebar-nav-scrollable li, .ch-sidebar-nav-scrollable .nav-item')
            .removeClass('ch-sidebar-search-hidden')
            .removeAttr('style');

        // Step 1: Section-aware filtering
        var headerLis = sidebar.find('.ch-sidebar-section-header, .ch-sidebar-nav-scrollable li:has(.nav-item-head)');

        headerLis.each(function () {
            var headerLi = $(this);
            var headerText = headerLi.find('.nav-item-head').text().toLowerCase().trim();
            var sectionItems = headerLi.nextUntil('.ch-sidebar-section-header, li:has(.nav-item-head)', '.nav-item');
            var sectionHasMatch = false;
            var headerMatches = headerText.length > 0 && headerText.indexOf(term) !== -1;

            if (headerMatches) {
                // Whole section matches by heading name
                sectionItems.each(function () {
                    var item = $(this);
                    item.removeClass('ch-sidebar-search-hidden').removeAttr('style');
                    item.find('.nav-item').removeClass('ch-sidebar-search-hidden').removeAttr('style');
                    var directLinks = item.find('.nav-link').not('[data-toggle="collapse"], [data-bs-toggle="collapse"]');
                    matchCount += directLinks.length || 1;
                });
                sectionHasMatch = true;
                anyMatch = true;
            } else {
                sectionItems.each(function () {
                    var item = $(this);
                    var parentLink = item.children('.nav-link');
                    var parentText = (parentLink.find('.menu-title').text() || parentLink.text()).toLowerCase().trim();
                    var subMenu = item.find('.sub-menu');
                    var itemHasMatch = false;

                    if (parentText.indexOf(term) !== -1) {
                        itemHasMatch = true;
                    }

                    if (subMenu.length) {
                        var subLinks = subMenu.find('.nav-link');
                        var subMatchCount = 0;
                        subLinks.each(function () {
                            var subLink = $(this);
                            var subText = subLink.text().toLowerCase().trim();
                            var subItem = subLink.closest('.nav-item');
                            if (subText.indexOf(term) !== -1 || parentText.indexOf(term) !== -1) {
                                subItem.removeClass('ch-sidebar-search-hidden').removeAttr('style');
                                subMatchCount++;
                                matchCount++;
                            } else {
                                subItem.addClass('ch-sidebar-search-hidden').attr('style', 'display: none !important;');
                            }
                        });

                        if (subMatchCount > 0) {
                            itemHasMatch = true;
                            if (!isRailMode()) {
                                item.find('.collapse').addClass('show');
                                item.children('.nav-link').attr('aria-expanded', 'true');
                            }
                        }
                    }

                    if (itemHasMatch) {
                        item.removeClass('ch-sidebar-search-hidden').removeAttr('style');
                        sectionHasMatch = true;
                        anyMatch = true;
                        if (!subMenu.length) {
                            matchCount++;
                        }
                    } else {
                        item.addClass('ch-sidebar-search-hidden').attr('style', 'display: none !important;');
                    }
                });
            }

            // Toggle section header: only show if section has matching items
            if (sectionHasMatch) {
                headerLi.removeClass('ch-sidebar-search-hidden').removeAttr('style');
            } else {
                headerLi.addClass('ch-sidebar-search-hidden').attr('style', 'display: none !important;');
            }
        });

        // Step 2: Items before first header (e.g., Dashboard, Profile)
        var preItems = headerLis.first().prevAll('.nav-item');
        preItems.each(function () {
            var item = $(this);
            var text = item.find('.nav-link').text().toLowerCase();
            var isMatch = text.indexOf(term) !== -1;
            if (isMatch) {
                item.removeClass('ch-sidebar-search-hidden').removeAttr('style');
                anyMatch = true;
                matchCount++;
            } else {
                item.addClass('ch-sidebar-search-hidden').attr('style', 'display: none !important;');
            }
        });

        // Show synergy bridge bar if items match
        if (anyMatch && !isRailMode()) {
            var matchLabel = matchCount === 1 ? '1 item in menu' : matchCount + ' items in menu';
            var bridgeBar = $('<div class="ch-sidebar-search-bridge-bar">' +
                '<span><i class="mdi mdi-filter-outline mr-1"></i> ' + matchLabel + '</span>' +
                '<button type="button" class="btn btn-link p-0 ch-sidebar-bridge-btn" id="ch-sidebar-bridge-global" title="Search all 105+ hospital routes in Global Omnibar">' +
                'Search all <kbd>↵</kbd>' +
                '</button>' +
                '</div>');
            sidebar.find('.ch-sidebar-nav-scrollable').prepend(bridgeBar);

            bridgeBar.find('#ch-sidebar-bridge-global').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var currentTerm = (searchInput && searchInput.length ? searchInput.val().trim() : '') || term;
                if (typeof window.triggerGlobalSearch === 'function') {
                    window.triggerGlobalSearch(currentTerm);
                }
            });
        }

        // Show "no results" message with global search bridge if no match found
        if (!anyMatch) {
            var noResults = $('<div class="ch-sidebar-no-results text-center py-3">' +
                '<div style="opacity: 0.8; margin-bottom: 8px;"><i class="mdi mdi-magnify-close mr-1"></i> No menu items match "<strong>' +
                escapeHtml(term) + '</strong>"</div>' +
                '<button type="button" class="ch-sidebar-search-all-btn" id="ch-sidebar-global-bridge-btn">' +
                '<i class="mdi mdi-magnify mr-1"></i> Search all routes with Global Search' +
                '</button>' +
                '</div>');
            sidebar.find('.ch-sidebar-nav-scrollable').append(noResults);

            noResults.find('#ch-sidebar-global-bridge-btn').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var currentTerm = (searchInput && searchInput.length ? searchInput.val().trim() : '') || term;
                if (typeof window.triggerGlobalSearch === 'function') {
                    window.triggerGlobalSearch(currentTerm);
                }
            });
        }
    }

    function toggleSearchClear(show) {
        if (searchClear.length) {
            searchClear.toggle(show);
        }
    }

    /* ──────────────────────────────────────
       SECTION COLLAPSE MEMORY
       ────────────────────────────────────── */
    function setupSectionCollapse() {
        // Restore collapsed state
        var collapsed = getCollapsedSections();
        sidebar.find('[data-section-toggle]').each(function () {
            var btn = $(this);
            var sectionId = btn.data('section-toggle');
            var target = $('#section-' + sectionId);

            if (collapsed.indexOf(sectionId) !== -1) {
                target.addClass('ch-section-collapsed');
                btn.addClass('ch-section-collapsed');
                btn.attr('aria-expanded', 'false');
            }

            btn.on('click', function (e) {
                e.preventDefault();
                target.toggleClass('ch-section-collapsed');
                btn.toggleClass('ch-section-collapsed');
                var isCollapsed = target.hasClass('ch-section-collapsed');
                btn.attr('aria-expanded', !isCollapsed);
                saveCollapsedSection(sectionId, isCollapsed);
            });
        });
    }

    function getCollapsedSections() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY_SECTIONS)) || [];
        } catch (e) {
            return [];
        }
    }

    function saveCollapsedSection(id, isCollapsed) {
        var sections = getCollapsedSections();
        var idx = sections.indexOf(id);
        if (isCollapsed && idx === -1) {
            sections.push(id);
        } else if (!isCollapsed && idx !== -1) {
            sections.splice(idx, 1);
        }
        try { localStorage.setItem(STORAGE_KEY_SECTIONS, JSON.stringify(sections)); } catch (e) { /* quota */ }
    }

    /* ──────────────────────────────────────
       KEYBOARD NAVIGATION
       ────────────────────────────────────── */
    function setupKeyboardNavigation() {
        sidebar.on('keydown', function (e) {
            var focusable = sidebar.find('.nav-link:visible');
            var current = focusable.index(document.activeElement);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (current < focusable.length - 1) {
                        focusable.eq(current + 1).focus();
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (current > 0) {
                        focusable.eq(current - 1).focus();
                    } else {
                        searchInput.focus();
                    }
                    break;

                case 'Escape':
                    if (getViewportTier() !== 'desktop' && sidebar.hasClass('active')) {
                        closeMobileDrawer();
                    }
                    break;

                case 'Home':
                    e.preventDefault();
                    focusable.first().focus();
                    break;

                case 'End':
                    e.preventDefault();
                    focusable.last().focus();
                    break;
            }
        });
    }

    /* ──────────────────────────────────────
       ACTIVE ROUTE DETECTION
       ────────────────────────────────────── */
    function setupActiveRouteDetection() {
        // Fix: Remove incorrectly added active classes by misc.js
        $('.sidebar .nav-item').removeClass('active');
        $('.sidebar .nav-link').removeClass('active');

        // Re-add active classes based on current URL
        var currentPath = window.location.pathname;
        var currentSearch = window.location.search;
        var currentUrl = currentPath + currentSearch;

        sidebar.find('.nav-link').each(function () {
            var href = $(this).attr('href');
            if (!href || href === '#' || href === 'javascript:void(0);') return;

            try {
                var linkUrl = new URL(href, window.location.origin);
                var linkPath = linkUrl.pathname;
                var linkSearch = linkUrl.search;
                var linkFull = linkPath + linkSearch;

                // Exact match or prefix match for route patterns
                if (linkFull === currentUrl || linkPath === currentPath) {
                    $(this).addClass('active');
                    $(this).closest('.nav-item').addClass('active');

                    // Auto-expand parent submenu
                    if ($(this).parents('.sub-menu').length) {
                        var parentCollapse = $(this).closest('.collapse');
                        parentCollapse.addClass('show');
                        parentCollapse.siblings('.nav-link').addClass('active').attr('aria-expanded', 'true');
                        parentCollapse.closest('.nav-item').addClass('active');
                    }
                }
            } catch (e) {
                // Malformed URL, skip
            }
        });
    }

    /* ──────────────────────────────────────
       PORTAL-BASED FLOATING FLYOUT
       For Tablet (80px rail) & Desktop Icon-Only (72px rail)
       Solves container overflow clipping glitches completely
       ────────────────────────────────────── */
    var flyoutEl, flyoutHeader, flyoutGroup, flyoutBody, flyoutTimer, activeFlyoutItem;

    function createFlyoutContainer() {
        if ($('#ch-sidebar-flyout').length) {
            flyoutEl = $('#ch-sidebar-flyout');
            flyoutHeader = flyoutEl.find('.ch-sidebar-flyout-header-title');
            flyoutGroup = flyoutEl.find('.ch-sidebar-flyout-header-group');
            flyoutBody = flyoutEl.find('.ch-sidebar-flyout-body');
            return;
        }

        flyoutEl = $([
            '<div id="ch-sidebar-flyout" class="ch-sidebar-flyout" role="dialog" aria-modal="false" aria-label="Submenu">',
            '  <div class="ch-sidebar-flyout-header">',
            '    <div class="ch-sidebar-flyout-header-content">',
            '      <div class="ch-sidebar-flyout-header-group"></div>',
            '      <div class="ch-sidebar-flyout-header-title"></div>',
            '    </div>',
            '    <button type="button" class="ch-sidebar-flyout-close" aria-label="Close submenu">',
            '      <i class="mdi mdi-close"></i>',
            '    </button>',
            '  </div>',
            '  <div class="ch-sidebar-flyout-body"></div>',
            '</div>'
        ].join(''));

        $('body').append(flyoutEl);
        flyoutHeader = flyoutEl.find('.ch-sidebar-flyout-header-title');
        flyoutGroup = flyoutEl.find('.ch-sidebar-flyout-header-group');
        flyoutBody = flyoutEl.find('.ch-sidebar-flyout-body');

        // Close button handler
        flyoutEl.on('click', '.ch-sidebar-flyout-close', function (e) {
            e.stopPropagation();
            hideFlyout(true);
        });

        // Keep open while hovering flyout
        flyoutEl.on('mouseenter', function () {
            clearTimeout(flyoutTimer);
        });

        flyoutEl.on('mouseleave', function () {
            if (getViewportTier() !== 'tablet') {
                flyoutTimer = setTimeout(function () {
                    hideFlyout(true);
                }, 150);
            }
        });

        // Close on clicking links inside flyout
        flyoutEl.on('click', '.ch-sidebar-flyout-link, .nav-link', function () {
            hideFlyout(true);
        });
    }

    function isRailMode() {
        var tier = getViewportTier();
        return tier === 'tablet' || (tier === 'desktop' && $('body').hasClass('sidebar-icon-only'));
    }

    function showFlyoutFor(navItem, immediate) {
        if (!isRailMode()) return;

        var subMenu = navItem.find('.sub-menu');
        if (!subMenu.length) {
            hideFlyout(true);
            return;
        }

        clearTimeout(flyoutTimer);

        activeFlyoutItem = navItem;
        $('.ch-sidebar .nav-item').removeClass('ch-sidebar-flyout-active');
        navItem.addClass('ch-sidebar-flyout-active');

        // Extract title and icon
        var parentLink = navItem.children('.nav-link');
        var iconEl = parentLink.find('.menu-icon').clone();
        iconEl.removeAttr('style').removeClass('mr-0 mb-0');
        var iconHtml = iconEl[0] ? iconEl[0].outerHTML : '';
        var titleText = parentLink.find('.menu-title').text().trim() || parentLink.attr('data-title') || '';

        // Extract group name
        var groupName = navItem.attr('data-group') || navItem.prevAll('li:has(.nav-item-head)').first().find('.nav-item-head').text().trim() || '';

        if (groupName) {
            flyoutGroup.text(groupName).show();
        } else {
            flyoutGroup.hide();
        }

        flyoutHeader.html((iconHtml ? iconHtml + ' ' : '') + '<span>' + escapeHtml(titleText) + '</span>');

        // Cleanly construct submenu links for flyout portal
        flyoutBody.empty();
        var menuList = $('<ul class="ch-sidebar-flyout-menu"></ul>');

        var foundLinks = subMenu.find('.nav-link');
        if (!foundLinks.length) {
            foundLinks = navItem.find('.collapse .nav-link, [id^="sidebar-"] .nav-link').not(parentLink);
        }

        foundLinks.each(function () {
            var origLink = $(this);
            var href = origLink.attr('href') || 'javascript:void(0);';
            var linkText = origLink.clone().children('.menu-icon, .badge, i').remove().end().text().trim() || origLink.text().trim();
            var origId = origLink.attr('id') || '';
            var isActive = origLink.hasClass('active') || origLink.closest('.nav-item').hasClass('active');
            var icon = origLink.find('i').clone();

            var li = $('<li class="ch-sidebar-flyout-item"></li>');
            var a = $('<a class="ch-sidebar-flyout-link" href="' + escapeHtml(href) + '"></a>');
            if (isActive) a.addClass('active');
            if (origId) a.attr('id', 'flyout-' + origId);
            if (icon.length) {
                icon.css('margin-right', '8px');
                a.append(icon);
            }
            a.append($('<span></span>').text(linkText));
            li.append(a);
            menuList.append(li);
        });

        flyoutBody.append(menuList);

        // Calculate position
        var itemEl = navItem[0];
        var rect = itemEl.getBoundingClientRect();
        var railWidth = getViewportTier() === 'tablet' ? 80 : 72;
        var topPos = rect.top;
        var windowHeight = $(window).height();

        flyoutEl.css({
            display: 'flex',
            top: topPos + 'px',
            left: railWidth + 'px'
        });

        // Clamp to viewport
        var flyoutHeight = flyoutEl.outerHeight();
        if (topPos + flyoutHeight > windowHeight - 16) {
            topPos = Math.max(16, windowHeight - flyoutHeight - 16);
            flyoutEl.css('top', topPos + 'px');
        }

        // Trigger smooth entrance
        requestAnimationFrame(function () {
            flyoutEl.addClass('ch-sidebar-flyout--visible');
        });
    }

    function hideFlyout(immediate) {
        if (!flyoutEl) return;
        clearTimeout(flyoutTimer);

        if (immediate) {
            flyoutEl.removeClass('ch-sidebar-flyout--visible');
            setTimeout(function () {
                if (!flyoutEl.hasClass('ch-sidebar-flyout--visible')) {
                    flyoutEl.css('display', 'none');
                }
            }, 180);
            if (activeFlyoutItem) {
                activeFlyoutItem.removeClass('ch-sidebar-flyout-active');
                activeFlyoutItem = null;
            }
        } else {
            flyoutTimer = setTimeout(function () {
                hideFlyout(true);
            }, 150);
        }
    }

    function setupFlyoutTriggers() {
        createFlyoutContainer();

        // 1. Mouse Hover (Desktop Icon-Only and Tablet pointer devices)
        sidebar.on('mouseenter', '.ch-sidebar-nav-scrollable .nav-item', function () {
            if (!isRailMode()) return;
            var item = $(this);
            if (item.find('.sub-menu').length) {
                showFlyoutFor(item);
            } else {
                hideFlyout();
            }
        });

        sidebar.on('mouseleave', '.ch-sidebar-nav-scrollable .nav-item', function () {
            if (!isRailMode()) return;
            flyoutTimer = setTimeout(function () {
                hideFlyout(true);
            }, 150);
        });

        // 2. Click / Tap (Touch Tablets & iPads)
        sidebar.on('click', '.ch-sidebar-nav-scrollable .nav-item > .nav-link', function (e) {
            if (!isRailMode()) return;

            var parentItem = $(this).closest('.nav-item');
            if (parentItem.find('.sub-menu').length) {
                e.preventDefault();
                e.stopPropagation();

                if (activeFlyoutItem && activeFlyoutItem[0] === parentItem[0] && flyoutEl.hasClass('ch-sidebar-flyout--visible')) {
                    hideFlyout(true);
                } else {
                    showFlyoutFor(parentItem, true);
                }
            } else {
                hideFlyout(true);
            }
        });

        // 3. Robust in-flow collapse toggle for Desktop Full & Mobile modes
        sidebar.on('click', '.nav-link[data-toggle="collapse"], .nav-link[data-bs-toggle="collapse"]', function (e) {
            if (isRailMode()) return; // Handled by flyout in rail mode

            e.preventDefault();
            var toggle = $(this);
            var targetSelector = toggle.attr('data-target') || toggle.attr('data-bs-target') || toggle.attr('href');
            if (targetSelector && targetSelector.startsWith('#')) {
                var collapseEl = $(targetSelector);
                if (collapseEl.length) {
                    var isCurrentlyOpen = collapseEl.hasClass('show');
                    if (isCurrentlyOpen) {
                        collapseEl.removeClass('show');
                        toggle.attr('aria-expanded', 'false');
                    } else {
                        // Collapse sibling submenus for clean accordion behavior
                        collapseEl.closest('.ch-sidebar-nav-scrollable').find('.collapse.show').not(collapseEl).removeClass('show').siblings('.nav-link').attr('aria-expanded', 'false');
                        collapseEl.addClass('show');
                        toggle.attr('aria-expanded', 'true');
                    }
                }
            }
        });

        // Close on clicking outside
        $(document).on('click', function (e) {
            if (isRailMode() && flyoutEl && flyoutEl.hasClass('ch-sidebar-flyout--visible')) {
                if (!$(e.target).closest('#ch-sidebar-flyout, .ch-sidebar').length) {
                    hideFlyout(true);
                }
            }
        });

        // Close on Escape key
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                hideFlyout(true);
            }
        });

        // Hide when scrolling the rail
        sidebar.find('.ch-sidebar-nav-scrollable').on('scroll', function () {
            if (isRailMode()) {
                hideFlyout(true);
            }
        });
    }

    /* ──────────────────────────────────────
       GROUP ATTRIBUTES (Tablet Visual Structure)
       ────────────────────────────────────── */
    function setupGroupAttributes() {
        var currentGroup = '';
        sidebar.find('.ch-sidebar-nav-scrollable').children().each(function () {
            var item = $(this);
            var head = item.find('.nav-item-head');
            if (head.length) {
                currentGroup = head.text().trim();
                item.attr('data-group', currentGroup);
            } else if (item.hasClass('nav-item') && currentGroup) {
                if (!item.attr('data-group')) {
                    item.attr('data-group', currentGroup);
                }
            }
        });
    }

    /* ──────────────────────────────────────
       RESIZE HANDLER
       ────────────────────────────────────── */
    function setupResizeHandler() {
        var resizeTimeout;
        $(window).on('resize', function () {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function () {
                var newWidth = window.innerWidth;
                if (newWidth === currentWidth) return;

                var oldTier = getTierForWidth(currentWidth);
                var newTier = getTierForWidth(newWidth);
                currentWidth = newWidth;

                if (oldTier !== newTier) {
                    onTierChange(oldTier, newTier);
                }
            }, 100);
        });
    }

    function getTierForWidth(w) {
        if (w < BREAKPOINT_MOBILE)  return 'mobile';
        if (w < BREAKPOINT_TABLET)  return 'tablet';
        return 'desktop';
    }

    function onTierChange(oldTier, newTier) {
        // Always close mobile drawer when resizing up
        if (oldTier !== 'desktop' && newTier === 'desktop') {
            closeMobileDrawer();
        }

        // Restore icon-only state when entering desktop
        if (newTier === 'desktop') {
            restoreSidebarState();
        } else {
            $('body').removeClass('sidebar-icon-only');
        }

        // Always close flyout on tier change
        hideFlyout(true);
    }

    /* ──────────────────────────────────────
       ARIA ATTRIBUTES
       ────────────────────────────────────── */
    function addAriaAttributes() {
        sidebar.attr({
            'role': 'navigation',
            'aria-label': 'Main navigation'
        });

        // Set aria-hidden based on viewport
        if (getViewportTier() !== 'desktop') {
            sidebar.attr('aria-hidden', 'true');
        }

        // Mark current page
        sidebar.find('.nav-link.active').attr('aria-current', 'page');

        // Ensure all collapse toggles have aria-expanded
        sidebar.find('.nav-link[data-toggle="collapse"], .nav-link[data-bs-toggle="collapse"]').each(function () {
            if (!$(this).attr('aria-expanded')) {
                $(this).attr('aria-expanded', 'false');
            }
        });
    }

    /* ──────────────────────────────────────
       DATA-TITLE TOOLTIPS
       ────────────────────────────────────── */
    function addDataTitleAttributes() {
        sidebar.find('.nav-link').each(function () {
            var title = $(this).find('.menu-title').text().trim();
            if (title && !$(this).attr('data-title')) {
                $(this).attr('data-title', title);
            }
        });
    }

    /* ──────────────────────────────────────
       UTILITY FUNCTIONS
       ────────────────────────────────────── */
    function debounce(fn, delay) {
        var timer;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ──────────────────────────────────────
       GLOBAL SYNERGY API
       ────────────────────────────────────── */

    /**
     * Trigger sidebar menu search with optional prefilled query
     */
    window.triggerSidebarSearch = function (query) {
        if (getViewportTier() !== 'desktop' && !sidebar.hasClass('active')) {
            openMobileDrawer();
        }

        if (searchInput && searchInput.length) {
            if (typeof query === 'string') {
                searchInput.val(query);
                filterSidebar(query.toLowerCase().trim());
                toggleSearchClear(query.trim().length > 0);
            }
            searchInput.focus();
            searchInput.select();
        }
    };

    /**
     * Locate and reveal a link in the sidebar by URL or route path
     * Expands collapsed parent submenus and sections, scrolls into view,
     * and triggers a highlighting pulse animation.
     */
    window.revealInSidebar = function (url) {
        if (!url || !sidebar.length) return false;

        var targetPath = url;
        try {
            targetPath = new URL(url, window.location.origin).pathname;
        } catch (e) {
            // Keep targetPath as url string
        }

        // Clear any active search in sidebar that might hide the target item
        if (searchInput.length && searchInput.val().trim().length > 0) {
            searchInput.val('');
            filterSidebar('');
            toggleSearchClear(false);
        }

        // Locate link by href matching url or pathname
        var matchingLink = sidebar.find('.ch-sidebar-nav-scrollable a.nav-link').filter(function () {
            var href = $(this).attr('href');
            if (!href || href === '#' || href === 'javascript:void(0);') return false;
            try {
                var linkPath = new URL(href, window.location.origin).pathname;
                return href === url || linkPath === targetPath || href.indexOf(targetPath) !== -1;
            } catch (err) {
                return href === url || href === targetPath;
            }
        }).first();

        if (!matchingLink.length) {
            return false;
        }

        // If on mobile, open drawer
        if (getViewportTier() !== 'desktop' && !sidebar.hasClass('active')) {
            openMobileDrawer();
        }

        // If in desktop icon-only mode, un-collapse sidebar to show full menu
        if ($('body').hasClass('sidebar-icon-only')) {
            $('body').removeClass('sidebar-icon-only');
            saveSidebarState('full');
        }

        // Un-collapse parent section group if collapsed
        var parentSection = matchingLink.closest('.ch-sidebar-section');
        if (parentSection.hasClass('ch-section-collapsed')) {
            parentSection.removeClass('ch-section-collapsed');
            var sectionId = parentSection.attr('id') ? parentSection.attr('id').replace('section-', '') : '';
            if (sectionId) {
                saveCollapsedSection(sectionId, false);
                $('[data-section-toggle="' + sectionId + '"]').removeClass('ch-section-collapsed').attr('aria-expanded', 'true');
            }
        }

        // Un-collapse parent sub-menu collapse if in dropdown
        var parentCollapse = matchingLink.closest('.collapse');
        if (parentCollapse.length) {
            parentCollapse.addClass('show');
            var collapseToggle = parentCollapse.siblings('.nav-link[data-toggle="collapse"], .nav-link[data-bs-toggle="collapse"]');
            collapseToggle.attr('aria-expanded', 'true');
        }

        var targetContainer = matchingLink.closest('.nav-item');

        // Scroll smoothly into view
        setTimeout(function () {
            var scrollable = sidebar.find('.ch-sidebar-nav-scrollable');
            if (scrollable.length && matchingLink.length) {
                var linkTop = matchingLink.offset().top;
                var scrollTop = scrollable.scrollTop();
                var scrollableTop = scrollable.offset().top;
                var targetOffset = linkTop - scrollableTop + scrollTop - 80;

                scrollable.animate({ scrollTop: Math.max(0, targetOffset) }, 250);
            }

            // Apply reveal pulse animation
            targetContainer.addClass('ch-sidebar-item-revealed');
            matchingLink.focus();

            setTimeout(function () {
                targetContainer.removeClass('ch-sidebar-item-revealed');
            }, 2500);
        }, 50);

        return true;
    };

})(jQuery);
