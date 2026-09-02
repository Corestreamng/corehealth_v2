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

// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let vitalTooltip = null;

// Utility function to format money
function formatMoney(amount) {
    const num = parseFloat(amount || 0);
    return `₦${num.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

$(document).ready(function() {
    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadUserPreferences();
    createVitalTooltip();
    loadBanks(); // Load available banks for payment

    // Auto-select patient from URL query parameter (e.g., from Patient list workbench button)
    const urlParams = new URLSearchParams(window.location.search);
    const patientId = urlParams.get('patient_id');
    if (patientId) {
        loadPatient(patientId);
    }

    // Auto-open queue from URL parameter (e.g., from dashboard queue widget click)
    const queueFilter = urlParams.get('queue_filter');
    if (queueFilter && ['all', 'unbilled', 'billed', 'hmo'].includes(queueFilter)) {
        setTimeout(function() { showQueue(queueFilter); }, 500);
    }
});

function initializeEventListeners() {
    // Generate initial reference number
    generateReferenceNumber();

    // Patient search (shared module)
    PatientSearch.init();

    // Workspace tabs
    $('.workspace-tab').on('click', function() {
        const tab = $(this).data('tab');
        switchWorkspaceTab(tab);
    });

    // Pending sub-tabs
    $('.pending-subtab').on('click', function() {
        const status = $(this).data('status');
        $('.pending-subtab').removeClass('active');
        $(this).addClass('active');
        renderPendingSubtabContent(status);
    });

    // Prescription sub-tabs (Billing/Pending/Dispense/History) - refresh on tab switch
    $(document).on('shown.bs.tab', '#prescSubTabs button[data-bs-toggle="tab"]', function(e) {
        const targetPane = $(e.target).data('bs-target');
        console.log('Prescription subtab switched to:', targetPane);
        refreshPrescSubtab(targetPane);

        // Hide all sticky bars when switching tabs
        hideAllStickyBars();

        // Show appropriate bar if there are selections in the new tab
        if (targetPane === '#presc-billing-pane') {
            updateStickyActionBar('billing');
        } else if (targetPane === '#presc-pending-pane') {
            updateStickyActionBar('pending');
        } else if (targetPane === '#presc-dispense-pane') {
            updateStickyActionBar('dispense');
        }
    });

    // Clear dispense selection when cart modal closes
    $('#dispenseCartModal').on('hidden.bs.modal', function() {
        console.log('Dispense cart modal closed - clearing selection');
        clearSelection('dispense');
    });

    // Navigation buttons
    $('#btn-back-to-search').on('click', function() {
        // Mobile: go back to search pane
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    });

    $('#btn-view-work-pane').on('click', function() {
        // Mobile: switch to work pane without selecting a patient
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    });

    $('#btn-toggle-search').on('click', function() {
        // Desktop/Tablet: toggle search pane visibility
        $('#left-panel').toggleClass('hidden');
    });

    // Clinical context button
    $('#btn-clinical-context').on('click', function() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }
        // Open clinical context modal with shared module
        ClinicalContext.load(currentPatient);
    });

    // Clinical modal refresh buttons
    $('.refresh-clinical-btn').on('click', function() {
        const panel = $(this).data('panel');
        refreshClinicalPanel(panel);
    });

    // Queue filter buttons
    $('.queue-item').on('click', function() {
        const filter = $(this).data('filter');
        showQueue(filter);
    });

    // Show all queue button
    $('#show-all-queue-btn, #view-queue-btn').on('click', function() {
        showQueue('all');
    });

    // Close queue button
    $('#btn-close-queue').on('click', function() {
        hideQueue();
    });

    // Refresh billing items button
    $('#refresh-billing-items').on('click', function() {
        if (currentPatient) {
            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Refreshing...');

            loadPatient(currentPatient);

            // Re-enable button after a short delay
            setTimeout(() => {
                $btn.prop('disabled', false).html(originalHtml);
            }, 1000);
        }
    });

    // Payment method change handler
    $('#payment-method').on('change', function() {
        const method = $(this).val();
        if (method === 'ACCOUNT') {
            $('#account-payment-note').show();
            $('#bank-selection-section').hide();
        } else if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
            $('#account-payment-note').hide();
            $('#bank-selection-section').show();
        } else {
            $('#account-payment-note').hide();
            $('#bank-selection-section').hide();
        }
    });

    // Filter receipts
    $('#filter-receipts').on('click', function() {
        if (currentPatient) {
            filterReceipts();
        }
    });

    // Export receipts
    $('#export-receipts').on('click', function() {
        if (currentPatient) {
            exportReceipts();
        }
    });

    // Refresh receipts
    $('#refresh-receipts').on('click', function() {
        if (currentPatient) {
            setDefaultReceiptDates();
            filterReceipts();
        }
    });

    // Select all receipts checkbox
    $(document).on('change', '#select-all-receipts', function() {
        $('.receipt-checkbox').prop('checked', $(this).is(':checked'));
        updatePrintSelectedButton();
    });

    // Individual receipt checkbox change
    $(document).on('change', '.receipt-checkbox', function() {
        updatePrintSelectedButton();
    });

    // Reprint individual receipt
    $(document).on('click', '.reprint-receipt', function() {
        const paymentId = $(this).data('id');
        if (paymentId) {
            reprintReceipt([paymentId]);
        }
    });

    // Create account button
    $(document).on('click', '#create-account-btn', function() {
        createPatientAccount();
    });

    // View services rendered
    $(document).on('click', '#view-services-rendered', function() {
        if (currentPatient) {
            window.open(`/patient-services-rendered/${currentPatient}`, '_blank');
        }
    });

    // Receipt format tab switching
    $(document).on('click', '.receipt-tab', function() {
        const format = $(this).data('format');
        $('.receipt-tab').removeClass('active');
        $(this).addClass('active');

        if (format === 'a4') {
            $('#receipt-content-a4').show();
            $('#receipt-content-thermal').hide();
        } else {
            $('#receipt-content-a4').hide();
            $('#receipt-content-thermal').show();
        }
    });

    // Print A4 receipt
    $('#print-a4-receipt').on('click', function() {
        printReceipt('receipt-content-a4');
    });

    // Print thermal receipt
    $('#print-thermal-receipt').on('click', function() {
        printReceipt('receipt-content-thermal');
    });

    // Close receipt display
    $('#close-receipt').on('click', function() {
        $('#receipt-display').hide();
        $('#receipt-content-a4').empty();
        $('#receipt-content-thermal').empty();
    });

    // ===== PRODUCT SEARCH FOR NEW REQUEST =====
    let productSearchTimeout = null;

    $('#product-search-input').on('input', function() {
        clearTimeout(productSearchTimeout);
        const query = $(this).val().trim();

        if (query.length < 2) {
            $('#product-search-results').hide();
            return;
        }

        productSearchTimeout = setTimeout(() => searchProducts(query), 300);
    });

    // Close product search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#product-search-input, #product-search-results').length) {
            $('#product-search-results').hide();
        }
    });

    // New prescription request form submission
    $('#new-prescription-request-form').on('submit', function(e) {
        e.preventDefault();
        submitNewPrescriptionRequest();
    });
}

// ===== PRODUCT SEARCH FUNCTIONS =====
let selectedProducts = [];

function searchProducts(query) {
    const $container = $('#product-search-results');
    
    // Ensure SearchManager is available
    if (typeof SearchManager !== 'undefined') {
        SearchManager.execute({
            inputVal: query,
            minLength: 2,
            delay: 300,
            url: wbUrl('/pharmacy-workbench/search-products'),
            data: {
                term: query,
                patient_id: currentPatient
            },
            onStart: function() {
                $container.html('<li class="list-group-item text-center"><i class="mdi mdi-loading mdi-spin"></i> Loading products...</li>').show();
            },
            onSuccess: function(results) {
                displayProductSearchResults(results, query);
            },
            onError: function() {
                $container.html('<li class="list-group-item text-danger"><i class="mdi mdi-alert-circle"></i> Failed to search products</li>');
            },
            onEmptyQuery: function() {
                $container.empty().hide();
            }
        });
    } else {
        // Fallback if not loaded
        $container.html('<li class="list-group-item text-center"><i class="mdi mdi-loading mdi-spin"></i> Loading products...</li>').show();
        $.ajax({
            url: wbUrl('/pharmacy-workbench/search-products'),
            method: 'GET',
            data: { term: query, patient_id: currentPatient },
            success: function(results) { displayProductSearchResults(results, query); },
            error: function() {
                $container.html('<li class="list-group-item text-danger"><i class="mdi mdi-alert-circle"></i> Failed to search products</li>');
            }
        });
    }
}

function displayProductSearchResults(results, query) {
    const $container = $('#product-search-results');
    $container.empty();

    window.comboSearchMap = {};

    // Inject Free-Form option at the top
    const freeFormHtml = `
        <li class="list-group-item list-group-item-action text-primary" onclick="addFreeFormProductWorkbench()" style="cursor:pointer;">
            <i class="mdi mdi-plus-circle"></i> Not listed? Add free-form '${query}'
        </li>
    `;
    $container.append(freeFormHtml);

    if (results.length === 0) {
        $container.append('<li class="list-group-item text-center text-muted"><i class="mdi mdi-magnify"></i> No products found matching your search</li>');
        $container.show();
        return;
    }

    results.forEach(product => {
        const isCombo = !!product.is_combo;
        const isAlreadySelected = isCombo ? false : selectedProducts.some(p => p.id === product.id);
        const price = parseFloat(product.price || 0);
        const stockQty = product.stock_qty || 0;
        const payableAmount = parseFloat(product.payable_amount || price);
        const claimsAmount = parseFloat(product.claims_amount || 0);
        const coverageMode = product.coverage_mode;

        if (isCombo) {
            window.comboSearchMap[String(product.id)] = product;
        }

        // Product type badge
        const typeBadgeMap = {
            drug: '<span class="badge" style="background:#d4edda;color:#155724;">Drug</span>',
            consumable: '<span class="badge" style="background:#fff3cd;color:#856404;">Consumable</span>',
            utility: '<span class="badge" style="background:#d1ecf1;color:#0c5460;">Utility</span>'
        };
        const typeBadge = isCombo
            ? '<span class="badge badge-primary">Combo</span>'
            : (typeBadgeMap[product.product_type] || typeBadgeMap.drug);

        // Packaging chain info
        let packagingInfo = '';
        if (!isCombo && product.packagings && product.packagings.length > 0) {
            const baseUnit = product.base_unit_name || 'Piece';
            const chain = [baseUnit, ...product.packagings.sort((a,b) => a.level - b.level).map(p => p.name)];
            packagingInfo = `<div class="mt-1 small text-muted"><i class="mdi mdi-package-variant-closed"></i> ${chain.join(' → ')}</div>`;
        }

        // Combo breakdown
        let comboInfo = '';
        if (isCombo) {
            const productItems = (product.bundle_items || []).filter(item => item.type === 'product');
            const serviceItems = (product.bundle_items || []).filter(item => item.type === 'service');

            const productsHtml = productItems.length
                ? productItems.map(item => `<span class="badge badge-light mr-1 mb-1">${item.qty}x ${item.name}</span>`).join('')
                : '<span class="text-muted">No product items</span>';

            const servicesHtml = serviceItems.length
                ? `<div class="mt-1 small text-muted"><i class="mdi mdi-stethoscope"></i> Includes service items: ${serviceItems.map(item => `${item.qty}x ${item.name}`).join(', ')}</div>`
                : '';

            comboInfo = `
                <div class="mt-2">
                    <div class="small text-muted"><i class="mdi mdi-package-variant"></i> Product bundle:</div>
                    <div class="mt-1">${productsHtml}</div>
                    ${servicesHtml}
                </div>
            `;
        }

        // Build HMO coverage badge (like new_encounter)
        let coverageBadge = '';
        if (coverageMode) {
            coverageBadge = `
                <div class="mt-1">
                    <span class="badge badge-info">${coverageMode.toUpperCase()}</span>
                    <span class="text-danger ml-1">Pay: ₦${payableAmount.toLocaleString()}</span>
                    <span class="text-success ml-1">Claim: ₦${claimsAmount.toLocaleString()}</span>
                </div>
            `;
        }

        // Stock availability badge with intuitive thresholds
        // Thresholds: Out (0), Critical (1-5), Low (6-20), OK (>20)
        let stockBadge = '';
        if (isCombo) {
            stockBadge = `<span class="badge badge-secondary ml-1">Bundle</span>`;
        } else if (stockQty <= 0) {
            stockBadge = `<span class="badge badge-stock-out ml-1"><i class="mdi mdi-alert-circle"></i> Out of stock</span>`;
        } else if (stockQty <= 5) {
            stockBadge = `<span class="badge badge-stock-critical ml-1"><i class="mdi mdi-alert"></i> ${stockQty} only!</span>`;
        } else if (stockQty <= 20) {
            stockBadge = `<span class="badge badge-stock-low ml-1"><i class="mdi mdi-alert-outline"></i> ${stockQty} left</span>`;
        } else {
            stockBadge = `<span class="badge badge-stock-ok ml-1">${stockQty} avail.</span>`;
        }

        const item = `
            <li class="list-group-item list-group-item-action ${isAlreadySelected ? 'disabled' : ''}"
                style="background-color: #f8f9fa; cursor: ${isAlreadySelected ? 'not-allowed' : 'pointer'};"
                data-product-id="${product.id}"
                data-product-name="${product.product_name}"
                data-product-code="${product.product_code || ''}"
                data-product-price="${price}"
                data-product-category="${product.category_name || ''}"
                data-payable-amount="${payableAmount}"
                data-claims-amount="${claimsAmount}"
                data-coverage-mode="${coverageMode || ''}"
                data-stock-qty="${stockQty}"
                ${isAlreadySelected ? '' : `onclick="${isCombo ? `selectComboProducts(${product.id})` : 'selectProduct(this)'}"`}>
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted">[${product.category_name || 'N/A'}]</span>
                        <strong>${product.product_name}</strong>
                        ${product.product_code ? `<span class="text-muted">[${product.product_code}]</span>` : ''}
                        ${typeBadge}
                        ${stockBadge}
                        ${coverageBadge}
                        ${packagingInfo}
                        ${comboInfo}
                    </div>
                    <div class="text-right">
                        <strong>₦${price.toLocaleString()}</strong>
                        ${isAlreadySelected ? '<br><span class="badge badge-secondary">Already Added</span>' : ''}
                        ${isCombo ? '<br><span class="badge badge-primary mt-1">Add Bundle Items</span>' : ''}
                    </div>
                </div>
            </li>
        `;
        $container.append(item);
    });

    $container.show();
}

function addFreeFormProductWorkbench() {
    Swal.fire({
        title: 'Add Free-Form Medication',
        text: 'Enter the name of the medication to prescribe:',
        input: 'text',
        showCancelButton: true,
        confirmButtonText: 'Add',
        inputValidator: (value) => {
            if (!value) return 'You need to write something!'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const product = {
                id: 'FF_' + result.value,
                name: result.value + ' [Free-form]',
                code: '',
                price: 0,
                category: 'Free-form',
                payableAmount: 0,
                claimsAmount: 0,
                coverageMode: 'cash',
                stockQty: 999, // infinite for free-form
                qty: 1,
                dose: ''
            };
            if (selectedProducts.some(p => p.id === product.id)) {
                toastr.warning('Product already added');
                return;
            }
            selectedProducts.push(product);
            renderSelectedProducts();
            $('#product-search-input').val('');
            $('#product-search-results').hide();
        }
    });
}

function selectProduct(element) {
    const $el = $(element);
    const product = {
        id: $el.data('product-id'),
        name: $el.data('product-name'),
        code: $el.data('product-code'),
        price: parseFloat($el.data('product-price')) || 0,
        category: $el.data('product-category'),
        payableAmount: parseFloat($el.data('payable-amount')) || 0,
        claimsAmount: parseFloat($el.data('claims-amount')) || 0,
        coverageMode: $el.data('coverage-mode') || null,
        stockQty: parseInt($el.data('stock-qty')) || 0,
        qty: 1,
        dose: ''
    };

    // Check if already selected
    if (selectedProducts.some(p => p.id === product.id)) {
        toastr.warning('Product already added');
        return;
    }

    selectedProducts.push(product);
    renderSelectedProducts();

    // Clear search
    $('#product-search-input').val('');
    $('#product-search-results').hide();
}

function selectComboProducts(comboId) {
    const combo = window.comboSearchMap ? window.comboSearchMap[String(comboId)] : null;
    if (!combo) {
        toastr.error('Unable to load combo items');
        return;
    }

    // Store to comboDataMap for the modal
    window.comboDataMap = window.comboDataMap || {};
    window.comboDataMap[comboId] = combo;

    ComboConfirmModal.show({
        name        : combo.product_name || combo.service_name || 'Combo',
        bundleItems : combo.bundle_items || [],
        price       : parseFloat(combo.base_price || 0),
        payable     : parseFloat(combo.payable_amount != null ? combo.payable_amount : (combo.base_price || 0)),
        claims      : parseFloat(combo.claims_amount || 0),
        mode        : combo.coverage_mode || null,
        onConfirm   : function() {
            const productItems = (combo.bundle_items || []).filter(item => item.type === 'product');
            if (productItems.length === 0) {
                toastr.warning('Selected combo has no product items');
                return;
            }

            let addedCount = 0;
            productItems.forEach(item => {
                const itemQty = parseFloat(item.qty || 1) || 1;
                const existingIndex = selectedProducts.findIndex(p => p.id === item.id);

                if (existingIndex >= 0) {
                    selectedProducts[existingIndex].qty = (parseFloat(selectedProducts[existingIndex].qty) || 0) + itemQty;
                    return;
                }

                selectedProducts.push({
                    id: item.id,
                    name: item.name,
                    code: item.code || '',
                    price: parseFloat(item.price || 0),
                    category: item.category_name || '',
                    payableAmount: parseFloat(item.payable_amount || item.price || 0),
                    claimsAmount: parseFloat(item.claims_amount || 0),
                    coverageMode: item.coverage_mode || null,
                    stockQty: parseFloat(item.stock_qty || 0),
                    qty: itemQty,
                    dose: ''
                });
                addedCount++;
            });

            renderSelectedProducts();
            $('#product-search-input').val('');
            $('#product-search-results').hide();
            toastr.success(`${combo.product_name || 'Combo'}: ${productItems.length} combo item(s) added`);
        }
    });
}

function renderSelectedProducts() {
    const $tbody = $('#selected-products-tbody');
    $tbody.empty();

    if (selectedProducts.length === 0) {
        $('#selected-products-container').hide();
        return;
    }

    let grandTotal = 0;

    selectedProducts.forEach((product, index) => {
        const total = product.price * product.qty;
        grandTotal += total;

        // Use actual HMO breakdown from product data
        let patientPays = product.payableAmount || product.price;
        let hmoPays = product.claimsAmount || 0;
        let coverage = 'Cash';

        if (product.coverageMode) {
            coverage = product.coverageMode.toUpperCase();
        } else if (currentPatientData && currentPatientData.hmo_name && hmoPays> 0) {
            coverage = 'HMO';
        }

        // Build HMO coverage badge for display
        let coverageBadgeHtml = '';
        if (product.coverageMode) {
            coverageBadgeHtml = `
                <div class="small mt-1">
                    <span class="badge badge-info">${coverage}</span>
                    <span class="text-danger">Pay: ₦${patientPays.toLocaleString()}</span>
                    <span class="text-success">Claim: ₦${hmoPays.toLocaleString()}</span>
                </div>
            `;
        }

        const row = `
            <tr data-index="${index}">
                <td>
                    <strong>${product.name}</strong>
                    ${product.code ? ` <small class="text-muted">[${product.code}]</small>` : ''}
                    ${coverageBadgeHtml}
                </td>
                <td class="text-right">₦${product.price.toLocaleString()}</td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm product-qty-input text-center"
                           value="${product.qty}" min="1" max="999" data-index="${index}" style="width: 70px;">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm product-dose-input"
                           value="${product.dose}" placeholder="e.g., 1 tab BD x 7/7" data-index="${index}" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeSelectedProduct(${index})">
                        <i class="mdi mdi-close"></i>
                    </button>
                </td>
            </tr>
        `;
        $tbody.append(row);
    });

    $('#selected-products-total').text(`₦${grandTotal.toLocaleString()}`);
    $('#selected-products-container').show();

    // Attach change handlers
    $('.product-qty-input').on('change', function() {
        const index = $(this).data('index');
        const qty = parseInt($(this).val()) || 1;
        selectedProducts[index].qty = qty;
        updateProductTotal(index);
    });

    $('.product-dose-input').on('change', function() {
        const index = $(this).data('index');
        selectedProducts[index].dose = $(this).val();
    });
}

function updateProductTotal(index) {
    const product = selectedProducts[index];
    const total = product.price * product.qty;

    // Use actual HMO breakdown from product data
    let patientPays = product.payableAmount || product.price;
    let hmoPays = product.claimsAmount || 0;

    $(`tr[data-index="${index}"] .product-patient-pays`).html(`<strong class="text-danger">₦${(patientPays * product.qty).toLocaleString()}</strong>`);
    $(`tr[data-index="${index}"] .product-hmo-pays`).html(`<strong class="text-success">₦${(hmoPays * product.qty).toLocaleString()}</strong>`);
    $(`tr[data-index="${index}"] .product-total`).html(`<strong>₦${total.toLocaleString()}</strong>`);

    // Update grand total
    let grandTotal = 0;
    selectedProducts.forEach(p => grandTotal += p.price * p.qty);
    $('#selected-products-total').text(`₦${grandTotal.toLocaleString()}`);
}

function removeSelectedProduct(index) {
    selectedProducts.splice(index, 1);
    renderSelectedProducts();
}

function submitNewPrescriptionRequest() {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    if (selectedProducts.length === 0) {
        toastr.error('Please add at least one medication');
        return;
    }

    // Validate that all products have dose/frequency
    let missingDose = false;
    selectedProducts.forEach((p, index) => {
        if (!p.dose || p.dose.trim() === '') {
            missingDose = true;
            $(`.product-dose-input[data-index="${index}"]`).addClass('is-invalid');
        } else {
            $(`.product-dose-input[data-index="${index}"]`).removeClass('is-invalid');
        }
    });

    if (missingDose) {
        toastr.error('Please enter dose/frequency for all medications');
        return;
    }

    const formData = {
        patient_id: currentPatient,
        products: selectedProducts.map(p => ({
            product_id: p.id,
            qty: p.qty,
            dose: p.dose
        })),
        urgency: $('#request-urgency').val(),
        send_to_billing: $('#request-send-to-billing').val(),
        notes: $('#request-notes').val()
    };

    const $submitBtn = $('#new-prescription-request-form button[type="submit"]');
    const originalText = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Submitting...');

    $.ajax({
        url: wbUrl('/pharmacy-workbench/create-request'),
        method: 'POST',
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Prescription request created successfully');
                // Reset form
                selectedProducts = [];
                renderSelectedProducts();
                $('#new-prescription-request-form')[0].reset();
                // Switch to pending tab
                switchWorkspaceTab('pending');
                // Refresh prescription items
                loadPrescriptionItems(currentStatusFilter);
            } else {
                toastr.error(response.message || 'Failed to create request');
            }
        },
        error: function(xhr) {
            console.error('Request creation failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to create prescription request');
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html(originalText);
        }
    });
}

// Global banks cache
let availableBanks = [];

function loadBanks() {
    if (availableBanks.length> 0) {
        return; // Already loaded
    }

    $.ajax({
        url: wbUrl('/banks/active'),
        method: 'GET',
        success: function(response) {
            if (response.success && response.banks) {
                availableBanks = response.banks;
                populateBankDropdowns();
            }
        },
        error: function() {
            console.error('Failed to load banks');
        }
    });
}

function populateBankDropdowns() {
    const $paymentBank = $('#payment-bank');
    const $transactionBank = $('#transaction-bank');

    // Clear existing options except the placeholder
    $paymentBank.find('option:not(:first)').remove();
    $transactionBank.find('option:not(:first)').remove();

    // Populate with banks
    availableBanks.forEach(bank => {
        const optionText = bank.account_number ? `${bank.name} - ${bank.account_number}` : bank.name;
        const option = `<option value="${bank.id}">${optionText}</option>`;
        $paymentBank.append(option);
        $transactionBank.append(option);
    });
}

function generateReferenceNumber() {
    // Generate reference format: PAY-YYYYMMDD-HHMMSS
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');

    const reference = `PAY-${year}${month}${day}-${hours}${minutes}${seconds}`;
    $('#payment-reference').val(reference);
}

function loadPatient(patientId) {
    console.log('loadPatient called with ID:', patientId);
    currentPatient = patientId;

    // Hide all sticky bars when loading new patient
    hideAllStickyBars();

    // Hide all views to prevent stacking
    hideAllViews();

    // Show patient workspace
    $('#workspace-content').show().addClass('active');
    $('#patient-header').addClass('active');

    // Show loading indicator
    $('#patient-name').html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');
    $('#patient-meta').html('');

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Load patient prescription data
    $.ajax({
        url: wbUrl(`/pharmacy-workbench/patient/${patientId}/prescription-data`),
        method: 'GET',
        success: function(data) {
            console.log('Patient prescription data loaded:', data);
            currentPatientData = data.patient;
            displayPatientInfo(data.patient);

            // Initialize Clinical Alerts
            try { 
                $('#btn-manage-alerts').show();
                if(typeof ClinicalAlerts !== 'undefined') {
                    ClinicalAlerts.init(patientId, 'pharmacy');
                }
            } catch(e) { console.error('ClinicalAlerts init error:', e); }

            // Inject unified prescription partial HTML
            injectUnifiedPrescPartial(data.patient.id, data.patient.user_id);

            // Initialize Pharmacy Workbench specific DataTables (with adaptation buttons)
            // NOTE: Do NOT call initPrescManagement() as it uses the old renderPrescCard without action buttons
            initializePrescriptionDataTables(data.patient.id);

            // Update subtab counts
            updatePendingSubtabCounts(data.counts || {});

            // Initialize procedures DataTable
            initializeProceduresDataTable(patientId);

            // Switch to pending tab by default
            switchWorkspaceTab('pending');
        },
        error: function(xhr) {
            console.error('Error loading patient:', xhr);
            toastr.error('Failed to load patient data');
        }
    });
}

// Inject unified prescription partial HTML into pharmacy container
function injectUnifiedPrescPartial(patientId, patientUserId) {
    const html = `
        <style>
        /* Prescription Card Styles */
        .presc-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }
        .presc-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-color: #0d6efd;
        }
        .presc-card.selected {
            background: #e7f1ff;
            border-color: #0d6efd;
        }
        .presc-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .presc-card-title {
            font-weight: 600;
            color: #212529;
            font-size: 0.95rem;
        }
        .presc-card-code {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .presc-card-price {
            font-weight: 700;
            color: #198754;
            font-size: 1rem;
        }
        .presc-card-body {
            font-size: 0.875rem;
            color: #495057;
        }
        .presc-card-hmo-info {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 4px 8px;
            margin-top: 8px;
        }
        .presc-card-meta {
            border-top: 1px solid #f1f3f5;
            padding-top: 8px;
            margin-top: 8px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .presc-card-meta-item {
            display: inline-flex;
            align-items: center;
            margin-right: 12px;
        }
        .presc-card-meta-item i {
            margin-right: 4px;
        }
        .presc-card-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .presc-card-actions .btn {
            font-size: 0.75rem;
            padding: 2px 8px;
        }
        #presc_billing_table td,
        #presc_dispense_table td,
        #presc_history_table td {
            vertical-align: top;
            padding: 8px;
        }
        #presc_billing_table td:first-child,
        #presc_dispense_table td:first-child {
            width: 40px;
            text-align: center;
        }
        .presc-card-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        </style>

        <div class="presc-management-container" data-patient-id="${patientId}" data-patient-user-id="${patientUserId}">
            <!-- Sub-tabs Navigation -->
            <ul class="nav nav-tabs nav-tabs-modern mb-3" id="prescSubTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="presc-billing-tab" data-bs-toggle="tab" data-bs-target="#presc-billing-pane" type="button" role="tab">
                        <i class="mdi mdi-cash-register me-1"></i> Billing
                        <span class="badge bg-warning ms-1" id="presc-billing-count">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="presc-pending-tab" data-bs-toggle="tab" data-bs-target="#presc-pending-pane" type="button" role="tab">
                        <i class="mdi mdi-clock-outline me-1"></i> Pending
                        <span class="badge bg-danger ms-1" id="presc-pending-count">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="presc-dispense-tab" data-bs-toggle="tab" data-bs-target="#presc-dispense-pane" type="button" role="tab">
                        <i class="mdi mdi-pill me-1"></i> Ready to Dispense
                        <span class="badge bg-success ms-1" id="presc-dispense-count">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="presc-history-tab" data-bs-toggle="tab" data-bs-target="#presc-history-pane" type="button" role="tab">
                        <i class="mdi mdi-history me-1"></i> History
                        <span class="badge bg-secondary ms-1" id="presc-history-count">0</span>
                    </button>
                </li>
            </ul>

            <!-- Sub-tabs Content -->
            <div class="tab-content" id="prescSubTabsContent">
                <!-- Billing Tab -->
                <div class="tab-pane fade show active" id="presc-billing-pane" role="tabpanel">
                    <div class="card-modern card-modern">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="mdi mdi-cash-register"></i> Requested Prescriptions (Awaiting Billing)</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="printSelectedBillingPrescriptions()">
                                <i class="mdi mdi-printer"></i> Print Selected
                            </button>
                        </div>
                        <div class="card-body">
                            <input type="hidden" id="presc_patient_user_id" value="${patientUserId}">
                            <input type="hidden" id="presc_patient_id" value="${patientId}">

                            <!-- Billing DataTable with Card Layout -->
                            <div class="table-responsive">
                                <table class="table table-hover" style="width: 100%" id="presc_billing_table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" id="select-all-billing" onclick="toggleAllPrescBilling(this)"></th>
                                            <th><i class="mdi mdi-pill"></i> Medication</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <!-- Total and Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <label class="fw-bold">Total: </label>
                                    <span class="fs-5 text-primary" id="presc_billing_total">₦0.00</span>
                                    <input type="hidden" id="presc_billing_total_val" value="0">
                                </div>
                                <div>
                                    <button type="button" class="btn btn-danger me-2" onclick="dismissPrescItems('billing')">
                                        <i class="mdi mdi-close"></i> Dismiss Selected
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="billPrescItems()" id="btn-bill-presc">
                                        <i class="mdi mdi-check"></i> Bill Selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Tab (Awaiting Payment/Validation) -->
                <div class="tab-pane fade" id="presc-pending-pane" role="tabpanel">
                    <div class="card-modern card-modern">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-clock-outline"></i> Pending Items (Awaiting Payment / HMO Validation)</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning mb-3">
                                <i class="mdi mdi-alert-circle-outline"></i>
                                <strong>Important:</strong> These items have been billed but are waiting for payment or HMO validation before they can be dispensed.
                                <ul class="mb-0 mt-2">
                                    <li><span class="badge bg-danger">Awaiting Payment</span> - Patient needs to pay the billable amount</li>
                                    <li><span class="badge bg-info">Awaiting HMO Validation</span> - HMO claims need to be validated</li>
                                </ul>
                            </div>

                            <div class="mb-3 d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="printSelectedPendingPrescriptions()">
                                    <i class="mdi mdi-printer"></i> Print Selected
                                </button>
                            </div>

                            <!-- Pending DataTable with Card Layout -->
                            <div class="table-responsive">
                                <table class="table table-hover" style="width: 100%" id="presc_pending_table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" id="select-all-pending" onclick="toggleAllPrescPending(this)"></th>
                                            <th><i class="mdi mdi-pill"></i> Medication</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <hr>

                            <!-- Pending Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="mdi mdi-information-outline"></i> Items must be paid/validated before they can be dispensed
                                </div>
                                <div>
                                    <button type="button" class="btn btn-danger" onclick="dismissPrescItems('pending')">
                                        <i class="mdi mdi-close"></i> Dismiss Selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dispense Tab (Ready to Dispense) -->
                <div class="tab-pane fade" id="presc-dispense-pane" role="tabpanel">
                    <div class="card-modern card-modern">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="mdi mdi-pill"></i> Ready to Dispense</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openDispenseCartModal()" id="btn-header-cart">
                                <i class="mdi mdi-cart"></i> Cart <span id="header-cart-count" class="badge bg-primary ms-1" style="display: none;">0</span>
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Step 2: Dispense Cart - Shows selected items with stock status -->
                            <!-- Cart is now a modal - see dispenseCartModal below -->

                            <div class="alert alert-success mb-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <i class="mdi mdi-check-circle-outline"></i>
                                    <strong>Ready to Dispense</strong> — Select items and review stock before dispensing
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="openDispenseCartModal()" id="btn-open-cart">
                                    <i class="mdi mdi-cart"></i> View Cart
                                    <span id="floating-cart-count" class="badge bg-success ms-1" style="display: none;">0</span>
                                </button>
                            </div>

                            <!-- Dispense DataTable with Card Layout -->
                            <div class="table-responsive">
                                <table class="table table-hover" style="width: 100%" id="presc_dispense_table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" id="select-all-dispense" onclick="toggleAllPrescDispense(this)"></th>
                                            <th><i class="mdi mdi-pill"></i> Medication</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <hr>

                            <!-- Dispense Actions - Simplified -->
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-danger" onclick="dismissPrescItems('dispense')">
                                    <i class="mdi mdi-close"></i> Dismiss Selected
                                </button>
                                <button type="button" class="btn btn-success btn-lg px-4" onclick="addSelectedToCartAndOpen()">
                                    <i class="mdi mdi-cart-plus"></i> Add to Cart &amp; Review
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Tab -->
                <div class="tab-pane fade" id="presc-history-pane" role="tabpanel">
                    <div class="card-modern card-modern">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-history"></i> Dispensed Prescriptions (History)</h6>
                        </div>
                        <div class="card-body">
                            <!-- History DataTable with Card Layout -->
                            <div class="table-responsive">
                                <table class="table table-hover" style="width: 100%" id="presc_history_table">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="mdi mdi-pill"></i> Dispensed Medication</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#pharmacy-presc-container').html(html);
}

