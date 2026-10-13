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
            activePerspective: 'cockpit',
            chartHourly: null,
            chartRevenue: null,
            refreshTimer: null,
            autoRefreshEnabled: true
        },

        init: function (customConfig) {
            if (customConfig) {
                $.extend(this.config, customConfig);
            }

            this.setupClock();
            this.setupEventListeners();
            this.setupMobileTabs();
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
            $(document).on('click', '.dash-mobile-tab-btn', function (e) {
                e.preventDefault();
                var targetCol = $(this).data('target-col');

                $('.dash-mobile-tab-btn').removeClass('active');
                $(this).addClass('active');

                // On small screens, hide other columns and show target
                if ($(window).width() < 768) {
                    $('.dash-column').removeClass('mobile-active');
                    $('.' + targetCol).addClass('mobile-active');
                }
            });
        },

        setupEventListeners: function () {
            var self = this;

            // Perspective Pills
            $(document).on('click', '.dash-perspective-pill', function (e) {
                e.preventDefault();
                var perspective = $(this).data('perspective');
                self.setPerspective(perspective);
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

            // Pipeline item click redirection
            $(document).on('click', '.dash-pipeline-item[data-href]', function (e) {
                // If user clicked a direct child action button, don't double navigate
                if ($(e.target).closest('a, button').not(this).length) {
                    return;
                }
                var href = $(this).data('href');
                if (href) {
                    window.location.href = href;
                }
            });

            // Ledger row click redirection
            $(document).on('click', '.dash-ledger-row[data-target-url]', function (e) {
                if ($(e.target).closest('a, button').not(this).length) {
                    return;
                }
                var url = $(this).data('target-url');
                if (url) {
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
                    self.renderAll();
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

        renderAll: function () {
            if (!this.state.data) return;
            this.renderPipeline();
            this.renderKPIs();
            this.renderCharts();
            this.renderActivity();
            this.renderInsights();
            this.applyPerspectiveFilter();
        },

        renderPipeline: function () {
            var pipeline = this.state.data.pipeline || {};

            // Helper to set badge count and flasher
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

            var totalActive = (parseInt(pipeline.waiting, 10) || 0) +
                              (parseInt(pipeline.vitals, 10) || 0) +
                              (parseInt(pipeline.consultation, 10) || 0) +
                              (parseInt(pipeline.dispense, 10) || 0) +
                              (parseInt(pipeline.lab, 10) || 0) +
                              (parseInt(pipeline.imaging, 10) || 0) +
                              (parseInt(pipeline.surgery, 10) || 0) +
                              (parseInt(pipeline.maternity, 10) || 0) +
                              (parseInt(pipeline.admitted, 10) || 0) +
                              (parseInt(pipeline.store, 10) || 0) +
                              (parseInt(pipeline.unpaid, 10) || 0) +
                              (parseInt(pipeline.hmo, 10) || 0) +
                              (parseInt(pipeline.morgue, 10) || 0) +
                              (parseInt(pipeline.leave, 10) || 0);

            $('#pipe-total-label').text(totalActive > 0 ? (totalActive.toLocaleString() + ' Active') : 'Active Flow');
        },

        renderKPIs: function () {
            var kpis = this.state.data.kpis || {};

            var formatMoney = function (amount) {
                var val = parseFloat(amount) || 0;
                return '₦' + val.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            };

            var formatNumber = function (val) {
                return (parseInt(val, 10) || 0).toLocaleString('en-US');
            };

            $('#kpi-today-revenue').text(formatMoney(kpis.today_revenue));
            $('#kpi-vitals-today').text(formatNumber(kpis.vitals_today));
            $('#kpi-admitted-patients').text(formatNumber(kpis.admitted_patients));
            $('#kpi-occupancy-rate').text((parseFloat(kpis.occupancy_rate) || 0) + '%');
            $('#kpi-dispensed-today').text(formatNumber(kpis.dispensed_today));
            $('#kpi-lab-completed').text(formatNumber(kpis.lab_completed));
            $('#kpi-unbilled-bills').text(formatNumber(kpis.unbilled_bills));
            $('#kpi-low-stock').text(formatNumber(kpis.low_stock));
            $('#kpi-out-of-stock').text(formatNumber(kpis.out_of_stock));
            $('#kpi-surgery-pending').text(formatNumber(kpis.surgery_pending));
            $('#kpi-maternity-active').text(formatNumber(kpis.maternity_active));
            $('#kpi-store-requisitions').text(formatNumber(kpis.store_requisitions));
            $('#kpi-leave-pending').text(formatNumber(kpis.leave_pending));
        },

        renderCharts: function () {
            if (typeof window.Chart === 'undefined') return;

            var self = this;
            var hourlyFlow = this.state.data.hourlyFlow || [];
            var revenueTrend = this.state.data.revenueTrend || [];

            // Primary theme color
            var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--hospital-primary').trim() || '#011b33';

            // 1. Hourly Flow Bar Chart
            var flowLabels = [];
            var flowData = [];
            hourlyFlow.forEach(function (item) {
                flowLabels.push(item.label);
                flowData.push(item.total);
            });

            var ctxHourly = document.getElementById('dash-chart-hourly');
            if (ctxHourly) {
                if (self.state.chartHourly) {
                    self.state.chartHourly.data.labels = flowLabels;
                    self.state.chartHourly.data.datasets[0].data = flowData;
                    self.state.chartHourly.update();
                } else {
                    self.state.chartHourly = new window.Chart(ctxHourly, {
                        type: 'bar',
                        data: {
                            labels: flowLabels,
                            datasets: [{
                                label: 'Visits',
                                data: flowData,
                                backgroundColor: 'rgba(16, 185, 129, 0.65)',
                                hoverBackgroundColor: 'rgba(16, 185, 129, 0.9)',
                                borderRadius: 3,
                                maxBarThickness: 14
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: { display: false },
                            tooltips: {
                                mode: 'index',
                                intersect: false,
                                xPadding: 8,
                                yPadding: 6,
                                titleFontSize: 11,
                                bodyFontSize: 11
                            },
                            scales: {
                                xAxes: [{
                                    gridLines: { display: false },
                                    ticks: {
                                        fontSize: 9,
                                        fontColor: '#94a3b8',
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 7
                                    }
                                }],
                                yAxes: [{
                                    gridLines: {
                                        color: '#f1f5f9',
                                        drawBorder: false
                                    },
                                    ticks: {
                                        fontSize: 9,
                                        fontColor: '#94a3b8',
                                        beginAtZero: true,
                                        precision: 0,
                                        maxTicksLimit: 4
                                    }
                                }]
                            }
                        }
                    });
                }
            }

            // 2. Revenue Trend Area Chart
            var revLabels = [];
            var revData = [];
            revenueTrend.forEach(function (item) {
                var d = new Date(item.date);
                var formatted = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                revLabels.push(formatted);
                revData.push(item.total);
            });

            var ctxRev = document.getElementById('dash-chart-revenue');
            if (ctxRev) {
                if (self.state.chartRevenue) {
                    self.state.chartRevenue.data.labels = revLabels;
                    self.state.chartRevenue.data.datasets[0].data = revData;
                    self.state.chartRevenue.update();
                } else {
                    self.state.chartRevenue = new window.Chart(ctxRev, {
                        type: 'line',
                        data: {
                            labels: revLabels,
                            datasets: [{
                                label: 'Revenue (₦)',
                                data: revData,
                                borderColor: primaryColor,
                                backgroundColor: 'rgba(1, 27, 51, 0.08)',
                                borderWidth: 2,
                                pointRadius: 2,
                                pointHoverRadius: 4,
                                lineTension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: { display: false },
                            tooltips: {
                                mode: 'index',
                                intersect: false,
                                xPadding: 8,
                                yPadding: 6,
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
                                        fontSize: 9,
                                        fontColor: '#94a3b8',
                                        maxRotation: 0
                                    }
                                }],
                                yAxes: [{
                                    gridLines: {
                                        color: '#f1f5f9',
                                        drawBorder: false
                                    },
                                    ticks: {
                                        fontSize: 9,
                                        fontColor: '#94a3b8',
                                        beginAtZero: true,
                                        maxTicksLimit: 4,
                                        callback: function (value) {
                                            if (value >= 1000000) return '₦' + (value / 1000000).toFixed(1) + 'M';
                                            if (value >= 1000) return '₦' + (value / 1000).toFixed(0) + 'k';
                                            return '₦' + value;
                                        }
                                    }
                                }]
                            }
                        }
                    });
                }
            }
        },

        renderActivity: function () {
            var activity = this.state.data.activity || [];
            var $tbody = $('#dash-ledger-tbody');
            $tbody.empty();

            if (activity.length === 0) {
                $tbody.append('<tr><td colspan="4" class="text-center py-4 text-muted small">No recent department activities recorded today</td></tr>');
                return;
            }

            var deptBadgeClass = function (deptCode) {
                switch (deptCode) {
                    case 'doctor': return 'badge-dept-doctor';
                    case 'pharmacy': return 'badge-dept-pharmacy';
                    case 'lab': return 'badge-dept-lab';
                    case 'billing': return 'badge-dept-billing';
                    case 'nursing': return 'badge-dept-nursing';
                    case 'surgery': return 'badge-dept-surgery';
                    case 'store': return 'badge-dept-store';
                    default: return 'badge-dept-default';
                }
            };

            var statusBadgeClass = function (color) {
                switch (color) {
                    case 'success': return 'badge-status-success';
                    case 'warning': return 'badge-status-warning';
                    case 'danger': return 'badge-status-danger';
                    case 'primary': return 'badge-status-primary';
                    case 'purple': return 'badge-status-purple';
                    default: return 'badge-status-secondary';
                }
            };

            activity.forEach(function (item) {
                var rowHtml = '<tr class="dash-ledger-row" data-dept="' + (item.dept_code || 'all') + '" data-target-url="' + (item.target_url || '#') + '">';

                // Patient / File No
                rowHtml += '<td class="col-patient">';
                rowHtml += '  <div class="dash-patient-name" title="' + item.patient_name + '">' + item.patient_name + '</div>';
                rowHtml += '  <div class="dash-patient-file">#' + item.file_no + '</div>';
                rowHtml += '</td>';

                // Department
                rowHtml += '<td class="col-dept">';
                rowHtml += '  <span class="dash-dept-pill ' + deptBadgeClass(item.dept_code) + '">' + item.dept + '</span>';
                rowHtml += '</td>';

                // Event Description
                rowHtml += '<td class="col-event">';
                rowHtml += '  <span class="dash-event-text" title="' + item.event + '">' + item.event + '</span>';
                rowHtml += '</td>';

                // Status & Time
                rowHtml += '<td class="col-status text-right">';
                rowHtml += '  <span class="dash-status-pill ' + statusBadgeClass(item.status_color) + '">' + item.status_label + '</span>';
                rowHtml += '  <div class="dash-event-time">' + item.time + '</div>';
                rowHtml += '</td>';

                rowHtml += '</tr>';
                $tbody.append(rowHtml);
            });
        },

        renderInsights: function () {
            var insights = this.state.data.insights || [];
            var $strip = $('#dash-insights-strip');
            $strip.empty();

            $('#dash-insights-count').text(insights.length > 0 ? (insights.length + ' Alerts') : 'Live');

            if (insights.length === 0) {
                $strip.html('<div class="dash-insight-pill insight-ok"><i class="mdi mdi-check-circle"></i> All hospital operational queues running normal</div>');
                return;
            }

            insights.forEach(function (ins) {
                var severityClass = 'insight-' + (ins.severity || 'info');
                var iconClass = ins.icon || 'mdi-information-outline';
                var pill = '<div class="dash-insight-pill ' + severityClass + '">';
                pill += '  <i class="mdi ' + iconClass + '"></i>';
                pill += '  <strong>' + ins.title + ':</strong> ' + ins.message;
                pill += '</div>';
                $strip.append(pill);
            });
        },

        setPerspective: function (roleKey) {
            this.state.activePerspective = roleKey || 'cockpit';

            // Update pills UI
            $('.dash-perspective-pill').removeClass('active');
            $('.dash-perspective-pill[data-perspective="' + this.state.activePerspective + '"]').addClass('active');

            // Update ribbon title or perspective label
            var perspectiveName = $('.dash-perspective-pill[data-perspective="' + this.state.activePerspective + '"]').text().trim();
            $('#dash-active-perspective-label').text(perspectiveName);

            this.applyPerspectiveFilter();
        },

        applyPerspectiveFilter: function () {
            var perspective = this.state.activePerspective;

            // 1. Filter / Highlight Pipeline Queues in Col 1
            if (perspective === 'cockpit') {
                $('.dash-pipeline-item').removeClass('dimmed focused');
            } else {
                $('.dash-pipeline-item').each(function () {
                    var itemRole = $(this).data('role');
                    if (itemRole === perspective) {
                        $(this).addClass('focused').removeClass('dimmed');
                    } else {
                        $(this).addClass('dimmed').removeClass('focused');
                    }
                });
            }

            // 2. Filter Activity Ledger in Col 3
            if (perspective === 'cockpit') {
                $('.dash-ledger-row').show();
            } else {
                var visibleCount = 0;
                $('.dash-ledger-row').each(function () {
                    var rowDept = $(this).data('dept');
                    if (rowDept === perspective) {
                        $(this).show();
                        visibleCount++;
                    } else {
                        $(this).hide();
                    }
                });
                if (visibleCount === 0) {
                    $('#dash-ledger-tbody .empty-filter-row').remove();
                    $('#dash-ledger-tbody').append('<tr class="empty-filter-row"><td colspan="4" class="text-center py-4 text-muted small">No recent events for ' + perspective + ' perspective</td></tr>');
                } else {
                    $('#dash-ledger-tbody .empty-filter-row').remove();
                }
            }
        }
    };

    window.DashboardCockpit = DashboardCockpit;

})(window, document, jQuery);
