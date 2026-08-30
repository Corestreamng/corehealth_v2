    $(document).ready(function() {
        // Merge (do not replace) — modal partial already set registerUrl, emergencyIntakeUrl, etc.
        $.extend(window.patientFormConfig, {
            submitUrl: '{{ route("morgue.admit") }}',
            onSuccess: function(patientId, mode) {
                toastr.success("Patient record created/admitted successfully");
                $("#patientFormModal").modal("hide");
                loadData();
            }
        });
        // Handle queue filter deep-linking from dashboard
        const urlParams = new URLSearchParams(window.location.search);
        const queueFilter = urlParams.get('queue_filter');
        if (queueFilter === 'pending') {
            // Pending is already the first thing on the page, but we can highlight it
            $('#tab-workbench').tab('show');
        } else if (queueFilter === 'admitted') {
            $('#tab-workbench').tab('show');
            // Scroll to active residents table
            $('html, body').animate({
                scrollTop: $("#active-table").offset().top - 100
            }, 500);
        }

        loadData();

        $('#refresh-btn').click(function() {
            loadData();
        });

        function loadData() {
            $.get('{{ route("morgue.queue") }}', function(response) {
                renderPending(response.pending);
                renderActive(response.active);
                $('#stat-pending').text(response.pending.length);
                $('#stat-active').text(response.active.length);
            });
        }

        function loadServices(patientId, targetSelect) {
            $(targetSelect).html('<option value="">Loading services...</option>');
            $.get('{{ route("morgue.services") }}', { patient_id: patientId }, function(services) {
                let html = '<option value="">-- Select Service --</option>';
                services.forEach(s => {
                    const basePrice = s.price ? parseFloat(s.price.sale_price) : 0;
                    const payable = parseFloat(s.payable_amount);
                    const claims = parseFloat(s.claims_amount);

                    let priceText = `Base: ₦${basePrice.toLocaleString()}`;
                    if (s.coverage_mode !== 'cash') {
                        priceText = `Payable: ₦${payable.toLocaleString()} | HMO: ₦${claims.toLocaleString()}`;
                    }

                    html += `<option value="${s.id}">${s.service_name} (${priceText})</option>`;
                });
                $(targetSelect).html(html);
            });
        }

        function renderPending(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-4 text-muted">No pending admissions</td></tr>';
            } else {
                data.forEach(r => {
                    html += `
                        <tr>
                            <td class="fw-bold">${r.name}</td>
                            <td>${r.file_no}</td>
                            <td><span class="badge bg-danger">${r.death_type}</span></td>
                            <td>${r.date_of_death} ${r.time_of_death}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="openAdmitModal(${r.id}, '${r.name.replace(/'/g, "\\'")}', ${r.patient_id})">
                                    <i class="mdi mdi-login"></i> Admit
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#pending-body').html(html);
        }

        function renderActive(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="6" class="text-center py-4 text-muted">Morgue is currently empty</td></tr>';
            } else {
                data.forEach(a => {
                    html += `
                        <tr>
                            <td class="fw-bold">${a.name}</td>
                            <td>${a.file_no}</td>
                            <td>F: ${a.fridge_no || '-'} / T: ${a.tray_no || '-'}</td>
                            <td>${a.admitted_at}</td>
                            <td><span class="badge bg-info">${a.days_spent} days</span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openBillPanel(${a.id}, '${a.name}')">
                                        <i class="mdi mdi-receipt"></i> Bill
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="openServiceModal(${a.id}, ${a.patient_id})">
                                        <i class="mdi mdi-plus-circle"></i> Service
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="openReleaseModal(${a.id})">
                                        <i class="mdi mdi-logout"></i> Release
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#active-body').html(html);
        }

        window.openAdmitModal = function(id, name, patientId) {
            $('#admit-death-record-id').val(id);
            $('#admit-patient-name').text(name);
            loadServices(patientId, '#admit-daily-service');
            $('#admitModal').modal('show');
        };

        window.openServiceModal = function(id, patientId) {
            $('#service-admission-id').val(id);
            loadServices(patientId, '#morgue-service-id');
            $('#serviceModal').modal('show');
        };

        window.openReleaseModal = function(id) {
            $('#release-admission-id').val(id);
            $('#releaseModal').modal('show');
        };

        $('#btn-save-admission').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                death_record_id: $('#admit-death-record-id').val(),
                fridge_no: $('#admit-fridge').val(),
                tray_no: $('#admit-tray').val(),
                daily_service_id: $('#admit-daily-service').val(),
                notes: $('#admit-notes').val()
            };

            if (!data.daily_service_id) {
                toastr.warning('Please select a daily rate.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

            $.post('{{ route("morgue.admit") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#admitModal').modal('hide');
                    loadData();
                } else {
                    toastr.error(res.message);
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error processing admission.');
            }).always(() => {
                $(this).prop('disabled', false).text('Admit Body');
            });
        });

        $('#btn-save-service').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                morgue_admission_id: $('#service-admission-id').val(),
                service_id: $('#morgue-service-id').val(),
                qty: $('#morgue-service-qty').val()
            };

            if (!data.service_id) {
                toastr.warning('Please select a service.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

            $.post('{{ route("morgue.add-service") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#serviceModal').modal('hide');
                } else {
                    toastr.error(res.message);
                }
            }).always(() => {
                $(this).prop('disabled', false).text('Add to Bill');
            });
        });

        $('#btn-confirm-release').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                morgue_admission_id: $('#release-admission-id').val(),
                released_to_name: $('#release-name').val(),
                released_to_phone: $('#release-phone').val(),
                release_notes: $('#release-notes').val()
            };

            if (!data.released_to_name) {
                toastr.warning('Please enter releasee name.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Releasing...');

            $.post('{{ route("morgue.release") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#releaseModal').modal('hide');
                    loadData();
                } else {
                    toastr.error(res.message);
                }
            }).always(() => {
                $(this).prop('disabled', false).text('Confirm Release');
            });
        });
    });