// Initialize DataTables for prescription management (using unified endpoints like presc.blade.php)
function initializePrescriptionDataTables(patientId) {
    // Destroy existing DataTables if they exist
    if ($.fn.DataTable.isDataTable('#presc_billing_table')) {
        $('#presc_billing_table').DataTable().destroy();
    }
    if ($.fn.DataTable.isDataTable('#presc_pending_table')) {
        $('#presc_pending_table').DataTable().destroy();
    }
    if ($.fn.DataTable.isDataTable('#presc_dispense_table')) {
        $('#presc_dispense_table').DataTable().destroy();
    }
    if ($.fn.DataTable.isDataTable('#presc_history_table')) {
        $('#presc_history_table').DataTable().destroy();
    }

    // Reset billing total
    $('#presc_billing_total_val').val(0);
    prescBillingTotal = 0;
    updatePrescBillingTotalPharmacy();

    // Initialize Billing List DataTable (status=1 - unbilled items) with card layout
    $('#presc_billing_table').DataTable({
        dom: 'rtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`/prescBillList/${patientId}`),
            type: 'GET'
        },
        columns: [
            {
                data: null,
                name: "select",
                orderable: false,
                render: function(data, type, row) {
                    const price = parseFloat(row.payable_amount || 0) + parseFloat(row.claims_amount || 0);
                    return `<input type="checkbox" class="presc-card-checkbox presc-billing-check form-check-input"
                            data-id="${row.id}" data-price="${price}"
                            onchange="handlePrescBillingCheckPharmacy(this)">`;
                }
            },
            {
                data: null,
                name: "info",
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCardPharmacy(row, 'billing');
                }
            }
        ],
        paging: true,
        drawCallback: function() {
            const info = this.api().page.info();
            $('#unbilled-subtab-badge, #queue-unbilled-count, #presc-billing-count').text(info.recordsTotal);
            // Restore checked items after redraw
            restoreCheckedItemsState('#presc_billing_table', 'presc-billing-check');
            // Attach action button handlers
            attachPrescCardActionHandlers();
        }
    });

    // Initialize Pending List DataTable (status=2 but NOT ready - awaiting payment/validation)
    $('#presc_pending_table').DataTable({
        dom: 'rtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`/prescPendingList/${patientId}`),
            type: 'GET'
        },
        columns: [
            {
                data: null,
                name: "select",
                orderable: false,
                render: function(data, type, row) {
                    return `<input type="checkbox" class="presc-card-checkbox presc-pending-check form-check-input"
                            data-id="${row.id}"
                            onchange="handlePrescPendingCheckPharmacy(this)">`;
                }
            },
            {
                data: null,
                name: "info",
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCardPharmacy(row, 'pending');
                }
            }
        ],
        paging: true,
        drawCallback: function() {
            const info = this.api().page.info();
            $('#presc-pending-count').text(info.recordsTotal);
            // Restore checked items after redraw
            restoreCheckedItemsState('#presc_pending_table', 'presc-pending-check');
            // Attach action button handlers
            attachPrescCardActionHandlers();
        }
    });

    // Initialize Dispense List DataTable (status=2, READY - paid/validated as needed)
    $('#presc_dispense_table').DataTable({
        dom: 'rtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`/prescReadyList/${patientId}`),
            type: 'GET'
        },
        columns: [
            {
                data: null,
                name: "select",
                orderable: false,
                render: function(data, type, row) {
                    // All items in Ready tab are ready for dispense
                    const productId = row && row.product_id ? row.product_id : '';
                    return `<input type="checkbox" class="presc-card-checkbox presc-dispense-check form-check-input"
                            data-id="${row ? row.id : ''}" data-product-id="${productId}"
                            onchange="handlePrescDispenseCheckPharmacy(this)">`;
                }
            },
            {
                data: null,
                name: "info",
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCardPharmacy(row, 'dispense');
                }
            }
        ],
        paging: true,
        drawCallback: function() {
            const info = this.api().page.info();
            $('#billed-subtab-badge, #ready-subtab-badge, #queue-ready-count, #presc-dispense-count').text(info.recordsTotal);
            // Restore checked items after redraw
            restoreCheckedItemsState('#presc_dispense_table', 'presc-dispense-check');
            // Attach action button handlers
            attachPrescCardActionHandlers();
        }
    });

    // Initialize History List DataTable (ALL prescription requests) with card layout
    $('#presc_history_table').DataTable({
        dom: 'rtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`/prescHistoryList/${patientId}`),
            type: 'GET'
        },
        columns: [
            {
                data: null,
                name: "info",
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCardPharmacy(row, 'history');
                }
            }
        ],
        paging: true,
        drawCallback: function() {
            const info = this.api().page.info();
            $('#presc-history-count').text(info.recordsTotal);
        }
    });
}

