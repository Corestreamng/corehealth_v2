/**
 * CoreHealth v2 — Classic Operations Dashboard Standalone JS Module
 * PSR-12 & Modular Asset Standard Compliant
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        var cfg = window.DASHBOARD_CLASSIC_CONFIG || {};
        var routes = cfg.routes || {};
        var queueWorkbenchMap = cfg.queueWorkbenchMap || {};
        var activityWorkbenchMap = cfg.activityWorkbenchMap || {};
        var firstTabKey = cfg.firstTabKey || '';

        // ========================================
        // Date/Time Display
        // ========================================
        function updateDateTime() {
            var now = new Date();
            var options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            $('[id="currentDateTime"]').text(now.toLocaleDateString('en-US', options));
        }
        updateDateTime();
        setInterval(updateDateTime, 30000);

        // ========================================
        // Helpers
        // ========================================
        function getToday() {
            return new Date().toISOString().slice(0, 10);
        }

        function getThisMonthStart() {
            var now = new Date();
            return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        }

        function fmt(n) {
            if (typeof n === 'string') {
                n = n.replace(/,/g, '');
            }
            var num = Number(n || 0);
            if (isNaN(num)) num = 0;
            return '\u20A6' + num.toLocaleString('en-NG', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        var queueBgColors = {
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            success: '#10b981',
            primary: '#6366f1',
            purple: '#8b5cf6',
            dark: '#334155'
        };

        // ========================================
        // Generic UI Renderers
        // ========================================
        function renderQueues(containerId, queues) {
            var $c = $('#' + containerId);
            if (!$c.length || !queues || !queues.length) {
                $c.html('<div class="col-12 text-muted text-center py-2" style="font-size:0.8rem;">No queue data</div>');
                return;
            }
            var wb = queueWorkbenchMap[containerId] || {};
            var html = '';
            queues.forEach(function (q) {
                var bg = queueBgColors[q.color] || '#6b7280';
                var href = '#';
                if (wb.filterMap && wb.filterMap[q.filter]) {
                    var fm = wb.filterMap[q.filter];
                    var paramName = fm.param || 'queue_filter';
                    var baseUrl = fm.url;
                    if (baseUrl && baseUrl !== '#') {
                        href = baseUrl + (baseUrl.indexOf('?') !== -1 ? '&' : '?') + paramName + '=' + encodeURIComponent(fm.filter);
                    }
                } else if (wb.url) {
                    href = wb.url + (wb.url.indexOf('?') !== -1 ? '&' : '?') + 'queue_filter=' + encodeURIComponent(q.filter);
                }
                var tag = href !== '#' ? 'a' : 'div';
                var linkAttr = href !== '#' ? ' href="' + href + '" title="Open ' + q.name + ' queue"' : '';
                html += '<div class="col-xl-2 col-lg-3 col-md-4 col-6">' +
                    '<' + tag + linkAttr + ' class="text-decoration-none d-block h-100">' +
                    '<div class="dash-queue-item" style="cursor:' + (href !== '#' ? 'pointer' : 'default') + ';">' +
                    '<div class="dash-queue-icon" style="background:' + bg + ';"><i class="mdi ' + q.icon + '"></i></div>' +
                    '<div><div class="dash-queue-name">' + q.name + '</div>' +
                    '<div class="dash-queue-count">' + q.count + '</div></div>' +
                    '</div></' + tag + '></div>';
            });
            $c.html(html);
        }

        function renderInsights(containerId, insights) {
            var $c = $('#' + containerId);
            if (!$c.length || !insights || !insights.length) {
                $c.html('');
                return;
            }
            var html = '';
            insights.forEach(function (i) {
                html += '<div class="dash-insight-item severity-' + i.severity + '">' +
                    '<div class="dash-insight-icon severity-' + i.severity + '"><i class="mdi ' + i.icon + '"></i></div>' +
                    '<div><div class="dash-insight-title">' + i.title + '</div>' +
                    '<div class="dash-insight-msg">' + i.message + '</div></div>' +
                    '</div>';
            });
            $c.html(html);
        }

        function renderActivityTable(containerId, rows, columns) {
            var $head = $('#' + containerId + '-head');
            var $body = $('#' + containerId + '-body');
            if (!$head.length || !rows) return;
            var wbUrl = activityWorkbenchMap[containerId] || null;

            var hdr = '<tr>';
            columns.forEach(function (c) {
                hdr += '<th>' + c.label + '</th>';
            });
            hdr += '</tr>';
            $head.html(hdr);

            if (!rows.length) {
                $body.html('<tr><td colspan="' + columns.length + '" class="text-center text-muted py-2" style="font-size:0.8rem;">No activity yet today</td></tr>');
                return;
            }
            var rhtml = '';
            rows.forEach(function (r) {
                var rowClick = '';
                if (wbUrl && r.patient_id) {
                    rowClick = ' style="cursor:pointer;" onclick="window.location.href=\'' + wbUrl + '?patient_id=' + r.patient_id + '\'" title="Open patient in workbench"';
                }
                rhtml += '<tr class="' + (rowClick ? 'hover-highlight' : '') + '"' + rowClick + '>';
                columns.forEach(function (c) {
                    var val = c.render ? c.render(r) : (r[c.key] || '—');
                    rhtml += '<td>' + val + '</td>';
                });
                rhtml += '</tr>';
            });
            $body.html(rhtml);
        }

        function badgeHtml(label, color) {
            return '<span class="badge bg-' + color + '">' + label + '</span>';
        }

        // ========================================
        // Chart Engine
        // ========================================
        var chartInstances = {};
        var chartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 11 },
                    padding: 10,
                    cornerRadius: 6,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: { color: '#94a3b8', font: { size: 10, weight: '500' }, padding: 6 }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 45, padding: 4 }
                }
            }
        };

        function renderLineChart(canvasId, data, label, color) {
            if (!document.getElementById(canvasId) || typeof Chart === 'undefined') return;
            if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
            var ctx = document.getElementById(canvasId).getContext('2d');
            var gradient = ctx.createLinearGradient(0, 0, 0, 220);
            gradient.addColorStop(0, color + '30');
            gradient.addColorStop(1, color + '05');
            chartInstances[canvasId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(function (d) { return d.date; }),
                    datasets: [{
                        label: label,
                        data: data.map(function (d) { return parseFloat(d.total); }),
                        borderColor: color,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 2.5,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: color,
                        pointBorderWidth: 1.5
                    }]
                },
                options: chartDefaults
            });
        }

        function renderBarChart(canvasId, data, label, color) {
            if (!document.getElementById(canvasId) || typeof Chart === 'undefined') return;
            if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
            var ctx = document.getElementById(canvasId).getContext('2d');
            chartInstances[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(function (d) { return d.date || d.hour || d.label; }),
                    datasets: [{
                        label: label,
                        data: data.map(function (d) { return parseFloat(d.total || d.count || d.value); }),
                        backgroundColor: color + '80',
                        borderColor: color,
                        borderWidth: 1.5,
                        borderRadius: 4
                    }]
                },
                options: chartDefaults
            });
        }

        function renderDoughnutChart(canvasId, data, colors) {
            if (!document.getElementById(canvasId) || typeof Chart === 'undefined') return;
            if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
            var labelsArr = data.map(function (d) { return d.label || d.name || d.status; });
            var valuesArr = data.map(function (d) { return parseFloat(d.total || d.count || d.value); });
            var colorsArr = colors || data.map(function (d) { return d.color; });
            chartInstances[canvasId] = new Chart(document.getElementById(canvasId).getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labelsArr,
                    datasets: [{
                        data: valuesArr,
                        backgroundColor: colorsArr,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                usePointStyle: true,
                                pointStyleWidth: 8,
                                font: { size: 11, weight: '500' },
                                color: '#475569'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 10,
                            cornerRadius: 6,
                            displayColors: true
                        }
                    }
                }
            });
        }

        // ========================================
        // Domain Stats Fetchers (Live KPI Metrics)
        // ========================================
        function fetchReceptionistStats() {
            if (!routes.receptionistStats) return;
            $.get(routes.receptionistStats, function (data) {
                $('#recep-stat-new-patients').text(data.new_patients || '0');
                $('#recep-stat-returning-patients').text(data.returning_patients || '0');
                $('#recep-stat-admissions').text(data.admissions || '0');
                $('#recep-stat-bookings').text(data.bookings || '0');
            }).fail(function () {});
        }

        function fetchBillerStats() {
            if (!routes.billerStats) return;
            $.get(routes.billerStats, function (data) {
                $('#biller-stat-revenue').text(fmt(data.today_revenue));
                $('#biller-stat-payment-requests').text(data.payment_requests || '0');
                $('#biller-stat-my-payments').text(data.my_payments || '0');
                $('#biller-stat-consultations').text(data.consultations || '0');
            }).fail(function () {});
        }

        function fetchAdminStats() {
            if (!routes.adminStats) return;
            $.get(routes.adminStats, function (data) {
                $('#admin-stat-staff').text(data.total_staff || '0');
                $('#admin-stat-patients').text(data.total_patients || '0');
                $('#admin-stat-clinics').text(data.total_clinics || '0');
                $('#admin-stat-revenue').text(fmt(data.total_revenue));
            }).fail(function () {});
        }

        function fetchPharmacyStats() {
            if (!routes.pharmacyStats) return;
            $.get(routes.pharmacyStats, function (data) {
                $('#pharm-stat-queue').text(data.queue_today || '0');
                $('#pharm-stat-dispensed').text(data.dispensed_today || '0');
                $('#pharm-stat-products').text(data.total_products || '0');
                $('#pharm-stat-low-stock').text(data.low_stock || '0');
            }).fail(function () {});
        }

        function fetchNursingStats() {
            if (!routes.nursingStats) return;
            $.get(routes.nursingStats, function (data) {
                $('#nurs-stat-vitals-queue').text(data.vitals_queue || '0');
                $('#nurs-stat-bed-requests').text(data.bed_requests || '0');
                $('#nurs-stat-medication-due').text(data.medication_due || '0');
                $('#nurs-stat-admitted').text(data.admitted_patients || '0');
            }).fail(function () {});
        }

        function fetchLabStats() {
            if (!routes.labStats) return;
            $.get(routes.labStats, function (data) {
                $('#lab-stat-queue').text(data.lab_queue || '0');
                $('#lab-stat-completed').text(data.completed_today || '0');
                $('#lab-stat-month').text(data.total_services || '0');
                $('#lab-stat-services').text(data.total_services || '0');
            }).fail(function () {});
        }

        function fetchDoctorStats() {
            if (!routes.doctorStats) return;
            $.get(routes.doctorStats, function (data) {
                $('#doc-stat-consultations').text(data.consultations_today || '0');
                $('#doc-stat-ward-rounds').text(data.ward_rounds || '0');
                $('#doc-stat-patients').text(data.my_patients || '0');
                $('#doc-stat-appointments').text(data.appointments_today || '0');
            }).fail(function () {});
        }

        function fetchHmoStats() {
            if (!routes.hmoStats) return;
            $.get(routes.hmoStats, function (data) {
                $('#hmo-stat-patients').text(data.hmo_patients || '0');
                $('#hmo-stat-pending-claims').text(data.pending_claims || '0');
                $('#hmo-stat-approved-claims').text(data.approved_claims || '0');
                $('#hmo-stat-total-hmos').text(data.total_hmos || '0');
            }).fail(function () {});
        }

        // ========================================
        // Enhanced Data Fetchers
        // ========================================
        function loadEnhancedReception() {
            if (!routes.dataReception) return;
            $.get(routes.dataReception, function (d) {
                renderQueues('recep-queues', d.queues);
                renderInsights('recep-insights', d.insights);
                if (d.hourlyFlow) renderBarChart('recepHourlyFlowChart', d.hourlyFlow, 'Patients', '#3b82f6');
                if (d.patientBreakdown && d.patientBreakdown.length) {
                    renderDoughnutChart('recepPatientBreakdownChart', d.patientBreakdown, d.patientBreakdown.map(function (x) { return x.color; }));
                }
                renderActivityTable('recep-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'Type', key: 'visit_type' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedBilling() {
            if (!routes.dataBilling) return;
            $.get(routes.dataBilling, function (d) {
                renderQueues('biller-queues', d.queues);
                renderInsights('biller-insights', d.insights);
                if (d.revenueTrend && d.revenueTrend.length) renderLineChart('billerRevenueChart', d.revenueTrend, 'Revenue (\u20A6)', '#10b981');
                if (d.paymentMethods && d.paymentMethods.length) {
                    renderDoughnutChart('billerPaymentMethodsChart', d.paymentMethods, d.paymentMethods.map(function (x) { return x.color; }));
                }
                renderActivityTable('biller-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'Amount', key: 'amount_formatted' },
                    { label: 'Method', key: 'method' },
                    { label: 'Processed By', key: 'processed_by' }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedPharmacy() {
            if (!routes.dataPharmacy) return;
            $.get(routes.dataPharmacy, function (d) {
                renderQueues('pharm-queues', d.queues);
                renderInsights('pharm-insights', d.insights);
                if (d.stats) {
                    $('#pharm-stat-queue').text(d.stats.queue || '0');
                    $('#pharm-stat-dispensed').text(d.stats.dispensed || '0');
                    $('#pharm-stat-products').text(d.stats.products || '0');
                    $('#pharm-stat-low-stock').text(d.stats.low_stock || '0');
                }
                if (d.dispensingTrend && d.dispensingTrend.length) renderLineChart('pharmacyDispensingChart', d.dispensingTrend, 'Dispensed', '#16a34a');
                if (d.stockHealth && d.stockHealth.length) renderDoughnutChart('pharmacyStockChart', d.stockHealth, d.stockHealth.map(function (x) { return x.color; }));
                renderActivityTable('pharm-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'Product', key: 'product' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedStore() {
            if (!routes.dataStore) return;
            $.get(routes.dataStore, function (d) {
                renderQueues('store-queues', d.queues);
                renderInsights('store-insights', d.insights);
                if (d.stats) {
                    $('#store-stat-pending-reqs').text(d.stats.pending_reqs || '0');
                    $('#store-stat-pending-pos').text(d.stats.pending_pos || '0');
                    $('#store-stat-approved-pos').text(d.stats.approved_pos || '0');
                    $('#store-stat-fulfill').text(d.stats.fulfill || '0');
                }
                if (d.requisitionTrend && d.requisitionTrend.length) renderLineChart('storeRequisitionChart', d.requisitionTrend, 'Requisitions', '#f59e0b');
                if (d.stockHealth && d.stockHealth.length) renderDoughnutChart('storeStockChart', d.stockHealth, d.stockHealth.map(function (x) { return x.color; }));
                renderActivityTable('store-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Type', key: 'type' },
                    { label: 'Ref', key: 'ref' },
                    { label: 'Detail', key: 'detail' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedMaternity() {
            if (!routes.dataMaternity) return;
            $.get(routes.dataMaternity, function (d) {
                renderQueues('mat-queues', d.queues);
                renderInsights('mat-insights', d.insights);
                if (d.stats) {
                    $('#mat-stat-active').text(d.stats.active || '0');
                    $('#mat-stat-anc').text(d.stats.anc || '0');
                    $('#mat-stat-deliveries').text(d.stats.deliveries || '0');
                    $('#mat-stat-pnc').text(d.stats.pnc || '0');
                }
                if (d.enrollmentTrend && d.enrollmentTrend.length) renderLineChart('maternityEnrollmentChart', d.enrollmentTrend, 'Enrollments', '#ef4444');
                if (d.riskBreakdown && d.riskBreakdown.length) renderDoughnutChart('maternityRiskChart', d.riskBreakdown, d.riskBreakdown.map(function (x) { return x.color; }));
                renderActivityTable('mat-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Type', key: 'type' },
                    { label: 'Ref', key: 'ref' },
                    { label: 'Detail', key: 'detail' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedMorgue() {
            if (!routes.dataMorgue) return;
            $.get(routes.dataMorgue, function (d) {
                renderQueues('morgue-queues', d.queues);
                renderInsights('morgue-insights', d.insights);
                if (d.stats) {
                    $('#morgue-stat-occupants').text(d.stats.occupants || '0');
                    $('#morgue-stat-admissions').text(d.stats.admissions || '0');
                    $('#morgue-stat-releases').text(d.stats.releases || '0');
                    $('#morgue-stat-pending').text(d.stats.pending || '0');
                }
                if (d.admissionTrend && d.admissionTrend.length) renderLineChart('morgueAdmissionChart', d.admissionTrend, 'Admissions', '#64748b');
                if (d.statusBreakdown && d.statusBreakdown.length) renderDoughnutChart('morgueStatusChart', d.statusBreakdown, d.statusBreakdown.map(function (x) { return x.color; }));
                renderActivityTable('morgue-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Type', key: 'type' },
                    { label: 'Ref', key: 'ref' },
                    { label: 'Detail', key: 'detail' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedEss() {
            if (!routes.dataEss) return;
            $.get(routes.dataEss, function (d) {
                renderQueues('ess-queues', d.queues);
                renderInsights('ess-insights', d.insights);
                if (d.stats) {
                    $('#ess-stat-balance').text(d.stats.balance || '0');
                    $('#ess-stat-pending').text(d.stats.pending || '0');
                    $('#ess-stat-upcoming').text(d.stats.upcoming || '0');
                    $('#ess-stat-payslip').text(d.stats.payslip || '—');
                }
                if (d.leaveBreakdown && d.leaveBreakdown.length) renderDoughnutChart('essLeaveChart', d.leaveBreakdown, ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']);
                renderActivityTable('ess-activity', d.activity, [
                    { label: 'Date', key: 'time' },
                    { label: 'Type', key: 'type' },
                    { label: 'Category', key: 'ref' },
                    { label: 'Detail', key: 'detail' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedChildHealth() {
            if (!routes.dataChildHealth) return;
            $.get(routes.dataChildHealth, function (d) {
                renderQueues('child-queues', d.queues);
                renderInsights('child-insights', d.insights);
                if (d.stats) {
                    $('#child-stat-today').text(d.stats.today || '0');
                    $('#child-stat-overdue').text(d.stats.overdue || '0');
                    $('#child-stat-growth').text(d.stats.growth || '0');
                    $('#child-stat-new').text(d.stats.new || '0');
                }
                if (d.statusBreakdown && d.statusBreakdown.length) renderDoughnutChart('childStatusChart', d.statusBreakdown, d.statusBreakdown.map(function (x) { return x.color; }));
                renderActivityTable('child-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Type', key: 'type' },
                    { label: 'Patient', key: 'ref' },
                    { label: 'Detail', key: 'detail' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedHR() {
            if (!routes.dataHr) return;
            $.get(routes.dataHr, function (d) {
                renderQueues('hr-queues', d.queues);
                renderInsights('hr-insights', d.insights);
                if (d.stats) {
                    $('#hr-stat-total').text(d.stats.total || '0');
                    $('#hr-stat-leave').text(d.stats.leave || '0');
                    $('#hr-stat-pending').text(d.stats.pending || '0');
                    $('#hr-stat-new').text(d.stats.new || '0');
                }
                if (d.deptBreakdown && d.deptBreakdown.length) renderDoughnutChart('hrDeptChart', d.deptBreakdown, ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#ec4899']);
                renderActivityTable('hr-activity', d.activity, [
                    { label: 'Date', key: 'time' },
                    { label: 'Type', key: 'type' },
                    { label: 'Staff', key: 'ref' },
                    { label: 'Detail', key: 'detail' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedTheatre() {
            if (!routes.dataTheatre) return;
            $.get(routes.dataTheatre, function (d) {
                renderQueues('theatre-queues', d.queues);
                renderInsights('theatre-insights', d.insights);
                if (d.stats) {
                    $('#theatre-stat-scheduled').text(d.stats.scheduled || '0');
                    $('#theatre-stat-ongoing').text(d.stats.ongoing || '0');
                    $('#theatre-stat-completed').text(d.stats.completed || '0');
                    $('#theatre-stat-pending').text(d.stats.pending || '0');
                }
                if (d.categoryBreakdown && d.categoryBreakdown.length) renderDoughnutChart('theatreDeptChart', d.categoryBreakdown, ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#ec4899']);
                renderActivityTable('theatre-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Type', key: 'type' },
                    { label: 'Patient', key: 'ref' },
                    { label: 'Detail', key: 'detail' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedNursing() {
            if (!routes.dataNursing) return;
            $.get(routes.dataNursing, function (d) {
                renderQueues('nurs-queues', d.queues);
                renderInsights('nurs-insights', d.insights);
                if (d.stats) {
                    $('#nurs-stat-vitals-queue').text(d.stats.vitals_queue || '0');
                    $('#nurs-stat-bed-requests').text(d.stats.bed_requests || '0');
                    $('#nurs-stat-medication-due').text(d.stats.medication_due || '0');
                    $('#nurs-stat-admitted').text(d.stats.admitted || '0');
                }
                if (d.vitalsTrend && d.vitalsTrend.length) renderLineChart('nursingVitalsChart', d.vitalsTrend, 'Vitals', '#ef4444');
                if (d.bedOccupancy && d.bedOccupancy.length) renderDoughnutChart('nursingBedChart', d.bedOccupancy, d.bedOccupancy.map(function (x) { return x.color; }));
                renderActivityTable('nurs-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'Activity', render: function (r) { return badgeHtml(r.activity, r.activity_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedLab() {
            if (!routes.dataLab) return;
            $.get(routes.dataLab, function (d) {
                renderQueues('lab-queues', d.queues);
                renderInsights('lab-insights', d.insights);
                if (d.stats) {
                    $('#lab-stat-queue').text(d.stats.queue || '0');
                    $('#lab-stat-completed').text(d.stats.completed || '0');
                    $('#lab-stat-month').text(d.stats.tests_this_month || '0');
                    $('#lab-stat-services').text(d.stats.services || '0');
                }
                if (d.categoryBreakdown && d.categoryBreakdown.length) renderDoughnutChart('labCategoriesChart', d.categoryBreakdown, ['#0891b2', '#0e7490', '#155e75', '#164e63', '#06b6d4']);
                if (d.requestTrend && d.requestTrend.length) renderLineChart('labRequestsChart', d.requestTrend, 'Test Requests', '#0891b2');
                renderActivityTable('lab-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'Test', key: 'test_name' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            });
        }

        function loadEnhancedImaging() {
            if (!routes.dataImaging) return;
            $.get(routes.dataImaging, function (d) {
                renderQueues('imaging-queues', d.queues);
                renderInsights('imaging-insights', d.insights);
                if (d.stats) {
                    $('#imaging-stat-pending').text(d.stats.pending || '0');
                    $('#imaging-stat-completed').text(d.stats.completed || '0');
                    $('#imaging-stat-month').text(d.stats.scans_this_month || '0');
                    $('#imaging-stat-services').text(d.stats.services || '0');
                }
                if (d.categoryBreakdown && d.categoryBreakdown.length) renderDoughnutChart('imagingCategoriesChart', d.categoryBreakdown, ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899']);
                if (d.requestTrend && d.requestTrend.length) renderLineChart('imagingRequestsChart', d.requestTrend, 'Scan Requests', '#4f46e5');
                renderActivityTable('imaging-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'Scan', key: 'test_name' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            });
        }

        function loadEnhancedDoctor() {
            if (!routes.dataDoctor) return;
            $.get(routes.dataDoctor, function (d) {
                renderQueues('doc-queues', d.queues);
                renderInsights('doc-insights', d.insights);
                renderActivityTable('doc-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'Clinic', key: 'clinic' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedHmo() {
            if (!routes.dataHmo) return;
            $.get(routes.dataHmo, function (d) {
                renderQueues('hmo-queues', d.queues);
                renderInsights('hmo-insights', d.insights);
                if (d.stats) {
                    $('#hmo-stat-patients').text(d.stats.patients || '0');
                    $('#hmo-stat-pending-claims').text(d.stats.pending_claims || '0');
                    $('#hmo-stat-approved-claims').text(d.stats.approved_claims || '0');
                    $('#hmo-stat-total-hmos').text(d.stats.total_hmos || '0');
                }
                if (d.claimsTrend && d.claimsTrend.length) renderLineChart('hmoClaimsChart', d.claimsTrend, 'Claims (\u20A6)', '#7c3aed');
                if (d.providerDistribution && d.providerDistribution.length) renderDoughnutChart('hmoDistributionChart', d.providerDistribution, d.providerDistribution.map(function (x) { return x.color; }));
                renderActivityTable('hmo-activity', d.activity, [
                    { label: 'Time', key: 'time' },
                    { label: 'Patient', key: 'patient_name' },
                    { label: 'HMO', key: 'hmo_name' },
                    { label: 'Amount', key: 'amount_formatted' },
                    { label: 'Status', render: function (r) { return badgeHtml(r.status_label, r.status_color); } }
                ]);
            }).fail(function () {});
        }

        function loadEnhancedAccounts() {
            if (!routes.dataAccounts) return;
            $.get(routes.dataAccounts, function (d) {
                if (d.summary) {
                    $('#acct-stat-today-revenue').text(fmt(d.summary.revenue_today));
                    $('#acct-stat-month-revenue').text(fmt(d.summary.revenue_month));
                    $('#acct-stat-outstanding').text(fmt(d.summary.outstanding));
                }
                if (d.kpis) {
                    $('#acct-stat-collection-rate').text(d.kpis.collection_rate + '%');
                }
                renderInsights('acct-insights', d.insights);
                if (d.revenueTrend && d.revenueTrend.length) renderLineChart('acctRevenueChart', d.revenueTrend, 'Revenue (\u20A6)', '#10b981');
                if (d.paymentMethods && d.paymentMethods.length) renderDoughnutChart('acctPaymentMethodsChart', d.paymentMethods, d.paymentMethods.map(function (x) { return x.color; }));

                if (d.departmentRevenue && d.departmentRevenue.length) {
                    var html = '';
                    var maxVal = Math.max.apply(null, d.departmentRevenue.map(function (x) { return parseFloat(x.total); }));
                    d.departmentRevenue.forEach(function (dept) {
                        var pct = maxVal > 0 ? (parseFloat(dept.total) / maxVal * 100) : 0;
                        html += '<div class="dash-aging-bar"><div class="dash-aging-label"><span>' + dept.department + '</span><span>' + fmt(dept.total) + '</span></div>' +
                            '<div class="dash-aging-track"><div class="dash-aging-fill" style="width:' + pct + '%;background:#3b82f6;"></div></div></div>';
                    });
                    $('#acct-dept-bars').html(html);
                }

                if (d.kpis && d.kpis.outstanding_aging) {
                    var agingColors = { '0-30 days': '#10b981', '30-60 days': '#f59e0b', '60-90 days': '#f97316', '90+ days': '#ef4444' };
                    var agingData = d.kpis.outstanding_aging;
                    var agingMax = Math.max.apply(null, Object.values(agingData).map(Number));
                    var ahtml = '';
                    Object.keys(agingData).forEach(function (key) {
                        var val = parseFloat(agingData[key]);
                        var pct = agingMax > 0 ? (val / agingMax * 100) : 0;
                        ahtml += '<div class="dash-aging-bar"><div class="dash-aging-label"><span>' + key + '</span><span>' + fmt(val) + '</span></div>' +
                            '<div class="dash-aging-track"><div class="dash-aging-fill" style="width:' + pct + '%;background:' + (agingColors[key] || '#6b7280') + ';"></div></div></div>';
                    });
                    $('#acct-aging-bars').html(ahtml);
                }
            }).fail(function () {});

            if (routes.dataAuditLog) {
                $.get(routes.dataAuditLog, { limit: 15 }, function (d) {
                    var $body = $('#acct-audit-body');
                    if (!d.log || !d.log.length) {
                        $body.html('<tr><td colspan="4" class="text-center text-muted py-2" style="font-size:0.8rem;">No audit data</td></tr>');
                        return;
                    }
                    var html = '';
                    d.log.forEach(function (row) {
                        html += '<tr><td>' + row.time + '</td><td>' + (row.user_name || 'System') + '</td>' +
                            '<td>' + badgeHtml(row.event, row.event_color) + '</td>' +
                            '<td>' + (row.module || '—') + '</td></tr>';
                    });
                    $body.html(html);
                }).fail(function () {});
            }
        }

        function loadEnhancedAudit() {
            if (!routes.dataAudit) return;
            $.get(routes.dataAudit, function (d) {
                if (d.summary) {
                    $('#audit-stat-revenue').text(fmt(d.summary.revenue_today));
                    $('#audit-stat-admissions').text(d.summary.active_admissions);
                    $('#audit-stat-requisitions').text(d.summary.pending_requisitions);
                    $('#audit-stat-diagnostics').text(d.summary.diagnostic_orders);
                    $('#audit-stat-discounts').text(fmt(d.summary.system_discounts_today));
                    $('#audit-stat-refunds').text(fmt(d.summary.refunds_today));
                }

                if (d.recentActivity) {
                    var tb = $('#audit-activity-body');
                    var th = $('#audit-activity-head');
                    tb.empty();
                    th.html('<tr><th>Time</th><th>User</th><th>Event</th><th>Module</th></tr>');

                    if (d.recentActivity.length === 0) {
                        tb.append('<tr><td colspan="4" class="text-center text-muted py-2" style="font-size:0.8rem;">No recent system logs.</td></tr>');
                    } else {
                        d.recentActivity.forEach(function (l) {
                            tb.append('<tr>' +
                                '<td><span class="text-muted" title="' + l.full_time + '">' + l.time + '</span></td>' +
                                '<td><i class="mdi mdi-account-circle text-muted me-1"></i>' + l.user + '</td>' +
                                '<td>' + badgeHtml(l.event, l.event_color) + '</td>' +
                                '<td><span class="badge bg-light text-dark border border-secondary">' + l.module + '</span></td>' +
                                '</tr>');
                        });
                    }
                }

                if (d.revenueTrend && d.revenueTrend.length > 0) {
                    var labels = d.revenueTrend.map(function (x) { return x.date; });
                    var values = d.revenueTrend.map(function (x) { return x.total; });
                    var ctx = document.getElementById('auditRevenueTrendChart');
                    if (ctx && typeof Chart !== 'undefined') {
                        if (window.auditRevChart) window.auditRevChart.destroy();
                        window.auditRevChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Revenue',
                                    data: values,
                                    borderColor: '#059669',
                                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                                    tension: 0.4,
                                    fill: true,
                                    pointBackgroundColor: '#fff',
                                    pointBorderColor: '#059669',
                                    pointBorderWidth: 1.5,
                                    pointRadius: 3
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: { borderDash: [2, 4], color: '#f3f4f6' },
                                        ticks: { callback: function (val) { return '\u20A6' + (val / 1000) + 'k'; } }
                                    },
                                    x: { grid: { display: false } }
                                }
                            }
                        });
                    }
                }
            }).fail(function () {});
        }

        // ========================================
        // Chart Data Fetchers (legacy/domain specific)
        // ========================================
        function renderRegistrationsChart() {
            if (!routes.chartRegistrations) return;
            $.get(routes.chartRegistrations, { start: getThisMonthStart(), end: getToday() }, function (data) {
                renderLineChart('adminRegistrationsChart', data, 'Registrations', '#3b82f6');
                renderLineChart('recepRegistrationsChart', data, 'Registrations', '#3b82f6');
            });
        }

        function renderDoctorCharts() {
            if (routes.clinicTimeline) {
                $.get(routes.clinicTimeline, { start: getThisMonthStart(), end: getToday() }, function (data) {
                    renderLineChart('doctorConsultationsChart', data, 'Consultations', '#3b82f6');
                });
            }
            if (routes.clinicStatus) {
                $.get(routes.clinicStatus, { start: getThisMonthStart(), end: getToday() }, function (data) {
                    if (data && data.length) {
                        renderDoughnutChart('doctorOutcomesChart', data.map(function (d) {
                            return { label: d.status, value: d.total };
                        }), ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']);
                    }
                });
            }
        }

        function renderRevenueChart(targetId) {
            if (!routes.chartRevenue) return;
            $.get(routes.chartRevenue, { start: getThisMonthStart(), end: getToday() }, function (data) {
                renderLineChart(targetId, data, 'Revenue (\u20A6)', '#10b981');
            });
        }

        // ========================================
        // Tab Loader & Switching
        // ========================================
        var _tabsLoaded = {};

        function loadTabData(tabName, forceRefresh) {
            if (!forceRefresh && _tabsLoaded[tabName]) {
                return;
            }
            _tabsLoaded[tabName] = true;

            switch (tabName) {
                case 'receptionist':
                    loadEnhancedReception();
                    fetchReceptionistStats();
                    renderRegistrationsChart();
                    break;
                case 'biller':
                    loadEnhancedBilling();
                    fetchBillerStats();
                    break;
                case 'admin':
                    fetchAdminStats();
                    renderRevenueChart('adminRevenueChart');
                    renderRegistrationsChart();
                    break;
                case 'pharmacy':
                    loadEnhancedPharmacy();
                    fetchPharmacyStats();
                    break;
                case 'store':
                    loadEnhancedStore();
                    break;
                case 'maternity':
                    loadEnhancedMaternity();
                    break;
                case 'morgue':
                    loadEnhancedMorgue();
                    break;
                case 'ess':
                    loadEnhancedEss();
                    break;
                case 'child_health':
                    loadEnhancedChildHealth();
                    break;
                case 'hr':
                    loadEnhancedHR();
                    break;
                case 'theatre':
                    loadEnhancedTheatre();
                    break;
                case 'nursing':
                    loadEnhancedNursing();
                    fetchNursingStats();
                    break;
                case 'lab':
                    loadEnhancedLab();
                    fetchLabStats();
                    break;
                case 'imaging':
                    loadEnhancedImaging();
                    break;
                case 'doctor':
                    loadEnhancedDoctor();
                    fetchDoctorStats();
                    renderDoctorCharts();
                    break;
                case 'hmo':
                    loadEnhancedHmo();
                    fetchHmoStats();
                    break;
                case 'accounts':
                    loadEnhancedAccounts();
                    break;
                case 'audit':
                    loadEnhancedAudit();
                    break;
            }
        }

        function initializeAllStats() {
            var $activeBtn = $('#dashboardTabs .nav-link.active');
            if ($activeBtn.length) {
                var tab = $activeBtn.attr('id').replace('-tab', '');
                loadTabData(tab);
            } else if (firstTabKey) {
                loadTabData(firstTabKey);
            }

            // Immediately prefetch all 18 tab KPI stats so cards are populated across all perspectives without dashes
            setTimeout(function () {
                fetchReceptionistStats();
                fetchBillerStats();
                fetchAdminStats();
                fetchPharmacyStats();
                fetchNursingStats();
                fetchLabStats();
                fetchDoctorStats();
                fetchHmoStats();
                loadEnhancedStore();
                loadEnhancedMaternity();
                loadEnhancedMorgue();
                loadEnhancedEss();
                loadEnhancedChildHealth();
                loadEnhancedHR();
                loadEnhancedTheatre();
                loadEnhancedImaging();
                loadEnhancedAccounts();
                loadEnhancedAudit();
            }, 60);
        }

        initializeAllStats();

        // Support both click and shown.bs.tab across Bootstrap 4 & 5
        function handleTabSwitch(targetTab) {
            if (!targetTab) return;
            loadTabData(targetTab);
        }

        $(document).on('click', '#dashboardTabs button, #dashboardTabs .nav-link, #dashboardTabs a', function (e) {
            var id = $(this).attr('id') || '';
            var targetTab = id.replace('-tab', '');
            if (targetTab) {
                handleTabSwitch(targetTab);
            }
        });

        $(document).on('shown.bs.tab', '#dashboardTabs button, #dashboardTabs .nav-link, #dashboardTabs a', function (e) {
            var id = $(e.target).attr('id') || '';
            var targetTab = id.replace('-tab', '');
            if (targetTab) {
                handleTabSwitch(targetTab);
            }
        });

        // 5-minute auto refresh
        setInterval(function () {
            var $activeBtn = $('#dashboardTabs .nav-link.active');
            if ($activeBtn.length) {
                loadTabData($activeBtn.attr('id').replace('-tab', ''), true);
            }
        }, 300000);
    });
})(jQuery);
