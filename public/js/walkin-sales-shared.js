/**
 * CoreHealth v2 - Shared Walk-In Sales Module
 * Operates across Reception Workbench and Billing Workbench
 */
(function(window, $) {
    'use strict';

    window.CoreHealth = window.CoreHealth || {};

    let walkinCart = [];
    let searchDebounceTimer = null;
    let searchXhr = null;

    const WalkinSales = {
        init: function() {
            this.bindEvents();
        },

        isBillingContext: function() {
            return window.location.pathname.includes('billing-workbench') ||
                   (typeof window.WORKBENCH_CONFIG !== 'undefined' && window.WORKBENCH_CONFIG.context === 'billing');
        },

        getBasePrefix: function() {
            return this.isBillingContext() ? '/billing-workbench' : '/reception';
        },

        getCurrentPatientId: function() {
            let pid = null;
            if (typeof window.currentPatient !== 'undefined' && window.currentPatient) {
                pid = (typeof window.currentPatient === 'object') ? (window.currentPatient.id || window.currentPatient.patient_id) : window.currentPatient;
            }
            if (!pid && typeof window.currentPatientId !== 'undefined' && window.currentPatientId) {
                pid = window.currentPatientId;
            }
            if (!pid && typeof window.currentPatientData !== 'undefined' && window.currentPatientData) {
                pid = window.currentPatientData.id || window.currentPatientData.patient_id;
            }
            if (!pid && typeof currentPatient !== 'undefined' && currentPatient) {
                pid = (typeof currentPatient === 'object') ? (currentPatient.id || currentPatient.patient_id) : currentPatient;
            }
            if (!pid && typeof currentPatientData !== 'undefined' && currentPatientData) {
                pid = currentPatientData.id || currentPatientData.patient_id;
            }
            if (!pid) {
                const domVal = $('#selected-patient-id, #patient_id, #patient-id, input[name="patient_id"]').val();
                if (domVal) pid = domVal;
            }
            if (!pid) {
                const headerPid = $('#patient-header').data('patient-id') || $('#patient-info-header').data('patient-id') || $('#workspace-content').data('patient-id');
                if (headerPid) pid = headerPid;
            }
            return pid ? String(pid) : null;
        },

        bindEvents: function() {
            const self = this;

            // Search input typing
            $(document).on('input', '#walkin-search', function() {
                if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
                const query = $(this).val();
                const activeType = self.getActiveCatalogType();
                searchDebounceTimer = setTimeout(function() {
                    self.searchCatalog(query, activeType);
                }, 250);
            });

            // Clear search button
            $(document).on('click', '#btn-clear-walkin-search', function() {
                $('#walkin-search').val('').trigger('input');
            });

            // Subtabs switching
            $(document).on('click', '#walkin-subtabs .nav-link', function(e) {
                e.preventDefault();
                $('#walkin-subtabs .nav-link').removeClass('active');
                $(this).addClass('active');

                const targetPane = $(this).attr('href');
                $('#walkin-subtabs-content .tab-pane').removeClass('show active');
                $(targetPane).addClass('show active');

                const type = $(this).data('type');
                if (type !== 'free_form') {
                    const query = $('#walkin-search').val() || '';
                    self.searchCatalog(query, type);
                }
            });

            // When Walkin tab is activated in workbench
            $(document).on('click', '.workspace-tab[data-tab="walkin"], a[href="#walkin-tab"]', function() {
                const activeType = self.getActiveCatalogType();
                if (activeType !== 'free_form') {
                    const query = $('#walkin-search').val() || '';
                    self.searchCatalog(query, activeType);
                }
                self.loadRecentRequests();
            });

            // Add from search results
            $(document).on('click', '.walkin-add-catalog-item', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const type = $(this).data('type');
                const name = $(this).data('name');
                const price = parseFloat($(this).data('price')) || 0;
                const deptId = $(this).data('department-id');
                const deptName = $(this).data('department-name');
                self.addItem(id, type, name, price, deptId, deptName);
            });

            // Free-form submission
            $(document).on('submit', '#walkin-freeform-form', function(e) {
                e.preventDefault();
                self.handleFreeFormSubmit();
            });

            // Cart remove item
            $(document).on('click', '.walkin-remove-cart-item', function(e) {
                e.preventDefault();
                const index = parseInt($(this).data('index'), 10);
                self.removeItem(index);
            });

            // Checkout / Submit button
            $(document).on('click', '#btn-submit-walkin', function(e) {
                e.preventDefault();
                self.submitCart();
            });

            // Sub-tab: Recent (24h) tab shown in sidebar
            $(document).on('shown.bs.tab', '#walkin-cart-tabs a[href="#walkin-recent-pane"], #walkin-cart-tabs a[data-toggle="tab"][href="#walkin-recent-pane"]', function () {
                self.loadRecentRequests();
            });
            $(document).on('click', '#walkin-cart-tabs a[href="#walkin-recent-pane"]', function () {
                self.loadRecentRequests();
            });

            // Toggle select all unpaid walkin requests
            $(document).on('change', '#walkin-select-all-unpaid', function() {
                const isChecked = $(this).is(':checked');
                $('.walkin-unpaid-item-checkbox').prop('checked', isChecked);
                self.updateWalkinSelectedTotal();
            });

            // Individual walkin unpaid checkbox change
            $(document).on('change', '.walkin-unpaid-item-checkbox', function() {
                const total = $('.walkin-unpaid-item-checkbox').length;
                const checked = $('.walkin-unpaid-item-checkbox:checked').length;
                $('#walkin-select-all-unpaid').prop('checked', total > 0 && total === checked);
                self.updateWalkinSelectedTotal();
            });

            // Click Pay Selected Walk-in
            $(document).on('click', '#btn-pay-selected-walkin', function(e) {
                e.preventDefault();
                self.paySelectedRecentRequests();
            });

            // Click Pay Single Walk-in
            $(document).on('click', '.btn-pay-single-walkin', function(e) {
                e.preventDefault();
                const sId = $(this).data('service-request-id');
                const payable = parseFloat($(this).data('payable')) || 0;
                self.paySingleRecentRequest(sId, payable);
            });

            // Discard single request
            $(document).on('click', '.btn-discard-recent-request', function(e) {
                e.preventDefault();
                const type = $(this).data('type');
                const id = $(this).data('id');
                const name = $(this).data('name');
                const reqNo = $(this).data('request-no');
                self.openDiscardModal(type, id, name, reqNo);
            });

            // Discard request form submit
            $(document).on('submit', '#discardRequestForm', function(e) {
                e.preventDefault();
                self.submitDiscardRequest();
            });
        },

        getActiveCatalogType: function() {
            return $('#walkin-subtabs .nav-link.active').data('type') || 'lab';
        },

        searchCatalog: function(query, type) {
            query = (query || '').trim();
            type = type || this.getActiveCatalogType();
            const $container = $('#walkin-search-results');

            if (type === 'free_form') {
                return;
            }

            const prefix = this.getBasePrefix();
            let endpoint = `${prefix}/services/lab`;
            if (type === 'imaging') {
                endpoint = `${prefix}/services/imaging`;
            } else if (type === 'product') {
                endpoint = `${prefix}/products`;
            } else if (type === 'procedure') {
                endpoint = `${prefix}/services/procedure`;
            }

            $container.html('<div class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> Searching catalog...</div>');

            if (searchXhr) {
                searchXhr.abort();
            }

            const self = this;
            searchXhr = $.ajax({
                url: endpoint,
                method: 'GET',
                data: { q: query },
                success: function(data) {
                    const items = Array.isArray(data) ? data : (data.services || data.products || []);
                    self.renderSearchResults(items, type, query);
                },
                error: function(xhr, status) {
                    if (status !== 'abort') {
                        $container.html('<p class="text-danger text-center py-3"><i class="mdi mdi-alert-circle"></i> Failed to sync items from catalog</p>');
                    }
                }
            });
        },

        renderSearchResults: function(items, type, query) {
            const $container = $('#walkin-search-results');
            $container.empty();

            if (!items || items.length === 0) {
                $container.html(`<p class="text-muted text-center py-4">No ${type} items found${query ? ' matching "' + this.escapeHtml(query) + '"' : ''}</p>`);
                return;
            }

            const typeMeta = {
                lab: { label: 'Lab', badge: 'info' },
                imaging: { label: 'Imaging', badge: 'warning' },
                product: { label: 'Product', badge: 'success' },
                procedure: { label: 'Procedure', badge: 'primary' }
            }[type] || { label: type, badge: 'secondary' };

            const self = this;
            items.forEach(function(item) {
                const price = parseFloat(item.price || item.current_sale_price || 0);
                const codeBadge = item.code ? `<span class="badge bg-light text-dark border ms-1 font-11">${self.escapeHtml(item.code)}</span>` : '';
                const deptName = item.department_name || (type === 'lab' ? 'Laboratory' : (type === 'imaging' ? 'Radiology & Imaging' : (type === 'product' ? 'Pharmacy' : 'General')));
                const deptBadge = `<span class="badge bg-light text-secondary border ms-1 font-11" title="Benefiting Department"><i class="mdi mdi-office-building font-10"></i> ${self.escapeHtml(deptName)}</span>`;

                $container.append(`
                    <div class="walkin-service-item d-flex justify-content-between align-items-center p-2 border-bottom">
                        <div>
                            <span class="badge bg-${typeMeta.badge} text-white font-11">${typeMeta.label}</span>
                            ${codeBadge}
                            <span class="ms-2 fw-bold font-13">${self.escapeHtml(item.name || item.product_name || item.service_name)}</span>
                            ${deptBadge}
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-3 fw-bold font-13">₦${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            <button class="btn btn-sm btn-primary walkin-add-catalog-item"
                                    data-id="${item.id}"
                                    data-type="${type}"
                                    data-name="${self.escapeHtml(item.name || item.product_name || item.service_name)}"
                                    data-price="${price}"
                                    data-department-id="${item.department_id || ''}"
                                    data-department-name="${self.escapeHtml(deptName)}">
                                <i class="mdi mdi-plus"></i> Add
                            </button>
                        </div>
                    </div>
                `);
            });
        },

        addItem: function(id, type, name, price, deptId, deptName) {
            const patientId = this.getCurrentPatientId();
            if (!patientId) {
                if (typeof toastr !== 'undefined') toastr.warning('Please select a patient first');
                return;
            }

            const exists = walkinCart.some(item => item.id == id && item.type == type);
            if (exists) {
                if (typeof toastr !== 'undefined') toastr.info('Item is already in cart');
                return;
            }

            const self = this;
            const prefix = this.getBasePrefix();
            const isProduct = (type === 'product');

            $.ajax({
                url: `${prefix}/tariff-preview`,
                method: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                    patient_id: patientId,
                    service_id: isProduct ? null : id,
                    product_id: isProduct ? id : null,
                    qty: 1
                },
                success: function(data) {
                    walkinCart.push({
                        id: id,
                        type: type,
                        name: name,
                        department_id: data.department_id || deptId || null,
                        department_name: data.department_name || deptName || null,
                        base_price: parseFloat(data.base_price || price),
                        payable_amount: parseFloat(data.payable_amount !== undefined ? data.payable_amount : price),
                        claims_amount: parseFloat(data.claims_amount || 0),
                        coverage_mode: data.coverage_mode || null,
                        hmo_name: data.hmo_name || 'Self Pay',
                        quantity: 1,
                        is_billable: 1
                    });
                    self.updateCartUI();
                    self.switchToCartTab();
                    if (typeof toastr !== 'undefined') toastr.success(`Added "${name}" to cart`);
                },
                error: function() {
                    walkinCart.push({
                        id: id,
                        type: type,
                        name: name,
                        department_id: deptId || null,
                        department_name: deptName || null,
                        base_price: parseFloat(price),
                        payable_amount: parseFloat(price),
                        claims_amount: 0,
                        coverage_mode: null,
                        hmo_name: 'Self Pay',
                        quantity: 1,
                        is_billable: 1
                    });
                    self.updateCartUI();
                    self.switchToCartTab();
                }
            });
        },

        switchToCartTab: function() {
            const $cartTab = $('#walkin-cart-tabs a[href="#walkin-cart-pane"]');
            if ($cartTab.length) {
                if (typeof $cartTab.tab === 'function') {
                    $cartTab.tab('show');
                } else if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    bootstrap.Tab.getOrCreateInstance($cartTab[0]).show();
                }
            }
        },

        handleFreeFormSubmit: function() {
            const patientId = this.getCurrentPatientId();
            if (!patientId) {
                if (typeof toastr !== 'undefined') toastr.warning('Please select a patient first');
                return;
            }

            const name = $('#walkin-freeform-name').val().trim();
            const deptId = $('#walkin-freeform-dept').val();
            const deptName = $('#walkin-freeform-dept option:selected').data('name') || $('#walkin-freeform-dept option:selected').text();
            const desc = $('#walkin-freeform-desc').val().trim();
            const price = parseFloat($('#walkin-freeform-price').val()) || 0;
            const qty = parseInt($('#walkin-freeform-qty').val(), 10) || 1;
            const isBillable = $('#walkin-freeform-billable').is(':checked') ? 1 : 0;

            if (!name) {
                if (typeof toastr !== 'undefined') toastr.warning('Item name is required');
                $('#walkin-freeform-name').focus();
                return;
            }

            if (!deptId) {
                if (typeof toastr !== 'undefined') toastr.warning('Please select a benefiting department');
                $('#walkin-freeform-dept').focus();
                return;
            }

            if (price < 0) {
                if (typeof toastr !== 'undefined') toastr.warning('Price cannot be negative');
                return;
            }

            const payable = isBillable ? price : 0;

            walkinCart.push({
                id: 'free_form_' + Date.now(),
                type: 'free_form',
                is_free_form: 1,
                name: name,
                department_id: parseInt(deptId, 10),
                department_name: deptName,
                description: desc,
                price: price,
                base_price: price,
                payable_amount: payable,
                claims_amount: 0,
                coverage_mode: null,
                hmo_name: 'Self Pay',
                qty: qty,
                quantity: qty,
                is_billable: isBillable
            });

            this.updateCartUI();

            // Reset form
            $('#walkin-freeform-form')[0].reset();
            $('#walkin-freeform-dept').val('');
            $('#walkin-freeform-billable').prop('checked', true);

            // Switch to cart pane
            this.switchToCartTab();

            if (typeof toastr !== 'undefined') toastr.success(`Added custom item "${name}" to cart`);
        },

        removeItem: function(index) {
            walkinCart.splice(index, 1);
            this.updateCartUI();
        },

        updateCartUI: function() {
            const $body = $('#walkin-cart-body');
            $body.empty();

            $('#cart-count-badge').text(walkinCart.length);

            if (walkinCart.length === 0) {
                $body.html(`
                    <tr id="walkin-cart-empty">
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="mdi mdi-cart-outline font-24"></i>
                            <p class="mb-0 mt-2 font-12">No items selected</p>
                        </td>
                    </tr>
                `);
                $('#walkin-subtotal').text('₦0.00');
                $('#walkin-cart-total').text('₦0.00');
                $('#walkin-hmo-row').hide();
                $('#btn-submit-walkin').prop('disabled', true);
                return;
            }

            let subtotal = 0;
            let totalPayable = 0;
            let totalClaims = 0;
            let hmoName = 'Self Pay';

            const self = this;
            walkinCart.forEach(function(item, idx) {
                const itemSubtotal = item.base_price * item.quantity;
                const itemPayable = item.payable_amount * item.quantity;
                const itemClaims = (item.claims_amount || 0) * item.quantity;

                subtotal += itemSubtotal;
                totalPayable += itemPayable;
                totalClaims += itemClaims;

                if (item.hmo_name && item.hmo_name !== 'Self Pay' && item.hmo_name !== 'Private') {
                    hmoName = item.hmo_name;
                }

                const freeFormBadge = item.is_free_form ? '<span class="badge bg-secondary text-white font-10 ms-1">Custom</span>' : '';
                const deptBadge = item.department_name ? `<span class="badge bg-light text-secondary border font-10 ms-1" title="Benefiting Department"><i class="mdi mdi-office-building font-10"></i> ${self.escapeHtml(item.department_name)}</span>` : '';
                const unbillableBadge = !item.is_billable ? '<span class="badge bg-warning text-dark font-10 ms-1">Non-billable</span>' : '';

                $body.append(`
                    <tr>
                        <td class="font-12">
                            <div class="fw-bold">${self.escapeHtml(item.name)} ${freeFormBadge} ${deptBadge} ${unbillableBadge}</div>
                            <small class="text-muted">Qty: ${item.quantity}</small>
                        </td>
                        <td class="text-end font-12">₦${itemSubtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-end font-12 text-success">${itemClaims > 0 ? '-₦' + itemClaims.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-'}</td>
                        <td class="text-end font-12 fw-bold text-primary">₦${itemPayable.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-center font-12">
                            <button class="btn btn-xs btn-outline-danger walkin-remove-cart-item" data-index="${idx}" title="Remove">
                                <i class="mdi mdi-close"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            $('#walkin-subtotal').text(`₦${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);

            if (totalClaims > 0) {
                $('#walkin-hmo-row').show();
                $('#walkin-hmo-name').text(hmoName);
                $('#walkin-hmo-amount').text(`-₦${totalClaims.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            } else {
                $('#walkin-hmo-row').hide();
            }

            $('#walkin-cart-total').text(`₦${totalPayable.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            $('#btn-submit-walkin').prop('disabled', false);
        },

        submitCart: function() {
            const patientId = this.getCurrentPatientId();
            if (!patientId) {
                if (typeof toastr !== 'undefined') toastr.warning('Please select a patient first');
                return;
            }

            if (walkinCart.length === 0) {
                if (typeof toastr !== 'undefined') toastr.warning('Cart is empty');
                return;
            }

            const $btn = $('#btn-submit-walkin');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing Walk-in...');

            const self = this;
            const prefix = this.getBasePrefix();

            $.ajax({
                url: `${prefix}/book-walkin`,
                method: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                    patient_id: patientId,
                    items: walkinCart
                },
                success: function(response) {
                    $btn.prop('disabled', false).html(originalHtml);

                    if (response.success) {
                        if (typeof toastr !== 'undefined') toastr.success(response.message || 'Walk-in request created successfully');
                        walkinCart = [];
                        self.updateCartUI();
                        self.loadRecentRequests();

                        // Switch to recent tab
                        $('#walkin-cart-tabs a[href="#walkin-recent-pane"]').tab('show');

                        // If user has billing permissions and items are payable, open Checkout Modal!
                        const canBill = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.canBill) ||
                                        ($('#paymentModal').length > 0);

                        if (canBill && response.requests && response.requests.length > 0) {
                            const checkoutItems = [];
                            let actualTotal = 0;

                            response.requests.forEach(function(r) {
                                const payable = parseFloat(r.payable_amount || 0);
                                if (r.service_request_id && payable > 0) {
                                    checkoutItems.push({
                                        id: r.service_request_id,
                                        qty: 1,
                                        discount: 0
                                    });
                                    actualTotal += payable;
                                }
                            });

                            if (checkoutItems.length > 0) {
                                self.launchPaymentModal(checkoutItems, actualTotal, patientId);
                            }
                        }

                        // Trigger callbacks
                        if (typeof loadBillingItems === 'function') loadBillingItems();
                        if (typeof loadQueueCounts === 'function') loadQueueCounts();
                    } else {
                        if (typeof toastr !== 'undefined') toastr.error(response.message || 'Failed to create walk-in requests');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(originalHtml);
                    const msg = xhr.responseJSON?.message || 'Server error while processing walk-in request';
                    if (typeof toastr !== 'undefined') toastr.error(msg);
                }
            });
        },

        updateWalkinSelectedTotal: function() {
            let sum = 0;
            let count = 0;
            $('.walkin-unpaid-item-checkbox:checked').each(function() {
                sum += parseFloat($(this).data('payable')) || 0;
                count++;
            });

            $('#walkin-selected-total').text('₦' + sum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            const $btn = $('#btn-pay-selected-walkin');
            if (count > 0 && sum > 0) {
                $btn.prop('disabled', false);
            } else {
                $btn.prop('disabled', true);
            }
        },

        paySelectedRecentRequests: function() {
            const patientId = this.getCurrentPatientId();
            if (!patientId) {
                if (typeof toastr !== 'undefined') toastr.warning('Please select a patient first');
                return;
            }

            let checkoutItems = [];
            let totalPayable = 0;

            $('.walkin-unpaid-item-checkbox:checked').each(function() {
                const sId = $(this).data('service-request-id');
                const payable = parseFloat($(this).data('payable')) || 0;
                if (sId) {
                    checkoutItems.push({
                        id: sId,
                        qty: 1,
                        discount: 0
                    });
                    totalPayable += payable;
                }
            });

            if (checkoutItems.length === 0) {
                if (typeof toastr !== 'undefined') toastr.warning('Please select at least one unpaid item');
                return;
            }

            this.launchPaymentModal(checkoutItems, totalPayable, patientId);
        },

        paySingleRecentRequest: function(serviceRequestId, payable) {
            const patientId = this.getCurrentPatientId();
            if (!patientId || !serviceRequestId) return;

            const checkoutItems = [{
                id: serviceRequestId,
                qty: 1,
                discount: 0
            }];

            this.launchPaymentModal(checkoutItems, payable, patientId);
        },

        showModal: function(modalSelector) {
            const selector = modalSelector.startsWith('#') ? modalSelector : `#${modalSelector}`;
            const $modal = $(selector);
            if ($modal.length && typeof $modal.modal === 'function') {
                $modal.modal('show');
                return;
            }
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                try {
                    const el = document.querySelector(selector);
                    if (el) {
                        if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
                            bootstrap.Modal.getOrCreateInstance(el).show();
                        } else if (typeof bootstrap.Modal === 'function') {
                            new bootstrap.Modal(el).show();
                        }
                    }
                } catch (e) {
                    console.warn('Bootstrap modal show warning:', e);
                }
            }
        },

        hideModal: function(modalSelector) {
            const selector = modalSelector.startsWith('#') ? modalSelector : `#${modalSelector}`;
            const $modal = $(selector);
            if ($modal.length && typeof $modal.modal === 'function') {
                $modal.modal('hide');
                return;
            }
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                try {
                    const el = document.querySelector(selector);
                    if (el && typeof bootstrap.Modal.getInstance === 'function') {
                        const inst = bootstrap.Modal.getInstance(el);
                        if (inst) inst.hide();
                    }
                } catch (e) {
                    console.warn('Bootstrap modal hide warning:', e);
                }
            }
        },

        launchPaymentModal: function(items, totalAmount, patientId) {
            window.pendingPaymentItems = items;
            window.pendingPaymentPatientId = patientId;

            const formatted = `₦${parseFloat(totalAmount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            $('#summary-subtotal').text(formatted);
            $('#summary-discount').text('₦0.00');
            $('#summary-total').text(formatted);
            $('#modal-item-count').text(items.length);

            // Split payments initial legs
            if ($('#payment-method').val() === 'SPLIT' && window.CoreHealth && window.CoreHealth.SplitPayments) {
                window.CoreHealth.SplitPayments.ensureInitialLegs();
            }

            this.showModal('#paymentModal');
        },

        openDiscardModal: function(type, id, name, requestNo) {
            this.currentDiscard = {
                type: type,
                id: id
            };

            $('#discard_service_name').text(name || 'Unknown Item');
            $('#discard_request_no').text(requestNo || id);
            $('#discard_reason').val('Erroneous booking / duplicate request');

            if ($('#discardRequestModal').length > 0) {
                this.showModal('#discardRequestModal');
            } else {
                // Fallback prompt if modal element is not in DOM
                const self = this;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Discard Request',
                        text: `Are you sure you want to discard "${name}"?`,
                        input: 'textarea',
                        inputPlaceholder: 'Reason for discarding (min 10 characters)...',
                        inputValue: 'Erroneous booking / duplicate request',
                        showCancelButton: true,
                        confirmButtonText: 'Discard',
                        confirmButtonColor: '#d33',
                        preConfirm: (reason) => {
                            if (!reason || reason.trim().length < 10) {
                                Swal.showValidationMessage('Reason must be at least 10 characters');
                                return false;
                            }
                            return reason;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            self.executeDiscard(type, id, result.value);
                        }
                    });
                }
            }
        },

        submitDiscardRequest: function() {
            if (!this.currentDiscard || !this.currentDiscard.type || !this.currentDiscard.id) {
                return;
            }

            const reason = $('#discard_reason').val();
            if (!reason || reason.trim().length < 10) {
                if (typeof toastr !== 'undefined') toastr.warning('Please provide a reason (minimum 10 characters)');
                return;
            }

            const $btn = $('#confirmDiscardBtn');
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Discarding...');

            const self = this;
            this.executeDiscard(this.currentDiscard.type, this.currentDiscard.id, reason, function() {
                self.hideModal('#discardRequestModal');
                $btn.prop('disabled', false).html('<i class="mdi mdi-delete"></i> Discard Request');
            });
        },

        executeDiscard: function(type, id, reason, callback) {
            const self = this;
            const prefix = this.getBasePrefix();
            const url = `${prefix}/request/${type}/${id}/discard`;

            $.ajax({
                url: url,
                method: 'DELETE',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                    reason: reason
                },
                success: function(response) {
                    if (callback) callback();
                    if (typeof toastr !== 'undefined') toastr.success(response.message || 'Request discarded successfully');

                    // Refresh recent requests list
                    self.loadRecentRequests();

                    // Refresh workbench components
                    if (typeof loadBillingItems === 'function') loadBillingItems();
                    if (typeof loadQueueCounts === 'function') loadQueueCounts();
                    const pid = self.getCurrentPatientId();
                    if (pid && typeof loadPatientQueueEntries === 'function') {
                        loadPatientQueueEntries(pid);
                    }
                    if (typeof updateQueueCounts === 'function') updateQueueCounts();
                    if (typeof reloadServiceRequestsData === 'function') reloadServiceRequestsData();
                },
                error: function(xhr) {
                    if (callback) callback();
                    const msg = xhr.responseJSON?.message || 'Failed to discard request';
                    if (typeof toastr !== 'undefined') toastr.error(msg);
                }
            });
        },

        loadRecentRequests: function() {
            const patientId = this.getCurrentPatientId();
            const $container = $('#recent-requests-container');
            if (!$container.length) return;

            if (!patientId) {
                $container.html(`
                    <div class="text-center text-muted py-4">
                        <i class="mdi mdi-account-search-outline font-24"></i>
                        <p class="mb-0 mt-2 font-12">Please select a patient first</p>
                    </div>
                `);
                return;
            }

            $container.html('<div class="text-center text-muted py-3"><i class="mdi mdi-loading mdi-spin font-20"></i><p class="mb-0 mt-1 font-11">Loading recent requests...</p></div>');

            const prefix = this.getBasePrefix();
            const self = this;

            $.ajax({
                url: `${prefix}/patient/${patientId}/recent-requests`,
                method: 'GET',
                success: function(response) {
                    if (response.success && response.requests && response.requests.length > 0) {
                        const canBill = (window.WORKBENCH_CONFIG && (window.WORKBENCH_CONFIG.canBill || window.WORKBENCH_CONFIG.canProcessPayment))
                            || ($('#paymentModal').length > 0)
                            || ($('#btn-submit-walkin').text().includes('Checkout') || $('#btn-submit-walkin').text().includes('Pay'));

                        const unpaidRequests = response.requests.filter(req => !req.is_paid && req.payable > 0 && req.service_request_id);

                        let html = '';

                        // Multi-select toolbar for unpaid requests
                        if (canBill && unpaidRequests.length > 0) {
                            html += `
                                <div class="recent-requests-billing-bar">
                                    <div class="d-flex align-items-center">
                                        <input type="checkbox" class="walkin-select-all-unpaid" id="walkin-select-all-unpaid" style="cursor:pointer; width:16px; height:16px; margin:0 6px 0 0;">
                                        <label class="mb-0 fw-bold font-12" for="walkin-select-all-unpaid" style="cursor:pointer;">
                                            Select All Unpaid (<span id="walkin-unpaid-count">${unpaidRequests.length}</span>)
                                        </label>
                                    </div>
                                    <button class="btn btn-sm btn-success btn-pay-selected" id="btn-pay-selected-walkin" disabled>
                                        <i class="mdi mdi-cash-register"></i> Pay Selected (<span id="walkin-selected-total">₦0.00</span>)
                                    </button>
                                </div>
                            `;
                        }

                        response.requests.forEach(req => {
                            const typeClass = self.getTypeClass(req.type);
                            const billingClass = self.getBillingStatusClass(req.billing_status);
                            const deliveryClass = self.getDeliveryStatusClass(req.delivery_status);
                            const coverageBadge = req.coverage_mode ? `<span class="badge bg-success-subtle text-success border border-success-subtle ms-1 font-10">${self.escapeHtml(req.coverage_mode)}</span>` : '';
                            const deptBadge = req.department_name ? `<span class="badge bg-light text-dark border ms-1 font-10" title="Benefiting Department"><i class="mdi mdi-office-building"></i> ${self.escapeHtml(req.department_name)}</span>` : '';
                            const createdAt = req.created_at ? new Date(req.created_at).toLocaleString('en-GB', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'}) : '';
                            const isUnpaid = !req.is_paid && req.payable > 0 && req.service_request_id;
                            const itemStateClass = req.is_paid ? 'is-paid' : (isUnpaid ? 'is-unpaid' : '');

                            // Billable indicator for free_form items
                            let billableBadge = '';
                            if (req.type === 'free_form') {
                                if (req.is_billable) {
                                    billableBadge = '<span class="badge bg-info text-white font-10 ms-1">Billable</span>';
                                } else {
                                    billableBadge = '<span class="badge bg-secondary text-white font-10 ms-1">Non-Billable</span>';
                                }
                            }

                            const checkboxHtml = (canBill && isUnpaid) ? `
                                <input type="checkbox" class="walkin-unpaid-item-checkbox" 
                                       data-service-request-id="${req.service_request_id}" 
                                       data-payable="${req.payable}" 
                                       data-name="${self.escapeHtml(req.name)}">
                            ` : '';

                            const payBtnHtml = (canBill && isUnpaid) ? `
                                <button class="btn btn-sm btn-outline-success btn-pay-single-walkin" 
                                       data-service-request-id="${req.service_request_id}" 
                                       data-payable="${req.payable}" 
                                       title="Pay Now">
                                    <i class="mdi mdi-cash"></i> Pay
                                </button>
                            ` : '';

                            const deleteBtnHtml = !req.is_paid ? `
                                <button class="btn btn-sm btn-outline-danger btn-discard-recent-request" 
                                        data-type="${req.type}" 
                                        data-id="${req.id}" 
                                        data-name="${self.escapeHtml(req.name)}" 
                                        data-request-no="${req.id}" 
                                        title="Delete / Discard Request">
                                    <i class="mdi mdi-trash-can-outline"></i>
                                </button>
                            ` : '';

                            const priceFormatted = parseFloat(req.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const payableFormatted = parseFloat(req.payable || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const hmoCoversFormatted = req.hmo_covers > 0 ? parseFloat(req.hmo_covers).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : null;

                            html += `
                                <div class="recent-request-item ${itemStateClass}">
                                    <div class="recent-request-header d-flex align-items-center justify-content-between mb-1">
                                        <div class="d-flex align-items-center flex-grow-1 overflow-hidden me-2">
                                            ${checkboxHtml}
                                            <span class="recent-request-name text-truncate" title="${self.escapeHtml(req.name)}">${self.escapeHtml(req.name)}</span>
                                            <span class="badge bg-${typeClass} text-white recent-request-type ms-1 flex-shrink-0">${self.escapeHtml(req.type_label || req.type)}</span>
                                            ${billableBadge}
                                            ${coverageBadge}
                                            ${deptBadge}
                                        </div>
                                        <small class="text-muted font-11 flex-shrink-0">${createdAt}</small>
                                    </div>
                                    <div class="recent-request-details d-flex align-items-center justify-content-between pt-1 border-top">
                                        <div class="recent-request-pricing d-flex align-items-center flex-wrap gap-2">
                                            <span class="font-11 text-muted">Price: <strong class="text-dark">₦${priceFormatted}</strong></span>
                                            ${hmoCoversFormatted ? `<span class="font-11 text-success">HMO: -₦${hmoCoversFormatted}</span>` : ''}
                                            <span class="font-11 text-muted">Pay: <strong class="${req.payable > 0 ? 'text-primary' : 'text-muted'}">₦${payableFormatted}</strong></span>
                                            <span class="font-11 text-muted">Dept: <strong class="text-dark">${self.escapeHtml(req.department_name || 'General')}</strong></span>
                                        </div>
                                        <div class="recent-request-status d-flex align-items-center gap-1 flex-shrink-0">
                                            <span class="badge ${billingClass} font-10">${self.escapeHtml(req.billing_status || 'Pending')}</span>
                                            <span class="badge ${deliveryClass} font-10">${self.escapeHtml(req.delivery_status || 'Pending')}</span>
                                            ${payBtnHtml}
                                            ${deleteBtnHtml}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        $container.html(html);
                    } else {
                        $container.html(`
                            <div class="text-center text-muted py-4">
                                <i class="mdi mdi-clock-outline font-24"></i>
                                <p class="mb-0 mt-2 font-12">No recent requests in the last 24 hours</p>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $container.html(`
                        <div class="text-center text-muted py-4">
                            <i class="mdi mdi-alert-circle font-24 text-danger"></i>
                            <p class="mb-0 mt-2 font-12 text-danger">Failed to load recent requests</p>
                        </div>
                    `);
                }
            });
        },

        getTypeClass: function(type) {
            const classes = {
                'lab': 'info',
                'imaging': 'warning',
                'product': 'success',
                'procedure': 'secondary',
                'free_form': 'primary',
                'consultation': 'primary'
            };
            return classes[(type || '').toLowerCase()] || 'secondary';
        },

        getBillingStatusClass: function(status) {
            if (!status) return 'bg-warning-subtle text-warning border border-warning-subtle';
            const s = status.toLowerCase();
            if (s.includes('paid')) return 'bg-success text-white';
            if (s.includes('billed')) return 'bg-info text-white';
            return 'bg-warning-subtle text-warning border border-warning-subtle';
        },

        getDeliveryStatusClass: function(status) {
            if (!status) return 'bg-light text-muted border';
            const s = status.toLowerCase();
            if (s.includes('completed') || s.includes('dispensed')) return 'bg-success-subtle text-success border border-success-subtle';
            if (s.includes('progress') || s.includes('sample') || s.includes('awaiting')) return 'bg-info-subtle text-info border border-info-subtle';
            return 'bg-light text-muted border';
        },

        escapeHtml: function(str) {
            if (!str) return '';
            return $('<div>').text(str).html();
        }
    };

    window.CoreHealth.WalkinSales = WalkinSales;

    // Backward compatibility bridges
    window.walkinCart = walkinCart;
    window.searchWalkinServices = function(q, t) { WalkinSales.searchCatalog(q, t); };
    window.addToWalkinCart = function(id, t, n, p) { WalkinSales.addItem(id, t, n, p); };
    window.removeFromWalkinCart = function(idx) { WalkinSales.removeItem(idx); };
    window.updateWalkinCartUI = function() { WalkinSales.updateCartUI(); };
    window.submitWalkinServices = function() { WalkinSales.submitCart(); };
    window.loadRecentRequests = function() { WalkinSales.loadRecentRequests(); };

    $(document).ready(function() {
        WalkinSales.init();
    });

})(window, jQuery);