{{-- ═══ REPORTS & BILL SCRIPTS ═══ --}}
(function() {
    'use strict';

    /* ── Hospital branding (from appsettings) ── */
    const HOS = {!! json_encode([
        'name'    => $sett->site_name ?? config('app.name'),
        'color'   => $hosColor,
        'logo'    => $sett->logo ? 'data:image/jpeg;base64,'.$sett->logo : '',
        'address' => $sett->contact_address ?? '',
        'phone'   => $sett->contact_phones ?? '',
        'email'   => $sett->contact_emails ?? '',
        'tagline' => $sett->hos_tagline ?? '',
    ]) !!};

    let trendChart = null;
    let typesChart = null;
    let currentBillData = null;   // last loaded bill, for printing

    /* ── Utility ── */
    function fmt(n) { return '₦' + parseFloat(n||0).toLocaleString('en-NG', {minimumFractionDigits:2}); }
    function fmtDate(s) { return s ? s.substr(0,10) : '—'; }

    /* ── Shared branded header HTML (used in all print windows) ── */
    function brandedHeader(docTitle) {
        return `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;
                    padding:16px 20px;border-bottom:3px solid ${HOS.color};background:#f8f9fa;">
            <div style="display:flex;align-items:center;gap:14px;">
                ${HOS.logo ? `<img src="${HOS.logo}" style="width:70px;height:70px;object-fit:contain;border-radius:6px;">` : ''}
                <div>
                    <div style="font-size:1.4rem;font-weight:700;color:${HOS.color};">${HOS.name}</div>
                    ${HOS.tagline ? `<div style="font-size:0.8rem;color:#666;">${HOS.tagline}</div>` : ''}
                </div>
            </div>
            <div style="text-align:right;font-size:0.82rem;color:#495057;line-height:1.7;">
                ${HOS.address ? HOS.address + '<br>' : ''}
                ${HOS.phone ? 'Tel: ' + HOS.phone + '<br>' : ''}
                ${HOS.email ? HOS.email : ''}
            </div>
        </div>
        <div style="background:${HOS.color};color:#fff;text-align:center;padding:8px 20px;
                    font-size:0.95rem;font-weight:600;letter-spacing:1px;">
            ${docTitle}
        </div>`;
    }

    /* ── Reports load ── */
    function loadReports() {
        const from = $('#rpt-date-from').val();
        const to   = $('#rpt-date-to').val();
        $.get('{{ route("morgue.reports") }}', { date_from: from, date_to: to })
            .done(function(res) {
                /* KPI cards */
                $('#rpt-total-admissions').text(res.stats.total_admissions);
                $('#rpt-total-released').text(res.stats.total_released);
                $('#rpt-currently-stored').text(res.stats.currently_stored);
                $('#rpt-avg-stay').text(parseFloat(res.stats.avg_stay_days||0).toFixed(1));
                $('#rpt-revenue').html('₦' + parseFloat(res.stats.total_revenue||0).toLocaleString('en-NG',{minimumFractionDigits:2}));
                $('#rpt-pending-rev').html('₦' + parseFloat(res.stats.pending_revenue||0).toLocaleString('en-NG',{minimumFractionDigits:2}));

                /* Trend chart */
                if (trendChart) trendChart.destroy();
                const trendCtx = document.getElementById('rpt-chart-trend').getContext('2d');
                trendChart = new Chart(trendCtx, {
                    type: 'bar',
                    data: {
                        labels: res.trend.labels,
                        datasets: [{
                            label: 'Admissions',
                            data: res.trend.data,
                            backgroundColor: 'rgba(63,81,181,0.7)',
                            borderColor: '#3f51b5',
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });

                /* Death types chart */
                if (typesChart) typesChart.destroy();
                const typeLabels = Object.keys(res.death_types);
                const typeData   = Object.values(res.death_types);
                const typesCtx = document.getElementById('rpt-chart-types').getContext('2d');
                typesChart = new Chart(typesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            data: typeData,
                            backgroundColor: ['#f44336','#2196f3','#ff9800','#4caf50','#9c27b0','#00bcd4']
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });

                /* Tables */
                renderReportTables(res.admissions);
            })
            .fail(function() { toastr.error('Failed to load reports.'); });
    }

    function renderReportTables(admissions) {
        const allRows  = admissions;
        const actRows  = admissions.filter(a => a.status === 'stored');
        const relRows  = admissions.filter(a => a.status === 'released');

        $('#rpt-all-body').html(allRows.map(rowAll).join(''));
        $('#rpt-active-body').html(actRows.map(rowActive).join(''));
        $('#rpt-released-body').html(relRows.map(rowReleased).join(''));
    }

    function statusBadge(s) {
        const map = { admitted: 'bg-info', released: 'bg-success' };
        return `<span class="badge ${map[s]||'bg-secondary'}">${s}</span>`;
    }

    function rowAll(a) {
        return `<tr>
            <td>${a.body_code||'—'}</td>
            <td>${a.name}</td>
            <td>${a.file_no||'—'}</td>
            <td>${a.death_type||'—'}</td>
            <td>${fmtDate(a.admitted_at)}</td>
            <td>${fmtDate(a.released_at)}</td>
            <td>${a.days||0}</td>
            <td>${statusBadge(a.status)}</td>
        </tr>`;
    }
    function rowActive(a) {
        return `<tr>
            <td>${a.body_code||'—'}</td>
            <td>${a.name}</td>
            <td>${a.file_no||'—'}</td>
            <td>${a.death_type||'—'}</td>
            <td>${fmtDate(a.admitted_at)}</td>
            <td>${a.days||0}</td>
        </tr>`;
    }
    function rowReleased(a) {
        return `<tr>
            <td>${a.body_code||'—'}</td>
            <td>${a.name}</td>
            <td>${a.file_no||'—'}</td>
            <td>${a.death_type||'—'}</td>
            <td>${fmtDate(a.admitted_at)}</td>
            <td>${fmtDate(a.released_at)}</td>
            <td>${a.days||0}</td>
        </tr>`;
    }

    /* ── Patient Bill offcanvas ── */
    window.openBillPanel = function(admissionId, patientName) {
        currentBillData = null;
        $('#btn-print-bill').hide();
        $('#bill-patient-info').html(`<strong>${patientName}</strong>`);
        $('#bill-totals').hide();
        $('#bill-items-list').hide();
        $('#bill-empty').hide();
        $('#bill-loading').show();

        $('#billModal').modal('show');

        $.get(`{{ url('morgue/patient') }}/${admissionId}/bill`)
            .done(function(res) {
                $('#bill-loading').hide();
                $('#bill-patient-info').html(`
                    <strong>${res.patient_name}</strong>
                    <span class="text-muted small ml-2">File: ${res.file_no||'—'}</span>
                    ${res.body_code ? `<span class="badge bg-secondary ml-1">${res.body_code}</span>` : ''}
                `);

                currentBillData = res;   // store for print

                if (res.items && res.items.length) {
                    $('#bill-total-amount').text(fmt(res.total_amount));
                    $('#bill-paid-amount').text(fmt(res.paid_amount));
                    $('#bill-pending-amount').text(fmt(res.pending_amount));
                    $('#bill-totals').show();
                    $('#btn-print-bill').show();

                    const rows = res.items.map(i => `
                        <tr>
                            <td>${i.service_name}</td>
                            <td>${fmtDate(i.date)}</td>
                            <td class="text-center">${i.qty}</td>
                            <td class="text-right">${fmt(i.unit_price)}</td>
                            <td class="text-right">${fmt(i.total)}</td>
                            <td class="text-center">
                                ${i.paid
                                    ? `<span class="badge-paid">Paid</span>`
                                    : `<span class="badge-pending">Pending</span>`}
                            </td>
                        </tr>
                    `).join('');
                    $('#bill-items-tbody').html(rows);
                    $('#bill-items-list').show();
                } else {
                    $('#bill-empty').show();
                }
            })
            .fail(function() {
                $('#bill-loading').hide();
                toastr.error('Failed to load patient bill.');
            });
    };

    /* ── Print patient bill ── */
    window.printBillModal = function() {
        const d = currentBillData;
        if (!d) return;
        const rows = (d.items||[]).map(i => `
            <tr>
                <td>${i.service_name}</td>
                <td>${fmtDate(i.date)}</td>
                <td style="text-align:center">${i.qty}</td>
                <td style="text-align:right">${fmt(i.unit_price)}</td>
                <td style="text-align:right">${fmt(i.total)}</td>
                <td style="text-align:center">
                    <span style="background:${i.paid?'#43a047':'#e53935'};color:#fff;padding:2px 8px;
                                border-radius:4px;font-size:0.75rem;">
                        ${i.paid?'Paid':'Pending'}
                    </span>
                </td>
            </tr>`).join('');

        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head>
            <meta charset="UTF-8">
            <title>Morgue Bill – ${d.patient_name}</title>
            <style>
                *{box-sizing:border-box;margin:0;padding:0}
                body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#212529;background:#fff;print-color-adjust:exact;-webkit-print-color-adjust:exact}
                .container{max-width:800px;margin:0 auto;padding:20px}
                table{width:100%;border-collapse:collapse;margin-top:16px}
                th,td{padding:8px 10px;border:1px solid #dee2e6;font-size:0.82rem}
                thead th{background:${HOS.color};color:#fff;font-weight:600}
                .info-row{display:flex;gap:16px;padding:14px 20px;background:#f8f9fa;border-bottom:1px solid #dee2e6}
                .info-cell small{color:#6c757d;display:block;font-size:0.72rem}
                .totals-row{display:flex;gap:0;border:1px solid #dee2e6;border-radius:6px;overflow:hidden;margin:16px 0}
                .totals-cell{flex:1;text-align:center;padding:12px;border-right:1px solid #dee2e6}
                .totals-cell:last-child{border-right:none}
                .totals-cell .lbl{font-size:0.72rem;color:#6c757d;display:block}
                .totals-cell .val{font-size:1.1rem;font-weight:700}
                .footer{margin-top:24px;font-size:0.75rem;color:#999;text-align:center;border-top:1px solid #dee2e6;padding-top:12px}
                @media print{@page{size:A4;margin:10mm} .no-print{display:none}}
            </style>
        </head><body>
        <div class="container">
            ${brandedHeader('MORGUE BILL / STATEMENT')}
            <div class="info-row">
                <div class="info-cell"><small>Patient Name</small><strong>${d.patient_name}</strong></div>
                <div class="info-cell"><small>File No.</small><strong>${d.file_no||'—'}</strong></div>
                ${ d.body_code ? `<div class="info-cell"><small>Body Code</small><strong>${d.body_code}</strong></div>` : '' }
                <div class="info-cell" style="margin-left:auto"><small>Print Date</small><strong>${new Date().toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})}</strong></div>
            </div>
            <div class="totals-row">
                <div class="totals-cell"><span class="lbl">Total Billed</span><span class="val">${fmt(d.total_amount)}</span></div>
                <div class="totals-cell"><span class="lbl">Paid</span><span class="val" style="color:#43a047">${fmt(d.paid_amount)}</span></div>
                <div class="totals-cell"><span class="lbl">Outstanding</span><span class="val" style="color:#e53935">${fmt(d.pending_amount)}</span></div>
            </div>
            <table>
                <thead><tr><th>Service</th><th>Date</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>${rows || '<tr><td colspan="6" style="text-align:center;color:#999">No items</td></tr>'}</tbody>
            </table>
            <div class="footer">This is a computer-generated document &mdash; ${HOS.name}</div>
        </div>
        <script>window.onload=function(){window.print();}<\/script>
        </body></html>`);
        win.document.close();
    };

    /* ── Print report table ── */
    window.printReportTable = function(tableId, subtitle) {
        const tbl = document.getElementById(tableId);
        if (!tbl) return;
        const title = (subtitle || 'MORGUE ADMISSIONS REPORT').toUpperCase();
        const dateRange = $('#rpt-date-from').val() && $('#rpt-date-to').val()
            ? `Period: ${$('#rpt-date-from').val()} to ${$('#rpt-date-to').val()}`
            : '';
        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head>
            <meta charset="UTF-8">
            <title>${title}</title>
            <style>
                *{box-sizing:border-box;margin:0;padding:0}
                body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#212529;background:#fff;print-color-adjust:exact;-webkit-print-color-adjust:exact}
                .container{max-width:900px;margin:0 auto;padding:16px}
                table{width:100%;border-collapse:collapse;margin-top:14px}
                th,td{padding:7px 9px;border:1px solid #dee2e6;font-size:0.8rem}
                thead th{background:${HOS.color};color:#fff;font-weight:600}
                tbody tr:nth-child(even){background:#f8f9fa}
                .meta{padding:8px 20px;font-size:0.8rem;color:#666;background:#f8f9fa;border-bottom:1px solid #dee2e6}
                .footer{margin-top:20px;font-size:0.72rem;color:#999;text-align:center;border-top:1px solid #dee2e6;padding-top:10px}
                @media print{@page{size:A4 landscape;margin:8mm}}
            </style>
        </head><body>
        <div class="container">
            ${brandedHeader(title)}
            ${ dateRange ? `<div class="meta">${dateRange} &nbsp;&bull;&nbsp; Printed: ${new Date().toLocaleDateString('en-GB')}</div>` : '' }
            ${tbl.outerHTML}
            <div class="footer">This is a computer-generated document &mdash; ${HOS.name}</div>
        </div>
        <script>window.onload=function(){window.print();}<\/script>
        </body></html>`);
        win.document.close();
    };

    /* ── Init ── */
    $(document).ready(function() {
        // Default dates: last 3 months
        const today = new Date();
        const past  = new Date(today); past.setMonth(past.getMonth() - 3);
        $('#rpt-date-to').val(today.toISOString().substr(0,10));
        $('#rpt-date-from').val(past.toISOString().substr(0,10));

        // Load reports when tab shown
        $('a[href="#pane-reports"]').on('shown.bs.tab', function() {
            loadReports();
        });

        // Filter form submit
        $('#reports-filter-form').on('submit', function(e) {
            e.preventDefault();
            loadReports();
        });

        // Reset filters
        $('#btn-clear-report-filters').on('click', function() {
            const t = new Date();
            const p = new Date(t); p.setMonth(p.getMonth() - 3);
            $('#rpt-date-to').val(t.toISOString().substr(0,10));
            $('#rpt-date-from').val(p.toISOString().substr(0,10));
            loadReports();
        });
