/**
 * CoreHealth v2 — Operational Cockpit Controller
 * High-density zero-scroll multi-column clinical dashboard
 * Chart.js 2.9.4 compatible | PSR / JS Strict mode
 */
(function (window, document, $) {
    'use strict';

    var DashboardCockpit = {
        config: {
            summaryUrl: '/dashboard/data/cockpit-summary',
            refreshIntervalMs: 60000,
            primaryColor: '#011b33',
            userRoles: []
        },

        state: {
            data: null,
            activeDomain: 'cockpit',
            chartInstance: null,
            chartInstance2: null,
            refreshTimer: null,
            autoRefreshEnabled: true,
            hasInitializedDomain: false,
            cachedLaunchpadHtml: '',
            queueViewMode: (function () {
                try {
                    return localStorage.getItem('corehealth_queue_view_mode') || 'stream';
                } catch (e) {
                    return 'stream';
                }
            })()
        },

        init: function (customConfig) {
            if (customConfig) {
                $.extend(this.config, customConfig);
            }

            // Cache default launchpad HTML
            this.state.cachedLaunchpadHtml = $('#dash-shortcuts-dock').html();

            this.setupClock();
            this.setupEventListeners();
            this.setupMobileTabs();
            this.applyQueueViewMode();
            this.fetchSummary(false);
            this.startAutoRefresh();
        },

        setupClock: function () {
            var updateClock = function () {
                var now = new Date();
                var timeStr = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
                var dateStr = now.toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric'
                });
                $('#dash-live-clock').text(dateStr + ' • ' + timeStr);
            };
            updateClock();
            setInterval(updateClock, 1000);
        },

        setupMobileTabs: function () {
            $(document).on('click', '.dash-viewport-tab-btn, .dash-mobile-tab-btn', function (e) {
                e.preventDefault();
                var targetCol = $(this).data('target-col');

                $('.dash-viewport-tab-btn, .dash-mobile-tab-btn').removeClass('active');
                $(this).addClass('active');

                if ($(window).width() < 992) {
                    $('.dash-column').removeClass('mobile-active');
                    $('.' + targetCol).addClass('mobile-active');
                }
            });

            // Ensure active column state on tablet / mobile resize
            $(window).on('resize', function () {
                if ($(window).width() < 992) {
                    if (!$('.dash-column.mobile-active').length) {
                        $('.dash-col-pipeline').addClass('mobile-active');
                        $('.dash-viewport-tab-btn[data-target-col="dash-col-pipeline"]').addClass('active');
                    }
                } else {
                    $('.dash-column').removeClass('mobile-active');
                }
            });

            // Set initial mobile-active column on load
            if ($(window).width() < 992 && !$('.dash-column.mobile-active').length) {
                $('.dash-col-pipeline').addClass('mobile-active');
                $('.dash-viewport-tab-btn[data-target-col="dash-col-pipeline"]').addClass('active');
            }
        },

        toggleQueueViewMode: function () {
            this.state.queueViewMode = this.state.queueViewMode === 'stream' ? 'table' : 'stream';
            try {
                localStorage.setItem('corehealth_queue_view_mode', this.state.queueViewMode);
            } catch (e) {}
            this.applyQueueViewMode();
        },

        applyQueueViewMode: function () {
            var isTable = this.state.queueViewMode === 'table';
            if (isTable) {
                $('#dash-queue-stream').addClass('d-none');
                $('#dash-ledger-table-wrap').removeClass('d-none');
                $('#icon-queue-view-toggle').removeClass('mdi-view-headline').addClass('mdi-view-grid-outline');
                $('#btn-toggle-queue-view').attr('title', 'Switch to Queue Cards Stream');
            } else {
                $('#dash-queue-stream').removeClass('d-none');
                $('#dash-ledger-table-wrap').addClass('d-none');
                $('#icon-queue-view-toggle').removeClass('mdi-view-grid-outline').addClass('mdi-view-headline');
                $('#btn-toggle-queue-view').attr('title', 'Switch to Data Table Grid');
            }
        },

        setupEventListeners: function () {
            var self = this;

            // Toggle Queue View: Cards Stream vs Data Table
            $(document).on('click', '#btn-toggle-queue-view', function (e) {
                e.preventDefault();
                self.toggleQueueViewMode();
            });

            // Operational Pipeline Item Click (Contextually drives Col 2 & Col 3)
            $(document).on('click', '.dash-pipeline-item[data-domain]', function (e) {
                e.preventDefault();
                var domainKey = $(this).data('domain');
                if (domainKey) {
                    self.switchDomain(domainKey);
                    if ($(window).width() < 992) {
                        $('.dash-viewport-tab-btn[data-target-col="dash-col-telemetry"]').trigger('click');
                    }
                }
            });

            // Perspective Pills in Header Ribbon
            $(document).on('click', '.dash-perspective-pill[data-perspective]', function (e) {
                e.preventDefault();
                var perspective = $(this).data('perspective');
                self.switchDomain(perspective);
                if ($(window).width() < 992) {
                    $('.dash-viewport-tab-btn[data-target-col="dash-col-telemetry"]').trigger('click');
                }
            });

            // Manual Refresh
            $(document).on('click', '#dash-btn-refresh', function (e) {
                e.preventDefault();
                var $btn = $(this);
                $btn.addClass('spinning');
                self.fetchSummary(false, function () {
                    setTimeout(function () {
                        $btn.removeClass('spinning');
                    }, 400);
                });
            });

            // Auto-Refresh Toggle
            $(document).on('change', '#dash-toggle-autorefresh', function () {
                self.state.autoRefreshEnabled = $(this).is(':checked');
            });

            // Toggle "All Workbenches" dock from contextual shortcuts
            $(document).on('click', '#btn-toggle-all-workbenches', function (e) {
                e.preventDefault();
                var $dock = $('#all-workbenches-dock');
                if ($dock.hasClass('d-none')) {
                    $dock.removeClass('d-none');
                    $(this).html('<i class="mdi mdi-chevron-up me-1"></i> Hide Workbenches');
                } else {
                    $dock.addClass('d-none');
                    $(this).html('<i class="mdi mdi-dots-grid me-1"></i> All Workbenches');
                }
            });

            // Queue Stream item click redirection (if not clicking action button directly)
            $(document).on('click', '.dash-queue-item[data-target-url]', function (e) {
                if ($(e.target).closest('a, button').length) {
                    return;
                }
                var url = $(this).data('target-url');
                if (url && url !== '#') {
                    window.location.href = url;
                }
            });

            // Ledger row click redirection (if user didn't click action button directly)
            $(document).on('click', '.dash-ledger-row[data-target-url]', function (e) {
                if ($(e.target).closest('a, button').length) {
                    return;
                }
                var url = $(this).data('target-url');
                if (url && url !== '#') {
                    window.location.href = url;
                }
            });
        },

        startAutoRefresh: function () {
            var self = this;
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
            }
            this.state.refreshTimer = setInterval(function () {
                if (self.state.autoRefreshEnabled && !document.hidden) {
                    self.fetchSummary(true);
                }
            }, this.config.refreshIntervalMs);
        },

        fetchSummary: function (isSilent, callback) {
            var self = this;
            if (!isSilent) {
                $('#dash-sync-indicator').text('Syncing...').addClass('syncing');
            }

            $.ajax({
                url: self.config.summaryUrl,
                method: 'GET',
                dataType: 'json',
                success: function (res) {
                    self.state.data = res;
                    self.renderPipelineCounts();

                    // If domain has not been initialized, determine from user role
                    if (!self.state.hasInitializedDomain) {
                        self.state.hasInitializedDomain = true;
                        var defaultDomain = self.determineInitialDomain();
                        self.switchDomain(defaultDomain);
                    } else {
                        // Re-render current active domain with fresh data
                        self.switchDomain(self.state.activeDomain);
                    }

                    $('#dash-sync-indicator').text('Live').removeClass('syncing');
                    if (typeof callback === 'function') {
                        callback(null, res);
                    }
                },
                error: function (xhr, status, error) {
                    console.warn('Dashboard Cockpit summary fetch failed:', error);
                    $('#dash-sync-indicator').text('Offline').removeClass('syncing');
                    if (typeof callback === 'function') {
                        callback(error);
                    }
                }
            });
        },

        determineInitialDomain: function () {
            var roles = this.config.userRoles || [];
            if (roles.indexOf('DOCTOR') !== -1) return 'doctor';
            if (roles.indexOf('LAB SCIENTIST') !== -1) return 'lab';
            if (roles.indexOf('PHARMACIST') !== -1) return 'pharmacy';
            if (roles.indexOf('NURSE') !== -1) return 'nursing';
            if (roles.indexOf('STORE') !== -1) return 'store';
            if (roles.indexOf('BILLER') !== -1 || roles.indexOf('CASHIER') !== -1 || roles.indexOf('ACCOUNTS') !== -1) return 'billing';
            if (roles.indexOf('RECEPTIONIST') !== -1 || roles.indexOf('Receptionist') !== -1) return 'receptionist';
            if (roles.indexOf('RADIOLOGIST') !== -1) return 'imaging';
            if (roles.indexOf('SURGEON') !== -1) return 'theatre';
            if (roles.indexOf('MATERNITY') !== -1) return 'maternity';
            return 'cockpit';
        },

        renderPipelineCounts: function () {
            var pipeline = (this.state.data && this.state.data.pipeline) || {};

            var setQueue = function (elemId, count, highAlertThreshold) {
                var $elem = $(elemId);
                var num = parseInt(count, 10) || 0;
                $elem.text(num);

                var $parentItem = $elem.closest('.dash-pipeline-item');
                if (num > 0) {
                    $elem.removeClass('badge-zero').addClass('badge-active');
                    if (highAlertThreshold && num >= highAlertThreshold) {
                        $parentItem.addClass('high-priority');
                    } else {
                        $parentItem.removeClass('high-priority');
                    }
                } else {
                    $elem.addClass('badge-zero').removeClass('badge-active');
                    $parentItem.removeClass('high-priority');
                }
            };

            setQueue('#pipe-waiting-count', pipeline.waiting, 10);
            setQueue('#pipe-vitals-count', pipeline.vitals, 8);
            setQueue('#pipe-consult-count', pipeline.consultation, 6);
            setQueue('#pipe-dispense-count', pipeline.dispense, 12);
            setQueue('#pipe-lab-count', pipeline.lab, 15);
            setQueue('#pipe-imaging-count', pipeline.imaging, 5);
            setQueue('#pipe-surgery-count', pipeline.surgery, 5);
            setQueue('#pipe-maternity-count', pipeline.maternity, 10);
            setQueue('#pipe-admitted-count', pipeline.admitted, 50);
            setQueue('#pipe-store-count', pipeline.store, 10);
            setQueue('#pipe-unpaid-count', pipeline.unpaid, 5);
            setQueue('#pipe-hmo-count', pipeline.hmo, 10);
            setQueue('#pipe-morgue-count', pipeline.morgue, 5);
            setQueue('#pipe-leave-count', pipeline.leave, 3);

            var totalActive = 0;
            for (var key in pipeline) {
                if (pipeline.hasOwnProperty(key)) {
                    totalActive += (parseInt(pipeline[key], 10) || 0);
                }
            }
            $('#pipe-total-label').text(totalActive > 0 ? (totalActive.toLocaleString() + ' Active') : 'Active Flow');
            $('#tab-badge-pipeline').text(totalActive > 0 ? totalActive.toLocaleString() : 'Live');
        },

        switchDomain: function (domainKey) {
            domainKey = domainKey || 'cockpit';
            this.state.activeDomain = domainKey;

            // 1. Highlight clicked pipeline item in Col 1
            $('.dash-pipeline-item').removeClass('active-pipeline focused dimmed');
            var $activePipe = $('.dash-pipeline-item[data-domain="' + domainKey + '"]');
            if ($activePipe.length) {
                $activePipe.addClass('active-pipeline');
            }

            // 2. Sync header ribbon perspective pill
            $('.dash-perspective-pill').removeClass('active');
            var $activePill = $('.dash-perspective-pill[data-perspective="' + domainKey + '"]');
            if ($activePill.length) {
                $activePill.addClass('active');
                $('#dash-active-perspective-label').text($activePill.text().trim());
            }

            if (!this.state.data || !this.state.data.domains) {
                return;
            }

            var domainData = this.state.data.domains[domainKey] || this.state.data.domains['cockpit'];
            if (!domainData) return;

            // 3. Update Column 2: Header, KPIs, Chart, Insights
            this.renderColumn2Telemetry(domainData);

            // 4. Update Column 3: Header, Shortcuts Dock, Live Queue Stream
            this.renderColumn3Ledger(domainData, domainKey);
        },

        renderColumn2Telemetry: function (domainData) {
            // Header
            $('#col2-domain-icon').attr('class', 'mdi ' + (domainData.icon || 'mdi-chart-areaspline') + ' text-primary');
            $('#col2-domain-title').text(domainData.title || 'Clinical Telemetry & Analytics');
            $('#col2-domain-badge').text(domainData.badge || 'Active Telemetry');

            // 2x4 Intensive KPI Matrix
            var $matrix = $('#dash-kpi-matrix');
            $matrix.empty();

            var kpis = domainData.kpis || [];
            kpis.forEach(function (k) {
                var valClass = k.color === 'danger' ? 'text-danger' : '';
                var unitHtml = k.unit ? '<span class="dash-kpi-unit ' + (k.color === 'danger' ? 'text-danger' : '') + '">' + k.unit + '</span>' : '';
                var href = k.link || '#';

                var tileHtml = '<a href="' + href + '" class="dash-kpi-tile kpi-tile-' + (k.color || 'primary') + ' text-decoration-none">' +
                    '<div class="dash-kpi-head">' +
                        '<span class="dash-kpi-label" title="' + k.label + '">' + k.label + '</span>' +
                        '<i class="mdi ' + k.icon + ' dash-kpi-icon text-' + (k.color || 'primary') + '"></i>' +
                    '</div>' +
                    '<div class="dash-kpi-val">' +
                        '<span class="' + valClass + '">' + k.val + '</span> ' + unitHtml +
                    '</div>' +
                    '<div class="dash-kpi-sub text-' + (k.color || 'muted') + '" title="' + k.sub + '">' +
                        '<i class="mdi mdi-arrow-right-circle-outline"></i> ' + k.sub +
                    '</div>' +
                '</a>';
                $matrix.append(tileHtml);
            });

            // Dual Micro-Charts
            this.renderDomainChart(domainData);

            // Domain Insights Strip (Compact 2-Column Grid)
            var $insightsContainer = $('#dash-insights-container');
            $insightsContainer.empty();

            var insights = domainData.insights || [];
            $('#dash-insights-count').text(insights.length > 0 ? (insights.length + ' Insights') : 'Live');

            if (insights.length === 0) {
                $insightsContainer.html(
                    '<div class="dash-insight-pill insight-ok">' +
                        '<i class="mdi mdi-check-circle-outline"></i>' +
                        '<span><strong>Status:</strong> Operational parameters within nominal benchmarks</span>' +
                    '</div>'
                );
            } else {
                insights.forEach(function (ins) {
                    var color = ins.color || ins.severity || 'info';
                    if (color === 'success') color = 'ok';
                    var icon = ins.icon || 'mdi-information-outline';
                    var desc = ins.desc || ins.message || ins.text || '';
                    var title = ins.title || 'Insight';
                    var pillHtml = '<div class="dash-insight-pill insight-' + color + '" title="' + title + ': ' + desc + '">' +
                        '<i class="mdi ' + icon + '"></i>' +
                        '<span><strong>' + title + ':</strong> ' + desc + '</span>' +
                    '</div>';
                    $insightsContainer.append(pillHtml);
                });
            }
        },

        renderDomainChart: function (domainData) {
            if (typeof window.Chart === 'undefined') return;

            var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--hospital-primary').trim() || '#011b33';

            // Chart 1: Hourly Activity Inflow
            $('#col2-chart-title').text(domainData.chart_title || 'Hourly Activity Inflow');
            $('#col2-chart-badge').text(domainData.chart_badge || 'Today Flow');

            var ctx1 = document.getElementById('dash-chart-canvas');
            if (ctx1) {
                if (this.state.chartInstance) {
                    this.state.chartInstance.destroy();
                    this.state.chartInstance = null;
                }

                var flowLabels = [];
                var flowValues = [];
                var flowList = (this.state.data && this.state.data.hourlyFlow) || [];
                flowList.forEach(function (item) {
                    flowLabels.push(item.label);
                    flowValues.push(item.total);
                });

                this.state.chartInstance = new window.Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: flowLabels,
                        datasets: [{
                            label: 'Patient Flow',
                            data: flowValues,
                            backgroundColor: 'rgba(16, 185, 129, 0.65)',
                            hoverBackgroundColor: 'rgba(16, 185, 129, 0.9)',
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        tooltips: {
                            mode: 'index',
                            intersect: false,
                            xPadding: 6,
                            yPadding: 4,
                            titleFontSize: 10,
                            bodyFontSize: 10,
                            callbacks: {
                                label: function (tooltipItem) {
                                    return tooltipItem.yLabel + ' Patients / Orders';
                                }
                            }
                        },
                        scales: {
                            xAxes: [{
                                gridLines: { display: false },
                                ticks: {
                                    fontSize: 8,
                                    fontColor: '#94a3b8',
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 6
                                }
                            }],
                            yAxes: [{
                                gridLines: { color: '#f1f5f9', drawBorder: false },
                                ticks: {
                                    fontSize: 8,
                                    fontColor: '#94a3b8',
                                    beginAtZero: true,
                                    precision: 0,
                                    maxTicksLimit: 3
                                }
                            }]
                        }
                    }
                });
            }

            // Chart 2: 7-Day Performance Trend
            var ctx2 = document.getElementById('dash-chart-canvas-2');
            if (ctx2) {
                if (this.state.chartInstance2) {
                    this.state.chartInstance2.destroy();
                    this.state.chartInstance2 = null;
                }

                var revLabels = [];
                var revValues = [];
                var revList = (this.state.data && this.state.data.revenueTrend) || [];
                revList.forEach(function (item) {
                    var d = new Date(item.date);
                    revLabels.push(d.toLocaleDateString('en-US', { weekday: 'short', month: 'numeric', day: 'numeric' }));
                    revValues.push(item.total);
                });

                this.state.chartInstance2 = new window.Chart(ctx2, {
                    type: 'line',
                    data: {
                        labels: revLabels,
                        datasets: [{
                            label: 'Revenue Trend',
                            data: revValues,
                            backgroundColor: 'rgba(1, 27, 51, 0.08)',
                            borderColor: primaryColor,
                            borderWidth: 2,
                            hoverBackgroundColor: 'rgba(1, 27, 51, 0.15)',
                            pointRadius: 2,
                            pointHoverRadius: 4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        tooltips: {
                            mode: 'index',
                            intersect: false,
                            xPadding: 6,
                            yPadding: 4,
                            titleFontSize: 10,
                            bodyFontSize: 10,
                            callbacks: {
                                label: function (tooltipItem) {
                                    return '₦' + Number(tooltipItem.yLabel).toLocaleString();
                                }
                            }
                        },
                        scales: {
                            xAxes: [{
                                gridLines: { display: false },
                                ticks: {
                                    fontSize: 8,
                                    fontColor: '#94a3b8',
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 5
                                }
                            }],
                            yAxes: [{
                                gridLines: { color: '#f1f5f9', drawBorder: false },
                                ticks: {
                                    fontSize: 8,
                                    fontColor: '#94a3b8',
                                    beginAtZero: true,
                                    precision: 0,
                                    maxTicksLimit: 3,
                                    callback: function (val) {
                                        if (val >= 1000000) return '₦' + (val / 1000000).toFixed(1) + 'M';
                                        if (val >= 1000) return '₦' + (val / 1000).toFixed(0) + 'k';
                                        return '₦' + val;
                                    }
                                }
                            }]
                        }
                    }
                });
            }
        },

        renderColumn3Ledger: function (domainData, domainKey) {
            // Header
            $('#col3-queue-title').text(domainData.queue_title || 'Live Queue & Quick Actions');
            $('#col3-queue-badge').text(domainData.queue_badge || 'Live Stream');

            // Contextual Shortcuts Dock
            var $dock = $('#dash-shortcuts-dock');
            var shortcuts = domainData.shortcuts || [];

            if (domainData.shortcuts_type === 'custom' && shortcuts.length > 0) {
                var html = '<div class="d-flex justify-content-between align-items-center mb-1">' +
                    '<span class="dash-dock-title">' + (domainData.title ? domainData.title.split(' ')[0] : 'Domain') + ' Actions</span>' +
                    '<button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-muted" id="btn-toggle-all-workbenches" style="font-size:0.63rem;">' +
                        '<i class="mdi mdi-dots-grid me-1"></i> All Workbenches' +
                    '</button>' +
                '</div>' +
                '<div class="dash-shortcuts-custom-grid" id="domain-shortcuts-grid">';

                shortcuts.forEach(function (s) {
                    html += '<a href="' + s.url + '" class="dash-shortcut-card shortcut-' + (s.color || 'primary') + '" title="' + s.label + '">' +
                        '<div class="dash-shortcut-card-icon"><i class="mdi ' + s.icon + '"></i></div>' +
                        '<div class="dash-shortcut-card-info">' +
                            '<div class="dash-shortcut-card-title">' + s.label + '</div>' +
                            '<div class="dash-shortcut-card-sub">' + s.sub + '</div>' +
                        '</div>' +
                    '</a>';
                });

                html += '</div>';
                html += '<div class="dash-role-dock mt-2 d-none" id="all-workbenches-dock">' + this.state.cachedLaunchpadHtml + '</div>';

                $dock.html(html);
            } else {
                // Cockpit default: 18-role persistent dock
                $dock.html(this.state.cachedLaunchpadHtml);
            }

            // Live Queue Stream & Table Containers
            var $stream = $('#dash-queue-stream');
            var $tbody = $('#dash-ledger-tbody');
            $stream.empty();
            $tbody.empty();

            var items = domainData.queue_items || [];
            $('#tab-badge-queue').text(items.length > 0 ? items.length : '0');
            $('#tab-label-queue').text(domainData.queue_badge || 'Live Queue');

            if (items.length === 0) {
                var emptyMsg = '<div class="text-center py-4 text-muted small"><i class="mdi mdi-check-circle-outline me-1"></i> No active queue items for this clinical domain</div>';
                $stream.html(emptyMsg);
                $tbody.append('<tr class="empty-filter-row"><td colspan="4" class="text-center py-4 text-muted small"><i class="mdi mdi-check-circle-outline me-1"></i> No active queue items for this clinical domain</td></tr>');
                this.applyQueueViewMode();
                return;
            }

            var iconMap = {
                doctor: 'mdi-stethoscope',
                nursing: 'mdi-heart-pulse',
                pharmacy: 'mdi-pill',
                lab: 'mdi-flask-outline',
                billing: 'mdi-cash-multiple',
                store: 'mdi-store-warehouse',
                inpatient: 'mdi-hotel',
                surgery: 'mdi-scalpel',
                theatre: 'mdi-scalpel',
                maternity: 'mdi-mother-nurse',
                receptionist: 'mdi-account-plus',
                hmo: 'mdi-shield-check'
            };

            var streamBuffer = '';
            var tableBuffer = '';

            items.forEach(function (item) {
                var targetUrl = item.action_url || item.target_url || '#';
                var actionBtnHtml = '';
                var itemDomain = item.dept_code || domainData.domain || 'doctor';
                var itemIcon = iconMap[itemDomain] || domainData.icon || 'mdi-account-clock';

                if (item.action_label && targetUrl !== '#') {
                    actionBtnHtml = '<a href="' + targetUrl + '" class="dash-qi-btn btn-action-' + (item.status_color || 'primary') + '">' +
                        item.action_label +
                        '<i class="mdi mdi-chevron-right ms-1"></i>' +
                    '</a>';
                } else if (item.target_url && item.target_url !== '#') {
                    actionBtnHtml = '<a href="' + item.target_url + '" class="dash-qi-btn btn-action-primary">Open<i class="mdi mdi-chevron-right ms-1"></i></a>';
                }

                // 1. Mode A: Stream Card
                streamBuffer += '<div class="dash-queue-item qi-priority-' + (item.status_color || 'primary') + '" data-target-url="' + targetUrl + '">' +
                    '<div class="dash-qi-left">' +
                        '<div class="dash-qi-avatar avatar-' + itemDomain + '">' +
                            '<i class="mdi ' + itemIcon + '"></i>' +
                        '</div>' +
                        '<div class="dash-qi-info">' +
                            '<div class="dash-qi-head">' +
                                '<span class="dash-qi-name" title="' + (item.patient_name || 'Walk-in') + '">' + (item.patient_name || 'Walk-in') + '</span>' +
                                '<span class="dash-qi-file">#' + (item.file_no || 'N/A') + '</span>' +
                                '<span class="dash-qi-badge badge-status-' + (item.status_color || 'primary') + '">' + (item.status_label || 'Active') + '</span>' +
                            '</div>' +
                            '<div class="dash-qi-sub">' +
                                '<span class="dash-qi-detail" title="' + (item.detail || item.event || '') + '"><i class="mdi mdi-hospital-building me-1"></i>' + (item.detail || item.event || '') + '</span>' +
                                '<span class="dash-qi-meta"><i class="mdi mdi-clock-outline me-1"></i>' + (item.meta || item.time || '') + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="dash-qi-right">' +
                        actionBtnHtml +
                    '</div>' +
                '</div>';

                // 2. Mode B: Modern Table Row
                tableBuffer += '<tr class="dash-ledger-row" data-target-url="' + targetUrl + '">' +
                    '<td class="col-patient">' +
                        '<div class="dash-patient-name" title="' + (item.patient_name || 'Walk-in') + '">' + (item.patient_name || 'Walk-in') + '</div>' +
                        '<div class="dash-patient-file">#' + (item.file_no || 'N/A') + '</div>' +
                    '</td>' +
                    '<td class="col-dept">' +
                        '<div class="dash-event-text" title="' + (item.detail || item.event || '') + '">' + (item.detail || item.event || '') + '</div>' +
                        '<div class="dash-event-time">' + (item.meta || item.time || '') + '</div>' +
                    '</td>' +
                    '<td class="col-status text-right">' +
                        '<span class="dash-status-pill badge-status-' + (item.status_color || 'primary') + '">' + (item.status_label || 'Active') + '</span>' +
                    '</td>' +
                    '<td class="col-action text-right">' +
                        actionBtnHtml +
                    '</td>' +
                '</tr>';
            });

            $stream.html(streamBuffer);
            $tbody.html(tableBuffer);
            this.applyQueueViewMode();
        }
    };

    window.DashboardCockpit = DashboardCockpit;

})(window, document, jQuery);
