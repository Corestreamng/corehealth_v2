if (typeof window.wbUrl !== 'function') {
    window.wbUrl = function(path) {
        var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
        base = base.replace(/\/$/, '');
        var cleanPath = (path || '').replace(/^\//, '');
        return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
    };
}
if (typeof window.wbRoute !== 'function') {
    window.wbRoute = function(name, fallbackPath) {
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) {
            if (window.WORKBENCH_CONFIG.routes[name]) return window.WORKBENCH_CONFIG.routes[name];
            var dotKey = name.replace(/_/g, '.');
            if (window.WORKBENCH_CONFIG.routes[dotKey]) return window.WORKBENCH_CONFIG.routes[dotKey];
            var underscoreKey = name.replace(/\./g, '_');
            if (window.WORKBENCH_CONFIG.routes[underscoreKey]) return window.WORKBENCH_CONFIG.routes[underscoreKey];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

// CoreHealth v2 - Billing Payments & Items Sub-module

    // ========== BILLING WORKBENCH FUNCTIONS ==========

    function loadBillingItems() {
        if (!currentPatient) return;

        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/billing-data`),
            method: 'GET',
            success: function(response) {
                renderBillingItems(response.items);
                updateBillingBadge(response.items.length);
            },
            error: function(xhr) {
                console.error('Failed to load billing items', xhr);
                toastr.error('Failed to load billing items');
            }
        });
    }

    function renderBillingItems(items) {
        console.log('renderBillingItems called with:', items);

        const tbody = $('#billing-items-tbody');
        tbody.empty();

        // Default to 'all' or currently selected tab if any
        let activeFamilyUserId = $('#billing-family-tabs .workspace-tab.active').data('user-id') || 'all';

        if (items.length === 0) {
            tbody.html(`
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                    <p>No unpaid items for this patient</p>
                </td>
            </tr>
        `);
            return;
        }

        items.forEach(item => {
            // Use pre-formatted datetime or format it client-side
            const datetime = item.created_at_formatted || (item.created_at ? formatDateTime(item.created_at) : 'N/A');
            let itemName = item.name;
            if (item.treatment_plan_id && item.treatment_plan_name) {
                itemName += ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${item.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(item.treatment_plan_name)}</a>`;
            }

            const row = `
            <tr data-item-id="${item.id}" data-user-id="${item.user_id}">
                <td><input type="checkbox" class="billing-item-checkbox" data-id="${item.id}"></td>
                <td class="text-nowrap" style="font-size: 0.85rem;"><i class="mdi mdi-clock-outline text-muted"></i> ${datetime}</td>
                <td>${itemName}</td>
                <td>${item.category || 'N/A'}</td>
                <td>₦${parseFloat(item.price).toLocaleString()}</td>
                <td><input type="number" class="form-control item-qty-input" value="${item.qty}" min="1" data-id="${item.id}"></td>
                <td><input type="number" class="form-control item-discount-input" value="${item.discount || 0}" min="0" max="100" data-id="${item.id}"></td>
                <td>${item.claims_amount> 0 ? `<span class="hmo-badge">₦${parseFloat(item.claims_amount).toLocaleString()}</span>` : '-'}</td>
                <td class="item-total" data-id="${item.id}">₦${calculateItemTotal(item).toLocaleString()}</td>
            </tr>
        `;
            tbody.append(row);
        });

        // Attach event listeners
        $('.billing-item-checkbox').on('change', updatePaymentSummary);
        $('#select-all-billing-items').on('change', function() {
            const selectVisibleOnly = $('#select-visible-only').is(':checked');
            if (selectVisibleOnly) {
                // Only select/deselect visible (not filtered out) items
                $('.billing-item-checkbox').each(function() {
                    const row = $(this).closest('tr');
                    if (!row.hasClass('filtered-out')) {
                        $(this).prop('checked', $('#select-all-billing-items').is(':checked'));
                    }
                });
            } else {
                // Select all items regardless of filter
                $('.billing-item-checkbox').prop('checked', $(this).is(':checked'));
            }
            updatePaymentSummary();
        });
        $('.item-qty-input, .item-discount-input').on('input', function() {
            const id = $(this).data('id');
            recalculateItemTotal(id);
            updatePaymentSummary();
        });

        // Populate category filter dropdown and update stats
        populateCategoryFilter(items);
        applyBillingFilters();
        updateBillingFilterStats();

        console.log('Rendered', items.length, 'billing items');
    }

    // ========== BILLING FILTER FUNCTIONS ==========

    // Populate category filter dropdown with unique categories from items
    function populateCategoryFilter(items) {
        const select = $('#billing-category-filter');
        const categories = [...new Set(items.map(item => item.category).filter(c => c))].sort();

        select.empty();
        select.append('<option value="">All Categories</option>');
        categories.forEach(cat => {
            select.append(`<option value="${cat}">${cat}</option>`);
        });
    }

    // Apply all billing filters
    function applyBillingFilters() {
        const searchTerm = $('#billing-search-input').val().toLowerCase().trim();
        const categoryFilter = $('#billing-category-filter').val();
        const dateFrom = $('#billing-date-from').val();
        const dateTo = $('#billing-date-to').val();

        $('#billing-items-tbody tr').each(function() {
            const row = $(this);
            if (row.find('td').length < 3) return; // Skip empty rows

            const itemName = row.find('td:eq(2)').text().toLowerCase();
            const itemCategory = row.find('td:eq(3)').text();
            const itemDateText = row.find('td:eq(1)').text().trim();

            let visible = true;
            const activeFamilyUserId = $('#billing-family-tabs .workspace-tab.active').data('user-id') || 'all';

            // Family Tab Filter
            if (activeFamilyUserId !== 'all' && row.data('user-id') != activeFamilyUserId) {
                visible = false;
            }

            // Search filter
            if (searchTerm && !itemName.includes(searchTerm)) {
                visible = false;
            }

            // Category filter
            if (categoryFilter && itemCategory !== categoryFilter) {
                visible = false;
            }

            // Date filters
            if (dateFrom || dateTo) {
                const itemDate = parseDateFromDisplay(itemDateText);
                if (itemDate) {
                    if (dateFrom && itemDate < new Date(dateFrom)) {
                        visible = false;
                    }
                    if (dateTo) {
                        const toDate = new Date(dateTo);
                        toDate.setHours(23, 59, 59, 999);
                        if (itemDate > toDate) {
                            visible = false;
                        }
                    }
                }
            }

            if (visible) {
                row.removeClass('filtered-out');
                // Highlight search match
                if (searchTerm) {
                    row.addClass('highlighted-match');
                } else {
                    row.removeClass('highlighted-match');
                }
            } else {
                row.addClass('filtered-out');
                row.removeClass('highlighted-match');
                // Uncheck filtered out items if select-visible-only is checked
                if ($('#select-visible-only').is(':checked')) {
                    row.find('.billing-item-checkbox').prop('checked', false);
                }
            }
        });

        updateBillingFilterStats();
        updatePaymentSummary();
    }

    // Parse date from display format (DD/MM/YYYY HH:MM)
    function parseDateFromDisplay(dateText) {
        if (!dateText || dateText === 'N/A') return null;
        try {
            // Handle DD/MM/YYYY HH:MM format
            const parts = dateText.split(' ');
            const dateParts = parts[0].split('/');
            if (dateParts.length === 3) {
                return new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            }
            return new Date(dateText);
        } catch (e) {
            return null;
        }
    }

    // Update filter stats display
    function updateBillingFilterStats() {
        const totalRows = $('#billing-items-tbody tr').filter(function() {
            return $(this).find('td').length >= 3;
        }).length;

        const visibleRows = $('#billing-items-tbody tr').filter(function() {
            return $(this).find('td').length >= 3 && !$(this).hasClass('filtered-out');
        }).length;

        let visibleTotal = 0;
        $('#billing-items-tbody tr').each(function() {
            const row = $(this);
            if (row.find('td').length >= 3 && !row.hasClass('filtered-out')) {
                const totalText = row.find('.item-total').text().replace('₦', '').replace(/,/g, '');
                visibleTotal += parseFloat(totalText) || 0;
            }
        });

        $('#billing-items-total').text(totalRows);
        $('#billing-items-visible').text(visibleRows);
        $('#billing-visible-total').text(`₦${visibleTotal.toLocaleString()}`);
    }

    // Clear all billing filters
    function clearBillingFilters() {
        $('#billing-search-input').val('');
        $('#billing-category-filter').val('');
        $('#billing-date-from').val('');
        $('#billing-date-to').val('');
        applyBillingFilters();
    }

    // Initialize filter event listeners
    $(document).on('input', '#billing-search-input', debounce(applyBillingFilters, 300));
    $(document).on('change', '#billing-category-filter', applyBillingFilters);
    $(document).on('change', '#billing-date-from, #billing-date-to', applyBillingFilters);
    $(document).on('click', '#clear-billing-filters', clearBillingFilters);

    // Debounce utility
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Format datetime for display
    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        } catch (e) {
            return dateString;
        }
    }

    function calculateItemTotal(item) {
        const qty = parseFloat(item.qty) || 1;
        const price = parseFloat(item.price) || 0;
        const discount = parseFloat(item.discount) || 0;
        const subtotal = price * qty;
        const discountAmount = subtotal * (discount / 100);
        return subtotal - discountAmount;
    }

    function recalculateItemTotal(itemId) {
        const row = $(`tr[data-item-id="${itemId}"]`);
        const qty = parseFloat(row.find('.item-qty-input').val()) || 1;
        const price = parseFloat(row.find('td:eq(4)').text().replace('₦', '').replace(/,/g, ''));
        const discount = parseFloat(row.find('.item-discount-input').val()) || 0;

        const subtotal = price * qty;
        const discountAmount = subtotal * (discount / 100);
        const total = subtotal - discountAmount;

        row.find('.item-total').text(`₦${total.toLocaleString()}`);
    }

    function updatePaymentSummary() {
        const selectedItems = $('.billing-item-checkbox:checked');

        if (selectedItems.length === 0) {
            // Hide floating cart
            $('#floating-cart').fadeOut(200);
            $('#process-payment-btn').prop('disabled', true);
            $('#print-invoice-btn').prop('disabled', true);
            return;
        }

        let subtotal = 0;
        let totalDiscount = 0;

        selectedItems.each(function() {
            const row = $(this).closest('tr');
            const qty = parseFloat(row.find('.item-qty-input').val()) || 1;
            const price = parseFloat(row.find('td:eq(4)').text().replace('₦', '').replace(/,/g, ''));
            const discountPercent = parseFloat(row.find('.item-discount-input').val()) || 0;

            const itemSubtotal = price * qty;
            const itemDiscount = itemSubtotal * (discountPercent / 100);

            subtotal += itemSubtotal;
            totalDiscount += itemDiscount;
        });

        const total = subtotal - totalDiscount;

        // Update summary in modal
        $('#summary-subtotal').text(`₦${subtotal.toLocaleString()}`);
        $('#summary-discount').text(`₦${totalDiscount.toLocaleString()}`);
        $('#summary-total').text(`₦${total.toLocaleString()}`);

        // Update floating cart
        $('#cart-item-count').text(selectedItems.length);
        $('#modal-item-count').text(selectedItems.length);
        $('#cart-total-display').text(`₦${total.toLocaleString()}`);

        // Show floating cart with pulse animation
        const floatingCart = $('#floating-cart');
        if (!floatingCart.is(':visible')) {
            floatingCart.fadeIn(300);
        }
        // Pulse animation on update
        $('.floating-cart-btn').addClass('pulse');
        setTimeout(() => $('.floating-cart-btn').removeClass('pulse'), 300);

        $('#process-payment-btn').prop('disabled', false);
        $('#print-invoice-btn').prop('disabled', false);
    }

    // Process payment button click (toolbar) - opens the modal
    $(document).on('click', '#process-payment-btn', function() {
        const selectedItems = $('.billing-item-checkbox:checked');
        if (selectedItems.length === 0) {
            toastr.warning('Please select items to process payment');
            return;
        }
        // Auto-select patient default billing preference if set
        if (currentPatientData && currentPatientData.default_billing_mode) {
            $('#payment-method').val(currentPatientData.default_billing_mode).trigger('change');
            if (currentPatientData.default_billing_mode === 'BILL_TO_STAFF') {
                $('#payment-staff-id').val(currentPatientData.default_billing_id).trigger('change');
            } else if (currentPatientData.default_billing_mode === 'BILL_TO_ORGANIZATION') {
                $('#payment-organization-id').val(currentPatientData.default_billing_id).trigger('change');
            }

            // Add a small visual cue
            if ($('#default-billing-cue').length === 0) {
                $('.payment-method-section label').append(' <span id="default-billing-cue" class="badge bg-info text-white ms-2" style="font-size: 0.7rem;">Default</span>');
            }
        } else {
            // Reset to default
            $('#payment-method').val('CASH').trigger('change');
            $('#default-billing-cue').remove();
        }

        // Open the payment modal using Bootstrap 5 API
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
    });

    // Payment scripts are now included via partial at the bottom of the file

    // Print invoice button click handler
    $(document).on('click', '#print-invoice-btn', function() {
        printInvoice();
    });

    function printInvoice() {
        const selectedItems = $('.billing-item-checkbox:checked');

        if (selectedItems.length === 0) {
            toastr.warning('Please select items to print invoice');
            return;
        }

        // Collect selected item IDs
        const itemIds = [];
        selectedItems.each(function() {
            itemIds.push($(this).data('id'));
        });

        // Show loading
        toastr.info('Generating invoice...');

        $.ajax({
            url: wbUrl('/billing-workbench/print-invoice'),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                patient_id: currentPatient,
                item_ids: itemIds
            },
            success: function(response) {
                // Show in modal (reusing receipt modal)
                $('#modal-receipt-a4').html(response.invoice_a4);
                $('#modal-receipt-thermal').html(response.invoice_thermal);

                // Reset tabs to A4
                $('.receipt-modal-tab').removeClass('active');
                $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                $('#modal-receipt-a4').show();
                $('#modal-receipt-thermal').hide();

                // Update modal title temporarily
                $('#receiptPreviewModal .modal-title').text('Invoice Preview - ' + response.invoice_no);

                // Show modal
                $('#receiptPreviewModal').modal('show');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to generate invoice');
            }
        });
    }

    // Receipt Modal Tab Switching
    $(document).on('click', '.receipt-modal-tab', function() {
        const format = $(this).data('format');

        $('.receipt-modal-tab').removeClass('active');
        $(this).addClass('active');

        if (format === 'a4') {
            $('#modal-receipt-a4').show();
            $('#modal-receipt-thermal').hide();
        } else {
            $('#modal-receipt-a4').hide();
            $('#modal-receipt-thermal').show();
        }
    });

    // Modal Print Buttons
    $(document).on('click', '#modal-print-a4', function() {
        printReceiptContent('modal-receipt-a4');
    });

    $(document).on('click', '#modal-print-thermal', function() {
        printReceiptContent('modal-receipt-thermal');
    });

    function printReceiptContent(elementId) {
        const content = $(`#${elementId}`).html();
        if (!content || !content.trim()) return;

        const printWindow = window.open('', '', 'height=600,width=480');
        if (!printWindow) {
            toastr.warning('Please allow popups to print receipt');
            return;
        }

        printWindow.document.open();
        if (content.indexOf('<!DOCTYPE') !== -1 || content.indexOf('<html') !== -1) {
            printWindow.document.write(content);
        } else {
            printWindow.document.write('<!DOCTYPE html><html><head><title>Receipt</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('* { box-sizing: border-box; }');
            printWindow.document.write('@page { margin: 0; size: auto; }');
            printWindow.document.write('html, body { font-family: "Consolas", "Liberation Mono", monospace, Arial, sans-serif; padding: 2mm 3mm; margin: 0; width: 100%; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
            printWindow.document.write('th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }');
            printWindow.document.write('.text-center { text-align: center; }');
            printWindow.document.write('.text-right { text-align: right; }');
            printWindow.document.write('.font-weight-bold { font-weight: bold; }');
            printWindow.document.write('@media print { body { padding: 2mm 3mm !important; margin: 0 !important; width: 100% !important; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
        }
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            setTimeout(() => { printWindow.close(); }, 500);
        }, 250);
    }

    // ==========================================
    // ACCOUNT STATEMENT FUNCTIONALITY
    // ==========================================

    // Store generated statement content
    let generatedStatementA4 = null;
    let generatedStatementThermal = null;

    // Print Statement Button Click
    $(document).on('click', '#print-statement-btn', function() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }

        // Reset the modal to config view
        resetStatementModal();

        // Set default dates (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));

        $('#statement-date-to').val(today.toISOString().split('T')[0]);
        $('#statement-date-from').val(thirtyDaysAgo.toISOString().split('T')[0]);

        // Show modal
        $('#accountStatementModal').modal('show');
    });

    // Reset modal to initial state
    function resetStatementModal() {
        $('#statement-config-panel').show();
        $('#statement-preview-panel').hide();
        $('#statement-modal-footer').hide();
        generatedStatementA4 = null;
        generatedStatementThermal = null;

        // Reset checkboxes to checked
        $('#include-deposits').prop('checked', true);
        $('#include-payments').prop('checked', true);
        $('#include-withdrawals').prop('checked', true);
        $('#include-services').prop('checked', true);
    }

    // Date preset buttons
    $(document).on('click', '.statement-date-presets button', function() {
        const preset = $(this).data('preset');
        const today = new Date();
        let fromDate = new Date();

        switch (preset) {
            case '7days':
                fromDate.setDate(today.getDate() - 7);
                break;
            case '30days':
                fromDate.setDate(today.getDate() - 30);
                break;
            case 'thisMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'lastMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                today.setDate(0); // Last day of previous month
                break;
            case 'thisYear':
                fromDate = new Date(today.getFullYear(), 0, 1);
                break;
            case 'all':
                fromDate = new Date(2000, 0, 1);
                break;
        }

        $('#statement-date-from').val(fromDate.toISOString().split('T')[0]);
        $('#statement-date-to').val(preset === 'lastMonth' ?
            new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0] :
            new Date().toISOString().split('T')[0]);

        // Highlight active preset
        $('.statement-date-presets button').removeClass('btn-secondary').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass('btn-secondary');
    });

    // Generate Statement Button
    $(document).on('click', '#generate-statement-btn', function() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }

        const dateFrom = $('#statement-date-from').val();
        const dateTo = $('#statement-date-to').val();

        if (!dateFrom || !dateTo) {
            toastr.warning('Please select a date range');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Generating...');

        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/generate-statement`),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                date_from: dateFrom,
                date_to: dateTo,
                include_deposits: $('#include-deposits').is(':checked'),
                include_payments: $('#include-payments').is(':checked'),
                include_withdrawals: $('#include-withdrawals').is(':checked'),
                include_services: $('#include-services').is(':checked')
            },
            success: function(response) {
                if (response.success) {
                    // Store generated content
                    generatedStatementA4 = response.statement_a4;
                    generatedStatementThermal = response.statement_thermal;

                    // Update preview panes
                    $('#statement-pane-a4').html(response.statement_a4);
                    $('#statement-pane-thermal').html(response.statement_thermal);

                    // Switch to preview panel
                    $('#statement-config-panel').hide();
                    $('#statement-preview-panel').show();
                    $('#statement-modal-footer').show();

                    // Reset tabs to A4
                    $('.statement-modal-tab').removeClass('active');
                    $('.statement-modal-tab[data-format="a4"]').addClass('active');
                    $('#statement-pane-a4').addClass('active').show();
                    $('#statement-pane-thermal').removeClass('active').hide();

                    toastr.success(`Statement generated with ${response.transaction_count} transactions`);
                } else {
                    toastr.error(response.message || 'Failed to generate statement');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to generate statement');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="mdi mdi-file-document"></i> Generate Statement');
            }
        });
    });

    // Statement Modal Tab Switching
    $(document).on('click', '.statement-modal-tab', function() {
        const format = $(this).data('format');

        // Handle config tab
        if (format === 'config') {
            $('#statement-config-panel').show();
            $('#statement-preview-panel').hide();
            $('#statement-modal-footer').hide();
            return;
        }

        $('.statement-modal-tab').removeClass('active');
        $(this).addClass('active');

        $('.statement-modal-pane').removeClass('active').hide();
        $(`#statement-pane-${format}`).addClass('active').show();
    });

    // Back to Options Button
    $(document).on('click', '#statement-back-btn', function() {
        $('#statement-config-panel').show();
        $('#statement-preview-panel').hide();
        $('#statement-modal-footer').hide();
    });

    // Statement Print Buttons
    $(document).on('click', '#statement-print-a4', function() {
        printStatementContent('statement-pane-a4');
    });

    $(document).on('click', '#statement-print-thermal', function() {
        printStatementContent('statement-pane-thermal');
    });

    function printStatementContent(elementId) {
        const content = $(`#${elementId}`).html();
        if (!content || !content.trim()) return;

        const printWindow = window.open('', '', 'height=700,width=480');
        if (!printWindow) {
            toastr.warning('Please allow popups to print statement');
            return;
        }

        printWindow.document.open();
        if (content.indexOf('<!DOCTYPE') !== -1 || content.indexOf('<html') !== -1) {
            printWindow.document.write(content);
        } else {
            printWindow.document.write('<!DOCTYPE html><html><head><title>Account Statement</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('* { box-sizing: border-box; }');
            printWindow.document.write('@page { margin: 0; size: auto; }');
            printWindow.document.write('html, body { font-family: "Consolas", "Liberation Mono", monospace, Arial, sans-serif; padding: 2mm 3mm; margin: 0; font-size: 11px; width: 100%; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
            printWindow.document.write('th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }');
            printWindow.document.write('th { background: #f5f5f5; font-weight: bold; }');
            printWindow.document.write('.text-center { text-align: center; }');
            printWindow.document.write('.text-right { text-align: right; }');
            printWindow.document.write('.font-weight-bold { font-weight: bold; }');
            printWindow.document.write('.summary-card { display: inline-block; padding: 8px; margin: 4px; border: 1px solid #ddd; border-radius: 4px; }');
            printWindow.document.write('.type-badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }');
            printWindow.document.write('.credit { color: #28a745; }');
            printWindow.document.write('.debit { color: #dc3545; }');
            printWindow.document.write('@media print { body { padding: 2mm 3mm !important; margin: 0 !important; width: 100% !important; } }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(content);
            printWindow.document.write('</body></html>');
        }
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            setTimeout(() => { printWindow.close(); }, 500);
        }, 300);
    }

    // Account Tab
    function loadAccountSummary() {
        if (!currentPatient) return;

        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/account-summary`),
            method: 'GET',
            success: function(response) {
                renderAccountSummary(response);
            },
            error: function(xhr) {
                toastr.error('Failed to load account summary');
            }
        });
    }

    function renderAccountSummary(data) {
        const balance = parseFloat(data.balance);

        // Update hero balance in new UI
        const heroBalance = $('#account-hero-balance');
        heroBalance.removeClass('credit debit');

        $('#hero-balance-amount').text(`₦${balance.toLocaleString()}`);

        if (balance > 0) {
            heroBalance.addClass('credit');
            $('#hero-balance-status').text('Credit Balance');
        } else if (balance < 0) {
            heroBalance.addClass('debit');
            $('#hero-balance-status').text('Debit Balance');
        } else {
            $('#hero-balance-status').text('Balanced');
        }

        // Update pending bills stat
        $('#pending-bills-stat').text(`₦${parseFloat(data.unpaid_total || 0).toLocaleString()}`);

        // Also update the account tab cards with new modern UI
        if (data.account) {
            displayAccountInfo(data.account, data.unpaid_total);
        } else {
            showNoAccountState();
        }
    }

    // My Transactions Modal
    $(document).on('click', '#btn-my-transactions', function() {
        $('#myTransactionsModal').modal('show');
        // Set default dates to today
        const today = new Date().toISOString().split('T')[0];
        $('#my-trans-from-date').val(today);
        $('#my-trans-to-date').val(today);

        // Populate bank dropdown
        populateMyTransactionsBankDropdown();
    });

    function populateMyTransactionsBankDropdown() {
        const $bankSelect = $('#my-trans-bank');
        $bankSelect.find('option:not(:first)').remove();

        if (availableBanks.length > 0) {
            availableBanks.forEach(bank => {
                $bankSelect.append(`<option value="${bank.id}">${bank.name}</option>`);
            });
        }
    }

    $(document).on('click', '#load-my-transactions', function() {
        const fromDate = $('#my-trans-from-date').val();
        const toDate = $('#my-trans-to-date').val();
        const paymentType = $('#my-trans-payment-type').val();
        const bankId = $('#my-trans-bank').val();

        loadMyTransactions(fromDate, toDate, paymentType, bankId);
    });

    // Print My Transactions
    $(document).on('click', '#print-my-transactions', function() {
        const printContent = document.getElementById('my-transactions-modal-body').innerHTML;
        const fromDate = $('#my-trans-from-date').val();
        const toDate = $('#my-trans-to-date').val();

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>My Transactions Report</title>
            <link rel="stylesheet" href="${window.location.origin}/assets/css/bootstrap.min.css">
            <link rel="stylesheet" href="${window.location.origin}/assets/css/style.css">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background: #fff;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #dee2e6;
                }
                .print-header h2 {
                    margin-bottom: 5px;
                    color: #333;
                }
                .date-range {
                    color: #666;
                    margin-bottom: 0;
                    font-size: 0.9rem;
                }
                .print-date {
                    font-size: 0.8rem;
                    color: #888;
                }
                .table {
                    width: 100%;
                    margin-top: 15px;
                }
                .table th {
                    background-color: #f8f9fa;
                    font-weight: 600;
                    border-top: 2px solid #dee2e6;
                }
                .table td, .table th {
                    padding: 0.5rem;
                    font-size: 0.85rem;
                }
                .my-transactions-filter { display: none !important; }
                .summary-section {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .summary-stat-card {
                    display: inline-block;
                    padding: 12px 20px;
                    margin: 5px;
                    background: #fff;
                    border-radius: 8px;
                    text-align: center;
                    min-width: 140px;
                    border: 1px solid #dee2e6;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                .stat-value {
                    font-size: 1.25rem;
                    font-weight: bold;
                    color: #333;
                    display: block;
                }
                .stat-label {
                    font-size: 0.75rem;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .payment-type-breakdown {
                    margin-top: 15px;
                }
                .card {
                    border: 1px solid #dee2e6;
                    box-shadow: none;
                }
                .card-body {
                    padding: 0.75rem;
                }
                .btn { display: none !important; }
                @media print {
                    body {
                        padding: 0;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .no-print { display: none !important; }
                    .summary-stat-card {
                        background: #f8f9fa !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .table th {
                        background-color: #e9ecef !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container-fluid">
                <div class="print-header">
                    <h2>My Transactions Report</h2>
                    <p class="date-range">Period: ${fromDate} to ${toDate}</p>
                    <p class="print-date">Printed on: ${new Date().toLocaleString()}</p>
                </div>
                ${printContent}
            </div>
            <script>
                // Wait for Bootstrap CSS to load before printing
                setTimeout(function() {
                    window.print();
                }, 500);
            <\/script>
        </body>
        </html>
    `);
        printWindow.document.close();
    });

    function loadMyTransactions(fromDate, toDate, paymentType, bankId) {
        $.ajax({
            url: wbUrl('/billing-workbench/my-transactions'),
            method: 'GET',
            data: {
                from: fromDate,
                to: toDate,
                payment_type: paymentType,
                bank_id: bankId
            },
            success: function(response) {
                renderMyTransactions(response.transactions);
                renderMyTransactionsSummary(response.summary);
            },
            error: function(xhr) {
                toastr.error('Failed to load transactions');
            }
        });
    }

    function renderMyTransactions(transactions) {
        const tbody = $('#my-transactions-tbody');
        tbody.empty();

        if (transactions.length === 0) {
            tbody.html(`
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                    <p>No transactions found for the selected period</p>
                </td>
            </tr>
        `);
            return;
        }

        transactions.forEach(tx => {
            const row = `
            <tr>
                <td>${tx.created_at}</td>
                <td>${tx.patient_name}</td>
                <td>${tx.file_no}</td>
                <td>${tx.reference_no || 'N/A'}</td>
                <td>${tx.payment_type}</td>
                <td>${tx.bank_name || '-'}</td>
                <td>₦${parseFloat(tx.total).toLocaleString()}</td>
                <td>₦${parseFloat(tx.total_discount).toLocaleString()}</td>
            </tr>
        `;
            tbody.append(row);
        });
    }

    function renderMyTransactionsSummary(summary) {
        $('#my-total-transactions').text(summary.count);
        $('#my-total-amount').text(`₦${parseFloat(summary.total_amount).toLocaleString()}`);
        $('#my-total-discounts').text(`₦${parseFloat(summary.total_discount).toLocaleString()}`);

        // Render breakdown by payment type
        const breakdown = $('#payment-type-breakdown');
        breakdown.empty();

        if (summary.by_type) {
            let html = '<h6 class="mt-3 mb-2">Breakdown by Payment Type</h6><div class="row">';
            Object.keys(summary.by_type).forEach(type => {
                const data = summary.by_type[type];
                html += `
                <div class="col-md-3 mb-2">
                    <div style="padding: 1rem; background: white; border-radius: 0.5rem; border: 1px solid #dee2e6;">
                        <strong>${type}</strong><br>
                        <small>${data.count} transactions</small><br>
                        <span style="font-size: 1.1rem; color: var(--hospital-primary);">₦${parseFloat(data.amount).toLocaleString()}</span>
                    </div>
                </div>
            `;
            });
            html += '</div>';
            breakdown.html(html);
        }

        $('#my-transactions-summary').show();
    }

    function updateBillingBadge(count) {
        $('#billing-badge').text(count);
    }

    // Old lab-specific functions removed, keeping legacy compatibility stubs

    function recordBilling(requestIds) {
        console.warn('Legacy function called - no longer applicable in billing workbench');
    }

    function collectSample(requestIds) {
        console.warn('Legacy function called - no longer applicable in billing workbench');
    }

    function dismissRequests(requestIds, section) {
        console.warn('Legacy function called - no longer applicable in billing workbench');
    }


    // Create vital tooltip element
    function createVitalTooltip() {
        vitalTooltip = $('<div class="vital-tooltip"></div>').appendTo('body');

        // Hide on mouse leave
        $(document).on('mouseleave', '.vital-item', function() {
            vitalTooltip.removeClass('active');
        });
    }

    // Show vital tooltip with details
    function showVitalTooltip(event, vitalType, value, normalRange) {
        const tooltip = vitalTooltip;
        const $target = $(event.currentTarget);
        const offset = $target.offset();

        let deviation = '';
        let status = 'Normal';

        // Calculate deviation based on vital type
        if (vitalType === 'temperature' && value !== 'N/A') {
            const temp = parseFloat(value);
            const idealTemp = 37.0;
            const diff = Math.abs(temp - idealTemp);
            deviation = temp > idealTemp ? `+${diff.toFixed(1)}°C above ideal` : `-${diff.toFixed(1)}°C below ideal`;
            status = (temp >= 36.1 && temp <= 38.0) ? 'Normal' : 'Abnormal';
        } else if (vitalType === 'pulse' && value !== 'N/A') {
            const pulse = parseInt(value);
            const idealPulse = 80;
            const diff = Math.abs(pulse - idealPulse);
            deviation = pulse > idealPulse ? `+${diff} bpm above ideal` : `-${diff} bpm below ideal`;
            status = (pulse >= 60 && pulse <= 100) ? 'Normal' : 'Abnormal';
        } else if (vitalType === 'bp' && value !== 'N/A' && value.includes('/')) {
            const [sys, dia] = value.split('/').map(v => parseInt(v));
            status = (sys >= 90 && sys <= 140 && dia >= 60 && dia <= 90) ? 'Normal' : 'Abnormal';
            deviation = sys > 140 ? 'High BP' : sys < 90 ? 'Low BP' : 'Optimal';
        }

        const content = `
        <div style="font-weight: 600; margin-bottom: 0.5rem;">${vitalType.toUpperCase()}</div>
        <div><strong>Value:</strong> ${value}</div>
        <div><strong>Normal Range:</strong> ${normalRange}</div>
        <div><strong>Status:</strong> <span style="color: ${status === 'Normal' ? '#28a745' : '#dc3545'}">${status}</span></div>
        ${deviation ? `<div><strong>Deviation:</strong> ${deviation}</div>` : ''}
    `;

        tooltip.html(content);
        tooltip.css({
            top: offset.top - tooltip.outerHeight() - 10,
            left: offset.left + ($target.outerWidth() / 2) - (tooltip.outerWidth() / 2)
        });
        tooltip.addClass('active');
    }

    // Check for drug allergies
    function checkForAllergies(medications, patientAllergies) {
        if (!patientAllergies) {
            return [];
        }

        // Normalize allergies to array format
        let allergiesArray = [];

        if (typeof patientAllergies === 'string') {
            // Handle comma-separated string
            allergiesArray = patientAllergies.split(',').map(a => a.trim()).filter(a => a.length > 0);
        } else if (Array.isArray(patientAllergies)) {
            // Handle array (could be array of strings or array of objects)
            allergiesArray = patientAllergies.map(a => {
                if (typeof a === 'string') return a.trim();
                if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
                return '';
            }).filter(a => a.length > 0);
        } else if (typeof patientAllergies === 'object' && patientAllergies !== null) {
            // Handle single object or object with values
            if (patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen) {
                allergiesArray = [(patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen).trim()];
            } else {
                // Try to extract values from object
                allergiesArray = Object.values(patientAllergies).map(a => {
                    if (typeof a === 'string') return a.trim();
                    if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
                    return '';
                }).filter(a => a.length > 0);
            }
        }

        if (allergiesArray.length === 0) {
            return [];
        }

        const alerts = [];
        medications.forEach(med => {
            const drugName = (med.drug_name || med.product_name || '').toLowerCase();
            allergiesArray.forEach(allergy => {
                if (drugName.includes(allergy.toLowerCase())) {
                    alerts.push({
                        medication: med.drug_name || med.product_name,
                        allergy: allergy
                    });
                }
            });
        });

        return alerts;
    }

    // Display allergy alert banner
    function displayAllergyAlert(alerts) {
        if (alerts.length === 0) return '';

        const allergyList = alerts.map(alert =>
            `<strong>${alert.medication}</strong> (Allergic to: ${alert.allergy})`
        ).join('<br>');

        return `
        <div class="allergy-alert">
            <div class="allergy-alert-icon">⚠️</div>
            <div>
                <strong>ALLERGY WARNING!</strong><br>
                ${allergyList}
            </div>
        </div>
    `;
    }

    // Animate refresh button
    function animateRefresh(buttonElement) {
        const $btn = $(buttonElement);
        $btn.addClass('refreshing');
        setTimeout(() => $btn.removeClass('refreshing'), 600);
    }

    // Attach functions to window for global access across sub-modules
    window.renderBillingItems = renderBillingItems;
    window.loadBillingItems = loadBillingItems;
    window.updatePaymentSummary = updatePaymentSummary;
    window.createVitalTooltip = createVitalTooltip;
    window.showVitalTooltip = showVitalTooltip;
    window.calculateItemTotal = calculateItemTotal;
    window.recalculateItemTotal = recalculateItemTotal;
    window.populateCategoryFilter = populateCategoryFilter;
    window.applyBillingFilters = applyBillingFilters;
    window.updateBillingBadge = updateBillingBadge;
    window.printInvoice = printInvoice;
    window.printReceiptContent = printReceiptContent;
    window.printStatementContent = printStatementContent;
    window.resetStatementModal = resetStatementModal;
    window.loadAccountSummary = loadAccountSummary;
    window.renderAccountSummary = renderAccountSummary;
    window.populateMyTransactionsBankDropdown = populateMyTransactionsBankDropdown;
    window.loadMyTransactions = loadMyTransactions;
    window.renderMyTransactions = renderMyTransactions;
    window.renderMyTransactionsSummary = renderMyTransactionsSummary;
    window.checkForAllergies = checkForAllergies;
    window.displayAllergyAlert = displayAllergyAlert;
    window.animateRefresh = animateRefresh;


