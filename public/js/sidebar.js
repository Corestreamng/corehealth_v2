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
    var touchStartX = 0;
    var touchStartY = 0;
    var touchDeltaX = 0;
    var isSwiping   = false;
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
            openMobileDrawer();
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

    function openMobileDrawer() {
        sidebar.addClass('active');
        showBackdrop();
        lockBodyScroll();
        sidebar.attr('aria-hidden', 'false');

        // Focus the search input for quick access
        setTimeout(function () {
            if (searchInput.length && searchInput.is(':visible')) {
                searchInput.focus();
            }
        }, TRANSITION_MS);
    }

    function closeMobileDrawer() {
        sidebar.removeClass('active');
        hideBackdrop();
        unlockBodyScroll();
        sidebar.attr('aria-hidden', 'true');
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
       SWIPE GESTURE (Mobile)
       ────────────────────────────────────── */
    function setupSwipeGesture() {
        var sidebarEl = sidebar[0];
        if (!sidebarEl) return;

        // Swipe-to-close on the sidebar
        sidebarEl.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isSwiping = false;
        }, { passive: true });

        sidebarEl.addEventListener('touchmove', function (e) {
            touchDeltaX = e.touches[0].clientX - touchStartX;
            var deltaY = Math.abs(e.touches[0].clientY - touchStartY);

            // Only treat as horizontal swipe if horizontal movement dominates
            if (Math.abs(touchDeltaX) > deltaY && Math.abs(touchDeltaX) > 10) {
                isSwiping = true;
            }
        }, { passive: true });

        sidebarEl.addEventListener('touchend', function () {
            if (isSwiping && touchDeltaX < -SWIPE_THRESHOLD) {
                closeMobileDrawer();
            }
            isSwiping = false;
            touchDeltaX = 0;
        }, { passive: true });

        // Swipe-to-open from left edge of screen
        document.addEventListener('touchstart', function (e) {
            if (e.touches[0].clientX < 20 && !sidebar.hasClass('active')) {
                touchStartX = e.touches[0].clientX;
                isSwiping = false;
            }
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (touchStartX < 20 && !sidebar.hasClass('active')) {
                touchDeltaX = e.touches[0].clientX - touchStartX;
                if (touchDeltaX > 10) {
                    isSwiping = true;
                }
            }
        }, { passive: true });

        document.addEventListener('touchend', function () {
            if (isSwiping && touchDeltaX > SWIPE_THRESHOLD && !sidebar.hasClass('active')) {
                if (getViewportTier() !== 'desktop') {
                    openMobileDrawer();
                }
            }
            isSwiping = false;
            touchDeltaX = 0;
            touchStartX = 0;
        }, { passive: true });
    }

    /* ──────────────────────────────────────
       SEARCH / FILTER (Ctrl+K / Cmd+K)
       ────────────────────────────────────── */
    function setupSearch() {
        if (!searchInput.length) return;

        // Keyboard shortcut: Ctrl+K or Cmd+K
        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (getViewportTier() !== 'desktop' && !sidebar.hasClass('active')) {
                    openMobileDrawer();
                }
                searchInput.focus();
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

        // ESC clears search
        searchInput.on('keydown', function (e) {
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
        sidebar.find('.ch-sidebar-no-results').remove();

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
                            } else {
                                subItem.addClass('ch-sidebar-search-hidden').attr('style', 'display: none !important;');
                            }
                        });

                        if (subMatchCount > 0) {
                            itemHasMatch = true;
                            item.find('.collapse').addClass('show');
                            item.children('.nav-link').attr('aria-expanded', 'true');
                        }
                    }

                    if (itemHasMatch) {
                        item.removeClass('ch-sidebar-search-hidden').removeAttr('style');
                        sectionHasMatch = true;
                        anyMatch = true;
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
            } else {
                item.addClass('ch-sidebar-search-hidden').attr('style', 'display: none !important;');
            }
        });

        // Show "no results" message if no match found
        if (!anyMatch) {
            var noResults = $('<div class="ch-sidebar-no-results text-center py-3 text-muted" style="font-size: 13px;"><i class="mdi mdi-magnify mr-1"></i> No menu items match "<strong>' +
                escapeHtml(term) + '</strong>"</div>');
            sidebar.find('.ch-sidebar-nav-scrollable').append(noResults);
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
        flyoutEl.on('click', '.nav-link', function () {
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

})(jQuery);
