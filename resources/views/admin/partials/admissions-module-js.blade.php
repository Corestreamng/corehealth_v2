{{-- Admission Module JS Partial --}}
{{-- Usage: @include('admin.partials.admissions-module-js') --}}
{{-- Requires: toastr, jQuery, Bootstrap --}}

<script>
window.AdmissionModule = (function() {
    'use strict';

    let _patientId = null;
    let _selectedAdmissionId = null;
    let _container = null;
    let _onBadgeUpdate = null;
    let _printTarget = null; // 'self' = use own modal, 'billing' = use receiptPreviewModal

    const API = {
        admissions: (pid) => `/admission-module/patient/${pid}/admissions`,
        detail: (aid) => `/admission-module/admission/${aid}/detail`,
        history: (pid) => `/admission-module/patient/${pid}/history`,
        printBill: (aid) => `/admission-module/admission/${aid}/print-bill`,
    };

    const iconMap = {
        'mdi-bed': '🛏️',
        'mdi-account-nurse': '👩‍⚕️',
        'mdi-stethoscope': '🩺',
        'mdi-flask': '🔬',
        'mdi-radiology-box': '📷',
        'mdi-pill': '💊',
        'mdi-medical-bag': '🏥',
        'mdi-bandage': '🩹',
        'mdi-file-document': '📋',
    };

    function fmt(v) {
        return '₦' + parseFloat(v || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    /**
     * Initialize the module.
     * @param {number|string} patientId
     * @param {object} opts - { container: DOMElement|jQuery, onBadgeUpdate: fn(count), printTarget: 'self'|'billing' }
     */
    function init(patientId, opts) {
        opts = opts || {};
        _patientId = patientId;
        if (opts.container) {
            const $c = $(opts.container);
            _container = $c.hasClass('adm-mod') ? $c : ($c.find('.adm-mod').length ? $c.find('.adm-mod') : $c);
        } else {
            _container = $('.adm-mod').first();
        }
        _onBadgeUpdate = opts.onBadgeUpdate || null;
        _printTarget = opts.printTarget || 'self';
        _selectedAdmissionId = null;

        // Reset UI
        _container.find('.adm-mod-detail-content').hide();
        _container.find('.adm-mod-placeholder').html(
            '<i class="mdi mdi-gesture-tap"></i><p>Select an admission to view details</p>'
        ).show();

        loadList();
    }

    function loadList() {
        if (!_patientId) return;

        $.ajax({
            url: API.admissions(_patientId),
            method: 'GET',
            success: function(response) {
                renderList(response.admissions);
                if (_onBadgeUpdate) _onBadgeUpdate(response.count);
            },
            error: function(xhr) {
                console.error('Failed to load admission history', xhr);
                if (typeof toastr !== 'undefined') toastr.error('Failed to load admission history');
            }
        });
    }

    function renderList(admissions) {
        const tbody = _container.find('.adm-mod-tbody');
        tbody.empty();

        if (!admissions || admissions.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="mdi mdi-hospital-building" style="font-size: 3rem;"></i>
                        <p>No admission history found</p>
                    </td>
                </tr>
            `);
            return;
        }

        admissions.forEach(function(adm) {
            const statusClass = adm.status === 'admitted' ? 'admitted' : 'discharged';
            const statusText = adm.status === 'admitted' ? 'Admitted' : 'Discharged';
            const row = `
                <tr data-adm-mod-id="${adm.id}" class="${_selectedAdmissionId == adm.id ? 'selected' : ''}">
                    <td>${adm.admitted_date}</td>
                    <td>${adm.discharge_date || '<span class="text-success">In Progress</span>'}</td>
                    <td>${adm.los} day${adm.los> 1 ? 's' : ''}</td>
                    <td>${adm.ward}<br><small class="text-muted">${adm.bed}</small></td>
                    <td>${adm.doctor}</td>
                    <td><span class="admission-status-pill ${statusClass}">${statusText}</span></td>
                    <td class="font-weight-bold">${fmt(adm.total_bill)}</td>
                </tr>
            `;
            tbody.append(row);
        });

        if (_selectedAdmissionId) {
            loadDetail(_selectedAdmissionId);
        }
    }

    function loadDetail(admissionId) {
        _selectedAdmissionId = admissionId;

        _container.find('.adm-mod-detail-content').hide();
        _container.find('.adm-mod-placeholder').html(
            '<i class="mdi mdi-loading mdi-spin" style="font-size: 3rem;"></i><p>Loading admission details...</p>'
        ).show();

        $.ajax({
            url: API.detail(admissionId),
            method: 'GET',
            success: function(response) {
                renderDetail(response);
                _container.find('.adm-mod-placeholder').hide();
                _container.find('.adm-mod-detail-content').show();
            },
            error: function(xhr) {
                _container.find('.adm-mod-placeholder').html(
                    '<i class="mdi mdi-alert-circle-outline" style="font-size: 3rem; color: #dc3545;"></i><p>Failed to load admission details</p>'
                );
                if (typeof toastr !== 'undefined') toastr.error(xhr.responseJSON?.error || 'Failed to load admission details');
            }
        });
    }

    function renderDetail(data) {
        if (!data) return;
        try {
            const adm = data.admission || {};
            const categories = data.categories || [];
            const totals = data.totals || {};
            const timeline = data.timeline || [];
            const hmoClaims = data.hmo_claims;

            // Status badge
            const badge = _container.find('.adm-mod-status-badge');
            badge.removeClass('admitted discharged').addClass(adm.status || 'admitted');
            badge.html((adm.status === 'admitted')
                ? '<i class="mdi mdi-hospital"></i> Currently Admitted'
                : '<i class="mdi mdi-check-circle"></i> Discharged');

            // Info cards
            _container.find('.adm-mod-admitted-date').text(adm.admitted_date || 'N/A');
            _container.find('.adm-mod-discharge-date').text(adm.discharge_date || 'N/A');
            _container.find('.adm-mod-los').text(adm.los || 'N/A');
            _container.find('.adm-mod-ward-bed').text((adm.ward || 'N/A') + ' / ' + (adm.bed || 'N/A'));
            _container.find('.adm-mod-doctor').text(adm.doctor || 'N/A');
            _container.find('.adm-mod-reason').text(adm.reason || 'N/A');

            // HMO claims section
            if (hmoClaims && hmoClaims.total_items > 0) {
                _container.find('.adm-mod-hmo-section').show();
                _container.find('.adm-mod-hmo-name').text(hmoClaims.hmo_name || 'N/A');
                _container.find('.adm-mod-hmo-no').text(hmoClaims.hmo_no || 'N/A');
                _container.find('.adm-mod-hmo-items').text(hmoClaims.total_items || 0);
                _container.find('.adm-mod-hmo-approved').text(fmt(hmoClaims.approved));
                _container.find('.adm-mod-hmo-pending').text(fmt(hmoClaims.pending));
                _container.find('.adm-mod-hmo-rejected').text(fmt(hmoClaims.rejected));
                _container.find('.adm-mod-hmo-awaiting').text(fmt(hmoClaims.awaiting_code));
                _container.find('.adm-mod-hmo-express').text(fmt(hmoClaims.express));
            } else {
                _container.find('.adm-mod-hmo-section').hide();
            }

            // Categories
            renderCategories(categories);

            // Totals
            _container.find('.adm-mod-gross-total').text(fmt(totals.gross));
            _container.find('.adm-mod-total-discount').text('-' + fmt(totals.discount));
            _container.find('.adm-mod-hmo-coverage').text('-' + fmt(totals.hmo));
            _container.find('.adm-mod-paid-amount').text('-' + fmt(totals.paid));
            _container.find('.adm-mod-balance-due').text(fmt(totals.balance));

            // Timeline
            renderTimeline(timeline);

            // Store full data for full detail modal
            _container.data('admDetailData', data);
        } catch (e) {
            console.error('Error rendering admission detail:', e);
            if (typeof toastr !== 'undefined') toastr.error('Failed to display admission details: ' + e.message);
        }
    }

    function renderCategories(categories) {
        const container = _container.find('.adm-mod-categories');
        container.empty();

        if (!categories || categories.length === 0) {
            container.html('<p class="text-muted text-center py-3">No charges during this admission</p>');
            return;
        }

        categories.forEach(function(cat, index) {
            const icon = iconMap[cat.icon] || '📋';
            const catItems = Array.isArray(cat.items) ? cat.items : (cat.items && typeof cat.items === 'object' ? Object.values(cat.items) : []);
            const itemCount = cat.count || catItems.length;
            const html = `
                <div class="bill-category-item" data-adm-cat-index="${index}">
                    <div class="category-info">
                        <span class="category-icon">${icon}</span>
                        <div>
                            <span class="category-name">${cat.name || 'Category'}</span>
                            <span class="category-count">${itemCount} item${itemCount !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <span class="category-amount">${fmt(cat.total)}</span>
                </div>
                <div class="category-items" data-adm-cat-items="${index}">
                    ${catItems.map(function(item) {
                        return '<div class="category-item-row">' +
                            '<span>' + (item.name || 'Item') + ((item.qty && item.qty > 1) ? ' (x' + item.qty + ')' : '') + '</span>' +
                            '<span>' + fmt(item.amount) + '</span>' +
                        '</div>';
                    }).join('')}
                </div>
            `;
            container.append(html);
        });
    }

    function renderTimeline(timeline) {
        const container = _container.find('.adm-mod-timeline');
        container.empty();

        if (!timeline || timeline.length === 0) {
            container.html('<p class="text-muted text-center py-3">No timeline data</p>');
            return;
        }

        timeline.forEach(function(day) {
            let items = [];
            if (Array.isArray(day.items)) {
                items = day.items;
            } else if (day.items && typeof day.items === 'object') {
                items = Object.values(day.items);
            } else if (day.categories) {
                const cats = Array.isArray(day.categories) ? day.categories : Object.values(day.categories);
                cats.forEach(function(cat) {
                    if (cat && cat.items) {
                        const catSub = Array.isArray(cat.items) ? cat.items : Object.values(cat.items);
                        items = items.concat(catSub);
                    }
                });
            }

            const itemsHtml = items.length > 0
                ? items.map(function(item) {
                    return '<div><span>' + (item.name || 'Item') + '</span><span>' + fmt(item.amount) + '</span></div>';
                }).join('')
                : '<div class="text-muted"><span>No items on this day</span></div>';

            const html = `
                <div class="timeline-day">
                    <div class="timeline-day-header">
                        Day ${day.day_number || 1} - ${day.date || ''} (${fmt(day.total)})
                    </div>
                    <div class="timeline-day-items">
                        ${itemsHtml}
                    </div>
                </div>
            `;
            container.append(html);
        });
    }

    function openFullDetailModal() {
        const data = _container.data('admDetailData');
        if (!data) {
            if (typeof toastr !== 'undefined') toastr.warning('Please select an admission first');
            return;
        }

        const modal = $('.adm-mod-full-detail-modal');
        const adm = data.admission;
        const items = data.bill_items || [];
        const totals = data.totals;
        const hmoClaims = data.hmo_claims;

        // Header info
        modal.find('.adm-mod-fd-patient').text(adm.patient_name || '-');
        modal.find('.adm-mod-fd-fileno').text(adm.patient_file_no || '-');
        modal.find('.adm-mod-fd-admitted').text(adm.admitted_date);
        modal.find('.adm-mod-fd-discharged').text(adm.discharge_date);
        modal.find('.adm-mod-fd-los').text(adm.los);
        modal.find('.adm-mod-fd-wardbed').text(adm.ward + ' / ' + adm.bed);
        modal.find('.adm-mod-fd-doctor').text(adm.doctor);
        modal.find('.adm-mod-fd-priority').text((adm.priority || 'routine').charAt(0).toUpperCase() + (adm.priority || 'routine').slice(1));
        modal.find('.adm-mod-fd-reason').text(adm.reason);

        // Items table
        const tbody = modal.find('.adm-mod-fd-tbody');
        tbody.empty();
        items.forEach(function(item, i) {
            const vsClass = item.validation_status || '';
            const vsBadge = item.claims> 0 && item.validation_status
                ? '<span class="validation-badge ' + item.validation_status + '">' + (item.validation_status || '').replace('_', ' ') + '</span>'
                : '-';
            const paidIcon = item.paid ? '<i class="mdi mdi-check-circle text-success"></i>' : '<i class="mdi mdi-close-circle text-danger"></i>';

            tbody.append(`
                <tr>
                    <td>${i + 1}</td>
                    <td>${item.date}</td>
                    <td>${item.name}</td>
                    <td>${item.qty}</td>
                    <td>${fmt(item.price)}</td>
                    <td>${fmt(item.amount)}</td>
                    <td>${fmt(item.discount)}</td>
                    <td>${fmt(item.claims)}</td>
                    <td class="font-weight-bold">${fmt(item.payable)}</td>
                    <td>${vsBadge}</td>
                    <td>${item.auth_code || '-'}</td>
                    <td>${item.validator ? item.validator + (item.validated_at ? '<br><small>' + item.validated_at + '</small>' : '') : '-'}</td>
                    <td>${paidIcon}</td>
                </tr>
            `);
        });

        // HMO section in modal
        if (hmoClaims && hmoClaims.total_items> 0) {
            modal.find('.adm-mod-fd-hmo-section').show();
            modal.find('.adm-mod-fd-hmo-content').html(`
                <div class="mb-1"><strong>${hmoClaims.hmo_name || 'HMO'}</strong> (${hmoClaims.hmo_no || 'N/A'})</div>
                <div>Approved: <span class="text-success font-weight-bold">${fmt(hmoClaims.approved)}</span></div>
                <div>Pending: <span class="text-warning font-weight-bold">${fmt(hmoClaims.pending)}</span></div>
                <div>Rejected: <span class="text-danger font-weight-bold">${fmt(hmoClaims.rejected)}</span></div>
                <div>Awaiting Code: <span style="color:#6f42c1" class="font-weight-bold">${fmt(hmoClaims.awaiting_code)}</span></div>
                <div>Express: <span class="text-info font-weight-bold">${fmt(hmoClaims.express)}</span></div>
            `);
        } else {
            modal.find('.adm-mod-fd-hmo-section').hide();
        }

        // Totals
        modal.find('.adm-mod-fd-gross').text(fmt(totals.gross));
        modal.find('.adm-mod-fd-discount').text('-' + fmt(totals.discount));
        modal.find('.adm-mod-fd-hmo').text('-' + fmt(totals.hmo));
        modal.find('.adm-mod-fd-paid').text('-' + fmt(totals.paid));
        modal.find('.adm-mod-fd-balance').text(fmt(totals.balance));

        modal.modal('show');
    }

    function printBill(admissionId) {
        const aid = admissionId || _selectedAdmissionId;
        if (!aid) {
            if (typeof toastr !== 'undefined') toastr.warning('No admission selected');
            return;
        }

        if (typeof toastr !== 'undefined') toastr.info('Generating admission bill...');

        $.ajax({
            url: API.printBill(aid),
            method: 'GET',
            success: function(response) {
                if (_printTarget === 'billing' && $('#receiptPreviewModal').length) {
                    // In billing workbench, reuse existing receipt modal
                    $('#modal-receipt-a4').html(response.bill_a4);
                    $('#modal-receipt-thermal').html(response.bill_thermal);
                    $('.receipt-modal-tab').removeClass('active');
                    $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                    $('#modal-receipt-a4').show();
                    $('#modal-receipt-thermal').hide();
                    $('#receiptPreviewModal .modal-title').text('Admission Bill - ' + response.bill_no);
                    $('#receiptPreviewModal').modal('show');
                } else {
                    // Use own print modal
                    const modal = $('.adm-mod-print-modal');
                    modal.find('.modal-title').html('<i class="mdi mdi-printer"></i> Admission Bill - ' + response.bill_no);
                    modal.find('.adm-mod-print-a4').html(response.bill_a4);
                    modal.find('.adm-mod-print-thermal').html(response.bill_thermal);
                    modal.find('.adm-mod-print-tab').removeClass('active');
                    modal.find('.adm-mod-print-tab[data-format="a4"]').addClass('active');
                    modal.find('.adm-mod-print-a4').show();
                    modal.find('.adm-mod-print-thermal').hide();
                    modal.modal('show');
                }
            },
            error: function(xhr) {
                if (typeof toastr !== 'undefined') toastr.error(xhr.responseJSON?.error || 'Failed to generate admission bill');
            }
        });
    }

    // ============ Event Delegation ============
    $(document).on('click', '.adm-mod .adm-mod-tbody tr[data-adm-mod-id]', function() {
        const admissionId = $(this).data('adm-mod-id');
        _container = $(this).closest('.adm-mod');
        const container = _container;
        container.find('.adm-mod-tbody tr').removeClass('selected');
        $(this).addClass('selected');
        _selectedAdmissionId = admissionId;
        loadDetail(admissionId);

        // Only collapse list panel on mobile screens to give detail panel more space
        if (window.innerWidth < 992) {
            var listPanel = container.find('.admissions-list-panel');
            listPanel.addClass('collapsed');
            container.find('.adm-mod-expand-list-btn').show();
        }
    });

    // Expand list panel button
    $(document).on('click', '.adm-mod .adm-mod-expand-list-btn', function() {
        var container = $(this).closest('.adm-mod');
        container.find('.admissions-list-panel').removeClass('collapsed');
        $(this).hide();
    });

    $(document).on('click', '.adm-mod .bill-category-item[data-adm-cat-index]', function() {
        const index = $(this).data('adm-cat-index');
        $(this).toggleClass('expanded');
        $(this).closest('.adm-mod').find('.category-items[data-adm-cat-items="' + index + '"]').toggleClass('show');
    });

    $(document).on('click', '.adm-mod .adm-mod-toggle-timeline', function() {
        const container = $(this).closest('.adm-mod');
        const timeline = container.find('.adm-mod-timeline');
        const btn = $(this);

        if (timeline.is(':visible')) {
            timeline.slideUp();
            btn.html('<i class="mdi mdi-timeline"></i> Show Day-by-Day Breakdown');
        } else {
            timeline.slideDown();
            btn.html('<i class="mdi mdi-timeline"></i> Hide Day-by-Day Breakdown');
        }
    });

    $(document).on('click', '.adm-mod .adm-mod-refresh-btn', function() {
        loadList();
        if (typeof toastr !== 'undefined') toastr.info('Refreshing admission history...');
    });

    $(document).on('click', '.adm-mod .adm-mod-print-bill-btn', function() {
        printBill();
    });

    $(document).on('click', '.adm-mod .adm-mod-full-detail-btn', function() {
        openFullDetailModal();
    });

    $(document).on('click', '.adm-mod .adm-mod-history-btn', function() {
        openHistoryModal();
    });

    // Full detail modal print button
    $(document).on('click', '.adm-mod-full-detail-modal .adm-mod-fd-print-btn', function() {
        printBill();
    });

    // Print modal tab switching
    $(document).on('click', '.adm-mod-print-modal .adm-mod-print-tab', function() {
        const format = $(this).data('format');
        const modal = $(this).closest('.adm-mod-print-modal');
        modal.find('.adm-mod-print-tab').removeClass('active');
        $(this).addClass('active');
        if (format === 'a4') {
            modal.find('.adm-mod-print-a4').show();
            modal.find('.adm-mod-print-thermal').hide();
        } else {
            modal.find('.adm-mod-print-a4').hide();
            modal.find('.adm-mod-print-thermal').show();
        }
    });

    // Print modal actual print
    $(document).on('click', '.adm-mod-print-modal .adm-mod-do-print-btn', function() {
        const modal = $(this).closest('.modal');
        const content = modal.find('.adm-mod-print-a4:visible, .adm-mod-print-thermal:visible').html();
        if (!content || !content.trim()) return;

        const isThermal = modal.find('.adm-mod-print-thermal:visible').length > 0;
        const printWin = window.open('', '_blank', isThermal ? 'height=700,width=480' : 'height=800,width=900');
        if (!printWin) {
            if (typeof toastr !== 'undefined') toastr.warning('Please allow popups to print');
            return;
        }

        printWin.document.open();
        if (content.indexOf('<!DOCTYPE') !== -1 || content.indexOf('<html') !== -1) {
            printWin.document.write(content);
        } else {
            printWin.document.write('<!DOCTYPE html><html><head><title>Admission Bill</title>');
            printWin.document.write('<style>');
            printWin.document.write('* { box-sizing: border-box; }');
            if (isThermal) {
                printWin.document.write('@page { margin: 0; size: auto; }');
                printWin.document.write('html, body { font-family: "Consolas", "Liberation Mono", monospace, Arial, sans-serif; padding: 2mm 3mm; margin: 0; width: 100%; }');
                printWin.document.write('@media print { body { padding: 2mm 3mm !important; margin: 0 !important; width: 100% !important; } }');
            } else {
                printWin.document.write('@page { size: A4; margin: 10mm; }');
                printWin.document.write('html, body { font-family: Arial, sans-serif; padding: 10px; margin: 0; width: 100%; }');
            }
            printWin.document.write('table { width: 100%; border-collapse: collapse; }');
            printWin.document.write('th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }');
            printWin.document.write('</style>');
            printWin.document.write('</head><body>');
            printWin.document.write(content);
            printWin.document.write('</body></html>');
        }
        printWin.document.close();
        printWin.focus();
        setTimeout(function() {
            printWin.print();
            setTimeout(function() { printWin.close(); }, 500);
        }, 250);
    });

    function openHistoryModal() {
        // simple history - could be expanded
        if (!_patientId) return;

        if (typeof toastr !== 'undefined') toastr.info('Loading full admission history...');

        $.ajax({
            url: API.history(_patientId),
            method: 'GET',
            success: function(response) {
                const history = response.history || [];
                let html = '<table class="table table-sm table-striped"><thead><tr>' +
                    '<th>Dates</th><th>LOS</th><th>Ward</th><th>Doctor</th><th>Reason</th><th>Total</th><th>Status</th><th></th>' +
                    '</tr></thead><tbody>';

                if (history.length === 0) {
                    html += '<tr><td colspan="8" class="text-center text-muted py-3">No admission history</td></tr>';
                } else {
                    history.forEach(function(h) {
                        html += '<tr>' +
                            '<td>' + h.dates + '</td>' +
                            '<td>' + h.los + 'd</td>' +
                            '<td>' + h.ward + '</td>' +
                            '<td>' + h.doctor + '</td>' +
                            '<td>' + (h.reason || '-') + '</td>' +
                            '<td class="font-weight-bold">' + fmt(h.total) + '</td>' +
                            '<td><span class="admission-status-pill ' + h.status + '">' + (h.status === 'admitted' ? 'Admitted' : 'Discharged') + '</span></td>' +
                            '<td><button class="btn btn-xs btn-outline-primary adm-mod-history-view-btn" data-adm-id="' + h.id + '"><i class="mdi mdi-eye"></i></button></td>' +
                            '</tr>';
                    });
                }
                html += '</tbody></table>';

                // Use the dedicated history modal so full-detail modal is not corrupted
                const modal = $('.adm-mod-history-modal').length ? $('.adm-mod-history-modal') : $('.adm-mod-full-detail-modal');
                if (modal.hasClass('adm-mod-history-modal')) {
                    modal.find('.adm-mod-history-body').html(html);
                } else {
                    modal.find('.modal-title').html('<i class="mdi mdi-history"></i> Full Admission History');
                    modal.find('.modal-body').html(html);
                    modal.find('.adm-mod-fd-print-btn').hide();
                }
                modal.modal('show');
            },
            error: function() {
                if (typeof toastr !== 'undefined') toastr.error('Failed to load admission history');
            }
        });
    }

    // History modal view button - loads that admission in the split view
    $(document).on('click', '.adm-mod-history-view-btn', function() {
        const id = $(this).data('adm-id');
        $('.adm-mod-history-modal, .adm-mod-full-detail-modal').modal('hide');
        _selectedAdmissionId = id;

        // Select the row in the list
        _container.find('.adm-mod-tbody tr').removeClass('selected');
        _container.find('.adm-mod-tbody tr[data-adm-mod-id="' + id + '"]').addClass('selected');

        loadDetail(id);
    });

    // Public API
    return {
        init: init,
        loadList: loadList,
        loadDetail: loadDetail,
        openFullDetailModal: openFullDetailModal,
        printBill: printBill,
        getSelectedId: function() { return _selectedAdmissionId; },
    };
})();
</script>