// Helper to format money
function formatMoneyPharmacy(amount) {
    return parseFloat(amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Attach action button handlers for prescription cards
function attachPrescCardActionHandlers() {
    // Adapt button handler - pass all billing context
    $('.btn-adapt-product-card').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        openAdaptationModal(
            $btn.data('id'),
            $btn.data('product'),
            $btn.data('dose'),
            $btn.data('qty'),
            $btn.data('price'),
            $btn.data('status'),
            $btn.data('payable'),
            $btn.data('claims'),
            $btn.data('is-paid'),
            $btn.data('is-validated'),
            $btn.data('coverage-mode'),
            $btn.data('product-code')
        );
    });

    // Quantity adjustment button handler - pass all billing context
    $('.btn-adjust-qty-card').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        openQtyAdjustmentModal(
            $btn.data('id'),
            $btn.data('product'),
            $btn.data('qty'),
            $btn.data('price'),
            $btn.data('status'),
            $btn.data('payable'),
            $btn.data('claims'),
            $btn.data('is-paid'),
            $btn.data('is-validated'),
            $btn.data('coverage-mode')
        );
    });

    // Price adjustment button handler - pass pricing and tariff context
    $('.btn-adjust-price-card').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        openPriceAdjustmentModal(
            $btn.data('id'),
            $btn.data('product'),
            $btn.data('product-code'),
            $btn.data('price'),
            $btn.data('qty'),
            $btn.data('coverage-mode'),
            $btn.data('tariff-payable'),
            $btn.data('tariff-claims'),
            $btn.data('price-override'),
            $btn.data('price-override-reason'),
            $btn.data('price-override-by'),
            $btn.data('price-override-at')
        );
    });
}

