{{-- Clinical Reports Scripts Partial --}}
{{-- Included near the bottom of reception workbench.blade.php --}}
<script>
(function () {
    'use strict';

    // =========================================================================
    // CHART INSTANCES (kept to allow destroy/re-create on reload)
    // =========================================================================
    var crCharts = {};

    // =========================================================================
    // HELPERS
    // =========================================================================
    function getCrFilters() {
        return {
            date_from   : $('#cr-date-from').val() || '',
            date_to     : $('#cr-date-to').val()   || '',
            clinic_id   : $('#cr-clinic-filter').val() || '',
            hmo_id      : $('#cr-hmo-filter').val()    || '',
            ward_id     : $('#cr-ward-filter').val()   || '',
        };
    }

    function crDestroyChart(id) {
        if (crCharts[id]) {
            crCharts[id].destroy();
            delete crCharts[id];
        }
    }

    function crLoading(selector) {
        $(selector).html('<tr><td colspan="20" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
    }

    function crError(selector, msg) {
        $(selector).html('<tr><td colspan="20" class="text-center text-danger py-2"><i class="mdi mdi-alert-circle"></i> ' + (msg || 'Failed to load data.') + '</td></tr>');
    }

    var crStatusBadge = {
        pending   : 'secondary',
        booked    : 'primary',
        completed : 'success',
        declined  : 'danger',
        cancelled : 'danger',
        admitted  : 'success',
        discharged: 'secondary',
        active    : 'success',
        high      : 'danger',
        moderate  : 'warning',
        low       : 'info',
        normal    : 'secondary',
    };

    function badge(val, map) {
        var cls = (map || crStatusBadge)[String(val).toLowerCase()] || 'secondary';
        return '<span class="badge badge-' + cls + '">' + (val || 'N/A') + '</span>';
    }

    // =========================================================================
    // QUICK RANGE HELPER
    // =========================================================================
    function crApplyQuickRange(range) {
        var today = new Date();
        var from, to;
        to   = today.toISOString().split('T')[0];

        if (range === 'today') {
            from = to;
        } else if (range === 'week') {
            var d = new Date(today);
            d.setDate(d.getDate() - d.getDay());
            from = d.toISOString().split('T')[0];
        } else if (range === 'month') {
            from = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-01';
        } else if (range === 'last_month') {
            var lm = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            var lme = new Date(today.getFullYear(), today.getMonth(), 0);
            from = lm.toISOString().split('T')[0];
            to   = lme.toISOString().split('T')[0];
        } else if (range === 'quarter') {
            var q = Math.floor(today.getMonth() / 3);
            from = today.getFullYear() + '-' + String(q * 3 + 1).padStart(2, '0') + '-01';
        } else if (range === 'year') {
            from = today.getFullYear() + '-01-01';
        }
        if (from) $('#cr-date-from').val(from);
        if (to)   $('#cr-date-to').val(to);
    }

    // =========================================================================
    // INIT — called once when Clinical Reports main tab is activated
    // =========================================================================
    window.initClinicalReports = function () {
        // Set default range if blank
        if (!$('#cr-date-from').val()) {
            crApplyQuickRange('month');
        }
        loadCrOverview();
    };

    // =========================================================================
    // OVERVIEW
    // =========================================================================
    function loadCrOverview() {
        var params = getCrFilters();
        $('#cr-overview-kpis').html('<div class="col-12 text-center py-3"><div class="spinner-border text-primary"></div></div>');
        $.get('{{ route("clinical-reports.stats") }}', params)
            .done(function (data) {
                renderCrOverviewKpis(data);
                renderCrOverviewCharts(data);
            })
            .fail(function () {
                $('#cr-overview-kpis').html('<div class="col-12 text-center text-danger">Failed to load overview.</div>');
            });
    }

    function renderCrOverviewKpis(d) {
        var kpis = [
            { label: 'Total Encounters',  value: d.total_encounters  || 0, icon: 'mdi-account-multiple',    color: 'primary'  },
            { label: 'Unique Patients',   value: d.unique_patients   || 0, icon: 'mdi-account',              color: 'info'     },
            { label: 'Admissions',        value: d.total_admissions  || 0, icon: 'mdi-bed',                  color: 'warning'  },
            { label: 'Surgeries',         value: d.total_surgeries   || 0, icon: 'mdi-medical-bag',          color: 'success'  },
            { label: 'Deaths (RIP)',       value: d.total_deaths      || 0, icon: 'mdi-pulse',                color: 'danger'   },
            { label: 'Births',            value: d.total_births      || 0, icon: 'mdi-baby-carriage',        color: 'success'  },
            { label: 'Lab Requests',      value: d.total_lab         || 0, icon: 'mdi-test-tube',            color: 'secondary'},
            { label: 'Imaging Requests',  value: d.total_imaging     || 0, icon: 'mdi-radioactive',          color: 'secondary'},
        ];
        var html = '';
        kpis.forEach(function (k) {
            html += '<div class="col-md-3 col-6 mb-2">'
                  + '<div class="card border-left-' + k.color + ' shadow-sm h-100 py-1">'
                  + '<div class="card-body p-2">'
                  + '<div class="row no-gutters align-items-center">'
                  + '<div class="col mr-2"><div class="text-xs font-weight-bold text-' + k.color + ' text-uppercase mb-1">' + k.label + '</div>'
                  + '<div class="h5 mb-0 font-weight-bold text-gray-800">' + k.value.toLocaleString() + '</div></div>'
                  + '<div class="col-auto"><i class="mdi ' + k.icon + ' fa-2x text-gray-300"></i></div>'
                  + '</div></div></div></div>';
        });
        $('#cr-overview-kpis').html(html);
    }

    function renderCrOverviewCharts(d) {
        crDestroyChart('overviewBar');
        crDestroyChart('overviewLine');

        // Stacked bar: encounters per day (top diagnoses) — use encounters_by_day if provided
        if (d.encounters_by_day && d.encounters_by_day.length) {
            var labels = d.encounters_by_day.map(function (r) { return r.day; });
            var counts = d.encounters_by_day.map(function (r) { return r.total; });
            crCharts['overviewBar'] = new Chart($('#cr-overview-bar-chart')[0].getContext('2d'), {
                type: 'bar',
                data: { labels: labels, datasets: [{ label: 'Encounters', data: counts, backgroundColor: 'rgba(78,115,223,0.7)' }] },
                options: { responsive: true, plugins: { legend: { display: false }, title: { display: true, text: 'Daily Encounters' } }, scales: { y: { beginAtZero: true } } }
            });
        }

        // Line: admissions by day
        if (d.admissions_by_day && d.admissions_by_day.length) {
            var al = d.admissions_by_day.map(function (r) { return r.day; });
            var ac = d.admissions_by_day.map(function (r) { return r.total; });
            crCharts['overviewLine'] = new Chart($('#cr-overview-line-chart')[0].getContext('2d'), {
                type: 'line',
                data: { labels: al, datasets: [{ label: 'Admissions', data: ac, borderColor: '#1cc88a', backgroundColor: 'rgba(28,200,138,0.1)', tension: 0.3 }] },
                options: { responsive: true, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Daily Admissions' } }, scales: { y: { beginAtZero: true } } }
            });
        }
    }

    // =========================================================================
    // UNIT VISITS
    // =========================================================================
    function loadCrUnitVisits(clinicId) {
        var params = $.extend(getCrFilters(), clinicId ? { clinic_id: clinicId } : {});
        if (!clinicId) crLoading('#cr-unit-visits-table tbody');

        $.get('{{ route("clinical-reports.unit-visits") }}', params)
            .done(function (data) {
                var total = data.summary.reduce(function (s, r) { return s + parseInt(r.total); }, 0);
                var rows = '';
                (data.summary || []).forEach(function (r) {
                    var pct = total > 0 ? ((r.total / total) * 100).toFixed(1) : 0;
                    rows += '<tr style="cursor:pointer" class="cr-unit-visit-row" data-clinic-id="' + r.clinic_id + '" data-clinic-name="' + r.clinic_name + '">'
                          + '<td>' + r.clinic_name + '</td>'
                          + '<td class="text-center">' + parseInt(r.total).toLocaleString() + '</td>'
                          + '<td class="text-center">' + pct + '%</td></tr>';
                });
                $('#cr-unit-visits-table tbody').html(rows || '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>');
                renderCrUnitVisitsChart(data.summary, total);

                // Drill-down
                if (data.drill_down) {
                    var dr = '';
                    data.drill_down.forEach(function (e) {
                        dr += '<tr><td>' + (e.patient_id ? '<a href="/patient/' + e.patient_id + '">' + e.patient + '</a>' : e.patient) + '</td><td>' + e.file_no + '</td><td>' + e.date + '</td><td>' + e.doctor + '</td><td>' + e.hmo + '</td><td>' + badge(e.status) + '</td></tr>';
                    });
                    $('#cr-unit-visits-drill-table tbody').html(dr || '<tr><td colspan="6" class="text-center text-muted">No encounters</td></tr>');
                    $('#cr-unit-visits-drilldown').show();
                }
            })
            .fail(function () { crError('#cr-unit-visits-table tbody'); });
    }

    function renderCrUnitVisitsChart(summary, total) {
        crDestroyChart('unitVisits');
        if (!summary.length) return;
        var labels = summary.map(function (r) { return r.clinic_name; });
        var data   = summary.map(function (r) { return r.total; });
        var colors = labels.map(function (_, i) { return 'hsl(' + (i * 47) + ',65%,55%)'; });
        crCharts['unitVisits'] = new Chart($('#cr-unit-visits-chart')[0].getContext('2d'), {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: colors }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
        });
    }

    // =========================================================================
    // HMO TRENDS
    // =========================================================================
    function loadCrHmoTrends() {
        var params = getCrFilters();
        crLoading('#cr-hmo-totals-table tbody');
        $.get('{{ route("clinical-reports.hmo-trends") }}', params)
            .done(function (data) {
                var rows = '';
                (data.totals || []).forEach(function (r) {
                    rows += '<tr><td>' + r.hmo_name + '</td><td class="text-center">' + r.total + '</td><td class="text-center">' + r.unique_patients + '</td></tr>';
                });
                $('#cr-hmo-totals-table tbody').html(rows || '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>');
                renderCrHmoTrendsChart(data);
            })
            .fail(function () { crError('#cr-hmo-totals-table tbody'); });
    }

    function renderCrHmoTrendsChart(data) {
        crDestroyChart('hmoTrends');
        if (!data.daily || !data.daily.length) return;

        // Build datasets grouped by HMO name
        var byHmo = {};
        var allDays = [];
        data.daily.forEach(function (r) {
            if (!byHmo[r.hmo_name]) byHmo[r.hmo_name] = {};
            byHmo[r.hmo_name][r.day] = r.total;
            if (allDays.indexOf(r.day) === -1) allDays.push(r.day);
        });
        allDays.sort();

        var datasets = [];
        var colorIdx = 0;
        Object.keys(byHmo).forEach(function (hmo) {
            var hue    = colorIdx * 53;
            var color  = 'hsl(' + hue + ',65%,50%)';
            var bgColor = 'hsla(' + hue + ',65%,50%,0.15)';
            colorIdx++;
            datasets.push({
                label: hmo,
                data: allDays.map(function (d) { return byHmo[hmo][d] || 0; }),
                borderColor: color,
                backgroundColor: bgColor,
                tension: 0.3,
                fill: false,
            });
        });

        crCharts['hmoTrends'] = new Chart($('#cr-hmo-trends-chart')[0].getContext('2d'), {
            type: 'line',
            data: { labels: allDays, datasets: datasets },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'HMO Encounter Trends' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // =========================================================================
    // DIAGNOSIS SEARCH
    // =========================================================================
    var crDiagDebounce = null;

    function loadCrDiagnosis() {
        var keyword = $.trim($('#cr-diagnosis-keyword').val());
        if (keyword.length < 2) {
            $('#cr-diagnosis-tbody').html('<tr><td colspan="7" class="text-center text-muted small">Type at least 2 characters to search</td></tr>');
            $('#cr-diagnosis-result-count').text('');
            return;
        }
        var params = $.extend(getCrFilters(), { keyword: keyword });
        $('#cr-diagnosis-tbody').html('<tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
        $.get('{{ route("clinical-reports.search-diagnosis") }}', params)
            .done(function (data) {
                $('#cr-diagnosis-result-count').text(data.length + ' diagnosis match(es) found');
                if (!data.length) {
                    $('#cr-diagnosis-tbody').html('<tr><td colspan="7" class="text-center text-muted">No matching diagnoses found</td></tr>');
                    return;
                }
                var html = '';
                data.forEach(function (row, idx) {
                    var statusList  = (row.statuses  || []).join(', ') || 'N/A';
                    var queryList   = (row.queries   || []).join(', ') || 'N/A';
                    var rowId = 'cr-diag-row-' + idx;
                    html += '<tr class="cr-diag-main-row" id="' + rowId + '">'
                          + '<td class="text-center"><button class="btn btn-xs btn-outline-primary cr-diag-expand-btn" data-target="cr-diag-enc-' + idx + '" title="View encounters"><i class="mdi mdi-chevron-right"></i></button></td>'
                          + '<td><code>' + (row.icd_code || 'N/A') + '</code></td>'
                          + '<td>' + row.diagnosis + '</td>'
                          + '<td class="text-center font-weight-bold">' + row.unique_patients + '</td>'
                          + '<td class="text-center">' + row.total_encounters + '</td>'
                          + '<td><small>' + statusList + '</small></td>'
                          + '<td><small>' + queryList + '</small></td>'
                          + '</tr>';
                    // Expandable encounters sub-table
                    html += '<tr class="cr-diag-enc-row d-none" id="cr-diag-enc-' + idx + '">'
                          + '<td colspan="7" class="p-0 bg-light">'
                          + '<div class="p-2">'
                          + '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 cr-diag-enc-table" data-icd="' + (row.icd_code || '') + '" data-name="' + row.diagnosis + '">'
                          + '<thead class="thead-dark"><tr><th>Patient</th><th>File No</th><th>Date</th><th>Doctor</th><th>Query</th><th>Status</th><th>Details</th></tr></thead>'
                          + '<tbody><tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr></tbody>'
                          + '</table></div>'
                          + '</div>'
                          + '</td></tr>';
                });
                $('#cr-diagnosis-tbody').html(html);
            })
            .fail(function () { crError('#cr-diagnosis-tbody'); });
    }

    // Load encounters for a specific diagnosis when expanded
    function loadCrDiagnosisEncounters($table) {
        var icd  = $table.data('icd');
        var name = $table.data('name');
        var params = $.extend(getCrFilters(), { icd_code: icd, diagnosis_name: name });
        $.get('{{ route("clinical-reports.drill-down") }}', $.extend(params, { type: 'diagnosis' }))
            .done(function (data) {
                var rows = (data.records || data || []);
                if (!rows.length) {
                    $table.find('tbody').html('<tr><td colspan="7" class="text-center text-muted">No encounters found</td></tr>');
                    return;
                }
                var html = '';
                rows.forEach(function (e) {
                    html += '<tr>'
                          + '<td><a href="/patient/' + e.patient_id + '">' + e.patient + '</a></td>'
                          + '<td>' + (e.file_no || '') + '</td>'
                          + '<td>' + (e.date || e.encounter_date || '') + '</td>'
                          + '<td>' + (e.doctor || 'N/A') + '</td>'
                          + '<td><small>' + (e.query_type || 'N/A') + '</small></td>'
                          + '<td>' + badge(e.status) + '</td>'
                          + '<td><button class="btn btn-xs btn-info cr-enc-detail-btn" data-enc-id="' + e.id + '" title="View encounter"><i class="mdi mdi-eye"></i></button></td>'
                          + '</tr>';
                });
                $table.find('tbody').html(html);
            })
            .fail(function () { $table.find('tbody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load encounters</td></tr>'); });
    }

    // =========================================================================
    // MATERNITY
    // =========================================================================
    function loadCrMaternity(sub) {
        sub = sub || 'enrollments';
        var params = $.extend(getCrFilters(), { sub_category: sub });

        // Always load summary KPIs
        $.get('{{ route("clinical-reports.maternity") }}', $.extend({}, getCrFilters(), { sub_category: 'summary' }))
            .done(function (data) { renderCrMaternityKpis(data.summary); });

        if (sub === 'summary') return;

        var tableMap = {
            enrollments : '#cr-mat-enrollments-table tbody',
            anc_visits  : '#cr-mat-anc-table tbody',
            deliveries  : '#cr-mat-deliveries-table tbody',
            babies      : '#cr-mat-babies-table tbody',
            postnatal   : '#cr-mat-postnatal-table tbody',
        };
        var sel = tableMap[sub];
        if (!sel) return;
        crLoading(sel);

        $.get('{{ route("clinical-reports.maternity") }}', params)
            .done(function (data) {
                var rows = data.data || [];
                if (!rows.length) { $(sel).html('<tr><td colspan="20" class="text-center text-muted">No records</td></tr>'); return; }
                var html = '';
                if (sub === 'enrollments') {
                    rows.forEach(function (r) {
                        html += '<tr><td><a href="/patient/' + r.patient_id + '">' + r.patient + '</a></td><td>' + r.file_no + '</td><td>' + r.date + '</td><td>' + r.edd + '</td><td>' + badge(r.risk, { high: 'danger', moderate: 'warning', normal: 'secondary', low: 'info' }) + '</td><td>' + badge(r.status, { active: 'success', closed: 'secondary' }) + '</td></tr>';
                    });
                } else if (sub === 'anc_visits') {
                    rows.forEach(function (r) {
                        html += '<tr><td>' + (r.patient_id ? '<a href="/patient/' + r.patient_id + '">' + r.patient + '</a>' : r.patient) + '</td><td>' + r.file_no + '</td><td>' + r.date + '</td><td>' + (r.weight_kg || 'N/A') + '</td><td>' + r.bp + '</td><td>' + (r.fundal_ht || 'N/A') + '</td><td>' + (r.gestational_age || 'N/A') + '</td></tr>';
                    });
                } else if (sub === 'deliveries') {
                    rows.forEach(function (r) {
                        html += '<tr><td><a href="/patient/' + r.patient_id + '">' + r.mother + '</a></td><td>' + r.file_no + '</td><td>' + r.date + '</td><td><small>' + r.type + '</small></td><td class="text-center">' + r.babies + '</td><td>' + (r.blood_loss || 'N/A') + '</td><td><small>' + r.complications + '</small></td></tr>';
                    });
                } else if (sub === 'babies') {
                    rows.forEach(function (r) {
                        var sbBadge = r.still_birth === 'Yes' ? badge('Stillbirth', { stillbirth: 'dark' }) : '';
                        html += '<tr><td>' + r.mother + '</td><td>' + r.baby + '</td><td>' + r.sex + '</td><td>' + (r.weight_kg || 'N/A') + '</td><td>' + sbBadge + '</td><td>' + badge(r.status, { alive: 'success', deceased: 'danger' }) + '</td><td>' + (r.cause_of_death || '') + '</td></tr>';
                    });
                } else if (sub === 'postnatal') {
                    rows.forEach(function (r) {
                        html += '<tr><td>' + (r.patient_id ? '<a href="/patient/' + r.patient_id + '">' + r.patient + '</a>' : r.patient) + '</td><td>' + r.date + '</td><td>' + r.mother_condition + '</td><td>' + r.baby_condition + '</td></tr>';
                    });
                }
                $(sel).html(html);
            })
            .fail(function () { crError(sel); });
    }

    function renderCrMaternityKpis(s) {
        if (!s) return;
        var kpis = [
            { label: 'New Enrollments', value: s.enrollments, color: 'primary' },
            { label: 'Active Enrollments', value: s.activeEnroll, color: 'info' },
            { label: 'High Risk', value: s.highRisk, color: 'danger' },
            { label: 'ANC Visits', value: s.ancVisits, color: 'success' },
            { label: 'Deliveries', value: s.deliveries, color: 'warning' },
            { label: 'Live Births', value: s.liveBirths, color: 'success' },
            { label: 'Stillbirths', value: s.stillbirths, color: 'dark' },
            { label: 'Neonatal Deaths', value: s.neonatalDeath, color: 'danger' },
            { label: 'Postnatal Visits', value: s.postnatal, color: 'secondary' },
        ];
        var html = '';
        kpis.forEach(function (k) {
            html += '<div class="col mb-1"><div class="card border-top border-' + k.color + ' shadow-sm"><div class="card-body p-2 text-center"><div class="small text-muted">' + k.label + '</div><div class="h5 mb-0 font-weight-bold">' + (k.value || 0) + '</div></div></div></div>';
        });
        $('#cr-maternity-kpis').html(html);
    }

    // =========================================================================
    // MORTALITY
    // =========================================================================
    function loadCrMortality() {
        var params = $.extend(getCrFilters(), { type: 'mortality' });
        crLoading('#cr-mortality-table tbody');
        $.get('{{ route("clinical-reports.drill-down") }}', params)
            .done(function (data) {
                var rows = data.records || data || [];

                // KPIs
                var rip = rows.filter(function (r) { return r.death_type === 'RIP'; }).length;
                var bid = rows.filter(function (r) { return r.death_type === 'BID'; }).length;
                $('#cr-mortality-kpis').html(
                    '<div class="col-md-3"><div class="card border-left-danger shadow-sm"><div class="card-body p-2"><div class="text-xs text-uppercase text-danger">Total Deaths</div><div class="h5 mb-0 font-weight-bold">' + rows.length + '</div></div></div></div>'
                    + '<div class="col-md-3"><div class="card border-left-warning shadow-sm"><div class="card-body p-2"><div class="text-xs text-uppercase text-warning">In-Hospital (RIP)</div><div class="h5 mb-0 font-weight-bold">' + rip + '</div></div></div></div>'
                    + '<div class="col-md-3"><div class="card border-left-dark shadow-sm"><div class="card-body p-2"><div class="text-xs text-uppercase">Brought in Dead (BID)</div><div class="h5 mb-0 font-weight-bold">' + bid + '</div></div></div></div>'
                );

                if (!rows.length) { crError('#cr-mortality-table tbody', 'No deaths recorded in this period'); return; }
                var html = '';
                rows.forEach(function (r) {
                    html += '<tr><td>' + (r.patient_id ? '<a href="/patient/' + r.patient_id + '">' + r.patient + '</a>' : r.patient) + '</td><td>' + (r.file_no || '') + '</td><td>' + (r.age || 'N/A') + '</td><td>' + (r.sex || 'N/A') + '</td><td>' + r.date + '</td><td>' + badge(r.death_type, { rip: 'warning', bid: 'dark' }) + '</td><td><small>' + (r.primary_cause || 'N/A') + '</small></td><td><small>' + (r.contributing_factors || 'None') + '</small></td></tr>';
                });
                $('#cr-mortality-table tbody').html(html);
            })
            .fail(function () { crError('#cr-mortality-table tbody'); });
    }

    // =========================================================================
    // SURGERIES / PROCEDURES
    // =========================================================================
    function loadCrSurgeries() {
        var params = $.extend(getCrFilters(), { type: 'surgeries' });
        crLoading('#cr-surgeries-table tbody');
        $.get('{{ route("clinical-reports.drill-down") }}', params)
            .done(function (data) {
                var rows = data.records || data || [];
                if (!rows.length) { crError('#cr-surgeries-table tbody', 'No surgeries recorded'); return; }

                // Group by category for donut
                var catCounts = {};
                var html = '';
                rows.forEach(function (r) {
                    var cat = r.category || 'Uncategorised';
                    catCounts[cat] = (catCounts[cat] || 0) + 1;
                    html += '<tr><td>' + (r.patient_id ? '<a href="/patient/' + r.patient_id + '">' + r.patient + '</a>' : r.patient) + '</td><td>' + (r.file_no || '') + '</td><td>' + r.date + '</td><td><small>' + r.procedure_name + '</small></td><td><small>' + (r.category || 'N/A') + '</small></td><td>' + (r.doctor || 'N/A') + '</td><td>' + badge(r.outcome || 'N/A') + '</td></tr>';
                });
                $('#cr-surgeries-table tbody').html(html);

                crDestroyChart('surgeriesDonut');
                var cats = Object.keys(catCounts);
                crCharts['surgeriesDonut'] = new Chart($('#cr-surgeries-donut')[0].getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: cats, datasets: [{ data: cats.map(function (c) { return catCounts[c]; }), backgroundColor: cats.map(function (_, i) { return 'hsl(' + (i * 37) + ',65%,55%)'; }) }] },
                    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } }, title: { display: true, text: 'By Category' } } }
                });
            })
            .fail(function () { crError('#cr-surgeries-table tbody'); });
    }

    // =========================================================================
    // VACCINATIONS
    // =========================================================================
    function loadCrVaccinations() {
        var params = getCrFilters();
        crLoading('#cr-vacc-records-table tbody');
        crLoading('#cr-vacc-summary-table tbody');
        $.get('{{ route("clinical-reports.vaccinations") }}', params)
            .done(function (data) {
                // Schedule stats badges
                var ss = data.scheduleStats || {};
                var badgeHtml = '';
                var ssBadgeMap = { due: 'primary', overdue: 'danger', pending: 'secondary', administered: 'success', skipped: 'warning', contraindicated: 'dark' };
                Object.keys(ss).forEach(function (k) {
                    badgeHtml += '<div class="col-auto mb-1"><span class="badge badge-' + (ssBadgeMap[k] || 'secondary') + ' p-2">' + k.charAt(0).toUpperCase() + k.slice(1) + ': <strong>' + ss[k] + '</strong></span></div>';
                });
                if (ss['overdue'] && ss['overdue'] > 0) {
                    badgeHtml += '<div class="col-12"><div class="alert alert-danger py-1 small mb-1"><i class="mdi mdi-alert"></i> <strong>' + ss['overdue'] + '</strong> overdue vaccination(s) require attention.</div></div>';
                }
                $('#cr-vacc-schedule-stats').html(badgeHtml);

                // Summary by vaccine
                var sRows = '';
                (data.byVaccine || []).forEach(function (r) {
                    sRows += '<tr><td>' + r.vaccine_name + '</td><td class="text-center">' + r.total_doses + '</td><td class="text-center">' + r.patients + '</td></tr>';
                });
                $('#cr-vacc-summary-table tbody').html(sRows || '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>');

                // Administered records
                var rRows = '';
                (data.rows || []).forEach(function (r) {
                    rRows += '<tr><td>' + (r.patient_id ? '<a href="/patient/' + r.patient_id + '">' + r.patient + '</a>' : r.patient) + '</td><td>' + r.file_no + '</td><td>' + r.vaccine + '</td><td class="text-center">' + (r.dose_no || 'N/A') + '</td><td>' + (r.route || 'N/A') + '</td><td>' + r.date + '</td><td>' + r.nurse + '</td><td>' + r.next_due + '</td></tr>';
                });
                $('#cr-vacc-records-table tbody').html(rRows || '<tr><td colspan="8" class="text-center text-muted">No records</td></tr>');
            })
            .fail(function () {
                crError('#cr-vacc-records-table tbody');
                crError('#cr-vacc-summary-table tbody');
            });
    }

    // =========================================================================
    // REFERRALS
    // =========================================================================
    function loadCrReferrals() {
        var params = getCrFilters();
        crLoading('#cr-referrals-table tbody');
        $.get('{{ route("clinical-reports.referrals") }}', params)
            .done(function (data) {
                var s = data.summary || {};

                // KPI cards
                $('#cr-referrals-kpis').html(
                    '<div class="col-md-2 col-4 mb-1"><div class="card border-left-primary shadow-sm"><div class="card-body p-2 text-center"><div class="text-xs text-muted">Total</div><div class="h5 mb-0">' + (s.total || 0) + '</div></div></div></div>'
                    + '<div class="col-md-2 col-4 mb-1"><div class="card border-left-info shadow-sm"><div class="card-body p-2 text-center"><div class="text-xs text-muted">Internal</div><div class="h5 mb-0">' + (s.internal || 0) + '</div></div></div></div>'
                    + '<div class="col-md-2 col-4 mb-1"><div class="card border-left-warning shadow-sm"><div class="card-body p-2 text-center"><div class="text-xs text-muted">External</div><div class="h5 mb-0">' + (s.external || 0) + '</div></div></div></div>'
                    + '<div class="col-md-2 col-4 mb-1"><div class="card border-left-success shadow-sm"><div class="card-body p-2 text-center"><div class="text-xs text-muted">Booked</div><div class="h5 mb-0">' + (s.booked || 0) + '</div></div></div></div>'
                    + '<div class="col-md-2 col-4 mb-1"><div class="card border-left-success shadow-sm"><div class="card-body p-2 text-center"><div class="text-xs text-muted">Completed</div><div class="h5 mb-0">' + (s.completed || 0) + '</div></div></div></div>'
                    + '<div class="col-md-2 col-4 mb-1"><div class="card border-left-secondary shadow-sm"><div class="card-body p-2 text-center"><div class="text-xs text-muted">Conversion Rate</div><div class="h5 mb-0">' + (s.conversion_rate || 0) + '%</div></div></div></div>'
                );

                // Donut chart
                crDestroyChart('referralsDonut');
                crCharts['referralsDonut'] = new Chart($('#cr-referrals-donut')[0].getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Booked', 'Completed', 'Pending', 'Declined/Cancelled'],
                        datasets: [{ data: [s.booked || 0, s.completed || 0, s.pending || 0, s.declined_cancelled || 0], backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e', '#e74a3b'] }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
                });

                // Table
                var rows = '';
                (data.rows || []).forEach(function (r) {
                    rows += '<tr><td>' + (r.patient_id ? '<a href="/patient/' + r.patient_id + '">' + r.patient + '</a>' : r.patient) + '</td><td>' + r.file_no + '</td><td>' + badge(r.type, { internal: 'info', external: 'warning' }) + '</td><td><small>' + r.from_doctor + '</small></td><td><small>' + r.to + '</small></td><td><small>' + (r.reason || 'N/A') + '</small></td><td>' + badge(r.urgency, { urgent: 'danger', routine: 'secondary' }) + '</td><td>' + badge(r.status) + '</td><td>' + (r.booked === 'Yes' ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>') + '</td><td>' + r.date + '</td></tr>';
                });
                $('#cr-referrals-table tbody').html(rows || '<tr><td colspan="10" class="text-center text-muted">No referrals</td></tr>');
            })
            .fail(function () { crError('#cr-referrals-table tbody'); });
    }

    // =========================================================================
    // WARD OCCUPANCY
    // =========================================================================
    function loadCrOccupancy(wardId) {
        var params = $.extend(getCrFilters(), wardId ? { ward_id: wardId } : {});
        crLoading('#cr-occupancy-table tbody');
        $.get('{{ route("clinical-reports.occupancy") }}', params)
            .done(function (data) {
                var wards = data.wards || [];
                var rows = '';
                wards.forEach(function (w) {
                    var pctColor = w.occupancy_pct >= 90 ? 'danger' : (w.occupancy_pct >= 70 ? 'warning' : 'success');
                    rows += '<tr style="cursor:pointer" class="cr-occ-ward-row" data-ward-id="' + w.ward_id + '" data-ward-name="' + w.ward_name + '">'
                          + '<td>' + w.ward_name + '</td>'
                          + '<td><small>' + w.type + '</small></td>'
                          + '<td class="text-center">' + w.capacity + '</td>'
                          + '<td class="text-center font-weight-bold text-' + pctColor + '">' + w.occupied + '</td>'
                          + '<td class="text-center">' + w.available + '</td>'
                          + '<td class="text-center"><span class="badge badge-' + pctColor + '">' + w.occupancy_pct + '%</span></td>'
                          + '</tr>';
                });
                $('#cr-occupancy-table tbody').html(rows || '<tr><td colspan="6" class="text-center text-muted">No wards found</td></tr>');
                $('#cr-occupancy-avg-los').html('<i class="mdi mdi-clock-outline"></i> Average Length of Stay (discharged): <strong>' + (data.avg_los_days || 0) + ' days</strong>');

                // Bar chart
                crDestroyChart('occupancyBar');
                if (wards.length) {
                    crCharts['occupancyBar'] = new Chart($('#cr-occupancy-bar')[0].getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: wards.map(function (w) { return w.ward_name; }),
                            datasets: [
                                { label: 'Occupied', data: wards.map(function (w) { return w.occupied; }), backgroundColor: '#e74a3b' },
                                { label: 'Available', data: wards.map(function (w) { return w.available; }), backgroundColor: '#1cc88a' },
                            ]
                        },
                        options: {
                            responsive: true,
                            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
                            plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Ward Occupancy' } }
                        }
                    });
                }

                // Current patients
                var pRows = '';
                (data.current_patients || []).forEach(function (r) {
                    var daysColor = r.days > 14 ? 'danger' : (r.days > 7 ? 'warning' : 'success');
                    pRows += '<tr><td>' + (r.patient_id ? '<a href="/patient/' + r.patient_id + '">' + r.patient + '</a>' : r.patient) + '</td><td>' + r.file_no + '</td><td>' + r.ward + '</td><td>' + r.bed + '</td><td>' + r.admitted_at + '</td><td class="text-center"><span class="badge badge-' + daysColor + '">' + r.days + 'd</span></td></tr>';
                });
                if (pRows) {
                    $('#cr-occupancy-patients-table tbody').html(pRows);
                    $('#cr-occupancy-patients-section').show();
                }
            })
            .fail(function () { crError('#cr-occupancy-table tbody'); });
    }

    // =========================================================================
    // EVENT BINDINGS
    // =========================================================================
    $(document).ready(function () {

        // Quick range picker
        $('#cr-quick-range').on('change', function () {
            var v = $(this).val();
            if (v) crApplyQuickRange(v);
        });

        // Apply filters button
        $('#cr-apply-filters').on('click', function () {
            var activeTab = $('#cr-sub-tabs .nav-link.active').attr('href');
            crDispatchLoad(activeTab);
        });

        // Clear filters
        $('#cr-clear-filters').on('click', function () {
            $('#cr-date-from, #cr-date-to').val('');
            $('#cr-clinic-filter, #cr-hmo-filter, #cr-ward-filter').val('');
            $('#cr-quick-range').val('month');
            crApplyQuickRange('month');
        });

        // Sub-tab shown events
        $('#cr-sub-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            crDispatchLoad($(e.target).attr('href'));
        });

        // Unit visits row click → drill-down
        $(document).on('click', '.cr-unit-visit-row', function () {
            var clinicId   = $(this).data('clinic-id');
            var clinicName = $(this).data('clinic-name');
            $('#cr-unit-visits-drill-label').text(clinicName);
            $('#cr-unit-visits-drilldown').hide();
            loadCrUnitVisits(clinicId);
        });

        // Unit visits drill-down back
        $(document).on('click', '#cr-unit-visits-drill-back', function () {
            $('#cr-unit-visits-drilldown').hide();
        });

        // Diagnosis keyword search — debounced
        $('#cr-diagnosis-keyword').on('keyup', function () {
            clearTimeout(crDiagDebounce);
            crDiagDebounce = setTimeout(loadCrDiagnosis, 400);
        });
        $('#cr-diagnosis-search-btn').on('click', loadCrDiagnosis);

        // Diagnosis expand button
        $(document).on('click', '.cr-diag-expand-btn', function () {
            var target = $(this).data('target');
            var $row   = $('#' + target);
            var $icon  = $(this).find('i');
            if ($row.hasClass('d-none')) {
                $row.removeClass('d-none');
                $icon.removeClass('mdi-chevron-right').addClass('mdi-chevron-down');
                var $table = $row.find('.cr-diag-enc-table');
                if ($table.find('tbody tr td').text().indexOf('spinner') !== -1 || $table.find('tbody tr td .spinner-border').length) {
                    loadCrDiagnosisEncounters($table);
                }
            } else {
                $row.addClass('d-none');
                $icon.removeClass('mdi-chevron-down').addClass('mdi-chevron-right');
            }
        });

        // Encounter detail button
        $(document).on('click', '.cr-enc-detail-btn', function () {
            var encId = $(this).data('enc-id');
            if (typeof showEncounterDetails === 'function') {
                showEncounterDetails(encId);
            } else {
                window.open('/encounters/' + encId, '_blank');
            }
        });

        // Maternity sub-sub-tab navigation
        $('#cr-mat-sub-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var href = $(e.target).attr('href');
            var subMap = {
                '#cr-mat-enrollments' : 'enrollments',
                '#cr-mat-anc'         : 'anc_visits',
                '#cr-mat-deliveries'  : 'deliveries',
                '#cr-mat-babies'      : 'babies',
                '#cr-mat-postnatal'   : 'postnatal',
            };
            loadCrMaternity(subMap[href] || 'enrollments');
        });

        // Occupancy ward row click → show patients section filtered
        $(document).on('click', '.cr-occ-ward-row', function () {
            var wardId   = $(this).data('ward-id');
            var wardName = $(this).data('ward-name');
            $('#cr-occupancy-ward-label').text(wardName);
            loadCrOccupancy(wardId);
        });

        // Occupancy patients section close
        $(document).on('click', '#cr-occupancy-patients-close', function () {
            $('#cr-occupancy-patients-section').hide();
        });

        // Tab activation from parent: bind Clinical Reports main tab
        $('#clinical-reports-tab').on('shown.bs.tab', function () {
            window.initClinicalReports();
        });
    });

    // =========================================================================
    // DISPATCH TABLE — routes sub-tab href to its load function
    // =========================================================================
    function crDispatchLoad(href) {
        switch (href) {
            case '#cr-overview'    : loadCrOverview();      break;
            case '#cr-unit-visits' : loadCrUnitVisits();    break;
            case '#cr-hmo-trends'  : loadCrHmoTrends();     break;
            case '#cr-diagnosis'   : /* search on demand */  break;
            case '#cr-maternity'   : loadCrMaternity('enrollments'); break;
            case '#cr-mortality'   : loadCrMortality();     break;
            case '#cr-surgeries'   : loadCrSurgeries();     break;
            case '#cr-vaccinations': loadCrVaccinations();  break;
            case '#cr-referrals'   : loadCrReferrals();     break;
            case '#cr-occupancy'   : loadCrOccupancy();     break;
        }
    }

})();
</script>
