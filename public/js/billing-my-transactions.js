/**
 * CoreHealth v2 - Billing & Reception Transactions Console Module
 * Supports My Transactions with Line-Item Outlines, KPI Cards, Filters, Manage Grid, Adjustments, and Voiding
 */
(function(window, $) {
    'use strict';

    window.CoreHealth = window.CoreHealth || {};

    let rawTransactions = [];
    let manageDataTable = null;
    let currentAdjustPayment = null;

    const MyTransactions = {
        init: function() {
            this.bindEvents();
            this.initDefaultDates();
            this.loadBanks();
        },

        initDefaultDates: function() {
            const today = new Date().toISOString().split('T')[0];
            if (!$('#my-trans-from-date').val()) $('#my-trans-from-date').val(today);
            if (!$('#my-trans-to-date').val()) $('#my-trans-to-date').val(today);
            if (!$('#manage-trans-from').val()) $('#manage-trans-from').val(today);
            if (!$('#manage-trans-to').val()) $('#manage-trans-to').val(today);
        },

        bindEvents: function() {
            const self = this;

            // Load button in My Transactions tab
            $(document).on('click', '#btn-load-my-transactions, #load-my-transactions', function(e) {
                e.preventDefault();
                self.loadMyTransactions();
            });

            // Filter changes inside My Transactions tab
            $(document).on('change', '#my-trans-payment-type, #my-trans-item-type, #my-trans-category-filter', function() {
                self.renderTransactionsList();
            });

            // Print My Transactions summary
            $(document).on('click', '#btn-print-my-transactions, #print-my-transactions', function(e) {
                e.preventDefault();
                self.printSummary();
            });

            // Manage Transactions Refresh
            $(document).on('click', '#btn-refresh-manage-transactions', function(e) {
                e.preventDefault();
                if (manageDataTable) {
                    manageDataTable.ajax.reload();
                } else {
                    self.initManageDataTable();
                }
            });

            // Tab switch to Manage Transactions
            $(document).on('shown.bs.tab', '#tab-manage-transactions', function() {
                if (!manageDataTable) {
                    self.initManageDataTable();
                } else {
                    manageDataTable.columns.adjust().draw();
                }
            });

            // Adjust payment button click (from manage table)
            $(document).on('click', '.btn-adjust-payment', function(e) {
                e.preventDefault();
                const paymentId = $(this).data('id');
                self.openAdjustModal(paymentId);
            });

            // Void payment button click (from manage table)
            $(document).on('click', '.btn-void-payment', function(e) {
                e.preventDefault();
                const paymentId = $(this).data('id');
                self.confirmVoidPayment(paymentId);
            });

            // Permanently neutralize Bootstrap multi-modal focus theft
            if (typeof $.fn.modal !== 'undefined' && $.fn.modal.Constructor) {
                if ($.fn.modal.Constructor.prototype._enforceFocus) {
                    $.fn.modal.Constructor.prototype._enforceFocus = function() {};
                }
                if ($.fn.modal.Constructor.prototype.enforceFocus) {
                    $.fn.modal.Constructor.prototype.enforceFocus = function() {};
                }
            }

            // Prevent nested modal focus trap and focus reason field
            $(document).on('show.bs.modal', '#adjustPaymentModal', function() {
                $(document).off('focusin.modal focusin.bs.modal');
            });
            $(document).on('shown.bs.modal', '#adjustPaymentModal', function() {
                $(document).off('focusin.modal focusin.bs.modal');
                const reasonEl = document.getElementById('adjust-reason');
                if (reasonEl) {
                    setTimeout(function() {
                        reasonEl.focus();
                    }, 80);
                }
            });
            $(document).on('focus focusin click', '#adjust-reason', function() {
                $(document).off('focusin.modal focusin.bs.modal');
            });

            // Receipt Preview Modal Print Buttons
            $(document).on('click', '#modal-print-a4', function(e) {
                e.preventDefault();
                const content = $('#modal-receipt-a4').html();
                if (content && content.trim()) {
                    self.directPrintHtml(content);
                }
            });

            $(document).on('click', '#modal-print-thermal', function(e) {
                e.preventDefault();
                const content = $('#modal-receipt-thermal').html();
                if (content && content.trim()) {
                    self.directPrintHtml(content);
                }
            });

            // View receipt button click (from manage or my trans)
            $(document).on('click', '.btn-view-receipt, .btn-print-tx-receipt', function(e) {
                e.preventDefault();
                const paymentId = $(this).data('id');
                self.printReceipt(paymentId);
            });

            // Adjust Modal - Method change
            $(document).on('change', '#adjust-new-method', function() {
                const method = $(this).val();
                if (method === 'SPLIT') {
                    $('#adjust-bank-col').hide();
                    $('#adjust-split-section').slideDown(200);
                    self.initAdjustSplitRows();
                } else if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
                    $('#adjust-split-section').hide();
                    $('#adjust-bank-col').show();
                    self.populateBankSelect($('#adjust-new-bank'));
                } else {
                    $('#adjust-split-section').hide();
                    $('#adjust-bank-col').hide();
                }
            });

            // Adjust Modal - Add split leg
            $(document).on('click', '#btn-add-adjust-split-leg', function(e) {
                e.preventDefault();
                self.addAdjustSplitLeg();
            });

            // Adjust Modal - Remove split leg
            $(document).on('click', '.btn-remove-adjust-leg', function(e) {
                e.preventDefault();
                const rows = $('#adjust-split-legs-container .adjust-split-row');
                if (rows.length <= 2) {
                    toastr.warning('Split payment requires at least two legs.');
                    return;
                }
                $(this).closest('.adjust-split-row').remove();
                self.recalculateAdjustSummary();
            });

            // Adjust Modal - Input calculation
            $(document).on('input change', '.adjust-leg-amount, .adjust-leg-method', function() {
                const $row = $(this).closest('.adjust-split-row');
                const method = $row.find('.adjust-leg-method').val();
                const $bank = $row.find('.adjust-leg-bank');
                if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
                    self.populateBankSelect($bank);
                    $bank.show();
                } else {
                    $bank.hide();
                    $bank.val('');
                }
                self.recalculateAdjustSummary();
            });

            // Adjust form submit
            $(document).on('submit', '#form-adjust-payment', function(e) {
                e.preventDefault();
                self.submitAdjustment();
            });

            // Toggle item breakdown details accordion
            $(document).on('click', '.btn-toggle-items-breakdown', function(e) {
                e.preventDefault();
                const target = $(this).data('target');
                $(target).slideToggle(150);
                $(this).find('i').toggleClass('mdi-chevron-down mdi-chevron-up');
            });

            // Tab switching for console tabs
            $(document).on('click', '#trans-console-tabs .nav-link', function(e) {
                e.preventDefault();
                const target = $(this).attr('data-target') || $(this).attr('data-bs-target') || $(this).attr('href');
                if (target) {
                    $('#trans-console-tabs .nav-link').removeClass('active');
                    $(this).addClass('active');
                    $('#trans-console-tab-content .tab-pane').removeClass('show active');
                    $(target).addClass('show active');
                    if (target === '#pane-manage-transactions') {
                        self.initManageDataTable();
                    }
                }
            });

            // Universal modal close handler
            $(document).on('click', '#myTransactionsModal [data-bs-dismiss="modal"], #myTransactionsModal [data-dismiss="modal"]', function(e) {
                e.preventDefault();
                if (typeof $ !== 'undefined' && typeof $('#myTransactionsModal').modal === 'function') {
                    $('#myTransactionsModal').modal('hide');
                }
            });

            $(document).on('click', '#adjustPaymentModal [data-bs-dismiss="modal"], #adjustPaymentModal [data-dismiss="modal"]', function(e) {
                e.preventDefault();
                if (typeof $ !== 'undefined' && typeof $('#adjustPaymentModal').modal === 'function') {
                    $('#adjustPaymentModal').modal('hide');
                }
            });

            // Global trigger for toolbar button
            $(document).on('click', '#open-my-transactions-btn, #my-transactions-btn, #btn-my-transactions', function(e) {
                e.preventDefault();
                self.openModal();
            });
        },

        openModal: function() {
            const modalEl = document.getElementById('myTransactionsModal');
            if (modalEl) {
                if (typeof $ !== 'undefined' && typeof $('#myTransactionsModal').modal === 'function') {
                    $('#myTransactionsModal').modal('show');
                } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    try {
                        const inst = (typeof bootstrap.Modal.getInstance === 'function' ? bootstrap.Modal.getInstance(modalEl) : null) || new bootstrap.Modal(modalEl);
                        inst.show();
                    } catch (e) {
                        $('#myTransactionsModal').modal('show');
                    }
                }
                this.loadMyTransactions();
            }
        },

        loadMyTransactions: function() {
            const from = $('#my-trans-from-date').val();
            const to = $('#my-trans-to-date').val();
            const paymentType = $('#my-trans-payment-type').val();
            const $tbody = $('#my-transactions-tbody');

            $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="mdi mdi-loading mdi-spin font-24"></i><p class="mb-0">Loading transactions...</p></td></tr>');

            const self = this;
            const url = '/billing-workbench/my-transactions';

            $.ajax({
                url: url,
                method: 'GET',
                data: { from: from, to: to, payment_type: paymentType },
                success: function(res) {
                    rawTransactions = res.transactions || [];
                    self.updateCategoriesDropdown();
                    self.renderTransactionsList();
                },
                error: function() {
                    $tbody.html('<tr><td colspan="8" class="text-center py-4 text-danger"><i class="mdi mdi-alert-circle font-24"></i><p class="mb-0">Failed to load transactions</p></td></tr>');
                }
            });
        },

        updateCategoriesDropdown: function() {
            const categories = new Set();
            rawTransactions.forEach(tx => {
                if (tx.items && tx.items.length) {
                    tx.items.forEach(it => {
                        if (it.category) categories.add(it.category);
                    });
                }
            });

            const $catFilter = $('#my-trans-category-filter');
            const currentCat = $catFilter.val();
            $catFilter.find('option:not(:first)').remove();
            categories.forEach(cat => {
                $catFilter.append(`<option value="${cat}">${cat}</option>`);
            });
            if (currentCat) $catFilter.val(currentCat);
        },

        renderTransactionsList: function() {
            const itemTypeFilter = $('#my-trans-item-type').val();
            const categoryFilter = $('#my-trans-category-filter').val();
            const $tbody = $('#my-transactions-tbody');
            $tbody.empty();

            let totalVol = 0;
            let productVol = 0;
            let serviceVol = 0;
            let customVol = 0;
            let splitVol = 0;
            let count = 0;

            const filteredTx = rawTransactions.filter(tx => {
                if (!itemTypeFilter && !categoryFilter) return true;

                if (!tx.items || !tx.items.length) return false;

                return tx.items.some(it => {
                    let matchType = true;
                    if (itemTypeFilter) {
                        const itType = (it.type || '').toLowerCase();
                        matchType = itType.includes(itemTypeFilter.toLowerCase());
                    }
                    let matchCat = true;
                    if (categoryFilter) {
                        matchCat = (it.category === categoryFilter);
                    }
                    return matchType && matchCat;
                });
            });

            if (filteredTx.length === 0) {
                $tbody.html('<tr><td colspan="8" class="text-center py-5 text-muted"><i class="mdi mdi-receipt-text-outline font-28"></i><p class="mb-0">No transactions match your current filters</p></td></tr>');
            }

            const self = this;
            filteredTx.forEach((tx, idx) => {
                count++;
                const txTotal = Math.abs(parseFloat(tx.total || 0));
                totalVol += txTotal;

                if (tx.is_split_payment) {
                    splitVol += txTotal;
                }

                // Calculate product vs service breakdown from items
                if (tx.items && tx.items.length) {
                    tx.items.forEach(it => {
                        const itAmt = parseFloat(it.amount || 0);
                        const itType = (it.type || '').toLowerCase();
                        if (itType.includes('product')) productVol += itAmt;
                        else if (itType.includes('service')) serviceVol += itAmt;
                        else if (itType.includes('custom')) customVol += itAmt;
                    });
                }

                // Render Item Badges / Outlines
                let itemsSummaryHtml = '<div class="text-muted fst-italic">No item details</div>';
                let itemsListHtml = '';
                if (tx.items && tx.items.length) {
                    const badgeList = tx.items.map(it => {
                        const typeBadge = (it.type || '').toLowerCase() === 'custom'
                            ? '<span class="badge bg-warning text-dark font-10">Custom</span>'
                            : ((it.type || '').toLowerCase() === 'product'
                                ? '<span class="badge bg-success font-10">Product</span>'
                                : '<span class="badge bg-info font-10">Service</span>');

                        return `<div class="mb-1 d-flex justify-content-between align-items-center">
                            <span>${typeBadge} <strong>${self.escapeHtml(it.name)}</strong> <small class="text-muted">(Qty: ${it.qty || 1})</small></span>
                            <span class="text-primary fw-bold font-11">₦${parseFloat(it.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>`;
                    }).join('');

                    itemsListHtml = `<div class="p-2 border rounded bg-light mt-1 font-11" id="tx-items-box-${idx}" style="display: none;">${badgeList}</div>`;
                    itemsSummaryHtml = `
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="font-11 text-dark fw-bold">${tx.items.length} item(s)</span>
                            <button type="button" class="btn btn-xs btn-outline-primary btn-toggle-items-breakdown py-0 px-1 font-11" data-target="#tx-items-box-${idx}">
                                Details <i class="mdi mdi-chevron-down"></i>
                            </button>
                        </div>
                        ${itemsListHtml}
                    `;
                }

                // Split / Method breakdown badge
                let methodBadge = `<span class="badge bg-secondary font-11">${self.escapeHtml(tx.payment_method_display || tx.payment_method)}</span>`;
                if (tx.is_split_payment && tx.splits && tx.splits.length) {
                    const splitLines = tx.splits.map(s => `${(window.CoreHealth && window.CoreHealth.formatMethodName) ? window.CoreHealth.formatMethodName(s.payment_method) : s.payment_method}: ₦${parseFloat(s.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}`).join(', ');
                    methodBadge = `<span class="badge bg-purple text-white font-11" title="${self.escapeHtml(splitLines)}">Split (${tx.splits.length} tenders)</span><small class="d-block font-10 text-muted">${self.escapeHtml(splitLines)}</small>`;
                }

                const voidBadge = tx.is_voided
                    ? `<span class="badge bg-danger text-white font-10 ms-1" title="Reason: ${self.escapeHtml(tx.void_reason)}">VOIDED</span>`
                    : '';

                $tbody.append(`
                    <tr class="${tx.is_voided ? 'table-danger' : ''}">
                        <td class="text-center font-11 text-muted">${idx + 1}</td>
                        <td class="font-11 text-nowrap">${self.escapeHtml(tx.created_at || '-')}</td>
                        <td class="font-11 fw-bold text-nowrap">${self.escapeHtml(tx.reference_no || '-')} ${voidBadge}</td>
                        <td class="font-11">
                            <div class="fw-bold">${self.escapeHtml(tx.patient_name || '-')}</div>
                            <small class="text-muted">File: ${self.escapeHtml(tx.file_no || '-')}</small>
                        </td>
                        <td class="font-11">${methodBadge}</td>
                        <td class="font-11" style="min-width: 220px;">${itemsSummaryHtml}</td>
                        <td class="text-end font-12 fw-bold text-primary">₦${txTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-info btn-print-tx-receipt" data-id="${tx.id}" title="Print Receipt">
                                <i class="mdi mdi-printer"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            // Update KPI cards
            $('#kpi-my-count').text(count);
            $('#kpi-my-total').text(`₦${totalVol.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            $('#kpi-my-products').text(`₦${productVol.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            $('#kpi-my-services').text(`₦${serviceVol.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            $('#kpi-my-custom').text(`₦${customVol.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            $('#kpi-my-splits').text(`₦${splitVol.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        },

        initManageDataTable: function() {
            const self = this;
            if (manageDataTable) {
                manageDataTable.destroy();
            }

            manageDataTable = $('#manage-transactions-datatable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/billing-workbench/manage-transactions',
                    data: function(d) {
                        d.from = $('#manage-trans-from').val();
                        d.to = $('#manage-trans-to').val();
                        d.payment_method = $('#manage-trans-method').val();
                        d.status = $('#manage-trans-status').val();
                        d.scope = $('#manage-trans-scope').length ? $('#manage-trans-scope').val() : 'own';
                    }
                },
                columns: [
                    { data: 'created_at', name: 'created_at', className: 'font-11 text-nowrap', render: function(data) {
                        return data ? new Date(data).toLocaleString() : '-';
                    }},
                    { data: 'reference_no', name: 'reference_no', className: 'font-11 fw-bold' },
                    { data: 'patient_name', name: 'patient_name', className: 'font-11' },
                    { data: 'file_no', name: 'file_no', className: 'font-11' },
                    { data: 'payment_method_display', name: 'payment_method_display', className: 'font-11' },
                    { data: 'total_formatted', name: 'total', className: 'text-end font-12 fw-bold text-primary' },
                    { data: 'status_badge', name: 'status_badge', className: 'text-center' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
                ],
                order: [[0, 'desc']],
                pageLength: 15,
                responsive: true
            });
        },

        openAdjustModal: function(paymentId) {
            const self = this;
            self.loadBanks(function() {
                $.get(`/billing-workbench/payment/${paymentId}/details`, function(data) {
                    currentAdjustPayment = data;
                    $('#adjust-payment-id').val(data.id);
                    $('#adjust-tx-ref').text(data.reference_no);
                    $('#adjust-tx-patient').text(`${data.patient_name} (${data.file_no})`);
                    $('#adjust-tx-amount').text(`₦${Math.abs(data.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
                    $('#adjust-tx-current-method').text(data.payment_method_display);
                    $('#adjust-reason').val('');

                    // Populate single bank dropdown first
                    self.populateBankSelect($('#adjust-new-bank'));

                    // Preselect method
                    if (data.is_split_payment) {
                        $('#adjust-new-method').val('SPLIT').trigger('change');
                    } else {
                        $('#adjust-new-method').val(data.payment_method || 'CASH').trigger('change');
                        if (data.bank_id) {
                            $('#adjust-new-bank').val(data.bank_id);
                        }
                    }

                    const modalElem = document.getElementById('adjustPaymentModal');
                    if (modalElem) {
                        if (typeof $ !== 'undefined' && typeof $('#adjustPaymentModal').modal === 'function') {
                            $('#adjustPaymentModal').modal('show');
                        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            try {
                                const inst = (typeof bootstrap.Modal.getInstance === 'function' ? bootstrap.Modal.getInstance(modalElem) : null) || new bootstrap.Modal(modalElem);
                                inst.show();
                            } catch (e) {
                                $('#adjustPaymentModal').modal('show');
                            }
                        }
                    }
                }).fail(function() {
                    toastr.error('Could not load payment details for adjustment.');
                });
            });
        },

        initAdjustSplitRows: function() {
            const $container = $('#adjust-split-legs-container');
            $container.empty();

            const total = currentAdjustPayment ? Math.abs(parseFloat(currentAdjustPayment.total || 0)) : 0;
            const self = this;

            if (currentAdjustPayment && currentAdjustPayment.splits && currentAdjustPayment.splits.length >= 2) {
                currentAdjustPayment.splits.forEach(s => {
                    self.appendAdjustSplitRow(s.payment_method, s.amount, s.bank_id, s.reference_no);
                });
            } else {
                // Initialize default 2 legs
                self.appendAdjustSplitRow('CASH', '');
                self.appendAdjustSplitRow('POS', total > 0 ? total.toFixed(2) : '');
            }
            self.recalculateAdjustSummary();
        },

        appendAdjustSplitRow: function(method, amount, bankId, ref) {
            method = method || 'CASH';
            amount = amount !== undefined && amount !== null ? amount : '';
            bankId = bankId || '';
            ref = ref || '';

            const isBank = ['POS', 'TRANSFER', 'MOBILE'].includes(method);

            const row = $(`
                <div class="adjust-split-row p-2 mb-2 border rounded bg-white shadow-sm position-relative">
                    <div class="row g-2 align-items-center">
                        <div class="col-4">
                            <label class="small text-muted mb-0">Method</label>
                            <select class="form-select form-select-sm adjust-leg-method">
                                <option value="CASH" ${method === 'CASH' ? 'selected' : ''}>Cash</option>
                                <option value="POS" ${method === 'POS' ? 'selected' : ''}>POS</option>
                                <option value="TRANSFER" ${method === 'TRANSFER' ? 'selected' : ''}>Transfer</option>
                                <option value="MOBILE" ${method === 'MOBILE' ? 'selected' : ''}>Mobile</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="small text-muted mb-0">Bank</label>
                            <select class="form-select form-select-sm adjust-leg-bank" style="${isBank ? '' : 'display:none;'}">
                                <option value="">-- Bank --</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="small text-muted mb-0">Amount</label>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm adjust-leg-amount" placeholder="0.00" value="${amount}">
                        </div>
                        <div class="col-1 text-end pt-3">
                            <button type="button" class="btn btn-xs btn-outline-danger btn-remove-adjust-leg" title="Remove">
                                <i class="mdi mdi-delete-outline"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `);

            $('#adjust-split-legs-container').append(row);
            if (isBank) {
                this.populateBankSelect(row.find('.adjust-leg-bank'));
                if (bankId) row.find('.adjust-leg-bank').val(bankId);
            }
        },

        addAdjustSplitLeg: function() {
            const total = currentAdjustPayment ? Math.abs(parseFloat(currentAdjustPayment.total || 0)) : 0;
            let sum = 0;
            $('.adjust-split-row').each(function() {
                sum += parseFloat($(this).find('.adjust-leg-amount').val()) || 0;
            });
            const remainder = Math.max(0, total - sum);
            this.appendAdjustSplitRow('TRANSFER', remainder > 0 ? remainder.toFixed(2) : '');
            this.recalculateAdjustSummary();
        },

        recalculateAdjustSummary: function() {
            const total = currentAdjustPayment ? Math.abs(parseFloat(currentAdjustPayment.total || 0)) : 0;
            let sum = 0;
            $('.adjust-split-row').each(function() {
                sum += parseFloat($(this).find('.adjust-leg-amount').val()) || 0;
            });
            const diff = total - sum;

            $('#adjust-split-allocated').text(`₦${sum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            const $rem = $('#adjust-split-remaining');
            if (Math.abs(diff) < 0.01) {
                $rem.removeClass('text-danger text-warning').addClass('text-success').text('Balanced (₦0.00)');
            } else if (diff > 0) {
                $rem.removeClass('text-success text-danger').addClass('text-warning')
                    .text(`Remaining: ₦${diff.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            } else {
                $rem.removeClass('text-success text-warning').addClass('text-danger')
                    .text(`Over-allocated: ₦${Math.abs(diff).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            }
        },

        submitAdjustment: function() {
            if (!currentAdjustPayment) return;

            const paymentId = currentAdjustPayment.id;
            const method = $('#adjust-new-method').val();
            const reason = $('#adjust-reason').val().trim();
            const isSplit = (method === 'SPLIT');
            const total = Math.abs(parseFloat(currentAdjustPayment.total || 0));

            if (!reason || reason.length < 5) {
                toastr.warning('Please provide a reason of at least 5 characters for this adjustment.');
                return;
            }

            let splits = null;
            let bankId = null;

            if (isSplit) {
                splits = [];
                let sum = 0;
                let valid = true;

                $('.adjust-split-row').each(function(idx) {
                    const m = $(this).find('.adjust-leg-method').val();
                    const b = $(this).find('.adjust-leg-bank').val() || null;
                    const a = parseFloat($(this).find('.adjust-leg-amount').val()) || 0;

                    if (a <= 0) {
                        toastr.warning(`Split #${idx + 1} amount must be greater than 0.`);
                        valid = false;
                        return false;
                    }
                    if (['POS', 'TRANSFER', 'MOBILE'].includes(m) && !b) {
                        toastr.warning(`Please select bank for split #${idx + 1} (${m}).`);
                        valid = false;
                        return false;
                    }

                    sum += a;
                    splits.push({ payment_method: m, bank_id: b, amount: a });
                });

                if (!valid) return;

                if (splits.length < 2) {
                    toastr.warning('Split payment requires at least two legs.');
                    return;
                }

                if (Math.abs(sum - total) > 0.05) {
                    toastr.error(`Total of split legs (₦${sum.toFixed(2)}) must equal transaction total (₦${total.toFixed(2)}).`);
                    return;
                }
            } else {
                if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
                    bankId = $('#adjust-new-bank').val();
                    if (!bankId) {
                        toastr.warning('Please select a bank for this payment method.');
                        return;
                    }
                }
            }

            const $btn = $('#btn-submit-adjustment');
            const origText = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Applying...');

            const self = this;
            $.ajax({
                url: '/billing-workbench/adjust-payment',
                method: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                    payment_id: paymentId,
                    reason: reason,
                    is_split: isSplit ? 1 : 0,
                    payment_method: method,
                    bank_id: bankId,
                    splits: splits
                },
                success: function(res) {
                    $btn.prop('disabled', false).html(origText);
                    toastr.success(res.message || 'Payment method adjusted successfully!');
                    $('#adjustPaymentModal').modal('hide');
                    if (manageDataTable) manageDataTable.ajax.reload(null, false);
                    self.loadMyTransactions();
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(origText);
                    toastr.error(xhr.responseJSON?.message || 'Failed to adjust payment method.');
                }
            });
        },

        confirmVoidPayment: function(paymentId) {
            const self = this;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Void Transaction?',
                    text: 'This will reverse all General Ledger entries, restore patient account balances if paid from deposit, and mark the transaction as VOIDED. Please provide a reason:',
                    input: 'textarea',
                    inputPlaceholder: 'Enter void reason (required, min 5 chars)...',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Void Transaction',
                    inputValidator: (value) => {
                        if (!value || value.trim().length < 5) {
                            return 'You must enter a reason of at least 5 characters.';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        self.executeVoidPayment(paymentId, result.value.trim());
                    }
                });
            } else {
                const reason = prompt('Void Transaction: This will reverse all ledger entries and items. Enter reason (min 5 chars):');
                if (reason && reason.trim().length >= 5) {
                    self.executeVoidPayment(paymentId, reason.trim());
                } else if (reason !== null) {
                    toastr.warning('A valid reason of at least 5 characters is required to void.');
                }
            }
        },

        executeVoidPayment: function(paymentId, reason) {
            const self = this;
            $.ajax({
                url: '/billing-workbench/void-transaction',
                method: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                    payment_id: paymentId,
                    reason: reason
                },
                success: function(res) {
                    toastr.success(res.message || 'Transaction voided successfully!');
                    if (manageDataTable) manageDataTable.ajax.reload(null, false);
                    self.loadMyTransactions();
                    if (typeof loadBillingItems === 'function') loadBillingItems();
                    if (typeof loadAccountSummary === 'function') loadAccountSummary();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to void transaction.');
                }
            });
        },

        printReceipt: function(paymentId) {
            const self = this;
            $.ajax({
                url: '/billing-workbench/print-receipt',
                method: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                    payment_id: paymentId,
                    payment_ids: [paymentId]
                },
                success: function(res) {
                    if (res && res.receipt_a4) {
                        if ($('#modal-receipt-a4').length && $('#receiptPreviewModal').length) {
                            $('#modal-receipt-a4').html(res.receipt_a4);
                            $('#modal-receipt-thermal').html(res.receipt_thermal || res.receipt_a4);
                            $('.receipt-modal-tab').removeClass('active');
                            $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                            $('#modal-receipt-a4').show();
                            $('#modal-receipt-thermal').hide();

                            // Elevate receipt preview modal above myTransactionsModal
                            $('#receiptPreviewModal').css('z-index', 1070).modal('show');
                            setTimeout(function() {
                                $('.modal-backdrop').last().css('z-index', 1065);
                            }, 50);
                        } else {
                            self.directPrintHtml(res.receipt_a4);
                        }
                    } else {
                        toastr.error('Receipt content not returned from server.');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to generate receipt printout.');
                }
            });
        },

        directPrintHtml: function(htmlContent) {
            let $iframe = $('#receipt-print-frame');
            if (!$iframe.length) {
                $iframe = $('<iframe id="receipt-print-frame" style="position:absolute;width:0;height:0;left:-9999px;top:-9999px;border:none;"></iframe>');
                $('body').append($iframe);
            }
            const frameDoc = $iframe[0].contentWindow || $iframe[0].contentDocument;
            const doc = frameDoc.document || frameDoc;
            doc.open();
            doc.write(htmlContent);
            doc.close();
            setTimeout(function() {
                try {
                    ($iframe[0].contentWindow || $iframe[0]).focus();
                    ($iframe[0].contentWindow || $iframe[0]).print();
                } catch (e) {
                    const win = window.open('', '_blank', 'width=850,height=750');
                    if (win) {
                        win.document.write(htmlContent);
                        win.document.close();
                        win.focus();
                        setTimeout(function() { win.print(); }, 250);
                    }
                }
            }, 300);
        },

        printSummary: function() {
            const from = $('#my-trans-from-date').val() || '';
            const to = $('#my-trans-to-date').val() || '';
            const paymentType = $('#my-trans-payment-type').val() || '';
            const itemType = $('#my-trans-item-type').val() || '';

            const params = new URLSearchParams({
                action: 'print',
                from: from,
                to: to
            });

            if (paymentType) params.append('payment_type', paymentType);
            if (itemType) params.append('item_type', itemType);

            const printUrl = '/billing-workbench/my-transactions?' + params.toString();
            window.open(printUrl, '_blank', 'width=1050,height=850,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes');
        },

        loadBanks: function(callback) {
            if (window.availableBanks && window.availableBanks.length > 0) {
                if (typeof callback === 'function') callback(window.availableBanks);
                return;
            }

            const self = this;
            $.ajax({
                url: '/banks/active',
                method: 'GET',
                success: function(response) {
                    if (response && response.banks) {
                        window.availableBanks = response.banks;
                        // Populate any active selects immediately
                        self.populateBankSelect($('#my-trans-bank'));
                        self.populateBankSelect($('#adjust-new-bank'));
                        $('.adjust-leg-bank').each(function() {
                            self.populateBankSelect($(this));
                        });
                        if (typeof callback === 'function') callback(window.availableBanks);
                    } else {
                        if (typeof callback === 'function') callback([]);
                    }
                },
                error: function() {
                    console.warn('Could not load banks from /banks/active');
                    if (typeof callback === 'function') callback([]);
                }
            });
        },

        populateBankSelect: function($select) {
            if (!$select || !$select.length) return;
            const curr = $select.val();
            $select.find('option:not(:first)').remove();
            const self = this;

            const renderOptions = function(banks) {
                $select.find('option:not(:first)').remove();
                (banks || []).forEach(b => {
                    const text = b.account_number ? `${b.name} - ${b.account_number}` : b.name;
                    $select.append(`<option value="${b.id}">${text}</option>`);
                });
                if (curr) $select.val(curr);
            };

            if (!window.availableBanks || window.availableBanks.length === 0) {
                this.loadBanks(function(banks) {
                    renderOptions(banks);
                });
            } else {
                renderOptions(window.availableBanks);
            }
        },

        escapeHtml: function(str) {
            if (!str) return '';
            return $('<div>').text(str).html();
        }
    };

    window.CoreHealth.MyTransactions = MyTransactions;
    window.loadMyTransactions = function() { MyTransactions.loadMyTransactions(); };
    window.renderMyTransactions = function(d) { MyTransactions.renderTransactionsList(); };

    $(document).ready(function() {
        MyTransactions.init();
    });

})(window, jQuery);