// Toggle all checkboxes for billing
function toggleAllPrescBilling(checkbox) {
    const isChecked = $(checkbox).is(':checked');
    $('.presc-billing-check').prop('checked', isChecked);
    $('.presc-billing-check').each(function() {
        handlePrescBillingCheckPharmacy(this);
    });
}

// Toggle all checkboxes for pending
function toggleAllPrescPending(checkbox) {
    const isChecked = $(checkbox).is(':checked');
    $('.presc-pending-check').prop('checked', isChecked);
    $('.presc-pending-check').each(function() {
        handlePrescPendingCheckPharmacy(this);
    });
}

// Toggle all checkboxes for dispense
function toggleAllPrescDispense(checkbox) {
    const isChecked = $(checkbox).is(':checked');
    $('.presc-dispense-check').prop('checked', isChecked);
    $('.presc-dispense-check').each(function() {
        handlePrescDispenseCheckPharmacy(this);
    });
}

// Update billing total display
function updatePrescBillingTotalPharmacy() {
    $('#presc_billing_total').text('₦' + formatMoneyPharmacy(prescBillingTotal));
    $('#presc_billing_total_val').val(prescBillingTotal);
}

// Store for selected items data
let selectedItemsData = {
    billing: [],
    pending: [],
    dispense: []
};

// Store dismiss type for modal
let currentDismissType = null;

