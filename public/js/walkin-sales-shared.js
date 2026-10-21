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
            return window.currentPatient ||
                   (window.currentPatientData ? window.currentPatientData.id : null);
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
                self.addItem(id, type, name, price);
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

                $container.append(`
                    <div class="walkin-service-item d-flex justify-content-between align-items-center p-2 border-bottom">
                        <div>
                            <span class="badge bg-${typeMeta.badge} text-white font-11">${typeMeta.label}</span>
                            ${codeBadge}
                            <span class="ms-2 fw-bold font-13">${self.escapeHtml(item.name || item.product_name || item.service_name)}</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-3 fw-bold font-13">₦${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            <button class="btn btn-sm btn-primary walkin-add-catalog-item"
                                    data-id="${item.id}"
                                    data-type="${type}"
                                    data-name="${self.escapeHtml(item.name || item.product_name || item.service_name)}"
                                    data-price="${price}">
                                <i class="mdi mdi-plus"></i> Add
                            </button>
                        </div>
                    </div>
                `);
            });
        },

        addItem: function(id, type, name, price) {
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
                        base_price: parseFloat(data.base_price || price),
                        payable_amount: parseFloat(data.payable_amount !== undefined ? data.payable_amount : price),
                        claims_amount: parseFloat(data.claims_amount || 0),
                        coverage_mode: data.coverage_mode || null,
                        hmo_name: data.hmo_name || 'Self Pay',
                        quantity: 1,
                        is_billable: 1
                    });
                    self.updateCartUI();
                    if (typeof toastr !== 'undefined') toastr.success(`Added "${name}" to cart`);
                },
                error: function() {
                    walkinCart.push({
                        id: id,
                        type: type,
                        name: name,
                        base_price: parseFloat(price),
                        payable_amount: parseFloat(price),
                        claims_amount: 0,
                        coverage_mode: null,
                        hmo_name: 'Self Pay',
                        quantity: 1,
                        is_billable: 1
                    });
                    self.updateCartUI();
                }
            });
        },

        handleFreeFormSubmit: function() {
            const patientId = this.getCurrentPatientId();
            if (!patientId) {
                if (typeof toastr !== 'undefined') toastr.warning('Please select a patient first');
                return;
            }

            const name = $('#walkin-freeform-name').val().trim();
            const desc = $('#walkin-freeform-desc').val().trim();
            const price = parseFloat($('#walkin-freeform-price').val()) || 0;
            const qty = parseInt($('#walkin-freeform-qty').val(), 10) || 1;
            const isBillable = $('#walkin-freeform-billable').is(':checked') ? 1 : 0;

            if (!name) {
                if (typeof toastr !== 'undefined') toastr.warning('Item name is required');
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
                description: desc,
                base_price: price,
                payable_amount: payable,
                claims_amount: 0,
                coverage_mode: null,
                hmo_name: 'Self Pay',
                quantity: qty,
                is_billable: isBillable
            });

            this.updateCartUI();

            // Reset form
            $('#walkin-freeform-form')[0].reset();
            $('#walkin-freeform-billable').prop('checked', true);

            // Switch to cart pane
            $('#walkin-cart-tabs a[href="#walkin-cart-pane"]').tab('show');

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
                const unbillableBadge = !item.is_billable ? '<span class="badge bg-warning text-dark font-10 ms-1">Non-billable</span>' : '';

                $body.append(`
                    <tr>
                        <td class="font-12">
                            <div class="fw-bold">${self.escapeHtml(item.name)} ${freeFormBadge} ${unbillableBadge}</div>
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
                                window.pendingPaymentItems = checkoutItems;
                                window.pendingPaymentPatientId = patientId;

                                $('#summary-subtotal').text(`₦${actualTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
                                $('#summary-discount').text('₦0.00');
                                $('#summary-total').text(`₦${actualTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
                                $('#modal-item-count').text(checkoutItems.length);

                                // Trigger split recalculation if split active
                                if ($('#payment-method').val() === 'SPLIT' && window.CoreHealth.SplitPayments) {
                                    window.CoreHealth.SplitPayments.ensureInitialLegs();
                                }

                                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                    const modalElem = document.getElementById('paymentModal');
                                    if (modalElem) {
                                        const modalInstance = bootstrap.Modal.getInstance(modalElem) || new bootstrap.Modal(modalElem);
                                        modalInstance.show();
                                    }
                                } else {
                                    $('#paymentModal').modal('show');
                                }
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

        loadRecentRequests: function() {
            const patientId = this.getCurrentPatientId();
            if (!patientId) return;

            const $container = $('#recent-requests-container');
            $container.html('<div class="text-center text-muted py-3"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div>');

            const prefix = this.getBasePrefix();
            const self = this;

            $.ajax({
                url: `${prefix}/patient/${patientId}/visits`,
                method: 'GET',
                success: function(data) {
                    // Render recent walk-in requests if available
                    $container.html('<div class="text-center text-success py-3"><i class="mdi mdi-check-circle-outline"></i> Requests logged</div>');
                },
                error: function() {
                    $container.html('<p class="text-muted text-center py-2 font-11">No recent requests</p>');
                }
            });
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