// Gather selected items data from all tabs
function gatherSelectedItems() {
    const data = { billing: [], pending: [], dispense: [] };
    let grandTotal = 0;

    $('#presc_billing_table').find('.presc-billing-check:checked').each(function() {
        const $row = $(this).closest('tr');
        const $card = $row.find('.presc-card');
        const id = $(this).data('id');
        const price = parseFloat($card.attr('data-total-price')) || 0;
        const name = $card.find('.presc-card-title').text().trim() || 'Unknown';
        const qty = parseInt($card.attr('data-qty')) || 1;
        grandTotal += price;
        data.billing.push({ id, name, qty, price });
    });

    $('#presc_pending_table').find('.presc-pending-check:checked').each(function() {
        const $row = $(this).closest('tr');
        const $card = $row.find('.presc-card');
        const id = $(this).data('id');
        const name = $card.find('.presc-card-title').text().trim() || 'Unknown';
        const qty = parseInt($card.attr('data-qty')) || 1;
        const price = parseFloat($card.attr('data-total-price')) || 0;
        grandTotal += price;
        data.pending.push({ id, name, qty, price });
    });

    $('#presc_dispense_table').find('.presc-dispense-check:checked').each(function() {
        const $row = $(this).closest('tr');
        const $card = $row.find('.presc-card');
        const id = $(this).data('id');
        const name = $card.find('.presc-card-title').text().trim() || 'Unknown';
        const qty = parseInt($card.attr('data-qty')) || 1;
        const price = parseFloat($card.attr('data-total-price')) || 0;
        grandTotal += price;
        data.dispense.push({ id, name, qty, price });
    });

    data.totalCount = data.billing.length + data.pending.length + data.dispense.length;
    data.grandTotal = grandTotal;
    return data;
}

// Update floating cart — called by every checkbox handler
function updateStickyActionBar(type) {
    // Keep selectedItemsData in sync for dismiss modal
    const data = gatherSelectedItems();
    selectedItemsData.billing  = data.billing;
    selectedItemsData.pending  = data.pending;
    selectedItemsData.dispense = data.dispense;

    if (data.totalCount === 0) {
        $('#floating-cart').fadeOut(200);
        return;
    }

    $('#cart-item-count').text(data.totalCount);
    $('#cart-total-display').text('₦' + formatMoneyPharmacy(data.grandTotal));
    $('#floating-cart').fadeIn(300);
    $('.floating-cart-btn').addClass('pulse');
    setTimeout(() => $('.floating-cart-btn').removeClass('pulse'), 300);
}

// Open the cart review modal listing all selected items
function openCartReviewModal() {
    const data = gatherSelectedItems();
    $('#modal-cart-count').text(data.totalCount);

    let html = '';

    if (data.totalCount === 0) {
        html = `<div style="text-align:center;padding:2rem;color:#adb5bd;">
            <i class="mdi mdi-cart-outline" style="font-size:3rem;display:block;margin-bottom:0.5rem;"></i>
            <p>No items selected</p>
            <p class="small">Check items in any tab, then open the cart</p>
        </div>`;
    } else {
        // Billing section
        if (data.billing.length> 0) {
            const billingTotal = data.billing.reduce((s, i) => s + i.price, 0);
            html += `<div class="cart-section">
                <div class="cart-section-header">
                    <span><i class="mdi mdi-cash-register text-primary"></i> Billing</span>
                    <span class="badge bg-primary">${data.billing.length}</span>
                </div>`;
            data.billing.forEach(item => {
                html += `<div class="cart-item">
                    <div style="flex:1">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta">Qty: ${item.qty}</div>
                    </div>
                    <span class="cart-item-price">₦${formatMoneyPharmacy(item.price)}</span>
                    <button class="cart-item-remove" onclick="removeItemFromSelection('billing', ${item.id})" title="Remove">
                        <i class="mdi mdi-close-circle"></i>
                    </button>
                </div>`;
            });
            html += `<div class="cart-section-total">Subtotal: ₦${formatMoneyPharmacy(billingTotal)}</div>`;
            html += `<div class="cart-action-row">
                <button class="btn btn-primary btn-sm" onclick="billPrescItems()">
                    <i class="mdi mdi-cash-register"></i> Bill (${data.billing.length})
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="showDismissModal('billing')">
                    <i class="mdi mdi-close-circle"></i> Dismiss
                </button>
            </div></div>`;
        }

        // Pending section
        if (data.pending.length> 0) {
            html += `<div class="cart-section">
                <div class="cart-section-header">
                    <span><i class="mdi mdi-clock-outline text-warning"></i> Pending</span>
                    <span class="badge bg-warning text-dark">${data.pending.length}</span>
                </div>`;
            data.pending.forEach(item => {
                html += `<div class="cart-item">
                    <div style="flex:1">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta">Qty: ${item.qty}</div>
                    </div>
                    <span class="cart-item-price">₦${formatMoneyPharmacy(item.price)}</span>
                    <button class="cart-item-remove" onclick="removeItemFromSelection('pending', ${item.id})" title="Remove">
                        <i class="mdi mdi-close-circle"></i>
                    </button>
                </div>`;
            });
            html += `<div class="cart-action-row">
                <button class="btn btn-outline-primary btn-sm" onclick="printSelectedPendingPrescriptions()">
                    <i class="mdi mdi-printer"></i> Print
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="showDismissModal('pending')">
                    <i class="mdi mdi-close-circle"></i> Dismiss
                </button>
            </div></div>`;
        }

        // Dispense section
        if (data.dispense.length> 0) {
            const dispenseTotal = data.dispense.reduce((s, i) => s + i.price, 0);
            html += `<div class="cart-section">
                <div class="cart-section-header">
                    <span><i class="mdi mdi-pill text-success"></i> Ready to Dispense</span>
                    <span class="badge bg-success">${data.dispense.length}</span>
                </div>`;
            data.dispense.forEach(item => {
                html += `<div class="cart-item">
                    <div style="flex:1">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta">Qty: ${item.qty}</div>
                    </div>
                    <span class="cart-item-price">₦${formatMoneyPharmacy(item.price)}</span>
                    <button class="cart-item-remove" onclick="removeItemFromSelection('dispense', ${item.id})" title="Remove">
                        <i class="mdi mdi-close-circle"></i>
                    </button>
                </div>`;
            });
            html += `<div class="cart-section-total">Subtotal: ₦${formatMoneyPharmacy(dispenseTotal)}</div>`;
            html += `<div class="cart-action-row">
                <button class="btn btn-success btn-sm" onclick="$('#cartReviewModal').modal('hide'); addSelectedToCartAndOpen();">
                    <i class="mdi mdi-cart-plus"></i> Add to Dispense Cart (${data.dispense.length})
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="showDismissModal('dispense')">
                    <i class="mdi mdi-close-circle"></i> Dismiss
                </button>
            </div></div>`;
        }
    }

    $('#cart-review-body').html(html);
    $('#cartReviewModal').modal('show');
}

// Clear all selections across all tabs
function clearAllSelections() {
    ['billing', 'pending', 'dispense'].forEach(type => clearSelection(type));
    $('#cartReviewModal').modal('hide');
}

// Remove single item from selection
function removeItemFromSelection(type, itemId) {
    let checkboxClass = '';
    if (type === 'billing') checkboxClass = '.presc-billing-check';
    else if (type === 'pending') checkboxClass = '.presc-pending-check';
    else if (type === 'dispense') checkboxClass = '.presc-dispense-check';

    const $checkbox = $(`${checkboxClass}[data-id="${itemId}"]`);
    if ($checkbox.length) {
        $checkbox.prop('checked', false);
        // Trigger the handler
        if (type === 'billing') handlePrescBillingCheckPharmacy($checkbox[0]);
        else if (type === 'pending') handlePrescPendingCheckPharmacy($checkbox[0]);
        else if (type === 'dispense') handlePrescDispenseCheckPharmacy($checkbox[0]);
    }
    // Re-render modal if open
    if ($('#cartReviewModal').hasClass('show')) {
        openCartReviewModal();
    }
}

// Clear all selections for a type
function clearSelection(type) {
    let checkboxClass = '';
    let selectAllId = '';

    if (type === 'billing') {
        checkboxClass = '.presc-billing-check';
        selectAllId = '#select-all-billing';
    } else if (type === 'pending') {
        checkboxClass = '.presc-pending-check';
        selectAllId = '#select-all-pending';
    } else if (type === 'dispense') {
        checkboxClass = '.presc-dispense-check';
        selectAllId = '#select-all-dispense';
    }

    // Uncheck all
    $(checkboxClass).prop('checked', false);
    $(selectAllId).prop('checked', false);

    // Remove visual selection from cards
    $(checkboxClass).closest('tr').find('.presc-card').removeClass('selected');

    // Reset billing total if billing type
    if (type === 'billing') {
        prescBillingTotal = 0;
        updatePrescBillingTotalPharmacy();
    }

    // Update sticky bar (will hide it)
    updateStickyActionBar(type);

    // Clear stored data
    selectedItemsData[type] = [];
}

// Show dismiss confirmation modal
function showDismissModal(type) {
    currentDismissType = type;
    const items = selectedItemsData[type];

    if (!items || items.length === 0) {
        toastr.warning('Please select at least one item to dismiss');
        return;
    }

    // Update modal content
    $('#dismiss-count').text(items.length);

    // Build items preview
    let previewHtml = '<ul class="list-unstyled mb-0">';
    items.forEach(item => {
        previewHtml += `
            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <span><strong>${item.name}</strong> <small class="text-muted">× ${item.qty}</small></span>
                <span class="text-success">₦${formatMoneyPharmacy(item.price)}</span>
            </li>
        `;
    });
    previewHtml += '</ul>';
    $('#dismiss-items-preview').html(previewHtml);

    // Show modal
    $('#dismissConfirmModal').modal('show');
}

// Confirm dismiss action
function confirmDismiss() {
    if (!currentDismissType) return;

    // Close modal
    $('#dismissConfirmModal').modal('hide');

    // Call the actual dismiss function
    dismissPrescItemsConfirmed(currentDismissType);
}

// Hide floating cart (for patient switches / tab resets)
function hideAllStickyBars() {
    $('#floating-cart').fadeOut(200);
}

// Checkbox handler for billing
function handlePrescBillingCheckPharmacy(checkbox) {
    const price = parseFloat($(checkbox).data('price')) || 0;
    const card = $(checkbox).closest('tr').find('.presc-card');

    if ($(checkbox).is(':checked')) {
        prescBillingTotal += price;
        card.addClass('selected');
    } else {
        prescBillingTotal -= price;
        card.removeClass('selected');
    }

    if (prescBillingTotal < 0) prescBillingTotal = 0;
    updatePrescBillingTotalPharmacy();
    updateStickyActionBar('billing');
}

// Checkbox handler for dispense
function handlePrescDispenseCheckPharmacy(checkbox) {
    const card = $(checkbox).closest('tr').find('.presc-card');
    if ($(checkbox).is(':checked')) {
        card.addClass('selected');
    } else {
        card.removeClass('selected');
    }
    updateStickyActionBar('dispense');
}

// Checkbox handler for pending (no additional action needed, just for selection)
function handlePrescPendingCheckPharmacy(checkbox) {
    // Can add visual feedback if needed
    const card = $(checkbox).closest('tr').find('.presc-card');
    if ($(checkbox).is(':checked')) {
        card.addClass('selected');
    } else {
        card.removeClass('selected');
    }
    updateStickyActionBar('pending');
}

// Render prescription card for pharmacy workbench (matching presc_unified_scripts.blade.php format)
function renderPrescCardPharmacy(row, type) {
    const price = parseFloat(row.price || 0);
    const qty = parseInt(row.qty || 1);
    const payableAmount = parseFloat(row.payable_amount || 0);
    const claimsAmount = parseFloat(row.claims_amount || 0);
    const totalPrice = price * qty;
    const isPaid = row.is_paid || false;
    const isValidated = row.is_validated || false;
    const pendingReason = row.pending_reason || '';
    const isBundled = row.is_bundled || false;
    const procedureName = row.procedure_name || '';

    let statusBadges = '';
    let pendingAlert = '';
    let cardClass = 'presc-card';
    let cardStyle = '';

    // Treatment plan badge
    let tpBadge = '';
    if (row.treatment_plan_id && row.treatment_plan_name) {
        tpBadge = ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${row.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(row.treatment_plan_name)}</a>`;
    }

    // Handle Free-Form items early - simplified card look
    if (row.is_free_form) {
        let metaInfoFree = `
            <div class="presc-card-meta small text-muted mt-2">
                <div><i class="mdi mdi-account"></i> By: ${row.requested_by || 'N/A'}</div>
                <div><i class="mdi mdi-clock-outline"></i> ${row.requested_at || row.created_at || ''}</div>
                ${row.dispensed_by ? `<div><i class="mdi mdi-pill"></i> Dispensed: ${row.dispensed_by} (${row.dispensed_at || ''})</div>` : ''}
            </div>
        `;
        let statusBadge = row.status == 3 
            ? '<span class="badge bg-success">Dispensed</span>' 
            : '<span class="badge bg-secondary">Free-Form</span>';
        
        let dispenseBtn = '';
        if (row.status != 3) {
            dispenseBtn = `
            <div class="presc-card-actions mt-2 pt-2 border-top">
                <button type="button" class="btn btn-sm btn-success dispense-freeform-inline-btn" data-request-id="${row.id}" style="width: 100%;">
                    <i class="mdi mdi-check"></i> Dispense Free-Form
                </button>
            </div>`;
        }
        
        return `
            <div class="presc-card border-secondary"
                 data-id="${row.id}"
                 data-product-id=""
                 data-qty="${qty}"
                 style="border-left: 4px solid #6c757d; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); background: #fdfdfd;">
                 <div class="presc-card-header" style="display: flex; justify-content: space-between;">
                    <div>
                        <div class="presc-card-title fw-bold" style="font-size: 1.1rem; color: #333;">${row.free_form_name || row.product_name || 'Free-Form Item'}${tpBadge}</div>
                        <small class="text-muted"><i class="mdi mdi-file-document-edit"></i> Unmapped External Request</small>
                    </div>
                    <div class="text-end">
                        ${statusBadge}
                    </div>
                </div>
                <div class="presc-card-body mt-2 p-2 rounded" style="background: #f1f3f5; border: 1px solid #e9ecef;">
                    <div style="font-size: 0.95rem;"><strong>Dose/Freq:</strong> ${row.dose || 'N/A'}</div>
                    <div style="font-size: 0.95rem;"><strong>Qty:</strong> ${qty}</div>
                </div>
                ${metaInfoFree}
                ${dispenseBtn}
            </div>
        `;
    }

    // Bundled procedure indicator
    let bundledBadge = '';
    if (isBundled && procedureName) {
        bundledBadge = `<div class="mt-1"><span class="badge" style="background: #6f42c1; color: #fff;"><i class="fa fa-procedures mr-1"></i> Bundled: ${procedureName}</span></div>`;
    } else if (procedureName) {
        bundledBadge = `<div class="mt-1"><span class="badge bg-secondary"><i class="fa fa-procedures mr-1"></i> From: ${procedureName}</span></div>`;
    }

    // Different status display based on tab type
    if (type === 'billing') {
        statusBadges = '<span class="badge bg-warning text-dark">Unbilled</span>';
    } else if (type === 'pending') {
        // Show clear indication of what's pending
        cardClass += ' border-warning';
        cardStyle = 'border-left: 4px solid #ffc107;';

        if (payableAmount> 0 && !isPaid) {
            statusBadges += '<span class="badge bg-danger">Awaiting Payment</span>';
            pendingAlert = `
                <div class="alert alert-danger py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                    <i class="mdi mdi-cash-clock"></i> <strong>Payment Required:</strong> ₦${formatMoneyPharmacy(payableAmount)}
                </div>
            `;
        }
        if (claimsAmount> 0 && !isValidated) {
            statusBadges += ' <span class="badge bg-info">Awaiting HMO Validation</span>';
            pendingAlert += `
                <div class="alert alert-info py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                    <i class="mdi mdi-shield-alert"></i> <strong>HMO Validation Required:</strong> ₦${formatMoneyPharmacy(claimsAmount)} claim pending
                </div>
            `;
        }
    } else if (type === 'dispense') {
        // Items in dispense tab are ready - show green badges
        cardClass += ' border-success';
        cardStyle = 'border-left: 4px solid #28a745;';

        if (payableAmount> 0) {
            statusBadges += '<span class="presc-card-status paid"><i class="mdi mdi-check"></i> Paid</span>';
        }
        if (claimsAmount> 0) {
            statusBadges += ' <span class="presc-card-status validated"><i class="mdi mdi-check"></i> HMO Validated</span>';
        }
        if (payableAmount == 0 && claimsAmount == 0) {
            statusBadges = '<span class="badge bg-success">Ready to Dispense</span>';
        }
    } else if (type === 'history') {
        // History shows all requests - determine status badge based on actual status
        const status = parseInt(row.status || 0);

        if (status === 0) {
            statusBadges = '<span class="badge bg-danger">Dismissed</span>';
            cardClass += ' opacity-75';
        } else if (status === 1) {
            statusBadges = '<span class="badge bg-warning text-dark">Unbilled</span>';
        } else if (status === 2) {
            // Check if ready to dispense or awaiting something
            const pendingReasons = [];
            if (payableAmount> 0 && !isPaid) {
                pendingReasons.push('Payment');
            }
            if (claimsAmount> 0 && !isValidated) {
                pendingReasons.push('HMO Validation');
            }

            if (pendingReasons.length> 0) {
                statusBadges = `<span class="badge bg-info">Awaiting ${pendingReasons.join(' & ')}</span>`;
            } else {
                statusBadges = '<span class="badge bg-success">Ready to Dispense</span>';
            }
        } else if (status === 3) {
            statusBadges = '<span class="badge bg-secondary">Dispensed</span>';
        }
    }

    // HMO info if applicable
    let hmoInfo = '';
    if (type !== 'billing' && row.coverage_mode && row.coverage_mode !== 'null' && row.coverage_mode !== 'none') {
        hmoInfo = `
            <div class="presc-card-hmo-info small mt-1 p-2 bg-light rounded">
                <span class="badge bg-info">${(row.coverage_mode || '').toUpperCase()}</span>
                <span class="text-danger ms-2">Pay: ₦${formatMoneyPharmacy(payableAmount)}</span>
                <span class="text-success ms-2">HMO Claim: ₦${formatMoneyPharmacy(claimsAmount)}</span>
            </div>
        `;
    }

    // Pre-billing payable/claims estimate for billing tab cards
    // Effective unit price respects any pre-billing price override
    const effectiveUnitPrice = (row.price_override !== null && row.price_override !== undefined && row.price_override !== '')
        ? parseFloat(row.price_override) : price;
    let tariffPreviewHtml = '';
    if (type === 'billing') {
        const tp = row.tariff_preview;
        if (!tp) {
            // Cash patient — full price is payable, no HMO claim
            const cashTotal = effectiveUnitPrice * qty;
            tariffPreviewHtml = `
                <div class="presc-card-billing-preview small mt-1 p-2 rounded" style="background:#f6fff6; border:1px solid #28a745;">
                    <div class="fw-semibold mb-1" style="color:#155724;">
                        <i class="mdi mdi-calculator-variant text-success me-1"></i> Billing Estimate
                        <span class="badge bg-success ms-1">CASH</span>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:1rem;">
                        <span><strong class="text-danger">Patient Pays:</strong> ₦${formatMoneyPharmacy(cashTotal)}</span>
                        <span><strong class="text-muted">HMO Claim:</strong> ₦0.00</span>
                    </div>
                </div>`;
        } else if (tp.no_tariff) {
            // HMO patient but no tariff configured for this product
            const fallbackTotal = effectiveUnitPrice * qty;
            tariffPreviewHtml = `
                <div class="presc-card-billing-preview small mt-1 p-2 rounded" style="background:#fffdf0; border:1px solid #ffc107;">
                    <div class="fw-semibold mb-1" style="color:#856404;">
                        <i class="mdi mdi-alert-outline text-warning me-1"></i> Billing Estimate
                        <span class="badge bg-warning text-dark ms-1">HMO — No Tariff</span>
                    </div>
                    <div class="text-muted mb-1" style="font-size:0.78rem;">No tariff configured for this HMO — will be charged at full price</div>
                    <div class="d-flex flex-wrap" style="gap:1rem;">
                        <span><strong class="text-danger">Patient Pays:</strong> ₦${formatMoneyPharmacy(fallbackTotal)}</span>
                        <span><strong class="text-muted">HMO Claim:</strong> ₦0.00</span>
                    </div>
                </div>`;
        } else {
            // HMO patient with valid tariff — show breakdown
            const tpPayable = parseFloat(tp.payable_amount);
            const tpClaims = parseFloat(tp.claims_amount);
            const tpTotal = tpPayable + tpClaims;
            const mode = (tp.coverage_mode || 'primary').toUpperCase();
            let modeBadgeStyle = 'background:#17a2b8;color:#fff;';
            if (tp.coverage_mode === 'secondary') modeBadgeStyle = 'background:#ffc107;color:#000;';
            else if (tp.coverage_mode === 'express') modeBadgeStyle = 'background:#6f42c1;color:#fff;';
            tariffPreviewHtml = `
                <div class="presc-card-billing-preview small mt-1 p-2 rounded" style="background:#f0f8ff; border:1px solid #17a2b8;">
                    <div class="fw-semibold mb-1" style="color:#0c5460;">
                        <i class="mdi mdi-calculator-variant text-info me-1"></i> Billing Estimate
                        <span class="badge ms-1" style="${modeBadgeStyle}">${mode}</span>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:1rem;">
                        <span><strong class="text-danger">Patient Pays:</strong> ₦${formatMoneyPharmacy(tpPayable)}</span>
                        <span><strong class="text-success">HMO Claim:</strong> ₦${formatMoneyPharmacy(tpClaims)}</span>
                        ${tpTotal> 0 ? `<span class="text-muted">Total: ₦${formatMoneyPharmacy(tpTotal)}</span>` : ''}
                    </div>
                </div>`;
        }
    }

    // Meta info
    let metaInfo = `
        <div class="presc-card-meta small text-muted mt-2">
            <div><i class="mdi mdi-account"></i> By: ${row.requested_by || 'N/A'}</div>
            <div><i class="mdi mdi-clock-outline"></i> ${row.requested_at || row.created_at || ''}</div>
    `;
    if (row.billed_by) {
        metaInfo += `<div><i class="mdi mdi-cash-register"></i> Billed: ${row.billed_by} (${row.billed_at || ''})</div>`;
    }
    if (row.dispensed_by) {
        metaInfo += `<div><i class="mdi mdi-pill"></i> Dispensed: ${row.dispensed_by} (${row.dispensed_at || ''})</div>`;
    }
    // Show batch info for dispensed items
    if (type === 'history' && row.batch_number) {
        metaInfo += `<div><i class="mdi mdi-tag-outline text-info"></i> <span class="text-info">Batch: ${row.batch_number}${row.batch_expiry ? ' (Exp: ' + row.batch_expiry + ')' : ''}</span></div>`;
    }
    if (type === 'history' && row.dispensed_from_store_name) {
        metaInfo += `<div><i class="mdi mdi-store text-secondary"></i> From: ${row.dispensed_from_store_name}</div>`;
    }
    metaInfo += '</div>';

    // Stock information
    let stockInfo = '';
    const globalStock = parseInt(row.global_stock) || 0;
    const storeStocks = row.store_stocks || [];

    if (type === 'dispense' || type === 'billing') {
        // Stock status based on required qty and absolute thresholds
        // Critical: not enough for order OR <= 5 total
        // Low: less than 2x order qty OR <= 20 total
        // OK: enough stock
        let stockClass, stockIcon, stockBadge;
        if (globalStock <= 0) {
            stockClass = 'text-danger';
            stockIcon = 'mdi-alert-circle';
            stockBadge = '<span class="badge badge-stock-out ms-2"><i class="mdi mdi-alert-circle"></i> Out of Stock</span>';
        } else if (globalStock < qty) {
            stockClass = 'text-danger';
            stockIcon = 'mdi-alert-circle';
            stockBadge = `<span class="badge badge-stock-critical ms-2"><i class="mdi mdi-alert"></i> Insufficient (need ${qty})</span>`;
        } else if (globalStock <= 5 || globalStock < qty * 2) {
            stockClass = 'text-warning';
            stockIcon = 'mdi-alert';
            stockBadge = '<span class="badge badge-stock-low ms-2"><i class="mdi mdi-alert-outline"></i> Low Stock</span>';
        } else {
            stockClass = 'text-success';
            stockIcon = 'mdi-check-circle';
            stockBadge = '';
        }
        stockInfo = `
            <div class="presc-card-stock small mt-2 p-2 bg-light rounded">
                <div class="${stockClass}">
                    <i class="mdi ${stockIcon}"></i> <strong>Stock:</strong> ${globalStock} available
                    ${stockBadge}
                </div>
        `;
        if (storeStocks.length> 0) {
            stockInfo += '<div class="mt-1"><strong>By Store:</strong></div>';
            storeStocks.forEach(function(ss) {
                const storeClass = ss.quantity>= qty ? 'text-success' : 'text-warning';
                stockInfo += `<div class="${storeClass}"><i class="mdi mdi-store"></i> ${ss.store_name}: ${ss.quantity}</div>`;
            });
        }
        stockInfo += '</div>';
    }

    // Action buttons for adaptation and qty adjustment
    // Only available in billing (unbilled) and pending (billed but not paid/validated) stages
    let actionButtons = '';
    const coverageMode = row.coverage_mode || 'cash';

    // Price override badge (shown on card when override exists)
    let priceOverrideBadge = '';
    if (row.price_override !== null && row.price_override !== undefined && row.price_override !== '') {
        const overridePrice = parseFloat(row.price_override);
        const origPrice = parseFloat(row.price_original || price);
        const diff = overridePrice - origPrice;
        const diffClass = diff < 0 ? 'text-success' : (diff> 0 ? 'text-danger' : 'text-muted');
        const diffSign = diff>= 0 ? '+' : '';
        priceOverrideBadge = `
            <div class="small mt-1 p-2 rounded border border-primary" style="background: #f0f4ff;">
                <i class="mdi mdi-cash-edit text-primary me-1"></i>
                <span class="fw-semibold" style="color: #1a237e;">Price Adjusted:</span>
                <span style="color: #555; text-decoration: line-through;" class="ms-1">₦${formatMoneyPharmacy(origPrice)}</span>
                <i class="mdi mdi-arrow-right mx-1" style="color: #333;"></i>
                <strong style="color: #111;">₦${formatMoneyPharmacy(overridePrice)}</strong>/unit
                <span class="${diffClass} ms-1">(${diffSign}₦${formatMoneyPharmacy(Math.abs(diff))})</span>
                ${row.price_override_by ? '<br><small style="color: #444;"><i class="mdi mdi-account"></i> ' + row.price_override_by + '</small>' : ''}
                ${row.price_override_reason ? '<br><small style="color: #444;"><i class="mdi mdi-note-text"></i> ' + row.price_override_reason + '</small>' : ''}
            </div>`;
    }

    // Tariff-related data attributes for price adjustment button
    const tariffPayableUnit = row.tariff_preview && !row.tariff_preview.no_tariff ? (parseFloat(row.tariff_preview.payable_amount) / qty) : 0;
    const tariffClaimsUnit = row.tariff_preview && !row.tariff_preview.no_tariff ? (parseFloat(row.tariff_preview.claims_amount) / qty) : 0;

    if (type === 'billing') {
        // Unbilled items - can always be adapted, qty adjusted, or price adjusted (no billing impact yet)
        actionButtons = `
            <div class="presc-card-actions mt-2 pt-2 border-top">
                <button type="button" class="btn btn-xs btn-outline-info btn-adapt-product-card" data-id="${row.id}" data-product="${row.product_name || 'Unknown'}" data-product-code="${row.product_code || ''}" data-dose="${row.dose || ''}" data-qty="${qty}" data-price="${effectiveUnitPrice}" data-status="unbilled" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="false" data-is-validated="false" data-coverage-mode="${coverageMode}" title="Change to a different product">
                    <i class="mdi mdi-swap-horizontal"></i> Adapt Product
                </button>
                <button type="button" class="btn btn-xs btn-outline-warning btn-adjust-qty-card ms-1" data-id="${row.id}" data-product="${row.product_name || 'Unknown'}" data-qty="${qty}" data-price="${effectiveUnitPrice}" data-status="unbilled" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="false" data-is-validated="false" data-coverage-mode="${coverageMode}" title="Change the quantity">
                    <i class="mdi mdi-counter"></i> Adjust Qty
                </button>
                <button type="button" class="btn btn-xs btn-outline-primary btn-adjust-price-card ms-1" data-id="${row.id}" data-product="${row.product_name || 'Unknown'}" data-product-code="${row.product_code || ''}" data-qty="${qty}" data-price="${effectiveUnitPrice}" data-coverage-mode="${coverageMode}" data-tariff-payable="${tariffPayableUnit}" data-tariff-claims="${tariffClaimsUnit}" data-price-override="${row.price_override ?? ''}" data-price-override-reason="${row.price_override_reason || ''}" data-price-override-by="${row.price_override_by || ''}" data-price-override-at="${row.price_override_at || ''}" title="Adjust the unit price before billing">
                    <i class="mdi mdi-cash-edit"></i> Adjust Price
                </button>
            </div>
        `;
    } else if (type === 'pending') {
        // Pending items - billed but awaiting payment/validation
        // Can only adapt/adjust if:
        // 1. Payable only: payable NOT paid
        // 2. Payable + Claims: NEITHER paid nor validated
        // 3. Claims only: NOT validated

        const hasPayable = payableAmount> 0;
        const hasClaims = claimsAmount> 0;
        const canModify = (
            (hasPayable && !hasClaims && !isPaid) || // Payable only, not paid
            (hasPayable && hasClaims && !isPaid && !isValidated) || // Both, neither settled
            (!hasPayable && hasClaims && !isValidated) // Claims only, not validated
        );

        if (canModify) {
            actionButtons = `
                <div class="presc-card-actions mt-2 pt-2 border-top">
                    <button type="button" class="btn btn-xs btn-outline-info btn-adapt-product-card" data-id="${row.id}" data-product="${row.product_name || 'Unknown'}" data-product-code="${row.product_code || ''}" data-dose="${row.dose || ''}" data-qty="${qty}" data-price="${effectiveUnitPrice}" data-status="billed" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${row.coverage_mode || 'none'}" title="Change to a different product (will update billing)">
                        <i class="mdi mdi-swap-horizontal"></i> Adapt Product
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-warning btn-adjust-qty-card ms-1" data-id="${row.id}" data-product="${row.product_name || 'Unknown'}" data-qty="${qty}" data-price="${effectiveUnitPrice}" data-status="billed" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${row.coverage_mode || 'none'}" title="Change the quantity (will update billing)">
                        <i class="mdi mdi-counter"></i> Adjust Qty
                    </button>
                </div>
            `;
        } else {
            // Some settlement has occurred - show why modification is blocked
            let blockReason = '';
            if (hasPayable && hasClaims) {
                if (isPaid && !isValidated) blockReason = 'Payment received - awaiting HMO validation only';
                else if (!isPaid && isValidated) blockReason = 'HMO validated - awaiting payment only';
                else blockReason = 'Partially settled';
            }
            actionButtons = `
                <div class="presc-card-actions mt-2 pt-2 border-top">
                    <small class="text-muted"><i class="mdi mdi-lock"></i> ${blockReason || 'Cannot modify - partially settled'}</small>
                </div>
            `;
        }
    }
    // NOTE: No action buttons for 'dispense' type - items are ready/settled

    // Display price: total billed amount (patient share + HMO share)
    const displayPrice = (row.price_override !== null && row.price_override !== undefined && row.price_override !== '')
        ? parseFloat(row.price_override) * qty
        : (payableAmount + claimsAmount);

    // Adaptation banner
    let adaptationBanner = '';
    if (row.adapted_from_product_name) {
        adaptationBanner = `
            <div class="mt-2 p-2 rounded border" style="background-color: #e0f7fa; border-color: #4dd0e1; border-left: 4px solid #00acc1; font-size: 0.85rem;">
                <div class="fw-semibold" style="color: #006064;">
                    <i class="mdi mdi-swap-horizontal text-info"></i> Drug Adapted
                </div>
                <div class="text-muted" style="font-size: 0.8rem;">
                    <strong>Original:</strong> [${row.adapted_from_product_code || ''}] ${row.adapted_from_product_name}
                </div>
                ${row.adaptation_note ? `<div style="color: #004d40;"><strong>Note:</strong> ${row.adaptation_note}</div>` : ''}
                <div class="text-muted mt-1" style="font-size: 0.75rem; border-top: 1px dashed rgba(0, 96, 100, 0.2); padding-top: 4px;">
                    <i class="mdi mdi-account-edit"></i> Adapted by: ${row.adapted_by_name || 'Pharmacist'} at ${row.adapted_at}
                </div>
            </div>
        `;
    }

    // Quantity adjustment banner
    let qtyAdjustmentBanner = '';
    if (row.qty_adjusted_from !== null && row.qty_adjusted_from !== undefined && row.qty_adjusted_from !== '') {
        qtyAdjustmentBanner = `
            <div class="mt-2 p-2 rounded border" style="background-color: #fffde7; border-color: #fff176; border-left: 4px solid #fbc02d; font-size: 0.85rem;">
                <div class="fw-semibold" style="color: #f57f17;">
                    <i class="mdi mdi-counter text-warning"></i> Quantity Adjusted
                </div>
                <div class="text-muted" style="font-size: 0.8rem;">
                    <strong>Original Qty:</strong> ${row.qty_adjusted_from} &rarr; <strong>New Qty:</strong> ${qty}
                </div>
                ${row.qty_adjustment_reason ? `<div style="color: #f57f17;"><strong>Reason:</strong> ${row.qty_adjustment_reason}</div>` : ''}
                <div class="text-muted mt-1" style="font-size: 0.75rem; border-top: 1px dashed rgba(245, 127, 23, 0.2); padding-top: 4px;">
                    <i class="mdi mdi-account-edit"></i> Adjusted by: ${row.qty_adjusted_by_name || 'Pharmacist'} at ${row.qty_adjusted_at}
                </div>
            </div>
        `;
    }

    return `
        <div class="${cardClass}" 
             data-id="${row.id}" 
             data-product-id="${row.product_id || ''}" 
             data-qty="${qty}"
             data-payable="${payableAmount}"
             data-claims="${claimsAmount}"
             data-total-price="${displayPrice}"
             style="${cardStyle}">
             <div class="presc-card-header">
                <div>
                    <div class="presc-card-title">${row.product_name || 'Unknown Product'}${tpBadge}</div>
                    <small class="text-muted">[${row.product_code || ''}]</small>
                    ${bundledBadge}
                </div>
                <div class="text-end">
                    <div class="presc-card-price">₦${formatMoneyPharmacy(displayPrice)}</div>
                    ${statusBadges}
                </div>
            </div>
            ${pendingAlert}
            <div class="presc-card-body mt-2">
                <div><strong>Dose/Freq:</strong> ${row.dose || 'N/A'}</div>
                <div><strong>Qty:</strong> ${qty}</div>
                ${hmoInfo}
                ${tariffPreviewHtml}
                ${priceOverrideBadge}
                ${adaptationBanner}
                ${qtyAdjustmentBanner}
                ${stockInfo}
            </div>
            ${metaInfo}
            ${actionButtons}
        </div>
    `;
}

function displayPatientInfo(patient) {
    $('#patient-name').text(patient.name);

    const metaHtml = `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details"></i>
            <span>File: ${patient.file_no}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-calendar"></i>
            <span>Age: ${patient.age}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-gender-${patient.gender === 'Male' ? 'male' : 'female'}"></i>
            <span>${patient.gender}</span>
        </div>
        ${patient.hmo_name ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-hospital-building"></i>
            <span>${patient.hmo_name}</span>
        </div>
        ` : ''}
        ${patient.hmo_no ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details-outline"></i>
            <span>HMO No: ${patient.hmo_no}</span>
        </div>
        ` : ''}
    `;

    $('#patient-meta').html(metaHtml);

    // Enable clinical context button
    $('#btn-clinical-context').prop('disabled', false).attr('title', 'View clinical context for ' + patient.name);
}

function initializeHistoryDataTable(patientId) {
    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
        $('#investigation_history_list').DataTable().destroy();
    }

    $('#investigation_history_list').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: wbUrl(`/investigationHistoryList/${patientId}`),
            type: 'GET'
        },
        columns: [
            {
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: "No investigation history found for this patient",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        },
        drawCallback: function() {
            // Add click handler for view result buttons
            $('.view-invest-result-btn').off('click').on('click', function() {
                const requestId = $(this).data('request-id');
                viewInvestigationResult(requestId);
            });
        }
    });
}

// Helper functions for date formatting
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

// formatDateTime is now provided by ClinicalContext module (clinical-context.js)

function setDefaultReceiptDates() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    $('#receipts-from-date').val(formatDate(firstDay));
    $('#receipts-to-date').val(formatDate(lastDay));
}

function loadQueueCounts() {
    $.get(wbRoute('pharmacy.queue-counts', '/pharmacy/queue-counts'), function(counts) {
        $('#queue-all-count').text(counts.total || 0);
        $('#queue-unbilled-count').text(counts.unbilled || 0);
        $('#queue-ready-count').text(counts.ready || 0);
        $('#queue-hmo-count').text(counts.hmo || 0);
        $('#queue-freeform-count').text(counts.freeform || 0);
        var emergencyCount = counts.emergency || 0;
        $('#queue-emergency-count').text(emergencyCount);
        if (emergencyCount> 0) {
            $('#queue-emergency-count').closest('.queue-item').addClass('emergency-pulse');
        } else {
            $('#queue-emergency-count').closest('.queue-item').removeClass('emergency-pulse');
        }
        updateSyncIndicator();
    });
}

function startQueueRefresh() {
    queueRefreshInterval = setInterval(function() {
        loadQueueCounts();

        // Also refresh current patient data if a patient is selected
        if (currentPatient) {
            refreshCurrentPatientData();
        }

        // Refresh queue DataTable if queue view is active
        if ($('#queue-view').hasClass('active') && queueDataTable) {
            queueDataTable.ajax.reload(null, false);
        }
    }, 30000); // 30 seconds
}

// ===========================================
// CHECKED ITEMS STATE MANAGEMENT
// ===========================================

// Store checked item IDs for each table
let checkedItemsState = {
    billing: new Set(),
    pending: new Set(),
    dispense: new Set()
};

// Check if any tab has checked items
function hasCheckedItems() {
    return checkedItemsState.billing.size> 0 ||
           checkedItemsState.pending.size> 0 ||
           checkedItemsState.dispense.size> 0;
}

// Get checked items for a specific tab
function getCheckedItemsCount(tab) {
    return checkedItemsState[tab]?.size || 0;
}

// Save checked items state before refresh
function saveCheckedItemsState(tableId, checkboxClass) {
    const tabKey = tableId.includes('billing') ? 'billing' :
                   tableId.includes('pending') ? 'pending' :
                   tableId.includes('dispense') ? 'dispense' : null;

    if (!tabKey) return;

    checkedItemsState[tabKey].clear();
    $(`${tableId} .${checkboxClass}:checked`).each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) checkedItemsState[tabKey].add(String(id));
    });
}

// Restore checked items state after refresh
function restoreCheckedItemsState(tableId, checkboxClass) {
    const tabKey = tableId.includes('billing') ? 'billing' :
                   tableId.includes('pending') ? 'pending' :
                   tableId.includes('dispense') ? 'dispense' : null;

    if (!tabKey || checkedItemsState[tabKey].size === 0) return;

    $(`${tableId} .${checkboxClass}`).each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id && checkedItemsState[tabKey].has(String(id))) {
            $(this).prop('checked', true);
            // Trigger change event to update totals
            $(this).trigger('change');
        }
    });
}

// Clear checked items for a specific tab
function clearCheckedItems(tab) {
    if (checkedItemsState[tab]) {
        checkedItemsState[tab].clear();
    }
}

// Track checkbox changes for state management
$(document).on('change', '.presc-billing-check', function() {
    const id = $(this).attr('data-id') || $(this).data('id');
    if (id) {
        if ($(this).is(':checked')) {
            checkedItemsState.billing.add(String(id));
        } else {
            checkedItemsState.billing.delete(String(id));
        }
    }
});

$(document).on('change', '.presc-pending-check', function() {
    const id = $(this).attr('data-id') || $(this).data('id');
    if (id) {
        if ($(this).is(':checked')) {
            checkedItemsState.pending.add(String(id));
        } else {
            checkedItemsState.pending.delete(String(id));
        }
    }
});

$(document).on('change', '.presc-dispense-check', function() {
    const id = $(this).attr('data-id') || $(this).data('id');
    if (id) {
        if ($(this).is(':checked')) {
            checkedItemsState.dispense.add(String(id));
        } else {
            checkedItemsState.dispense.delete(String(id));
        }
    }
});

function refreshCurrentPatientData() {
    if (!currentPatient) return;

    // Skip auto-refresh if there are checked items in active tab
    const activeSubtab = $('#prescSubTabs button.active').data('bs-target');
    if (activeSubtab) {
        const tabKey = activeSubtab.includes('billing') ? 'billing' :
                       activeSubtab.includes('pending') ? 'pending' :
                       activeSubtab.includes('dispense') ? 'dispense' : null;

        if (tabKey && checkedItemsState[tabKey].size> 0) {
            console.log('Skipping auto-refresh: active tab has checked items');
            return;
        }
    }

    // Silently reload patient prescriptions
    loadPrescriptionItems(currentStatusFilter);

    // Also refresh the active prescription subtab's DataTable
    if (activeSubtab) {
        refreshPrescSubtab(activeSubtab);
    }

    // Update sync indicator
    updateSyncIndicator();
}

let lastSyncTimestamp = null;
let syncTimeUpdateInterval = null;

function updateSyncIndicator() {
    lastSyncTimestamp = Date.now();
    updateSyncTimeDisplay();

    // Start interval to update relative time every 10 seconds
    if (syncTimeUpdateInterval) {
        clearInterval(syncTimeUpdateInterval);
    }
    syncTimeUpdateInterval = setInterval(updateSyncTimeDisplay, 10000);
}

function updateSyncTimeDisplay() {
    if (!lastSyncTimestamp) {
        $('#last-sync-time').text('Just now');
        return;
    }

    const secondsAgo = Math.floor((Date.now() - lastSyncTimestamp) / 1000);

    if (secondsAgo < 10) {
        $('#last-sync-time').text('Just now');
    } else if (secondsAgo < 60) {
        $('#last-sync-time').text(secondsAgo + 's ago');
    } else {
        const minutesAgo = Math.floor(secondsAgo / 60);
        $('#last-sync-time').text(minutesAgo + 'm ago');
    }
}

