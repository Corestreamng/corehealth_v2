@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<!-- Chart.js for transaction analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- SheetJS for Excel export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'pharmacy'])
<script>
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
            url: '{{ url('/pharmacy-workbench/search-products') }}',
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
            url: '{{ url('/pharmacy-workbench/search-products') }}',
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
        url: '{{ url('/pharmacy-workbench/create-request') }}',
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
        url: '{{ url('/banks/active') }}',
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
        url: `{{ url('/pharmacy-workbench/patient/${patientId}/prescription-data') }}`,
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
            url: `{{ url('/prescBillList/${patientId}') }}`,
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
            url: `{{ url('/prescPendingList/${patientId}') }}`,
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
            url: `{{ url('/prescReadyList/${patientId}') }}`,
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
            url: `{{ url('/prescHistoryList/${patientId}') }}`,
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
            url: `{{ url('/investigationHistoryList/${patientId}') }}`,
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
    $.get('{{ route("pharmacy.queue-counts") }}', function(counts) {
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

// ========== LIVE DATA REFRESH FUNCTIONS ==========

/**
 * Refresh all prescription DataTables (billing, pending, dispense, history)
 * Call this after billing/dispensing actions to update all tabs
 */
function refreshAllPrescTables() {
    const tables = [
        '#presc_billing_table',
        '#presc_pending_table',
        '#presc_dispense_table',
        '#presc_history_table'
    ];

    tables.forEach(tableId => {
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().ajax.reload(null, false);
        }
    });

    // Hide sticky bars after refresh (selection is reset)
    hideAllStickyBars();

    // Update sync indicator
    updateSyncIndicator();
}

/**
 * Refresh a specific prescription subtab's DataTable
 * @param {string} paneId - The tab pane ID (e.g., '#presc-billing-pane')
 */
function refreshPrescSubtab(paneId) {
    if (!currentPatient) return;

    switch(paneId) {
        case '#presc-billing-pane':
            if ($.fn.DataTable.isDataTable('#presc_billing_table')) {
                $('#presc_billing_table').DataTable().ajax.reload(null, false);
            }
            break;
        case '#presc-pending-pane':
            if ($.fn.DataTable.isDataTable('#presc_pending_table')) {
                $('#presc_pending_table').DataTable().ajax.reload(null, false);
            }
            break;
        case '#presc-dispense-pane':
            if ($.fn.DataTable.isDataTable('#presc_dispense_table')) {
                $('#presc_dispense_table').DataTable().ajax.reload(null, false);
            }
            break;
        case '#presc-history-pane':
            if ($.fn.DataTable.isDataTable('#presc_history_table')) {
                $('#presc_history_table').DataTable().ajax.reload(null, false);
            }
            break;
    }
}

function initializeProceduresDataTable(patientId) {
    if ($.fn.DataTable.isDataTable('#procedures_history_list')) {
        $('#procedures_history_list').DataTable().destroy();
    }

    $('#procedures_history_list').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: `{{ url('/patient-procedures/list-by-patient/${patientId}') }}`,
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
            emptyTable: "No procedures found for this patient",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        }
    });
}

function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

    $('.workspace-tab-content').removeClass('active');
    $(`#${tab}-tab`).addClass('active');

    // Load tab-specific data
    if (!currentPatient) return;

    switch(tab) {
        case 'pending':
            // Load pending items based on active subtab
            const activeSubtab = $('.pending-subtab.active').data('status') || 'all';
            renderPendingSubtabContent(activeSubtab);
            break;
        case 'new-request':
            // Update patient name in new request form
            if (currentPatientData) {
                $('#new-request-patient-name').text(currentPatientData.name || 'Selected Patient');
            }
            break;
        case 'history':
            loadPatientDispensingHistory();
            break;
    }
}

// Current status filter for prescriptions
let currentStatusFilter = 'all';

function renderPendingSubtabContent(status) {
    currentStatusFilter = status;
    loadPrescriptionItems(status);
}

// ========== ACCOUNT BALANCE FUNCTIONS ==========

let currentAccountBalance = 0;

function loadAccountBalance(patientId) {
    $.ajax({
        url: `{{ url('/billing-workbench/patient/${patientId}/account-summary') }}`,
        method: 'GET',
        success: function(data) {
            currentAccountBalance = parseFloat(data.balance) || 0;
            updateAccountBalanceDisplays(data);
        },
        error: function(xhr) {
            console.error('Failed to load account balance', xhr);
        }
    });
}

function updateAccountBalanceDisplays(accountData) {
    const balance = parseFloat(accountData.balance) || 0;
    const formattedBalance = `₦${Math.abs(balance).toLocaleString()}`;

    // Update patient header balance
    $('#header-balance-amount').text(formattedBalance);
    $('#patient-header-balance').show();

    // Update billing tab balance
    $('#billing-balance-amount').text(formattedBalance);
    if (balance> 0) {
        $('#billing-account-balance').show();
        // Show account payment option if balance is positive
        $('#account-payment-option').show();
    } else {
        $('#billing-account-balance').hide();
        $('#account-payment-option').hide();
    }

    // Update account tab with new modern UI
    if (accountData.account) {
        displayAccountInfo(accountData.account, accountData.unpaid_total);
        // Initialize filters to current month before loading transactions
        initAccountTxFilters();
        loadAccountTransactions();
    } else {
        showNoAccountState();
    }
}

function displayAccountInfo(account, pendingBills) {
    const balance = parseFloat(account.balance) || 0;
    const formattedBalance = `₦${Math.abs(balance).toLocaleString()}`;

    // Update hero balance section
    const heroBalance = $('#account-hero-balance');
    heroBalance.removeClass('credit debit');

    $('#hero-balance-amount').text(`₦${balance.toLocaleString()}`);

    if (balance> 0) {
        heroBalance.addClass('credit');
        $('#hero-balance-status').text('Credit Balance');
    } else if (balance < 0) {
        heroBalance.addClass('debit');
        $('#hero-balance-status').text(`Debit Balance`);
    } else {
        $('#hero-balance-status').text('Balanced');
    }

    // Update pending bills stat
    $('#pending-bills-stat').text(`₦${parseFloat(pendingBills || 0).toLocaleString()}`);

    // Show account UI, hide no-account state
    $('#account-hero-section').show();
    $('#account-transactions-section').show();
    $('#no-account-state').hide();
    $('#account-transaction-panel').hide();
}

function showNoAccountState() {
    $('#account-hero-section').hide();
    $('#account-transactions-section').hide();
    $('#no-account-state').show();
}

function loadAccountTransactions() {
    if (!currentPatient) return;

    const fromDate = $('#account-tx-from-date').val() || '';
    const toDate = $('#account-tx-to-date').val() || '';
    const txType = $('#account-tx-type-filter').val() || '';

    // Load account-specific transaction history
    $.ajax({
        url: `{{ url('/billing-workbench/patient/${currentPatient}/account-transactions') }}`,
        method: 'GET',
        data: {
            from_date: fromDate,
            to_date: toDate,
            tx_type: txType
        },
        success: function(response) {
            console.log('Account transactions response:', response);
            const transactions = response.transactions || [];
            const summary = response.summary || {};
            renderAccountTransactions(transactions);
            updateAccountStats(summary);
        },
        error: function(xhr) {
            console.error('Failed to load account transactions', xhr);
            $('#transaction-timeline').html(`
                <div class="timeline-empty-state">
                    <i class="mdi mdi-alert-circle"></i>
                    <p>Failed to load transactions</p>
                    <small>Please try refreshing</small>
                </div>
            `);
        }
    });
}

function updateAccountStats(summary) {
    $('#total-deposits-stat').text(`₦${parseFloat(summary.total_deposits || 0).toLocaleString()}`);
    $('#total-withdrawals-stat').text(`₦${parseFloat(summary.total_withdrawals || 0).toLocaleString()}`);
    $('#tx-count-stat').text(summary.transaction_count || 0);
}

function renderAccountTransactions(transactions) {
    console.log('renderAccountTransactions called with:', transactions);
    const timeline = $('#transaction-timeline');
    console.log('Timeline element found:', timeline.length> 0);
    timeline.empty();

    if (!transactions || transactions.length === 0) {
        console.log('No transactions to render');
        timeline.html(`
            <div class="timeline-empty-state">
                <i class="mdi mdi-swap-horizontal"></i>
                <p>No account transactions yet</p>
                <small>Deposits and withdrawals will appear here</small>
            </div>
        `);
        return;
    }

    console.log('Rendering', transactions.length, 'transactions');
    transactions.forEach((tx, index) => {
        const amountClass = parseFloat(tx.amount)>= 0 ? 'positive' : 'negative';
        const amountPrefix = parseFloat(tx.amount)>= 0 ? '+' : '';

        const item = `
            <div class="timeline-item">
                <div class="timeline-icon ${tx.tx_color}">
                    <i class="mdi ${tx.tx_icon}"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-type">${tx.tx_type}</span>
                        <span class="timeline-amount ${amountClass}">${amountPrefix}₦${Math.abs(parseFloat(tx.amount)).toLocaleString()}</span>
                    </div>
                    <div class="timeline-meta">
                        <span><i class="mdi mdi-calendar"></i> ${tx.created_at}</span>
                        <span><i class="mdi mdi-clock"></i> ${tx.created_time}</span>
                        <span><i class="mdi mdi-account"></i> ${tx.cashier}</span>
                    </div>
                    ${tx.description ? `<div class="timeline-description">${tx.description}</div>` : ''}
                    <span class="timeline-balance">Balance after: ₦${parseFloat(tx.running_balance).toLocaleString()}</span>
                </div>
            </div>
        `;
        console.log('Appending item', index, 'to timeline');
        timeline.append(item);
    });
    console.log('Timeline HTML after render:', timeline.html().substring(0, 200));
}

// Account Tab Event Handlers - Transaction Panel
let currentTransactionType = 'deposit';

function openTransactionPanel(type) {
    currentTransactionType = type;
    const panel = $('#account-transaction-panel');
    const icon = $('#transaction-panel-icon');
    const title = $('#transaction-panel-title');
    const submitBtn = $('#transaction-submit-btn');
    const submitText = $('#transaction-submit-text');
    const amountHelp = $('#transaction-amount-help');
    const changeLabel = $('#preview-change-label');

    // Reset form
    $('#account-transaction-form')[0].reset();
    $('#transaction-type').val(type);

    // Update panel styling based on type
    panel.removeClass('deposit withdraw adjust');
    panel.addClass(type);

    // Get current balance for preview
    const balanceText = $('#hero-balance-amount').text().replace('₦', '').replace(/,/g, '');
    currentAccountBalance = parseFloat(balanceText) || 0;
    $('#preview-current-balance').text(`₦${currentAccountBalance.toLocaleString()}`);
    updateBalancePreview();

    // Show/hide payment method based on transaction type
    if (type === 'adjust') {
        // Hide payment method for adjustments
        $('#transaction-payment-method-group').hide();
        $('#transaction-bank-group').hide();
    } else {
        // Show payment method for deposits and withdrawals
        $('#transaction-payment-method-group').show();
        // Reset bank visibility based on current payment method
        const payMethod = $('#transaction-payment-method').val();
        if (['POS', 'TRANSFER', 'MOBILE'].includes(payMethod)) {
            $('#transaction-bank-group').show();
        } else {
            $('#transaction-bank-group').hide();
        }
    }

    if (type === 'deposit') {
        icon.attr('class', 'mdi mdi-plus-circle');
        title.text('Make Deposit');
        submitText.text('Confirm Deposit');
        amountHelp.text('Enter amount to add to account');
        changeLabel.text('After Deposit:');
        $('#transaction-description').removeAttr('required');
        $('#transaction-amount').attr('min', '0.01');
    } else if (type === 'withdraw') {
        icon.attr('class', 'mdi mdi-minus-circle');
        title.text('Make Withdrawal');
        submitText.text('Confirm Withdrawal');
        amountHelp.text('Enter amount to withdraw from account');
        changeLabel.text('After Withdrawal:');
        $('#transaction-description').removeAttr('required');
        $('#transaction-amount').attr('min', '0.01');
    } else if (type === 'adjust') {
        icon.attr('class', 'mdi mdi-swap-horizontal');
        title.text('Account Adjustment');
        submitText.text('Confirm Adjustment');
        amountHelp.text('Enter positive to credit, negative to debit');
        changeLabel.text('After Adjustment:');
        $('#transaction-description').attr('required', 'required');
        $('#transaction-amount').removeAttr('min');
    }

    panel.slideDown();
    $('#transaction-amount').focus();
}

// Transaction payment method change handler
$(document).on('change', '#transaction-payment-method', function() {
    const method = $(this).val();
    if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
        $('#transaction-bank-group').show();
    } else {
        $('#transaction-bank-group').hide();
        $('#transaction-bank').val('');
    }
});

function updateBalancePreview() {
    const amount = parseFloat($('#transaction-amount').val()) || 0;
    let newBalance = currentAccountBalance;

    if (currentTransactionType === 'deposit') {
        newBalance = currentAccountBalance + amount;
    } else if (currentTransactionType === 'withdraw') {
        newBalance = currentAccountBalance - amount;
    } else if (currentTransactionType === 'adjust') {
        newBalance = currentAccountBalance + amount; // Adjustment can be +/-
    }

    const previewElement = $('#preview-new-balance');
    previewElement.text(`₦${newBalance.toLocaleString()}`);
    previewElement.removeClass('positive negative');

    if (newBalance> 0) {
        previewElement.addClass('positive');
    } else if (newBalance < 0) {
        previewElement.addClass('negative');
    }
}

$(document).on('click', '#quick-deposit-btn', function() {
    openTransactionPanel('deposit');
});

$(document).on('click', '#quick-withdraw-btn', function() {
    openTransactionPanel('withdraw');
});

$(document).on('click', '#quick-adjust-btn', function() {
    openTransactionPanel('adjust');
});

$(document).on('click', '#close-transaction-panel', function() {
    $('#account-transaction-panel').slideUp();
});

$(document).on('input', '#transaction-amount', function() {
    updateBalancePreview();
});

$(document).on('submit', '#account-transaction-form', function(e) {
    e.preventDefault();
    processAccountTransaction();
});

function processAccountTransaction() {
    if (!currentPatientData) return;

    const type = $('#transaction-type').val();
    const amountInput = $('#transaction-amount').val();
    const amount = parseFloat(amountInput);
    const description = $('#transaction-description').val();
    const paymentMethod = $('#transaction-payment-method').val();
    const bankId = $('#transaction-bank').val();

    console.log('Processing transaction:', { type, amountInput, amount, description, paymentMethod, bankId });

    // For adjustments, allow any non-zero value (positive or negative)
    // For deposit/withdraw, require positive values
    if (type === 'adjust') {
        if (isNaN(amount) || amount === 0) {
            toastr.warning('Please enter a valid non-zero amount (positive to credit, negative to debit)');
            return;
        }
    } else {
        if (isNaN(amount) || amount <= 0) {
            toastr.warning('Please enter a valid positive amount');
            return;
        }
    }

    if (type === 'adjust' && !description) {
        toastr.warning('Description is required for adjustments');
        return;
    }

    // Validate bank selection for non-cash payments (except adjustments)
    if (type !== 'adjust' && ['POS', 'TRANSFER', 'MOBILE'].includes(paymentMethod) && !bankId) {
        toastr.warning('Please select a bank for this payment method');
        return;
    }

    // Check if withdraw amount exceeds balance
    if (type === 'withdraw' && amount> currentAccountBalance) {
        if (!confirm(`Warning: This withdrawal (₦${amount.toLocaleString()}) exceeds the current balance (₦${currentAccountBalance.toLocaleString()}). Continue anyway?`)) {
            return;
        }
    }

    let confirmMsg = '';
    if (type === 'deposit') {
        confirmMsg = `Deposit ₦${amount.toLocaleString()} to this account?`;
    } else if (type === 'withdraw') {
        confirmMsg = `Withdraw ₦${amount.toLocaleString()} from this account?`;
    } else {
        confirmMsg = `Apply adjustment of ₦${amount.toLocaleString()} to this account?`;
    }

    if (!confirm(confirmMsg)) return;

    $.ajax({
        url: '{{ url('/billing-workbench/account-transaction') }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatientData.id,
            transaction_type: type,
            amount: amount,
            description: description,
            payment_method: type !== 'adjust' ? paymentMethod : null,
            bank_id: (type !== 'adjust' && bankId) ? bankId : null
        },
        success: function(response) {
            toastr.success(response.message || 'Transaction saved successfully!');

            // Close panel and reset form
            $('#account-transaction-panel').slideUp();
            $('#account-transaction-form')[0].reset();

            // Refresh all account data
            loadAccountBalance(currentPatient);
            loadAccountSummary();
            loadAccountTransactions();

            // If on receipts tab, refresh receipts
            if ($('#receipts-tab').hasClass('active')) {
                loadPatientReceipts();
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to save transaction');
        }
    });
}

$(document).on('click', '#filter-account-tx', function() {
    loadAccountTransactions();
});

$(document).on('click', '#refresh-account-data', function() {
    loadAccountSummary();
    loadAccountTransactions();
    toastr.info('Refreshing account data...');
});

// Set default dates for account transactions filter
function initAccountTxFilters() {
    const today = new Date().toISOString().split('T')[0];
    const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    $('#account-tx-from-date').val(firstDay);
    $('#account-tx-to-date').val(today);
}

// Initialize when account tab is shown
$(document).on('shown.bs.tab', 'a[href="#account-tab"]', function() {
    initAccountTxFilters();
    if (currentPatient) {
        loadAccountTransactions();
    }
});

// Also call on workspace tab click
$(document).on('click', '.workspace-tab[data-tab="account-tab"]', function() {
    setTimeout(() => {
        initAccountTxFilters();
        if (currentPatient) {
            loadAccountTransactions();
        }
    }, 100);
});

function filterReceipts() {
    if (!currentPatient) return;

    const fromDate = $('#receipts-from-date').val() || '';
    const toDate = $('#receipts-to-date').val() || '';
    const paymentType = $('#receipts-payment-type').val() || '';

    const params = {};
    if (fromDate) params.from_date = fromDate;
    if (toDate) params.to_date = toDate;
    if (paymentType) params.payment_type = paymentType;

    $.ajax({
        url: `{{ url('/billing-workbench/patient/${currentPatient}/receipts') }}`,
        method: 'GET',
        data: params,
        success: function(data) {
            renderReceipts(data.receipts);
            updateReceiptsStats(data.stats);
        },
        error: function(xhr) {
            toastr.error('Failed to filter receipts');
        }
    });
}

function renderReceipts(receipts) {
    const tbody = $('#receipts-tbody');
    tbody.empty();

    if (receipts.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    <i class="mdi mdi-receipt" style="font-size: 3rem;"></i>
                    <p>No receipts found</p>
                </td>
            </tr>
        `);
        return;
    }

    receipts.forEach(receipt => {
        // Handle different possible field names from backend
        const paymentId = receipt.payment_id || receipt.id;
        const referenceNo = receipt.reference_no || receipt.reference_number || 'N/A';
        const dateValue = receipt.created_at || receipt.date || receipt.payment_date;
        const itemCount = receipt.item_count || receipt.items_count || 0;
        const total = parseFloat(receipt.total || 0);
        const discount = parseFloat(receipt.total_discount || receipt.discount || 0);
        const paymentType = receipt.payment_type || 'N/A';
        const cashier = receipt.created_by || receipt.cashier || 'N/A';

        const row = `
            <tr>
                <td><input type="checkbox" class="receipt-checkbox" data-id="${paymentId}"></td>
                <td>${referenceNo}</td>
                <td>${dateValue}</td>
                <td>${itemCount} item(s)</td>
                <td>₦${total.toLocaleString()}</td>
                <td>₦${discount.toLocaleString()}</td>
                <td>${paymentType}</td>
                <td>${cashier}</td>
                <td>
                    <button class="btn btn-sm btn-primary reprint-receipt" data-id="${paymentId}">
                        <i class="mdi mdi-printer"></i> Reprint
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });

    // Reset select all checkbox
    $('#select-all-receipts').prop('checked', false);

    // Update print selected button state
    updatePrintSelectedButton();
}

function updateReceiptsStats(stats) {
    if (stats) {
        $('#receipts-total-count').text(stats.count || 0);
        $('#receipts-total-amount').text(`₦${parseFloat(stats.total || 0).toLocaleString()}`);
        $('#receipts-total-discounts').text(`₦${parseFloat(stats.discounts || 0).toLocaleString()}`);
        $('#receipts-summary').show();
    }
}

function exportReceipts() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    const fromDate = $('#receipts-from-date').val() || '';
    const toDate = $('#receipts-to-date').val() || '';
    const paymentType = $('#receipts-payment-type').val() || '';

    const params = new URLSearchParams();
    if (fromDate) params.append('from_date', fromDate);
    if (toDate) params.append('to_date', toDate);
    if (paymentType) params.append('payment_type', paymentType);

    const url = `/billing-workbench/patient/${currentPatient}/receipts/export?${params.toString()}`;
    window.open(url, '_blank');
}

function createPatientAccount() {
    if (!currentPatientData) return;

    if (!confirm('Create a new account for this patient?')) return;

    $.ajax({
        url: '{{ url('/billing-workbench/create-account') }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatientData.id
        },
        success: function(response) {
            toastr.success(response.message || 'Account created successfully!');

            // Update patient data with new account info
            if (response.account) {
                currentPatientData.account_id = response.account.id;
            }

            // Reload account balance and all displays
            loadAccountBalance(currentPatient);

            // Show account UI state
            $('#no-account-state').hide();
            $('#account-hero-section').show();
            $('#account-transactions-section').show();

            // Reload account summary to show full data
            loadAccountSummary();

            // Load account transactions
            loadAccountTransactions();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create account');
        }
    });
}

// ========== PHARMACY WORKBENCH FUNCTIONS ==========

function loadPrescriptionItems(statusFilter = 'all') {
    if (!currentPatient) return;

    const params = {};
    if (statusFilter && statusFilter !== 'all') {
        params.status = statusFilter;
    }

    $.ajax({
        url: `{{ url('/pharmacy-workbench/patient/${currentPatient}/prescription-data') }}`,
        method: 'GET',
        data: params,
        success: function(response) {
            renderPrescriptionItems(response.items);
            updatePrescriptionBadge(response.items.length);
            // Update subtab counts
            updatePendingSubtabCounts(response.counts || {});
        },
        error: function(xhr) {
            console.error('Failed to load prescription items', xhr);
            toastr.error('Failed to load prescription items');
        }
    });
}

// Update pending subtab badge counts
function updatePendingSubtabCounts(counts) {
    if (counts.all !== undefined) {
        $('#all-pending-badge').text(counts.all);
        $('#queue-all-count').text(counts.all);
    }
    if (counts.unbilled !== undefined) {
        $('#unbilled-subtab-badge').text(counts.unbilled);
        $('#queue-unbilled-count').text(counts.unbilled);
    }
    if (counts.billed !== undefined) {
        $('#billed-subtab-badge').text(counts.billed);
    }
    if (counts.ready !== undefined) {
        $('#ready-subtab-badge').text(counts.ready);
        $('#queue-ready-count').text(counts.ready);
    }
}

function renderPrescriptionItems(items) {
    console.log('renderPrescriptionItems called with:', items, 'status filter:', currentStatusFilter);

    const $container = $('#pending-subtab-container');

    // If "All" tab is selected, show widgets; otherwise show filtered table
    if (currentStatusFilter === 'all') {
        renderAllPendingWidgets(items);
    } else {
        renderStatusTable(items, currentStatusFilter);
    }
}

function renderAllPendingWidgets(items) {
    const $container = $('#pending-subtab-container');

    if (items.length === 0) {
        $container.html(`
            <div style="text-align: center; padding: 3rem; color: #999;">
                <i class="mdi mdi-inbox-outline" style="font-size: 3rem;"></i>
                <p>No pending prescriptions for this patient</p>
            </div>
        `);
        return;
    }

    const unbilledItems = [];
    const billedItems = [];
    const readyItems = [];

    items.forEach(item => {
        // Use proper logic to categorize items
        if (item.status == 1) {
            // Status 1 = Unbilled
            unbilledItems.push(item);
        } else if (item.status == 2) {
            // Status 2 = Billed
            // Check if ready to dispense using HMO logic
            const payableAmount = parseFloat(item.payable_amount || 0);
            const claimsAmount = parseFloat(item.claims_amount || 0);
            const isPaid = item.payment_id != null;
            const isValidated = item.validation_status === 'validated' || item.validation_status === 'approved';

            let isReady = false;

            // If payable_amount> 0, must be paid
            if (payableAmount> 0 && !isPaid) {
                isReady = false;
            }
            // If claims_amount> 0, must be validated
            else if (claimsAmount> 0 && !isValidated) {
                isReady = false;
            }
            // All requirements met
            else {
                isReady = true;
            }

            if (isReady) {
                readyItems.push(item);
            } else {
                billedItems.push(item);
            }
        }
    });

    $container.empty();

    // Unbilled Section (Status 1)
    if (unbilledItems.length> 0) {
        const unbilledHtml = `
            <div class="request-section" data-section="unbilled">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-cash-register"></i>
                        Awaiting Billing (${unbilledItems.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="unbilled-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-unbilled" class="select-all-checkbox">
                        <label for="select-all-unbilled">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-billing" id="btn-record-billing" disabled>
                            <i class="mdi mdi-check-circle"></i>
                            Record Billing
                        </button>
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-unbilled" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
        $container.append(unbilledHtml);

        unbilledItems.forEach(item => {
            $('#unbilled-cards').append(createPrescriptionCard(item, 'unbilled'));
        });
    }

    // Billed (Awaiting Payment/Validation) Section
    if (billedItems.length> 0) {
        const billedHtml = `
            <div class="request-section" data-section="billed">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-receipt"></i>
                        Billed - Awaiting Payment/Validation (${billedItems.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="billed-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <span class="text-muted"><i class="mdi mdi-information"></i> Items must be paid/validated before dispensing</span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-billed" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss Selected
                        </button>
                    </div>
                </div>
            </div>
        `;
        $container.append(billedHtml);

        billedItems.forEach(item => {
            $('#billed-cards').append(createPrescriptionCard(item, 'billed'));
        });
    }

    // Ready to Dispense Section
    if (readyItems.length> 0) {
        const readyHtml = `
            <div class="request-section" data-section="ready">
                <div class="request-section-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <h5>
                        <i class="mdi mdi-check-circle"></i>
                        Ready to Dispense (${readyItems.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="ready-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-ready" class="select-all-checkbox">
                        <label for="select-all-ready">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-success" id="btn-dispense-ready" disabled>
                            <i class="mdi mdi-pill"></i>
                            Dispense Selected
                        </button>
                    </div>
                </div>
            </div>
        `;
        $container.append(readyHtml);

        readyItems.forEach(item => {
            $('#ready-cards').append(createPrescriptionCard(item, 'ready'));
        });
    }

    // Initialize handlers
    initializePrescriptionHandlers();
}

function renderStatusTable(items, status) {
    let html = `
        <div class="prescriptions-tab-header">
            <div class="prescriptions-toolbar">
                <button class="btn btn-sm btn-secondary" id="refresh-prescriptions">
                    <i class="mdi mdi-refresh"></i> Refresh
                </button>
                <button class="btn btn-sm btn-success" id="dispense-selected-btn" disabled>
                    <i class="mdi mdi-check-circle"></i> Dispense
                </button>
            </div>
        </div>
        <div class="prescriptions-container">
            <table class="table table-hover" id="prescriptions-table">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="select-all-prescriptions"></th>
                        <th>Medication</th>
                        <th>Qty</th>
                        <th class="text-right">Price</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="prescriptions-tbody">
    `;

    if (items.length === 0) {
        html += `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="mdi mdi-pill" style="font-size: 3rem;"></i>
                            <p>No prescriptions in this category</p>
                        </td>
                    </tr>
        `;
    } else {
        items.forEach(item => {
            html += createFilteredTableRow(item);
        });
    }

    html += `
                </tbody>
            </table>
        </div>
    `;

    $('#pending-subtab-container').html(html);

    // Attach event listeners
    $('#select-all-prescriptions').on('change', function() {
        $('#prescriptions-tbody .prescription-item-checkbox').prop('checked', $(this).is(':checked'));
        updateDispenseSummary();
    });

    $('#prescriptions-tbody .prescription-item-checkbox').on('change', updateDispenseSummary);

    $('.dispense-single-btn').on('click', function() {
        const itemId = $(this).data('id');
        dispenseItems([itemId]);
    });

    // Attach adapt product button handler
    $('.btn-adapt-product').on('click', function() {
        const $btn = $(this);
        const itemId = $btn.data('id');
        const productName = $btn.data('product-name');
        const dose = $btn.data('dose') || '';
        const price = parseFloat($btn.data('price')) || 0;
        const qty = parseInt($btn.data('qty')) || 1;
        const status = $btn.data('status') || 'unbilled';
        const payable = parseFloat($btn.data('payable')) || 0;
        const claims = parseFloat($btn.data('claims')) || 0;
        const isPaid = $btn.data('is-paid') === true || $btn.data('is-paid') === 'true';
        const isValidated = $btn.data('is-validated') === true || $btn.data('is-validated') === 'true';
        const coverageMode = $btn.data('coverage-mode') || 'cash';
        const productCode = $btn.data('product-code') || '';
        openAdaptationModal(itemId, productName, dose, qty, price, status, payable, claims, isPaid, isValidated, coverageMode, productCode);
    });

    // Attach quantity adjustment button handler
    $('.btn-adjust-qty').on('click', function() {
        const $btn = $(this);
        const itemId = $btn.data('id');
        const productName = $btn.data('product-name');
        const price = parseFloat($btn.data('price')) || 0;
        const qty = parseInt($btn.data('qty')) || 1;
        const status = parseInt($btn.data('status')) || 1;
        const payable = parseFloat($btn.data('payable')) || 0;
        const claims = parseFloat($btn.data('claims')) || 0;
        const isPaid = $btn.data('is-paid') === true || $btn.data('is-paid') === 'true';
        const isValidated = $btn.data('is-validated') === true || $btn.data('is-validated') === 'true';
        const coverageMode = $btn.data('coverage-mode') || 'cash';
        openQtyAdjustmentModal(itemId, productName, price, qty, status, payable, claims, isPaid, isValidated, coverageMode);
    });
}

function createFilteredTableRow(item) {
    const basePrice = parseFloat(item.base_price || item.price || 0);
    const qty = parseInt(item.qty) || 1;

    // Calculate proper ready status using HMO logic
    const payableAmount = parseFloat(item.payable_amount || 0);
    const claimsAmount = parseFloat(item.claims_amount || 0);
    const isPaid = item.payment_id != null;
    const isValidated = item.validation_status === 'validated' || item.validation_status === 'approved';
    const coverageMode = item.coverage_mode || 'cash';

    let isReady = false;
    let blockingReason = '';
    let statusText = 'Unbilled';
    let statusClass = 'status-requested';

    if (item.is_free_form) {
        statusText = 'Free-Form';
        statusClass = 'status-ready bg-secondary text-white';
        isReady = true;
    } else if (item.status == 1) {
        statusText = 'Unbilled';
        statusClass = 'status-requested';
    } else if (item.status == 2) {
        // Check readiness
        if (payableAmount > 0 && !isPaid) {
            isReady = false;
            blockingReason = 'Awaiting Payment';
            statusClass = 'status-billed';
            statusText = 'Awaiting Payment';
        } else if (claimsAmount > 0 && !isValidated) {
            isReady = false;
            blockingReason = 'Awaiting HMO Validation';
            statusClass = 'status-billed';
            statusText = 'Awaiting HMO Validation';
        } else {
            isReady = true;
            statusClass = 'status-ready';
            statusText = 'Ready to Dispense';
        }
    }

    // Ready indicators
    let readyIndicator = '';
    if (item.status == 2) {
        if (isPaid) {
            readyIndicator = '<br><span class="badge badge-success ml-1"><i class="mdi mdi-check"></i> Paid</span>';
        }
        if (isValidated) {
            readyIndicator += '<span class="badge badge-primary ml-1"><i class="mdi mdi-shield-check"></i> Validated</span>';
        }
        if (!isReady && blockingReason) {
            readyIndicator += `<br><small class="text-warning"><i class="mdi mdi-alert"></i> ${blockingReason}</small>`;
        }
    }

    // Build action buttons based on status
    let actionButtons = '';

    // Unbilled items (status 1) - always show adapt/adjust buttons
    if (item.status == 1) {
        actionButtons = `
            <button class="btn btn-info btn-sm btn-adapt-product" data-id="${item.id}" data-product-name="${item.product_name || 'Unknown'}" data-product-code="${item.product_code || ''}" data-dose="${item.dose || ''}" data-price="${basePrice}" data-qty="${qty}" data-status="unbilled" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${coverageMode}" title="Adapt Product">
                <i class="mdi mdi-swap-horizontal"></i>
            </button>
            <button class="btn btn-warning btn-sm btn-adjust-qty" data-id="${item.id}" data-product-name="${item.product_name || 'Unknown'}" data-price="${basePrice}" data-qty="${qty}" data-status="unbilled" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${coverageMode}" title="Adjust Quantity">
                <i class="mdi mdi-plus-minus"></i>
            </button>
        `;
    }
    // Billed items (status 2) - show buttons only if NOT settled
    else if (item.status == 2 && !isReady && canModifyBilled(payableAmount, claimsAmount, isPaid, isValidated)) {
        actionButtons = `
            <button class="btn btn-info btn-sm btn-adapt-product" data-id="${item.id}" data-product-name="${item.product_name || 'Unknown'}" data-product-code="${item.product_code || ''}" data-dose="${item.dose || ''}" data-price="${basePrice}" data-qty="${qty}" data-status="billed" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${coverageMode}" title="Adapt Product">
                <i class="mdi mdi-swap-horizontal"></i>
            </button>
            <button class="btn btn-warning btn-sm btn-adjust-qty" data-id="${item.id}" data-product-name="${item.product_name || 'Unknown'}" data-price="${basePrice}" data-qty="${qty}" data-status="billed" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${coverageMode}" title="Adjust Quantity">
                <i class="mdi mdi-plus-minus"></i>
            </button>
        `;
    }
    // Ready items (status 2 & isReady) - NO adapt/adjust buttons

    return `
        <tr data-item-id="${item.id}" class="${isReady ? 'table-success' : ''}">
            <td class="text-center">
                ${item.is_free_form 
                    ? '<span class="badge bg-secondary text-white" style="font-size: 0.65rem;">External</span>'
                    : `<input type="checkbox" class="prescription-item-checkbox" data-id="${item.id}" ${(isReady || item.status == 1) ? '' : 'disabled'}>`
                }
            </td>
            <td>
                <strong>${item.product_name || item.free_form_name || 'Unknown'}</strong>
                ${item.dose ? `<br><small class="text-muted">Dose: ${item.dose}</small>` : ''}
            </td>
            <td class="text-center">${qty}</td>
            <td class="text-right"><strong>₦${(basePrice * qty).toLocaleString()}</strong></td>
            <td>${item.doctor_name || 'N/A'}</td>
            <td>
                <span class="request-status-badge ${statusClass}">${statusText}</span>
                ${readyIndicator}
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    ${actionButtons}
                    <button class="btn btn-success btn-sm ${item.is_free_form ? 'dispense-free-form-single-btn' : 'dispense-single-btn'}" data-id="${item.id}" title="Dispense" ${isReady ? '' : 'disabled'}>
                        <i class="mdi mdi-pill"></i>
                    </button>
                    ${!item.is_free_form ? `
                    <button class="btn btn-primary btn-sm print-single-btn" data-id="${item.id}" title="Print">
                        <i class="mdi mdi-printer"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `;
}

// Helper function to check if a billed item can be modified (adapted or qty adjusted)
// Rules:
// 1. Payable only: NOT paid
// 2. Claims only: NOT validated
// 3. Both payable + claims: NEITHER paid NOR validated
function canModifyBilled(item) {
    const payableAmount = parseFloat(item.payable_amount || 0);
    const claimsAmount = parseFloat(item.claims_amount || 0);
    const isPaid = item.payment_id != null;
    const isValidated = item.validation_status === 'validated' || item.validation_status === 'approved';

    const hasPayable = payableAmount> 0;
    const hasClaims = claimsAmount> 0;

    // If payable only, must NOT be paid
    if (hasPayable && !hasClaims) {
        return !isPaid;
    }
    // If claims only, must NOT be validated
    if (!hasPayable && hasClaims) {
        return !isValidated;
    }
    // If both, NEITHER must be settled
    if (hasPayable && hasClaims) {
        return !isPaid && !isValidated;
    }
    // Default: allow
    return true;
}

function createPrescriptionCard(item, section) {
    const payableAmount = parseFloat(item.payable_amount || 0);
    const claimsAmount = parseFloat(item.claims_amount || 0);
    const isPaid = item.payment_id != null;
    const isValidated = item.validation_status === 'validated' || item.validation_status === 'approved';

    let canDeliver = true;
    let blockReason = '';
    let deliveryHint = '';

    // Determine payment mode display
    let paymentModeHtml = '';
    if (payableAmount> 0 && claimsAmount> 0) {
        paymentModeHtml = '<span class="badge badge-info">Co-Pay</span>';
    } else if (payableAmount> 0 && claimsAmount === 0) {
        paymentModeHtml = '<span class="badge badge-secondary">Cash</span>';
    } else if (payableAmount === 0 && claimsAmount> 0) {
        paymentModeHtml = '<span class="badge badge-primary">Full HMO</span>';
    }

    // Check delivery readiness
    if (payableAmount> 0 && !isPaid) {
        canDeliver = false;
        blockReason = 'Awaiting Payment';
        deliveryHint = `Patient owes ${formatMoney(payableAmount)}`;
    } else if (claimsAmount> 0 && !isValidated) {
        canDeliver = false;
        blockReason = 'Awaiting HMO Validation';
        deliveryHint = `Claims of ${formatMoney(claimsAmount)} pending validation`;
    }

    // Payment status badges
    let paymentStatusHtml = '';
    if (isPaid && payableAmount> 0) {
        paymentStatusHtml = '<span class="badge badge-success ml-1"><i class="mdi mdi-check"></i> Paid</span>';
    }
    if (isValidated && claimsAmount> 0) {
        paymentStatusHtml += '<span class="badge badge-success ml-1"><i class="mdi mdi-check"></i> HMO Validated</span>';
    }

    // Disable checkbox if not ready for sections that can't act
    let checkboxDisabled = '';
    if (section === 'billed') {
        checkboxDisabled = 'disabled';
    }

    // Warning banner for blocked items
    let warningHtml = '';
    if (!canDeliver && blockReason) {
        warningHtml = `
            <div class="card-warning">
                <i class="mdi mdi-alert-circle"></i>
                <strong>${blockReason}</strong>: ${deliveryHint}
            </div>
        `;
    }

    let isFreeForm = item.is_free_form == 1 || item.is_free_form === true;
    let freeFormBadge = isFreeForm ? `<span class="badge badge-secondary text-white ml-2" style="font-size:0.75rem;">[Free-form]</span>` : '';
    let borderStyle = isFreeForm ? 'border-left: 4px solid #6c757d; background-color: #f8f9fa;' : '';
    let checkboxOrIcon = isFreeForm ? `<i class="mdi mdi-information-outline text-muted fs-4"></i>` : `<input type="checkbox" class="prescription-checkbox" data-id="${item.id}" ${checkboxDisabled}>`;

    return `
        <div class="request-card" data-request-id="${item.id}" data-section="${section}" style="${borderStyle}">
            <div class="card-checkbox">
                ${checkboxOrIcon}
            </div>
            <div class="card-content">
                <div class="card-header-row">
                    <div class="card-title">
                        <strong>${item.product_name || item.medication_name || 'N/A'}</strong>${freeFormBadge}
                        ${paymentModeHtml}
                        ${paymentStatusHtml}
                        ${item.adapted_from_product_id ? '<span class="badge badge-warning ml-1" title="Adapted from another product"><i class="mdi mdi-swap-horizontal"></i> Adapted</span>' : ''}
                        ${item.qty_adjusted_from ? `<span class="badge badge-info ml-1" title="Quantity adjusted from ${item.qty_adjusted_from}"><i class="mdi mdi-counter"></i> Qty Adjusted</span>` : ''}
                    </div>
                    <div class="card-meta">
                        <span class="text-muted">Qty: ${item.qty || item.quantity || 'N/A'}</span>
                        ${!isFreeForm && section === 'unbilled' ? `
                            <button type="button" class="btn btn-xs btn-outline-info ml-2 btn-adapt-product" data-id="${item.id}" data-product="${item.product_name || item.medication_name || 'N/A'}" data-product-code="${item.product_code || ''}" data-dose="${item.dose || ''}" data-qty="${item.qty || item.quantity || 1}" data-price="${item.base_price || item.price || 0}" data-status="unbilled" data-payable="0" data-claims="0" data-is-paid="false" data-is-validated="false" data-coverage-mode="${item.coverage_mode || 'cash'}" title="Change to a different product">
                                <i class="mdi mdi-swap-horizontal"></i> Adapt
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-warning ml-1 btn-adjust-qty" data-id="${item.id}" data-product="${item.product_name || item.medication_name || 'N/A'}" data-qty="${item.qty || item.quantity || 1}" data-price="${item.base_price || item.price || 0}" data-status="unbilled" data-payable="0" data-claims="0" data-is-paid="false" data-is-validated="false" data-coverage-mode="${item.coverage_mode || 'cash'}" title="Change the quantity">
                                <i class="mdi mdi-counter"></i> Adjust Qty
                            </button>
                        ` : ''}
                        ${!isFreeForm && section === 'billed' && canModifyBilled(item) ? `
                            <button type="button" class="btn btn-xs btn-outline-info ml-2 btn-adapt-product" data-id="${item.id}" data-product="${item.product_name || item.medication_name || 'N/A'}" data-product-code="${item.product_code || ''}" data-dose="${item.dose || ''}" data-qty="${item.qty || item.quantity || 1}" data-price="${item.base_price || item.price || 0}" data-status="billed" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${item.coverage_mode || 'none'}" title="Change to a different product (will update billing)">
                                <i class="mdi mdi-swap-horizontal"></i> Adapt
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-warning ml-1 btn-adjust-qty" data-id="${item.id}" data-product="${item.product_name || item.medication_name || 'N/A'}" data-qty="${item.qty || item.quantity || 1}" data-price="${item.base_price || item.price || 0}" data-status="billed" data-payable="${payableAmount}" data-claims="${claimsAmount}" data-is-paid="${isPaid}" data-is-validated="${isValidated}" data-coverage-mode="${item.coverage_mode || 'none'}" title="Change the quantity (will update billing)">
                                <i class="mdi mdi-counter"></i> Adjust Qty
                            </button>
                        ` : ''}
                        ${!isFreeForm && section === 'billed' && !canModifyBilled(item) ? `
                            <span class="text-muted ml-2" title="Cannot modify - partially settled"><i class="mdi mdi-lock-outline"></i></span>
                        ` : ''}
                    </div>
                </div>

                <div class="card-details">
                    <div class="detail-item">
                        <i class="mdi mdi-account-outline"></i>
                        <span>${item.doctor_name || 'Unknown Doctor'}</span>
                    </div>
                    <div class="detail-item">
                        <i class="mdi mdi-calendar-outline"></i>
                        <span>${item.created_at || item.created_at_formatted || 'N/A'}</span>
                    </div>
                    ${item.dose && item.dose !== 'N/A' ? `
                        <div class="detail-item">
                            <i class="mdi mdi-pill"></i>
                            <span>Dose: ${item.dose}</span>
                        </div>
                    ` : ''}
                    ${item.notes ? `
                        <div class="detail-item">
                            <i class="mdi mdi-note-text-outline"></i>
                            <span>${item.notes}</span>
                        </div>
                    ` : ''}
                </div>

                ${!isFreeForm ? `
                <div class="card-pricing">
                    ${payableAmount > 0 ? `
                        <div class="pricing-item">
                            <span class="label">Patient Pays:</span>
                            <span class="value text-primary">${formatMoney(payableAmount)}</span>
                        </div>
                    ` : ''}
                    ${claimsAmount > 0 ? `
                        <div class="pricing-item">
                            <span class="label">HMO Pays:</span>
                            <span class="value text-info">${formatMoney(claimsAmount)}</span>
                        </div>
                    ` : ''}
                </div>
                ` : ''}

                ${warningHtml}
            </div>
        </div>
    `;
}

function createPrescriptionRow(item) {
    // Determine status class and text based on workflow stage
    let statusClass = 'status-requested';
    let statusText = 'Unbilled';

    // Calculate proper ready status using HMO logic
    const payableAmount = parseFloat(item.payable_amount || 0);
    const claimsAmount = parseFloat(item.claims_amount || 0);
    const isPaid = item.payment_id != null;
    const isValidated = item.validation_status === 'validated' || item.validation_status === 'approved';

    let isReady = false;
    let blockingReason = '';

    if (item.status == 1) {
        statusClass = 'status-requested';
        statusText = 'Unbilled';
    } else if (item.status == 2) {
        // Check readiness
        if (payableAmount> 0 && !isPaid) {
            isReady = false;
            blockingReason = 'Awaiting Payment';
            statusClass = 'status-billed';
            statusText = 'Awaiting Payment';
        } else if (claimsAmount> 0 && !isValidated) {
            isReady = false;
            blockingReason = 'Awaiting HMO Validation';
            statusClass = 'status-billed';
            statusText = 'Awaiting HMO Validation';
        } else {
            isReady = true;
            statusClass = 'status-ready';
            statusText = 'Ready to Dispense';
        }
    }

    // Calculate prices
    const basePrice = parseFloat(item.base_price || item.price || 0);
    const patientPays = parseFloat(item.payable_amount || basePrice);
    const hmoPays = parseFloat(item.claims_amount || 0);
    const qty = parseInt(item.qty) || 1;

    // Determine payment type badge
    let paymentBadge = '';
    if (hmoPays> 0 && patientPays> 0) {
        paymentBadge = '<span class="badge badge-info">Co-Pay</span>';
    } else if (hmoPays> 0 && patientPays == 0) {
        paymentBadge = '<span class="badge badge-success">Full HMO</span>';
    } else {
        paymentBadge = '<span class="badge badge-secondary">Cash</span>';
    }

    // Ready indicator
    let readyIndicator = '';
    if (item.status == 2) {
        if (isPaid) {
            readyIndicator = '<span class="badge badge-success ml-1"><i class="mdi mdi-check"></i> Paid</span>';
        }
        if (isValidated) {
            readyIndicator += '<span class="badge badge-primary ml-1"><i class="mdi mdi-shield-check"></i> HMO Validated</span>';
        }
        if (!isReady && blockingReason) {
            readyIndicator += `<br><small class="text-warning"><i class="mdi mdi-alert"></i> ${blockingReason}</small>`;
        }
    }

    if (item.status == 1) {
        // Unbilled row
        return `
            <tr data-item-id="${item.id}" data-product-request-id="${item.product_request_id || item.id}">
                <td><input type="checkbox" class="prescription-item-checkbox" data-id="${item.id}" data-status="unbilled"></td>
                <td>
                    <strong>${item.product_name || 'Unknown'}</strong>
                    ${item.dose ? `<br><small class="text-muted">Dose: ${item.dose}</small>` : ''}
                </td>
                <td class="text-center">${qty}</td>
                <td class="text-right"><strong>₦${(basePrice * qty).toLocaleString()}</strong></td>
                <td>${item.doctor_name || 'N/A'}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-primary btn-sm mark-billed-btn" data-id="${item.id}" title="Mark Billed">
                            <i class="mdi mdi-cash-register"></i>
                        </button>
                        <button class="btn btn-info btn-sm edit-item-btn" data-id="${item.id}" title="Edit">
                            <i class="mdi mdi-pencil"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    } else {
        // Billed or Ready row
        return `
            <tr data-item-id="${item.id}" data-product-request-id="${item.product_request_id || item.id}" class="${isReady ? 'table-success' : ''}">
                <td><input type="checkbox" class="prescription-item-checkbox" data-id="${item.id}" data-status="${isReady ? 'ready' : 'billed'}" ${isReady ? '' : 'disabled'}></td>
                <td>
                    <strong>${item.product_name || 'Unknown'}</strong>
                    ${item.dose ? `<br><small class="text-muted">Dose: ${item.dose}</small>` : ''}
                </td>
                <td class="text-center">${qty}</td>
                <td class="text-right">
                    <strong class="${patientPays> 0 ? 'text-danger' : 'text-success'}">
                        ₦${(patientPays * qty).toLocaleString()}
                    </strong>
                </td>
                <td class="text-right">
                    ${hmoPays> 0 ? `<strong class="text-primary">₦${(hmoPays * qty).toLocaleString()}</strong>` : '<span class="text-muted">-</span>'}
                </td>
                <td>
                    <span class="request-status-badge ${statusClass}">${statusText}</span>
                    ${readyIndicator}
                    <br>${paymentBadge}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-success btn-sm dispense-single-btn" data-id="${item.id}" title="Dispense" ${isReady ? '' : 'disabled'}>
                            <i class="mdi mdi-pill"></i>
                        </button>
                        <button class="btn btn-primary btn-sm print-single-btn" data-id="${item.id}" title="Print">
                            <i class="mdi mdi-printer"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }
}

function attachPrescriptionEventListeners() {
    // Checkboxes
    $('.prescription-item-checkbox').off('change').on('change', updateDispenseSummary);

    $('.select-status-checkbox').off('change').on('change', function() {
        const status = $(this).data('status');
        $(`#${status}-tbody .prescription-item-checkbox`).prop('checked', $(this).is(':checked'));
        updateDispenseSummary();
    });

    // Action buttons
    $('.dispense-single-btn').off('click').on('click', function() {
        const itemId = $(this).data('id');
        dispenseItems([itemId]);
    });

    $('.dispense-free-form-single-btn').off('click').on('click', function() {
        const itemId = $(this).data('id');
        dispenseFreeFormMed(itemId);
    });

    $('.print-single-btn').off('click').on('click', function() {
        const itemId = $(this).data('id');
        printPrescription([itemId]);
    });

    $('.mark-billed-btn').off('click').on('click', function() {
        const itemId = $(this).data('id');
        markItemBilled(itemId);
    });

    $('.edit-item-btn').off('click').on('click', function() {
        const itemId = $(this).data('id');
        editPrescriptionItem(itemId);
    });
}

// Card-based handlers for new layout
function initializePrescriptionHandlers() {
    // Select-all handlers
    $('.select-all-checkbox').off('change').on('change', function() {
        const isChecked = $(this).is(':checked');
        const section = $(this).attr('id').replace('select-all-', '');

        $(`.request-section[data-section="${section}"] .prescription-checkbox:not(:disabled)`).prop('checked', isChecked);

        updateSectionButtons(section);
    });

    // Individual checkbox handlers
    $('.prescription-checkbox').off('change').on('change', function() {
        const card = $(this).closest('.request-card');
        const section = card.data('section');

        updateSectionButtons(section);
    });

    // Adapt product button handler
    $('.btn-adapt-product').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const productRequestId = $btn.data('id');
        const productName = $btn.data('product') || $btn.data('product-name') || 'Unknown';
        const dose = $btn.data('dose') || '';
        const qty = parseInt($btn.data('qty')) || 1;
        const price = parseFloat($btn.data('price')) || 0;
        const status = $btn.data('status') || 'unbilled';
        const payable = parseFloat($btn.data('payable')) || 0;
        const claims = parseFloat($btn.data('claims')) || 0;
        const isPaid = $btn.data('is-paid') === true || $btn.data('is-paid') === 'true';
        const isValidated = $btn.data('is-validated') === true || $btn.data('is-validated') === 'true';
        const coverageMode = $btn.data('coverage-mode') || 'cash';
        const productCode = $btn.data('product-code') || '';

        openAdaptationModal(productRequestId, productName, dose, qty, price, status, payable, claims, isPaid, isValidated, coverageMode, productCode);
    });

    // Quantity adjustment button handler
    $('.btn-adjust-qty').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const productRequestId = $btn.data('id');
        const productName = $btn.data('product') || $btn.data('product-name') || 'Unknown';
        const qty = parseInt($btn.data('qty')) || 1;
        const price = parseFloat($btn.data('price')) || 0;
        const status = $btn.data('status') || 'unbilled';
        const payable = parseFloat($btn.data('payable')) || 0;
        const claims = parseFloat($btn.data('claims')) || 0;
        const isPaid = $btn.data('is-paid') === true || $btn.data('is-paid') === 'true';
        const isValidated = $btn.data('is-validated') === true || $btn.data('is-validated') === 'true';
        const coverageMode = $btn.data('coverage-mode') || 'cash';

        openQtyAdjustmentModal(productRequestId, productName, qty, price, status, payable, claims, isPaid, isValidated, coverageMode);
    });

    // Action button handlers
    $('#btn-record-billing').off('click').on('click', function() {
        const selected = getSelectedPrescriptions('unbilled');
        if (selected.length> 0) {
            recordBillingForPrescriptions(selected);
        }
    });

    $('#btn-dispense-ready').off('click').on('click', function() {
        const selected = getSelectedPrescriptions('ready');
        if (selected.length> 0) {
            dispenseItems(selected);
        }
    });

    $('#btn-dismiss-unbilled, #btn-dismiss-billed').off('click').on('click', function() {
        const section = $(this).attr('id').includes('unbilled') ? 'unbilled' : 'billed';
        const selected = getSelectedPrescriptions(section);
        if (selected.length> 0) {
            dismissPrescriptions(selected);
        }
    });
}

function updateSectionButtons(section) {
    const selectedCount = $(`.request-section[data-section="${section}"] .prescription-checkbox:checked`).length;

    if (section === 'unbilled') {
        $('#btn-record-billing, #btn-dismiss-unbilled').prop('disabled', selectedCount === 0);
    } else if (section === 'billed') {
        $('#btn-dismiss-billed').prop('disabled', selectedCount === 0);
    } else if (section === 'ready') {
        $('#btn-dispense-ready').prop('disabled', selectedCount === 0);
    }
}

function getSelectedPrescriptions(section) {
    const selected = [];
    $(`.request-section[data-section="${section}"] .prescription-checkbox:checked`).each(function() {
        selected.push($(this).data('id'));
    });
    return selected;
}

function dispenseFreeFormMed(requestId) {
    Swal.fire({
        title: 'Mark as Dispensed',
        text: 'Enter the quantity dispensed:',
        input: 'number',
        inputValue: 1,
        inputAttributes: {
            min: 1,
            step: 1
        },
        showCancelButton: true,
        confirmButtonText: 'Record as Dispensed',
        showLoaderOnConfirm: true,
        preConfirm: (qty) => {
            return $.ajax({
                url: '{{ route("pharmacy.dispense-free-form") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    request_id: requestId,
                    qty_dispensed: qty
                }
            }).catch(error => {
                Swal.showValidationMessage(
                    `Request failed: ${error.responseJSON?.message || error.statusText}`
                );
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            toastr.success('Medication marked as dispensed.');
            if (window.prescHistoryTable) {
                window.prescHistoryTable.ajax.reload(null, false);
            }
        }
    });
}

function recordBillingForPrescriptions(itemIds) {
    if (!confirm(`Record billing for ${itemIds.length} prescription(s)?`)) {
        return;
    }

    $.ajax({
        url: '{{ route("pharmacy.record-billing") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            prescription_ids: itemIds
        },
        success: function(response) {
            toastr.success(response.message || 'Billing recorded successfully');
            loadPrescriptionItems(currentStatusFilter);
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to record billing');
        }
    });
}

function dismissPrescriptions(itemIds) {
    if (!confirm(`Dismiss ${itemIds.length} prescription(s)?`)) {
        return;
    }

    $.ajax({
        url: '{{ route("pharmacy.dismiss") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            prescription_ids: itemIds
        },
        success: function(response) {
            toastr.success(response.message || 'Prescriptions dismissed');
            loadPrescriptionItems(currentStatusFilter);
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to dismiss prescriptions');
        }
    });
}

// Update dispense summary when items are selected
function updateDispenseSummary() {
    const selectedItems = $('.prescription-item-checkbox:checked');
    const count = selectedItems.length;

    if (count === 0) {
        $('#dispense-summary-card').hide();
        $('#dispense-selected-btn').prop('disabled', true);
        $('#print-selected-btn').prop('disabled', true);
        return;
    }

    $('#dispense-count').text(count);
    $('#dispense-summary-card').show();
    $('#dispense-selected-btn').prop('disabled', false);
    $('#print-selected-btn').prop('disabled', false);
}

// Dispense selected items
function dispenseItems(itemIds) {
    if (!itemIds || itemIds.length === 0) {
        toastr.warning('Please select items to dispense');
        return;
    }

    if (!confirm(`Dispense ${itemIds.length} medication(s)?`)) {
        return;
    }

    $.ajax({
        url: '{{ route("pharmacy.dispense") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            product_request_ids: itemIds
        },
        success: function(response) {
            toastr.success('Medications dispensed successfully!');
            loadPrescriptionItems();
            loadQueueCounts();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to dispense medications');
        }
    });
}

// Mark prescription item as billed
function markItemBilled(itemId) {
    if (!confirm('Mark this item as billed?')) {
        return;
    }

    $.ajax({
        url: `{{ url('/pharmacy-workbench/prescription/${itemId}/mark-billed') }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            toastr.success('Item marked as billed');
            loadPrescriptionItems();
        },
        error: function(xhr) {
            toastr.error('Failed to mark item as billed');
        }
    });
}

// Edit prescription item
function editPrescriptionItem(itemId) {
    toastr.info('Edit functionality coming soon');
    // Can implement modal edit dialog here later
}

// Print prescription slip - Load in modal instead of new window
function printPrescription(itemIds) {
    if (!itemIds || itemIds.length === 0) {
        toastr.warning('Please select items to print');
        return;
    }

    // Show loading in modal
    $('#prescriptionSlipModal').modal('show');
    $('#prescription-slip-content').html('<div class="text-center p-5"><i class="mdi mdi-loading mdi-spin" style="font-size: 3rem;"></i><p class="mt-3">Loading prescription slip...</p></div>');

    // Fetch prescription slip HTML via AJAX
    $.ajax({
        url: '{{ route("pharmacy.print-prescription-slip") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            product_request_ids: itemIds
        },
        success: function(response) {
            // Load the HTML into modal
            $('#prescription-slip-content').html(response);
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to load prescription slip');
            $('#prescriptionSlipModal').modal('hide');
        }
    });
}

// Print from modal
function printPrescriptionSlipFromModal() {
    const printContent = document.getElementById('prescription-slip-content').innerHTML;
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Prescription Slip</title>
        </head>
        <body>
            ${printContent}
            <script>
                window.onload = function() {
                    window.print();
                    window.onafterprint = function() { window.close(); };
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Print selected billing prescriptions
function printSelectedBillingPrescriptions() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Check both DataTable checkboxes and card-based checkboxes
    const itemIds = [];

    // Debug: Check all checkboxes in the table (not just checked)
    console.log('Billing - Total checkboxes in table:', $('#presc_billing_table').find('.presc-billing-check').length);
    console.log('Billing - Checked checkboxes:', $('#presc_billing_table').find('.presc-billing-check:checked').length);

    // From DataTable - use attr to get the raw value
    $('#presc_billing_table').find('.presc-billing-check:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        console.log('Found checked checkbox with data-id:', id);
        if (id) itemIds.push(id);
    });

    // From card-based view (unbilled section)
    $('.request-section[data-section="unbilled"] .prescription-checkbox:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) itemIds.push(id);
    });

    console.log('Billing print - Item IDs:', itemIds);

    if (itemIds.length === 0) {
        toastr.warning('Please select items to print');
        return;
    }

    printPrescription(itemIds);
}

// Print selected pending prescriptions
function printSelectedPendingPrescriptions() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Check both DataTable checkboxes and card-based checkboxes
    const itemIds = [];

    // From DataTable - use attr to get the raw value
    $('#presc_pending_table').find('.presc-pending-check:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) itemIds.push(id);
    });

    // From card-based view (billed section - pending payment/validation)
    $('.request-section[data-section="billed"] .prescription-checkbox:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) itemIds.push(id);
    });

    console.log('Pending print - Found checkboxes:', $('#presc_pending_table').find('.presc-pending-check:checked').length);
    console.log('Pending print - Item IDs:', itemIds);

    if (itemIds.length === 0) {
        toastr.warning('Please select items to print');
        return;
    }

    printPrescription(itemIds);
}

// Print all pending prescriptions
function printPendingPrescriptions() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Get all rows from pending table
    const pendingTable = $('#presc_pending_table').DataTable();
    const allData = pendingTable.rows().data().toArray();

    if (allData.length === 0) {
        toastr.warning('No pending prescriptions to print');
        return;
    }

    const itemIds = allData.map(row => row.id);
    printPrescription(itemIds);
}

// Print selected ready prescriptions
function printReadyPrescriptions() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Check both DataTable checkboxes and card-based checkboxes
    const itemIds = [];

    // From DataTable - use attr to get the raw value
    $('#presc_dispense_table').find('.presc-dispense-check:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) itemIds.push(id);
    });

    // From card-based view (ready section)
    $('.request-section[data-section="ready"] .prescription-checkbox:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) itemIds.push(id);
    });

    console.log('Ready print - Found checkboxes:', $('#presc_dispense_table').find('.presc-dispense-check:checked').length);
    console.log('Ready print - Item IDs:', itemIds);

    if (itemIds.length === 0) {
        toastr.warning('Please select items to print');
        return;
    }

    printPrescription(itemIds);
}

// Dispense selected prescriptions from dispense tab
function dispenseSelectedPrescriptions() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Get selected store
    const storeId = $('#dispense-store-select').val();
    if (!storeId) {
        toastr.warning('Please select a store to dispense from');
        $('#dispense-store-select').focus();
        return;
    }

    // Check both DataTable checkboxes and card-based checkboxes
    const itemIds = [];

    // From DataTable
    $('.presc-dispense-check:checked').each(function() {
        itemIds.push($(this).data('id'));
    });

    // From card-based view (ready section)
    $('.request-section[data-section="ready"] .prescription-checkbox:checked').each(function() {
        itemIds.push($(this).data('id'));
    });

    if (itemIds.length === 0) {
        toastr.warning('Please select items to dispense');
        return;
    }

    if (!confirm(`Dispense ${itemIds.length} prescription(s) from selected store?`)) {
        return;
    }

    $.ajax({
        url: '{{ route("pharmacy.dispense") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            product_request_ids: itemIds,
            store_id: storeId
        },
        success: function(response) {
            toastr.success(response.message || 'Prescriptions dispensed successfully');
            initializePrescriptionDataTables(currentPatient);
            loadQueueCounts();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to dispense prescriptions');
        }
    });
}

// Load patient dispensing history
function loadPatientDispensingHistory() {
    if (!currentPatient) return;

    const tbody = $('#receipts-tbody'); // Using receipts tbody for history
    tbody.html(`
        <tr>
            <td colspan="6" class="text-center text-muted py-5">
                <i class="mdi mdi-loading mdi-spin" style="font-size: 3rem;"></i>
                <p>Loading dispensing history...</p>
            </td>
        </tr>
    `);

    $.ajax({
        url: `{{ url('/pharmacy-workbench/patient/${currentPatient}/dispensing-history') }}`,
        method: 'GET',
        success: function(response) {
            renderDispensingHistory(response.items);
        },
        error: function(xhr) {
            console.error('Failed to load dispensing history', xhr);
            tbody.html(`
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="mdi mdi-alert-circle" style="font-size: 3rem;"></i>
                        <p>Failed to load history</p>
                    </td>
                </tr>
            `);
        }
    });
}

// Render dispensing history
function renderDispensingHistory(history) {
    const tbody = $('#receipts-tbody');
    tbody.empty();

    if (!history || history.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="6" class="text-center text-muted py-5">
                    <i class="mdi mdi-history" style="font-size: 3rem;"></i>
                    <p>No dispensing history for this patient</p>
                </td>
            </tr>
        `);
        return;
    }

    history.forEach(item => {
        const basePrice = parseFloat(item.base_price || 0);
        const patientPaid = parseFloat(item.payable_amount || 0);
        const hmoPaid = parseFloat(item.claims_amount || 0);
        const qty = parseInt(item.qty) || 1;

        const row = `
            <tr>
                <td>${item.dispense_date || 'N/A'}</td>
                <td>
                    <strong>${item.product_name || item.medication_name || 'Unknown'}</strong>
                    ${item.dose ? `<br><small class="text-muted">Dose: ${item.dose}</small>` : ''}
                </td>
                <td class="text-center">${qty}</td>
                <td class="text-right"><span class="text-muted">₦${(basePrice * qty).toLocaleString()}</span></td>
                <td class="text-right">
                    <strong class="${patientPaid> 0 ? 'text-danger' : 'text-success'}">
                        ₦${(patientPaid * qty).toLocaleString()}
                    </strong>
                </td>
                <td class="text-right">
                    ${hmoPaid> 0 ? `<strong class="text-primary">₦${(hmoPaid * qty).toLocaleString()}</strong>` : '<span class="text-muted">-</span>'}
                </td>
                <td>${item.dispensed_by || 'System'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary reprint-history-btn" data-id="${item.product_request_id}">
                        <i class="mdi mdi-printer"></i> Reprint
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });

    // Reprint button handler
    $('.reprint-history-btn').on('click', function() {
        const itemId = $(this).data('id');
        printPrescription([itemId]);
    });
}

// Event handlers for dispense and print selected buttons
$(document).on('click', '#dispense-selected-btn', function() {
    const itemIds = [];
    $('.prescription-item-checkbox:checked').each(function() {
        itemIds.push($(this).data('id'));
    });
    dispenseItems(itemIds);
});

$(document).on('click', '#print-selected-btn', function() {
    const itemIds = [];
    $('.prescription-item-checkbox:checked').each(function() {
        itemIds.push($(this).data('id'));
    });
    printPrescription(itemIds);
});

// Print tab option handlers
$(document).on('click', '#print-all-pending', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    // Get all pending prescription IDs
    const itemIds = [];
    $('.prescription-item-checkbox').each(function() {
        itemIds.push($(this).data('id'));
    });
    if (itemIds.length === 0) {
        toastr.warning('No pending prescriptions to print');
        return;
    }
    printPrescription(itemIds);
});

$(document).on('click', '#print-dispensed-today', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    toastr.info('Loading today\'s dispensed medications...');
    // Switch to history tab and filter by today
    switchWorkspaceTab('history');
    loadPatientDispensingHistory();
});

$(document).on('click', '#print-patient-medication-list', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    // Print all medications (pending and dispensed)
    toastr.info('Generating medication list...');
    printPrescription(['all']); // Special flag for all medications
});

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
    const price = parseFloat(row.find('td:eq(3)').text().replace('₦', '').replace(/,/g, ''));
    const discount = parseFloat(row.find('.item-discount-input').val()) || 0;

    const subtotal = price * qty;
    const discountAmount = subtotal * (discount / 100);
    const total = subtotal - discountAmount;

    row.find('.item-total').text(`₦${total.toLocaleString()}`);
}

function updatePaymentSummary() {
    const selectedItems = $('.billing-item-checkbox:checked');

    if (selectedItems.length === 0) {
        $('#payment-summary-card').hide();
        $('#process-payment-btn').prop('disabled', true);
        return;
    }

    let subtotal = 0;
    let totalDiscount = 0;

    selectedItems.each(function() {
        const row = $(this).closest('tr');
        const qty = parseFloat(row.find('.item-qty-input').val()) || 1;
        const price = parseFloat(row.find('td:eq(3)').text().replace('₦', '').replace(/,/g, ''));
        const discountPercent = parseFloat(row.find('.item-discount-input').val()) || 0;

        const itemSubtotal = price * qty;
        const itemDiscount = itemSubtotal * (discountPercent / 100);

        subtotal += itemSubtotal;
        totalDiscount += itemDiscount;
    });

    const total = subtotal - totalDiscount;

    $('#summary-subtotal').text(`₦${subtotal.toLocaleString()}`);
    $('#summary-discount').text(`₦${totalDiscount.toLocaleString()}`);
    $('#summary-total').text(`₦${total.toLocaleString()}`);

    $('#payment-summary-card').show();
    $('#process-payment-btn').prop('disabled', false);
}

// Process payment button click
$(document).on('click', '#process-payment-btn, #confirm-payment-btn', function() {
    processPayment();
});

function processPayment() {
    const selectedItems = $('.billing-item-checkbox:checked');

    if (selectedItems.length === 0) {
        toastr.warning('Please select items to process payment');
        return;
    }

    const items = [];
    selectedItems.each(function() {
        const row = $(this).closest('tr');
        items.push({
            id: $(this).data('id'),
            qty: parseFloat(row.find('.item-qty-input').val()) || 1,
            discount: parseFloat(row.find('.item-discount-input').val()) || 0
        });
    });

    const paymentType = $('#payment-method').val();
    const referenceNo = $('#payment-reference').val();
    const bankId = $('#payment-bank').val();
    const totalPayable = parseFloat($('#summary-total').text().replace('₦', '').replace(/,/g, ''));

    // Validate bank selection for non-cash payments
    if (['POS', 'TRANSFER', 'MOBILE'].includes(paymentType) && !bankId) {
        toastr.warning('Please select a bank for this payment method');
        return;
    }

    // Validate account balance payment
    if (paymentType === 'ACCOUNT') {
        if (currentAccountBalance <= 0) {
            toastr.error('Insufficient account balance');
            return;
        }
        if (totalPayable> currentAccountBalance) {
            toastr.error(`Insufficient account balance. Available: ₦${currentAccountBalance.toLocaleString()}`);
            return;
        }
        if (!confirm(`Deduct ₦${totalPayable.toLocaleString()} from account balance?`)) {
            return;
        }
    }

    // Show loading state
    const $confirmBtn = $('#confirm-payment-btn');
    const originalText = $confirmBtn.html();
    $confirmBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing Payment...');

    $.ajax({
        url: '{{ url('/billing-workbench/process-payment') }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            payment_type: paymentType,
            payment_method: paymentType,
            bank_id: bankId || null,
            reference_no: referenceNo,
            items: items
        },
        success: function(response) {
            // Reset button state
            $confirmBtn.prop('disabled', false).html(originalText);

            toastr.success('Payment processed successfully!');

            // Generate new reference number for next payment
            generateReferenceNumber();

            // Display receipt in modal
            $('#modal-receipt-a4').html(response.receipt_a4);
            $('#modal-receipt-thermal').html(response.receipt_thermal);

            // Reset tabs to A4
            $('.receipt-modal-tab').removeClass('active');
            $('.receipt-modal-tab[data-format="a4"]').addClass('active');
            $('#modal-receipt-a4').show();
            $('#modal-receipt-thermal').hide();

            // Show modal
            $('#receiptPreviewModal').modal('show');

            $('#payment-summary-card').hide();

            // Clear all prescription selections and reset summary
            $('.prescription-item-checkbox').prop('checked', false);
            $('#select-all-items').prop('checked', false);
            $('#summary-subtotal').text('₦0.00');
            $('#summary-discount').text('₦0.00');
            $('#summary-total').text('₦0.00');

            // Reload prescription items
            loadPrescriptionItems();

            // Reload account balance to reflect payment deduction
            loadAccountBalance(currentPatient);

            // Refresh receipts to show new payment
            loadPatientReceipts();

            // If account tab is active, reload it
            if ($('#account-tab').hasClass('active')) {
                loadAccountSummary();
            }

            // Update queue counts
            loadQueueCounts();
        },
        error: function(xhr) {
            // Reset button state on error
            $confirmBtn.prop('disabled', false).html(originalText);

            toastr.error(xhr.responseJSON?.message || 'Payment processing failed');
        }
    });
}

$(document).on('click', '#print-thermal-receipt', function() {
    printReceipt('receipt-content-thermal');
});

$(document).on('click', '#close-receipt', function() {
    $('#receipt-display').hide();
    $('#receipt-content-a4').empty();
    $('#receipt-content-thermal').empty();
    $('#payment-summary-card').show();
});

function printReceipt(elementId) {
    const content = $(`#${elementId}`).html();
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Receipt</title>');
    printWindow.document.write('<style>body{font-family: Arial, sans-serif; padding: 20px;} table{width: 100%; border-collapse: collapse;} th, td{padding: 8px; text-align: left; border-bottom: 1px solid #ddd;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

function loadPatientReceipts() {
    if (!currentPatient) return;

    $.ajax({
        url: `{{ url('/billing-workbench/patient/${currentPatient}/receipts') }}`,
        method: 'GET',
        success: function(response) {
            renderReceipts(response.receipts);
            if (response.stats) {
                updateReceiptsStats(response.stats);
            }
        },
        error: function(xhr) {
            console.error('Failed to load receipts', xhr);
            toastr.error('Failed to load receipts');
        }
    });
}

function updatePrintSelectedButton() {
    const selected = $('.receipt-checkbox:checked').length;
    $('#print-selected-receipts').prop('disabled', selected === 0);
}

$(document).on('click', '#print-selected-receipts', function() {
    const paymentIds = [];
    $('.receipt-checkbox:checked').each(function() {
        paymentIds.push($(this).data('id'));
    });
    reprintReceipt(paymentIds);
});

function reprintReceipt(paymentIds) {
    if (!paymentIds || paymentIds.length === 0) {
        toastr.warning('Please select receipts to print');
        return;
    }

    // Show loading state
    toastr.info('Generating receipt...');

    $.ajax({
        url: '{{ url('/billing-workbench/print-receipt') }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            payment_ids: paymentIds
        },
        success: function(response) {
            // Show in modal
            $('#modal-receipt-a4').html(response.receipt_a4);
            $('#modal-receipt-thermal').html(response.receipt_thermal);

            // Reset tabs to A4
            $('.receipt-modal-tab').removeClass('active');
            $('.receipt-modal-tab[data-format="a4"]').addClass('active');
            $('#modal-receipt-a4').show();
            $('#modal-receipt-thermal').hide();

            // Show modal
            $('#receiptPreviewModal').modal('show');
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to generate receipt');
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
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Receipt</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
    printWindow.document.write('th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }');
    printWindow.document.write('.text-center { text-align: center; }');
    printWindow.document.write('.text-right { text-align: right; }');
    printWindow.document.write('.font-weight-bold { font-weight: bold; }');
    printWindow.document.write('@media print { body { padding: 0; } }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

// Account Tab
function loadAccountSummary() {
    if (!currentPatient) return;

    $.ajax({
        url: `{{ url('/billing-workbench/patient/${currentPatient}/account-summary') }}`,
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

    if (balance> 0) {
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

    // Highlight today preset
    $('.my-trans-date-preset').removeClass('active btn-primary').addClass('btn-outline-primary');
    $('.my-trans-date-preset[data-preset="today"]').removeClass('btn-outline-primary').addClass('active btn-primary');
});

function populateMyTransactionsBankDropdown() {
    const $bankSelect = $('#my-trans-bank');
    $bankSelect.find('option:not(:first)').remove();

    if (availableBanks.length> 0) {
        availableBanks.forEach(bank => {
            $bankSelect.append(`<option value="${bank.id}">${bank.name}</option>`);
        });
    }
}

// Date Preset Handlers
$(document).on('click', '.my-trans-date-preset', function() {
    const preset = $(this).data('preset');
    const today = new Date();
    let fromDate, toDate;

    $('.my-trans-date-preset').removeClass('active btn-primary').addClass('btn-outline-primary');
    $(this).removeClass('btn-outline-primary').addClass('active btn-primary');

    switch(preset) {
        case 'today':
            fromDate = toDate = today;
            break;
        case 'yesterday':
            fromDate = toDate = new Date(today.setDate(today.getDate() - 1));
            break;
        case 'this_week':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            fromDate = startOfWeek;
            toDate = new Date();
            break;
        case 'last_7_days':
            fromDate = new Date(today.setDate(today.getDate() - 7));
            toDate = new Date();
            break;
        case 'this_month':
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            toDate = new Date();
            break;
        case 'last_month':
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            toDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'custom':
            // Just enable date inputs
            $('#my-trans-from-date').focus();
            return;
    }

    if (fromDate && toDate) {
        $('#my-trans-from-date').val(fromDate.toISOString().split('T')[0]);
        $('#my-trans-to-date').val(toDate.toISOString().split('T')[0]);

        // Auto-load transactions
        const paymentType = $('#my-trans-payment-type').val();
        const bankId = $('#my-trans-bank').val();
        loadMyTransactions(
            $('#my-trans-from-date').val(),
            $('#my-trans-to-date').val(),
            paymentType,
            bankId
        );
    }
});

$(document).on('click', '#load-my-transactions', function() {
    const fromDate = $('#my-trans-from-date').val();
    const toDate = $('#my-trans-to-date').val();
    const paymentType = $('#my-trans-payment-type').val();
    const bankId = $('#my-trans-bank').val();

    if (!fromDate || !toDate) {
        toastr.warning('Please select date range');
        return;
    }

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

// Excel Export for My Transactions
$(document).on('click', '#export-my-transactions-excel', function() {
    const fromDate = $('#my-trans-from-date').val();
    const toDate = $('#my-trans-to-date').val();
    const paymentType = $('#my-trans-payment-type').val();
    const bankId = $('#my-trans-bank').val();

    if (!fromDate || !toDate) {
        toastr.warning('Please load transactions first');
        return;
    }

    const transactions = [];
    $('#my-transactions-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            const row = {};
            row['Date'] = $(this).find('td:eq(0)').text().trim();
            row['Patient'] = $(this).find('td:eq(1)').text().trim();
            row['File No'] = $(this).find('td:eq(2)').text().trim();
            row['Reference'] = $(this).find('td:eq(3)').text().trim();
            row['Product'] = $(this).find('td:eq(4)').text().trim();
            row['Quantity'] = $(this).find('td:eq(5)').text().trim();
            row['Unit Price'] = $(this).find('td:eq(6)').text().trim();
            row['Payment Method'] = $(this).find('td:eq(7)').text().trim();
            row['Bank'] = $(this).find('td:eq(8)').text().trim();
            row['Amount'] = $(this).find('td:eq(9)').text().trim();
            row['Discount'] = $(this).find('td:eq(10)').text().trim();
            transactions.push(row);
        }
    });

    if (transactions.length === 0) {
        toastr.warning('No transactions to export');
        return;
    }

    // Create summary data
    const summary = {
        'Total Transactions': $('#my-total-transactions').text(),
        'Gross Amount': $('#my-total-amount').text(),
        'Total Discounts': $('#my-total-discounts').text(),
        'Net Amount': $('#my-net-amount').text()
    };

    // Generate Excel file
    const wb = XLSX.utils.book_new();

    // Summary Sheet
    const summaryData = Object.keys(summary).map(key => [key, summary[key]]);
    summaryData.unshift(['My Transactions Report']);
    summaryData.push([]);
    summaryData.push(['Period', `${fromDate} to ${toDate}`]);
    summaryData.push(['Generated', new Date().toLocaleString()]);
    summaryData.push([]);

    const ws1 = XLSX.utils.aoa_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, ws1, 'Summary');

    // Transactions Sheet
    const ws2 = XLSX.utils.json_to_sheet(transactions);
    XLSX.utils.book_append_sheet(wb, ws2, 'Transactions');

    // Download
    XLSX.writeFile(wb, `My_Transactions_${fromDate}_to_${toDate}.xlsx`);
    toastr.success('Excel file downloaded successfully');
});

// Store current transactions globally for exports
let currentMyTransactions = [];
let currentMyTransactionsSummary = {};

function loadMyTransactions(fromDate, toDate, paymentType, bankId) {
    // Show loading indicator
    $('#my-transactions-tbody').html(`
        <tr>
            <td colspan="12" class="text-center py-5">
                <i class="mdi mdi-loading mdi-spin" style="font-size: 3rem;"></i>
                <p>Loading transactions...</p>
            </td>
        </tr>
    `);

    $.ajax({
        url: '{{ url('/pharmacy-workbench/my-transactions') }}',
        method: 'GET',
        data: {
            from_date: fromDate,
            to_date: toDate,
            payment_type: paymentType,
            bank_id: bankId
        },
        success: function(response) {
            // Store globally for exports
            currentMyTransactions = response.transactions || response.items || [];
            currentMyTransactionsSummary = response.summary || response.stats || {};

            renderMyTransactions(currentMyTransactions);
            renderMyTransactionsSummary(currentMyTransactionsSummary);
            renderMyTransactionsCharts(currentMyTransactions, currentMyTransactionsSummary);
        },
        error: function(xhr) {
            $('#my-transactions-tbody').html(`
                <tr>
                    <td colspan="12" class="text-center text-danger py-5">
                        <i class="mdi mdi-alert-circle-outline" style="font-size: 3rem;"></i>
                        <p>Failed to load transactions</p>
                    </td>
                </tr>
            `);
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
                <td colspan="12" class="text-center text-muted py-5">
                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                    <p>No transactions found for the selected period</p>
                </td>
            </tr>
        `);
        return;
    }

    transactions.forEach(tx => {
        const date = new Date(tx.created_at);
        const formattedDate = date.toLocaleDateString('en-GB');
        const formattedTime = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

        const row = `
            <tr>
                <td>
                    <div>${formattedDate}</div>
                    <small class="text-muted">${formattedTime}</small>
                </td>
                <td>${tx.patient_name}</td>
                <td>${tx.file_no}</td>
                <td><span class="badge badge-info">${tx.reference_no || 'N/A'}</span></td>
                <td>
                    <div>${tx.product_name || 'N/A'}</div>
                </td>
                <td>${tx.quantity || 1}</td>
                <td>₦${parseFloat(tx.unit_price || 0).toLocaleString()}</td>
                <td><span class="badge badge-${getPaymentTypeBadgeClass(tx.payment_type)}">${tx.payment_type}</span></td>
                <td>${tx.bank_name || '-'}</td>
                <td class="font-weight-bold">₦${parseFloat(tx.total).toLocaleString()}</td>
                <td class="text-danger">₦${parseFloat(tx.total_discount || 0).toLocaleString()}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary view-transaction-details" data-id="${tx.id}" title="View Details">
                        <i class="mdi mdi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function getPaymentTypeBadgeClass(type) {
    const badges = {
        'CASH': 'success',
        'POS': 'primary',
        'TRANSFER': 'info',
        'MOBILE': 'warning',
        'HMO': 'secondary'
    };
    return badges[type] || 'secondary';
}

function renderMyTransactionsSummary(summary) {
    $('#my-total-transactions').text(summary.count || 0);
    $('#my-total-amount').text(`₦${parseFloat(summary.total_amount || 0).toLocaleString()}`);
    $('#my-total-discounts').text(`₦${parseFloat(summary.total_discount || 0).toLocaleString()}`);
    $('#my-net-amount').text(`₦${parseFloat(summary.net_amount || 0).toLocaleString()}`);

    // Render breakdown by payment type
    const breakdown = $('#payment-type-breakdown');
    breakdown.empty();

    if (summary.by_type && Object.keys(summary.by_type).length> 0) {
        let html = '<h6 class="mt-3 mb-2">Breakdown by Payment Type</h6><div class="row">';
        Object.keys(summary.by_type).forEach(type => {
            const data = summary.by_type[type];
            html += `
                <div class="col-md-3 col-sm-6 mb-2">
                    <div style="padding: 1rem; background: white; border-radius: 0.5rem; border: 1px solid #dee2e6;">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge badge-${getPaymentTypeBadgeClass(type)} mr-2">${type}</span>
                            <small class="text-muted">${data.count} txns</small>
                        </div>
                        <div style="font-size: 1.2rem; color: var(--hospital-primary); font-weight: 600;">
                            ₦${parseFloat(data.amount).toLocaleString()}
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        breakdown.html(html);
    }

    $('#my-transactions-summary').show();
}

function renderMyTransactionsCharts(transactions, summary) {
    // Payment Method Pie Chart
    if (summary.by_type && Object.keys(summary.by_type).length> 0) {
        const ctx = document.getElementById('my-trans-payment-chart');
        if (ctx) {
            // Destroy existing chart
            if (window.myTransPaymentChart) {
                window.myTransPaymentChart.destroy();
            }

            const labels = Object.keys(summary.by_type);
            const data = labels.map(type => summary.by_type[type].amount);
            const colors = labels.map(type => {
                const colorMap = {
                    'CASH': '#28a745',
                    'POS': '#007bff',
                    'TRANSFER': '#17a2b8',
                    'MOBILE': '#ffc107',
                    'HMO': '#6c757d'
                };
                return colorMap[type] || '#6c757d';
            });

            window.myTransPaymentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ₦' + context.parsed.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // Top Products Bar Chart
    if (transactions && transactions.length> 0) {
        const productSales = {};
        transactions.forEach(tx => {
            if (tx.product_name) {
                if (!productSales[tx.product_name]) {
                    productSales[tx.product_name] = 0;
                }
                productSales[tx.product_name] += parseFloat(tx.total);
            }
        });

        const sortedProducts = Object.entries(productSales)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5);

        if (sortedProducts.length> 0) {
            const ctx = document.getElementById('my-trans-products-chart');
            if (ctx) {
                // Destroy existing chart
                if (window.myTransProductsChart) {
                    window.myTransProductsChart.destroy();
                }

                window.myTransProductsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: sortedProducts.map(p => p[0].length> 20 ? p[0].substring(0, 20) + '...' : p[0]),
                        datasets: [{
                            label: 'Sales Amount',
                            data: sortedProducts.map(p => p[1]),
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₦' + value.toLocaleString();
                                    }
                                }
                            },
                            y: {
                                ticks: {
                                    autoSkip: false,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Sales: ₦' + context.parsed.x.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    }
}

// View Transaction Details
$(document).on('click', '.view-transaction-details', function() {
    const txId = $(this).data('id');
    const transaction = currentMyTransactions.find(tx => tx.id == txId);

    if (!transaction) {
        toastr.error('Transaction not found');
        return;
    }

    // Populate modal
    const date = new Date(transaction.created_at);
    $('#detail-id').text(transaction.id);
    $('#detail-datetime').text(date.toLocaleString('en-GB'));
    $('#detail-patient').text(transaction.patient_name);
    $('#detail-file-no').text(transaction.file_no);
    $('#detail-product').text(transaction.product_name || 'N/A');
    $('#detail-quantity').text(transaction.quantity || 1);
    $('#detail-unit-price').text('₦' + parseFloat(transaction.unit_price || 0).toLocaleString());
    $('#detail-subtotal').text('₦' + (parseFloat(transaction.unit_price || 0) * parseInt(transaction.quantity || 1)).toLocaleString());
    $('#detail-payment-method').html(`<span class="badge badge-${getPaymentTypeBadgeClass(transaction.payment_type)}">${transaction.payment_type}</span>`);
    $('#detail-bank').text(transaction.bank_name || '-');
    $('#detail-reference').text(transaction.reference_no || 'N/A');
    $('#detail-total').text('₦' + parseFloat(transaction.total).toLocaleString());
    $('#detail-discount').text('₦' + parseFloat(transaction.total_discount || 0).toLocaleString());

    const netAmount = parseFloat(transaction.total) - parseFloat(transaction.total_discount || 0);
    $('#detail-net').text('₦' + netAmount.toLocaleString());

    $('#transactionDetailsModal').modal('show');
});

// Print Transaction Detail
$(document).on('click', '#print-transaction-detail', function() {
    const content = $('#transaction-details-body').html();
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Transaction Receipt</title>
            <link rel="stylesheet" href="${window.location.origin}/assets/css/bootstrap.min.css">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background: #fff;
                }
                .detail-group {
                    margin-bottom: 1rem;
                }
                .detail-label {
                    font-size: 0.75rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    color: #6c757d;
                    font-weight: 600;
                    margin-bottom: 0.25rem;
                }
                .detail-value {
                    font-size: 1rem;
                    color: #212529;
                    font-weight: 500;
                }
                hr {
                    margin: 1.5rem 0;
                    border-top: 2px solid #e9ecef;
                }
                .header {
                    text-align: center;
                    margin-bottom: 2rem;
                    padding-bottom: 1rem;
                    border-bottom: 2px solid #dee2e6;
                }
                @media print {
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Transaction Receipt</h2>
                <p>Printed: ${new Date().toLocaleString()}</p>
            </div>
            ${content}
            <script>
                setTimeout(function() {
                    window.print();
                }, 500);
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
});

function updatePrescriptionBadge(count) {
    $('#prescriptions-badge').text(count);
}

// Legacy function stub for compatibility
function updateBillingBadge(count) {
    updatePrescriptionBadge(count);
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

function setResTempInModal(request) {
    $('#investResModal').find('form').trigger('reset');
    $('#invest_res_service_name').text(request.service ? request.service.name : '');
    $('#invest_res_entry_id').val(request.id);
    $('#invest_res_is_edit').val(0);
    $('#deleted_attachments').val('[]');
    $('#existing_attachments_container').hide();
    $('#existing_attachments_list').html('');

    // Check template version
    const isV2 = request.service && request.service.template_version == 2;

    if (isV2) {
        let structure = request.service.template_structure;
        if (typeof structure === 'string') {
            try {
                structure = JSON.parse(structure);
            } catch (e) {
                console.error('Error parsing V2 template structure:', e);
                structure = null;
            }
        }

        if (structure) {
            // Parse result_data if available (for edit mode)
            let existingData = null;
            if (request.result_data) {
                try {
                    existingData = typeof request.result_data === 'string' ? JSON.parse(request.result_data) : request.result_data;
                } catch (e) {
                    console.error('Error parsing result_data:', e);
                }
            }
            loadV2Template(structure, existingData);
        } else {
            console.error('Invalid V2 template structure');
            // Fallback or error handling
        }
    } else {
        // Use request.result if available (for edit), otherwise template body
        let content = request.result || (request.service ? request.service.template_body : '');
        loadV1Template(content);
    }

    // Load existing attachments if editing (logic to be added if edit mode is supported)
    loadExistingAttachments(request.id);
}

function loadV1Template(template) {
    $('#invest_res_template_version').val('1');
    $('#v1_template_container').show();
    $('#v2_template_container').hide();

    // Re-enable content editing if it was disabled upon save
    if (template) {
        template = template.replace(/contenteditable="false"/g, 'contenteditable="true"');
        template = template.replace(/contenteditable='false'/g, "contenteditable='true'");
    }

    // Initialize CKEditor if not already initialized
    if (!window.investResEditor) {
        ClassicEditor
            .create(document.querySelector('#invest_res_template_editor'), {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'insertTable',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                }
            })
            .then(editor => {
                window.investResEditor = editor;
                editor.setData(template || '');
            })
            .catch(err => {
                console.error(err);
            });
    } else {
        window.investResEditor.setData(template || '');
    }
}

function loadV2Template(template, existingData) {
    $('#invest_res_template_version').val('2');
    $('#v1_template_container').hide();
    $('#v2_template_container').show();

    let formHtml = '<div class="v2-result-form">';
    formHtml += '<h6 class="mb-3">' + (template.template_name || 'Result Entry') + '</h6>';

    // Sort parameters by order
    let parameters = template.parameters ? template.parameters.sort((a, b) => a.order - b.order) : [];

    parameters.forEach(param => {
        if (param.show_in_report === false) {
            return; // Skip hidden parameters
        }

        formHtml += '<div class="form-group row">';
        formHtml += '<label class="col-md-4 col-form-label">';
        formHtml += param.name;
        if (param.unit) {
            formHtml += ' <small class="text-muted">(' + param.unit + ')</small>';
        }
        if (param.required) {
            formHtml += ' <span class="text-danger">*</span>';
        }
        formHtml += '</label>';
        formHtml += '<div class="col-md-8">';

        let fieldId = 'param_' + param.id;
        let value = '';
        if (existingData && existingData[param.id]) {
            // Handle both direct value and object with value property
            if (typeof existingData[param.id] === 'object' && existingData[param.id] !== null && existingData[param.id].hasOwnProperty('value')) {
                value = existingData[param.id].value;
            } else {
                value = existingData[param.id];
            }
        }
        if (value === null || value === undefined) value = '';

        // Generate form field based on type
        if (param.type === 'string') {
            formHtml += '<input type="text" class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'value="' + value + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">';

        } else if (param.type === 'integer') {
            formHtml += '<input type="number" step="1" class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range) {
                formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'value="' + value + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">';

        } else if (param.type === 'float') {
            formHtml += '<input type="number" step="0.01" class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range) {
                formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'value="' + value + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">';

        } else if (param.type === 'boolean') {
            formHtml += '<select class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range && param.reference_range.reference_value !== undefined) {
                formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += '>';
            formHtml += '<option value="">Select</option>';
            formHtml += '<option value="true" ' + (value === true || value === 'true' ? 'selected' : '') + '>Yes/Positive</option>';
            formHtml += '<option value="false" ' + (value === false || value === 'false' ? 'selected' : '') + '>No/Negative</option>';
            formHtml += '</select>';

        } else if (param.type === 'enum') {
            formHtml += '<select class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            if (param.reference_range && param.reference_range.reference_value) {
                formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
            }
            formHtml += 'id="' + fieldId + '" ';
            if (param.required) formHtml += 'required ';
            formHtml += '>';
            formHtml += '<option value="">Select</option>';
            if (param.options) {
                param.options.forEach(opt => {
                    let optVal = typeof opt === 'object' ? opt.value : opt;
                    let optLabel = typeof opt === 'object' ? opt.label : opt;
                    formHtml += '<option value="' + optVal + '" ' + (value === optVal ? 'selected' : '') + '>' + optLabel + '</option>';
                });
            }
            formHtml += '</select>';

        } else if (param.type === 'long_text') {
            formHtml += '<textarea class="form-control v2-param-field" ';
            formHtml += 'data-param-id="' + param.id + '" ';
            formHtml += 'data-param-type="' + param.type + '" ';
            formHtml += 'id="' + fieldId + '" ';
            formHtml += 'rows="3" ';
            if (param.required) formHtml += 'required ';
            formHtml += 'placeholder="Enter ' + param.name + '">' + value + '</textarea>';
        }

        // Add reference range info if available
        if (param.reference_range) {
            formHtml += '<small class="form-text text-muted">';
            if (param.type === 'integer' || param.type === 'float') {
                if (param.reference_range.min !== null && param.reference_range.max !== null) {
                    formHtml += 'Normal range: ' + param.reference_range.min + ' - ' + param.reference_range.max;
                }
            } else if (param.type === 'boolean' && param.reference_range.reference_value !== undefined) {
                formHtml += 'Normal: ' + (param.reference_range.reference_value ? 'Yes/Positive' : 'No/Negative');
            } else if (param.type === 'enum' && param.reference_range.reference_value) {
                formHtml += 'Normal: ' + param.reference_range.reference_value;
            } else if (param.reference_range.text) {
                formHtml += param.reference_range.text;
            }
            formHtml += '</small>';
        }

        // Status indicator (will be updated on blur)
        formHtml += '<div class="mt-1"><span class="param-status" id="status_' + param.id + '"></span></div>';

        formHtml += '</div>';
        formHtml += '</div>';
    });

    formHtml += '</div>';

    $('#v2_form_fields').html(formHtml);

    // Add event listeners for value changes to show status
    $('.v2-param-field').on('blur change', function() {
        updateParameterStatus($(this));
    });

    // Trigger status update for pre-filled values
    $('.v2-param-field').each(function() {
        if ($(this).val()) {
            updateParameterStatus($(this));
        }
    });
}

function updateParameterStatus($field) {
    let paramId = $field.data('param-id');
    let paramType = $field.data('param-type');
    let value = $field.val();
    let $statusSpan = $('#status_' + paramId);

    if (!value || value === '') {
        $statusSpan.html('');
        return;
    }

    let status = '';
    let statusClass = '';

    if (paramType === 'integer' || paramType === 'float') {
        let numValue = parseFloat(value);
        let min = $field.data('ref-min');
        let max = $field.data('ref-max');

        if (min !== undefined && max !== undefined && min !== '' && max !== '') {
            if (numValue < min) {
                status = 'Low';
                statusClass = 'badge-warning';
            } else if (numValue> max) {
                status = 'High';
                statusClass = 'badge-danger';
            } else {
                status = 'Normal';
                statusClass = 'badge-success';
            }
        }
    } else if (paramType === 'boolean') {
        let refValue = $field.data('ref-value');
        if (refValue !== undefined) {
            let boolValue = value === 'true';
            let refBool = refValue === true || refValue === 'true';

            if (boolValue === refBool) {
                status = 'Normal';
                statusClass = 'badge-success';
            } else {
                status = 'Abnormal';
                statusClass = 'badge-warning';
            }
        }
    } else if (paramType === 'enum') {
        let refValue = $field.data('ref-value');
        if (refValue) {
            if (value === refValue) {
                status = 'Normal';
                statusClass = 'badge-success';
            } else {
                status = 'Abnormal';
                statusClass = 'badge-warning';
            }
        }
    }

    if (status) {
        $statusSpan.html('<span class="badge ' + statusClass + '">' + status + '</span>');
    } else {
        $statusSpan.html('');
    }
}

function loadExistingAttachments(requestId) {
    const container = $('#existing_attachments_list');
    const wrapper = $('#existing_attachments_container');
    container.empty();
    wrapper.hide();

    $.ajax({
        url: `{{ url('/lab-workbench/lab-service-requests/${requestId}/attachments') }}`,
        method: 'GET',
        success: function(attachments) {
            if (attachments && attachments.length> 0) {
                wrapper.show();
                attachments.forEach(att => {
                    const attDiv = $('<div>').addClass('attachment-item mb-2 d-flex justify-content-between align-items-center');
                    const link = $('<a>').attr('href', att.url).attr('target', '_blank').text(att.filename);
                    const deleteBtn = $('<button>')
                        .addClass('btn btn-sm btn-danger')
                        .html('<i class="fa fa-trash"></i>')
                        .on('click', function() {
                            markAttachmentForDeletion(att.id);
                            attDiv.remove();
                            if (container.children().length === 0) {
                                wrapper.hide();
                            }
                        });
                    attDiv.append(link).append(deleteBtn);
                    container.append(attDiv);
                });
            }
        }
    });
}

function markAttachmentForDeletion(attachmentId) {
    const current = $('#deleted_attachments').val();
    const deleted = current ? JSON.parse(current) : [];
    deleted.push(attachmentId);
    $('#deleted_attachments').val(JSON.stringify(deleted));
}

// Handle result form submission
$('#investResForm').on('submit', function(e) {
    e.preventDefault();

    // Copy data from editors/inputs to hidden fields
    copyResTemplateToField();

    const formData = new FormData(this);

    $.ajax({
        url: $(this).attr('action'),
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            alert('Result saved successfully!');
            $('#investResModal').modal('hide');
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error saving result: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
});

function copyResTemplateToField() {
    let version = $('#invest_res_template_version').val();

    if (version === '2') {
        // Collect V2 structured data
        let data = {};
        $('.v2-param-field').each(function() {
            let paramId = $(this).data('param-id');
            let paramType = $(this).data('param-type');
            let value = $(this).val();

            // Convert values to appropriate types
            if (paramType === 'integer') {
                data[paramId] = value ? parseInt(value) : null;
            } else if (paramType === 'float') {
                data[paramId] = value ? parseFloat(value) : null;
            } else if (paramType === 'boolean') {
                data[paramId] = value === 'true' ? true : (value === 'false' ? false : null);
            } else {
                data[paramId] = value || null;
            }
        });

        $('#invest_res_template_data').val(JSON.stringify(data));
        // For V2, we still save a simple HTML representation to result column for backward compat
        $('#invest_res_template_submited').val('<p>Structured result data (V2 template)</p>');
    } else {
        // V1: Copy from CKEditor
        if (window.investResEditor) {
            $('#invest_res_template_submited').val(window.investResEditor.getData());
        }
    }
    return true;
}

function editLabResult(obj) {
    const requestId = $(obj).data('id');

    $.ajax({
        url: `{{ url('/lab-workbench/lab-service-requests/${requestId}') }}`,
        method: 'GET',
        success: function(request) {
            // Populate the form with template structure AND existing result data
            setResTempInModal(request);

            // Set Edit Mode UI
            $('#invest_res_is_edit').val(1);
            $('#investResModalLabel').text('Edit Result: ' + (request.service ? request.service.name : ''));
            $('#invest_res_submit_btn').html('<i class="mdi mdi-content-save"></i> Update Result');

            $('#investResModal').modal('show');
        },
        error: function(xhr) {
            alert('Error loading request: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

// setResViewInModal, PrintElem, getFileIcon now provided by invest_res_view_js partial

// Delete Lab Request with Reason
let deleteRequestId = null;
let deleteEncounterId = null;

function deleteLabRequest(requestId, encounterId, serviceName) {
    deleteRequestId = requestId;
    deleteEncounterId = encounterId;
    $('#delete_service_name').text(serviceName);
    $('#delete_request_id').text(requestId);
    $('#delete_reason').val('');
    $('#deleteReasonModal').modal('show');
}

$('#deleteRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#delete_reason').val();

    if (reason.length < 10) {
        alert('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $.ajax({
        url: `{{ url('/lab-workbench/lab-service-requests/${deleteRequestId}') }}`,
        method: 'DELETE',
        data: {
            _token: '{{ csrf_token() }}',
            reason: reason
        },
        success: function(response) {
            $('#deleteReasonModal').modal('hide');
            alert(response.message);

            // Reload patient data if we're on a patient
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete request'));
        }
    });
});

// Dismiss Lab Request
let dismissRequestId = null;

function dismissSingleRequest(requestId, serviceName) {
    dismissRequestId = requestId;
    $('#dismiss_service_name').text(serviceName);
    $('#dismiss_request_id').text(requestId);
    $('#dismiss_reason').val('');
    $('#dismissReasonModal').modal('show');
}

$('#dismissRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#dismiss_reason').val();

    if (reason.length < 10) {
        alert('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $.ajax({
        url: `{{ url('/lab-workbench/lab-service-requests/${dismissRequestId}/dismiss') }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            reason: reason
        },
        success: function(response) {
            $('#dismissReasonModal').modal('hide');
            alert(response.message);

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to dismiss request'));
        }
    });
});

// ============================================
// ENHANCEMENT FUNCTIONS
// ============================================

// Load user preferences from localStorage
function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('pharmacyClinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('📊 Clinical Context ×');
    }
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
        deviation = temp> idealTemp ? `+${diff.toFixed(1)}°C above ideal` : `-${diff.toFixed(1)}°C below ideal`;
        status = (temp>= 36.1 && temp <= 38.0) ? 'Normal' : 'Abnormal';
    } else if (vitalType === 'pulse' && value !== 'N/A') {
        const pulse = parseInt(value);
        const idealPulse = 80;
        const diff = Math.abs(pulse - idealPulse);
        deviation = pulse> idealPulse ? `+${diff} bpm above ideal` : `-${diff} bpm below ideal`;
        status = (pulse>= 60 && pulse <= 100) ? 'Normal' : 'Abnormal';
    } else if (vitalType === 'bp' && value !== 'N/A' && value.includes('/')) {
        const [sys, dia] = value.split('/').map(v => parseInt(v));
        status = (sys>= 90 && sys <= 140 && dia>= 60 && dia <= 90) ? 'Normal' : 'Abnormal';
        deviation = sys> 140 ? 'High BP' : sys < 90 ? 'Low BP' : 'Optimal';
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
        allergiesArray = patientAllergies.split(',').map(a => a.trim()).filter(a => a.length> 0);
    } else if (Array.isArray(patientAllergies)) {
        // Handle array (could be array of strings or array of objects)
        allergiesArray = patientAllergies.map(a => {
            if (typeof a === 'string') return a.trim();
            if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
            return '';
        }).filter(a => a.length> 0);
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
            }).filter(a => a.length> 0);
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

// =============================================
// VIEW MANAGEMENT HELPERS
// =============================================

// Hide all overlapping views - call this before showing any new view
function hideAllViews() {
    $('#empty-state').hide();
    $('#queue-view').removeClass('active').hide();
    $('.queue-item').removeClass('active');
    $('#pharmacy-reports-view').removeClass('active').hide();
    $('#pharmacy-returns-view').removeClass('active').hide();
    $('#pharmacy-return-create-view').removeClass('active').hide();
    $('#pharmacy-damages-view').removeClass('active').hide();
    $('#pharmacy-damage-create-view').removeClass('active').hide();
    $('#pharmacy-stock-reports-view').removeClass('active').hide();
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active').hide();
}

// =============================================
// QUEUE FUNCTIONALITY
// =============================================

let queueDataTable = null;
let currentQueueFilter = 'all';

function showQueue(filter) {
    currentQueueFilter = filter;

    // Update queue title
    const titles = {
        'all': '🟡 All Unpaid Items',
        'hmo': '🟢 HMO Items',
        'credit': '🟠 Credit Accounts',
    };
    $('#queue-view-title').html(`<i class="mdi mdi-format-list-bulleted"></i> ${titles[filter] || titles['all']}`);

    // Update active state on queue buttons
    $('.queue-item').removeClass('active');
    if (filter !== 'all') {
        $(`.queue-item[data-filter="${filter}"]`).addClass('active');
    }

    // Hide other views, show queue view
    hideAllViews();
    $('#queue-view').show().addClass('active');

    // On mobile, hide search pane and show main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Initialize or reload DataTable
    initializeQueueDataTable(filter);
}

function hideQueue() {
    $('#queue-view').removeClass('active');
    $('.queue-item').removeClass('active');

    if (currentPatient) {
        // If patient was selected, show their workspace
        $('#patient-header').addClass('active');
        $('#workspace-content').addClass('active');
    } else {
        // Otherwise show empty state
        $('#empty-state').show();
    }

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

function initializeQueueDataTable(filter) {
    // Destroy existing DataTable if it exists
    if (queueDataTable) {
        queueDataTable.destroy();
    }

    // Initialize DataTable for payment queue
    queueDataTable = $('#queue-datatable').DataTable({
        ajax: {
            url: '{{ url('/pharmacy-workbench/prescription-queue') }}',
            data: { filter: filter },
            dataSrc: ''
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    var emergencyBadge = row.is_emergency
                        ? '<span class=\"badge bg-danger me-2\"><i class=\"fa fa-bolt\"></i> EMERGENCY</span>'
                        : '';
                    var borderStyle = row.is_emergency
                        ? 'border-left: 4px solid #dc3545; background: #fff8f8;'
                        : '';
                    return `
                        <div class="queue-patient-item" data-patient-id="${row.patient_id}" style="cursor: pointer; padding: 1rem; border-bottom: 1px solid #e9ecef; ${borderStyle}">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                ${emergencyBadge}
                                <div style="font-weight: 600; font-size: 1rem; color: #212529;">${row.patient_name}</div>
                                <span class="badge badge-primary">${row.file_no}</span>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #6c757d;">
                                <i class="mdi mdi-pill"></i> ${row.prescription_count || 0} prescription(s)
                                ${row.unbilled_count> 0 ? `<span class="badge badge-warning ml-2">${row.unbilled_count} unbilled</span>` : ''}
                                ${row.ready_count> 0 ? `<span class="badge badge-success ml-2">${row.ready_count} ready</span>` : ''}
                                ${row.hmo ? `<br><small><i class="mdi mdi-hospital-building"></i> ${row.hmo}</small>` : ''}
                            </div>
                        </div>
                    `;
                }
            }
        ],
        paging: true,
        pageLength: 10,
        searching: true,
        ordering: false,
        info: true,
        responsive: true,
        language: {
            emptyTable: "No patients in this queue",
            zeroRecords: "No patients found",
            info: "Showing _START_ to _END_ of _TOTAL_ patients",
            infoEmpty: "No patients to show",
            infoFiltered: "(filtered from _MAX_ total patients)"
        }
    });

    // Click handler for patient selection from queue
    $('#queue-datatable').on('click', '.queue-patient-item', function() {
        const patientId = $(this).data('patient-id');
        hideQueue();
        loadPatient(patientId);
    });
}

// ==========================================
// REPORTS VIEW FUNCTIONS
// ==========================================

function showPharmacyReports() {
    // Hide all views to prevent stacking
    hideAllViews();

    // Show pharmacy reports view
    $('#pharmacy-reports-view').show().addClass('active');

    // On mobile, switch to main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Initialize reports if not already done
    if (!window.pharmacyReportsInitialized) {
        initPharmacyReportsFilters();
        loadPharmacyReportsData();
        initPharmacyReportsDataTables();
        initPharmacyReportsCharts();
        window.pharmacyReportsInitialized = true;
    } else {
        // Refresh data
        loadPharmacyReportsData();
    }
}

function hidePharmacyReports() {
    $('#pharmacy-reports-view').removeClass('active');
    $('#empty-state').show();

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

// Legacy showReports for backward compatibility
function showReports() {
    showPharmacyReports();
}

function hideReports() {
    hidePharmacyReports();
}

function setDefaultDateFilters() {
    // Set date filters to current month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    // Format as YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    $('#report-date-from').val(formatDate(firstDay));
    $('#report-date-to').val(formatDate(lastDay));
}

function loadFilterOptions() {
    // Load doctors
    $.ajax({
        url: '{{ route("lab.filterDoctors") }}',
        method: 'GET',
        success: function(doctors) {
            let options = '<option value="">All Doctors</option>';
            doctors.forEach(function(doctor) {
                options += `<option value="${doctor.id}">${doctor.name}</option>`;
            });
            $('#report-doctor-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load doctors:', xhr);
        }
    });

    // Load HMOs with optgroups
    $.ajax({
        url: '{{ route("lab.filterHmos") }}',
        method: 'GET',
        success: function(hmoGroups) {
            let options = '<option value="">All HMOs</option>';
            Object.keys(hmoGroups).forEach(function(schemeName) {
                options += `<optgroup label="${schemeName}">`;
                hmoGroups[schemeName].forEach(function(hmo) {
                    options += `<option value="${hmo.id}">${hmo.name}</option>`;
                });
                options += '</optgroup>';
            });
            $('#report-hmo-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load HMOs:', xhr);
        }
    });

    // Load services
    $.ajax({
        url: '{{ route("lab.filterServices") }}',
        method: 'GET',
        success: function(services) {
            let options = '<option value="">All Services</option>';
            services.forEach(function(service) {
                options += `<option value="${service.id}">${service.name}</option>`;
            });
            $('#report-service-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load services:', xhr);
        }
    });
}

/* REPLACED BY NEW IMPLEMENTATION
function loadReportsStatistics(filters = {}) {
    // If no filters provided, use current form values
    if (Object.keys(filters).length === 0) {
        filters = {
            date_from: $('#report-date-from').val(),
            date_to: $('#report-date-to').val(),
            status: $('#report-status-filter').val(),
            service_id: $('#report-service-filter').val(),
            doctor_id: $('#report-doctor-filter').val(),
            hmo_id: $('#report-hmo-filter').val(),
            patient_search: $('#report-patient-search').val()
        };
    }

    $.ajax({
        url: '{{ route("lab.statistics") }}',
        method: 'GET',
        data: filters,
        success: function(data) {
            // Update summary cards
            $('#stat-total-requests').text(data.summary.total_requests);
            $('#stat-completed').text(data.summary.completed);
            $('#stat-pending').text(data.summary.pending);
            $('#stat-avg-tat').text(data.summary.avg_tat + 'h');

            // Store data for charts
            window.reportsData = data;

            // Update charts if they exist
            if (window.statusChart) {
                updateStatusChart(data.by_status);
            }
            if (window.trendsChart) {
                updateTrendsChart(data.monthly_trends);
            }

            // Update top services
            if (data.top_services && data.top_services.length> 0) {
                let servicesHtml = '<ul class="list-group list-group-flush">';
                data.top_services.forEach(function(service, index) {
                    const percentage = data.summary.total_requests> 0 ? Math.round((service.count / data.summary.total_requests) * 100) : 0;
                    servicesHtml += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div style="flex: 1; min-width: 0; margin-right: 15px;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-truncate font-weight-bold" title="${service.service}">${index + 1}. ${service.service}</span>
                                <span class="badge badge-primary badge-pill">${service.count}</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
                    </li>`;
                });
                servicesHtml += '</ul>';
                $('#top-services-list').html(servicesHtml);
            } else {
                $('#top-services-list').html('<p class="text-muted">No data available</p>');
            }
        },
        error: function(xhr) {
            console.error('Failed to load statistics:', xhr);
            toastr.error('Failed to load statistics');
        }
    });
}
*/

function initializeReportsDataTable() {
    if (window.reportsDataTable) {
        window.reportsDataTable.destroy();
    }

    window.reportsDataTable = $('#reports-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("lab.reports") }}',
            data: function(d) {
                // Add filter values to request
                d.date_from = $('#report-date-from').val();
                d.date_to = $('#report-date-to').val();
                d.status = $('#report-status-filter').val();
                d.service_id = $('#report-service-filter').val();
                d.doctor_id = $('#report-doctor-filter').val();
                d.hmo_id = $('#report-hmo-filter').val();
                d.patient_search = $('#report-patient-search').val();

                // Debug: Log what we're sending
                console.log('DataTable AJAX params:', {
                    date_from: d.date_from,
                    date_to: d.date_to,
                    status: d.status,
                    service_id: d.service_id,
                    doctor_id: d.doctor_id,
                    hmo_id: d.hmo_id,
                    patient_search: d.patient_search
                });
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'file_no', name: 'patient.file_no' },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'service_name', name: 'service.service_name' },
            { data: 'doctor_name', name: 'doctor_name', orderable: false },
            { data: 'hmo_name', name: 'hmo_name', orderable: false },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'tat', name: 'tat', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: false,
        scrollX: true
    });
}

function initializeReportsCharts() {
    // TODO: Implement Chart.js charts
    // - Status breakdown (bar chart)
    // - Monthly trends (line chart)
    console.log('Charts will be implemented with Chart.js');
}

function updateStatusChart(data) {
    // TODO: Update status chart with new data
}

function updateTrendsChart(data) {
    // TODO: Update trends chart with new data
}

function renderQueueCard(data) {
    // Status badges
    const statusBadges = getStatusBadges(data);

    // Patient meta
    const patientMeta = `
        <div class="queue-card-patient-meta">
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-account"></i>
                <span>${data.age} • ${data.gender}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-card-account-details"></i>
                <span>${data.file_no}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-hospital-building"></i>
                <span>${data.hmo}</span>
            </div>
        </div>
    `;

    // Note section
    const noteSection = data.note ? `
        <div class="queue-card-note">
            <div class="queue-card-note-label"><i class="mdi mdi-note-text"></i> Request Note</div>
            <div>${data.note}</div>
        </div>
    ` : '';

    // Attachments
    let attachmentsSection = '';
    if (data.attachments && data.attachments.length> 0) {
        const attachmentLinks = data.attachments.map(att => {
            const icon = getFileIcon(att.type);
            return `<a href="/storage/${att.path}" target="_blank" class="queue-card-attachment">
                ${icon} ${att.name}
            </a>`;
        }).join('');
        attachmentsSection = `
            <div class="queue-card-attachments">
                <strong><i class="mdi mdi-paperclip"></i> Attachments:</strong>
                ${attachmentLinks}
            </div>
        `;
    }

    // Result section
    const resultSection = data.result ? `
        <div class="queue-card-note" style="background: #f0f9ff; border-color: #0ea5e9;">
            <div class="queue-card-note-label" style="color: #0ea5e9;"><i class="mdi mdi-flask"></i> Result</div>
            <div>${data.result}</div>
        </div>
    ` : '';

    const emergencyBadge = data.is_emergency
        ? '<span class="badge bg-danger mb-1"><i class="fa fa-bolt"></i> EMERGENCY</span> '
        : '';
    const emergencyBorderStyle = data.is_emergency ? 'border-left: 4px solid #dc3545;' : '';

    return `
        <div class="queue-card" data-patient-id="${data.patient_id}" style="${emergencyBorderStyle}">
            <div class="queue-card-header">
                <div class="queue-card-patient">
                    <div class="queue-card-patient-name">${emergencyBadge}${data.patient_name}</div>
                    ${patientMeta}
                </div>
                <div class="queue-card-service">${data.service_name}</div>
            </div>
            <div class="queue-card-body">
                <div class="queue-card-status-row">
                    ${statusBadges}
                </div>
                ${noteSection}
                ${resultSection}
                ${attachmentsSection}
            </div>
            <div class="queue-card-actions">
                <button class="btn btn-primary btn-select-patient-from-queue" data-patient-id="${data.patient_id}">
                    <i class="mdi mdi-account-arrow-right"></i> Select Patient
                </button>
            </div>
        </div>
    `;
}

function getStatusBadges(data) {
    let badges = '';

    // Requested
    badges += `
        <div class="queue-card-status-item completed">
            <div class="queue-card-status-label"><i class="mdi mdi-calendar-check"></i> Requested</div>
            <div class="queue-card-status-value">${data.requested_by}<br><small>${data.requested_at}</small></div>
        </div>
    `;

    // Billing
    if (data.billed_by && data.billed_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billed</div>
                <div class="queue-card-status-value">${data.billed_by}<br><small>${data.billed_at}</small></div>
            </div>
        `;
    } else {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billing</div>
                <div class="queue-card-status-value">Awaiting billing</div>
            </div>
        `;
    }

    // Sample
    if (data.sample_taken_by && data.sample_taken_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample Taken</div>
                <div class="queue-card-status-value">${data.sample_taken_by}<br><small>${data.sample_taken_at}</small></div>
            </div>
        `;
    } else if (data.status>= 2) {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample</div>
                <div class="queue-card-status-value">Awaiting sample collection</div>
            </div>
        `;
    }

    // Result
    if (data.result_by && data.result_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">${data.result_by}<br><small>${data.result_at}</small></div>
            </div>
        `;
    } else if (data.status>= 3) {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">Awaiting result entry</div>
            </div>
        `;
    }

    return badges;
}

function getFileIcon(extension) {
    const icons = {
        'pdf': '<i class="mdi mdi-file-pdf"></i>',
        'doc': '<i class="mdi mdi-file-word"></i>',
        'docx': '<i class="mdi mdi-file-word"></i>',
        'jpg': '<i class="mdi mdi-file-image"></i>',
        'jpeg': '<i class="mdi mdi-file-image"></i>',
        'png': '<i class="mdi mdi-file-image"></i>',
    };
    return icons[extension] || '<i class="mdi mdi-file"></i>';
}

// Handle patient selection from queue
$(document).on('click', '.btn-select-patient-from-queue', function() {
    const patientId = $(this).data('patient-id');
    loadPatient(patientId);
    hideQueue();
});

// ==========================================
// REPORTS VIEW EVENT HANDLERS
// ==========================================

// Open reports view
$('#btn-view-reports').on('click', function() {
    showReports();
});

// Close reports view
$('#btn-close-reports').on('click', function() {
    hideReports();
});

// Reports filter form submission
$('#reports-filter-form').on('submit', function(e) {
    e.preventDefault();

    // Reload statistics with filters
    const filters = {
        date_from: $('#report-date-from').val(),
        date_to: $('#report-date-to').val(),
        status: $('#report-status-filter').val(),
        service_id: $('#report-service-filter').val(),
        doctor_id: $('#report-doctor-filter').val(),
        hmo_id: $('#report-hmo-filter').val(),
        patient_search: $('#report-patient-search').val()
    };

    loadReportsStatistics(filters);

    // Reload DataTable
    if (window.reportsDataTable) {
        window.reportsDataTable.ajax.reload();
    }
});

// Clear reports filters
$('#clear-report-filters').on('click', function() {
    $('#reports-filter-form')[0].reset();
    loadReportsStatistics();
    if (window.reportsDataTable) {
        window.reportsDataTable.ajax.reload();
    }
});

// Export buttons (TODO: Implement with DataTables buttons extension)
$('#export-excel').on('click', function() {
    toastr.info('Excel export will be implemented with DataTables buttons extension');
});

$('#export-pdf').on('click', function() {
    toastr.info('PDF export will be implemented with DataTables buttons extension');
});

$('#print-report').on('click', function() {
    toastr.info('Print functionality will be implemented with DataTables buttons extension');
});

// Show new request button when patient is selected
function updateQuickActions() {
    if (currentPatient) {
        $('#btn-new-request').show();
    } else {
        $('#btn-new-request').hide();
    }
}

// New request button handler
$('#btn-new-request').on('click', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    switchWorkspaceTab('new-request');
    $('#new-request-patient-name').text(currentPatient.name);
});

// ==========================================
// REPORTS & ANALYTICS HELPER FUNCTIONS
// ==========================================

function loadReportsStatistics(filters = {}) {
    // Show loading state
    $('#stat-total-requests').text('Loading...');
    $('#stat-completed').text('Loading...');
    $('#stat-pending').text('Loading...');
    $('#stat-avg-tat').text('Loading...');

    $.ajax({
        url: '{{ route("lab.statistics") }}',
        method: 'GET',
        data: filters,
        success: function(response) {
            // Update Summary Cards
            updateSummaryCards(response.summary);

            // Render Top Services
            renderTopServices(response.top_services, response.summary ? response.summary.total_requests : 0);

            // Render Top Doctors
            renderTopDoctors(response.top_doctors);

            // Initialize/Update Charts
            initializeReportsCharts(response.by_status, response.monthly_trends);
        },
        error: function(xhr) {
            console.error('Error loading statistics:', xhr);
            toastr.error('Failed to load report statistics');
        }
    });
}

function updateSummaryCards(summary) {
    if(!summary) return;

    $('#stat-total-requests').text(summary.total_requests || 0);
    $('#stat-completed').text(summary.completed_requests || 0);
    $('#stat-pending').text(summary.pending_requests || 0);

    // Update Avg TAT
    $('#stat-avg-tat').text((summary.avg_tat || 0) + ' hrs');
}

function renderTopServices(services, totalRequests = 0) {
    const container = $('#top-services-list');

    if (!services || services.length === 0) {
        container.html('<p class="text-muted text-center p-3">No data available for the selected period.</p>');
        return;
    }

    let html = '<ul class="list-group list-group-flush">';

    services.forEach((service, index) => {
        const percentage = totalRequests> 0 ? Math.round((service.count / totalRequests) * 100) : 0;

        html += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <div style="flex: 1; min-width: 0; margin-right: 15px;">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-truncate font-weight-bold" title="${service.name}">${index + 1}. ${service.name}</span>
                    <span class="badge badge-primary badge-pill">${service.count}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
        </li>`;
    });

    html += '</ul>';
    container.html(html);
}

function renderTopDoctors(doctors) {
    const container = $('#top-doctors-list');

    if (!doctors || doctors.length === 0) {
        container.html('<p class="text-muted text-center p-3">No data available.</p>');
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover table-sm"><thead><tr><th>Doctor Name</th><th class="text-center">Requests</th><th class="text-right">Total Revenue</th></tr></thead><tbody>';

    doctors.forEach(doc => {
        const docName = doc.doctor ? (doc.doctor.firstname + ' ' + doc.doctor.surname) : 'Unknown';
        html += `
            <tr>
                <td><i class="mdi mdi-doctor mr-1"></i> ${docName}</td>
                <td class="text-center"><span class="badge badge-pill badge-info">${doc.count}</span></td>
                <td class="text-right">₦${parseFloat(doc.revenue).toLocaleString()}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.html(html);
}

let statusChartInstance = null;
let trendsChartInstance = null;

function initializeReportsCharts(byStatus, monthlyTrends) {
    // 1. Status Chart (Doughnut)
    const statusCtx = document.getElementById('status-chart').getContext('2d');

    // Destroy existing chart if it exists
    if (statusChartInstance) {
        statusChartInstance.destroy();
    }

    const statusLabels = [];
    const statusData = [];
    const statusColors = [];

    // Map status IDs to names and colors
    const statusMap = {
        1: { name: 'Awaiting Billing', color: '#ffc107' },
        2: { name: 'Awaiting Sample', color: '#17a2b8' },
        3: { name: 'Awaiting Results', color: '#007bff' },
        4: { name: 'Completed', color: '#28a745' }
    };

    if (byStatus && byStatus.length> 0) {
        byStatus.forEach(item => {
            const info = statusMap[item.status] || { name: 'Unknown', color: '#6c757d' };
            statusLabels.push(info.name);
            statusData.push(item.count);
            statusColors.push(info.color);
        });
    } else {
        // Empty state
        statusLabels.push('No Data');
        statusData.push(0);
        statusColors.push('#e9ecef');
    }

    statusChartInstance = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right'
            }
        }
    });

    // 2. Monthly Trends Chart (Line)
    const trendsCtx = document.getElementById('trends-chart').getContext('2d');

    if (trendsChartInstance) {
        trendsChartInstance.destroy();
    }

    const trendLabels = [];
    const trendData = [];

    if (monthlyTrends && monthlyTrends.length> 0) {
        monthlyTrends.forEach(item => {
            trendLabels.push(item.month);
            trendData.push(item.count);
        });
    }

    trendsChartInstance = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Requests',
                data: trendData,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        precision: 0
                    }
                }]
            }
        }
    });
}

</script>

<!-- Prescription Slip Preview Modal -->
<div class="modal fade" id="prescriptionSlipModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-file-document"></i> Prescription Slip Preview</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto; background: #f7f9fb;">
                <div id="prescription-slip-content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="printPrescriptionSlipFromModal()">
                    <i class="mdi mdi-printer"></i> Print
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Pharmacy Workbench Prescription Functions --}}
<script>
// Override billPrescItems to use correct checkbox class for pharmacy workbench
window.billPrescItems = function() {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    // Get selected items from DataTable checkboxes - use scoped selector and attr
    const selectedIds = [];
    $('#presc_billing_table').find('.presc-billing-check:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) selectedIds.push(id);
    });

    // Also check card-based checkboxes (unbilled section)
    $('.request-section[data-section="unbilled"] .prescription-checkbox:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) selectedIds.push(id);
    });

    console.log('Bill - Found checkboxes:', $('#presc_billing_table').find('.presc-billing-check:checked').length);
    console.log('Bill - Selected IDs:', selectedIds);

    // Validate
    if (selectedIds.length === 0) {
        toastr.warning('Please select at least one item to bill');
        return;
    }

    if (!confirm('Are you sure you want to bill the selected items?')) {
        return;
    }

    const $btn = $('#btn-bill-presc');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Billing...');

    $.ajax({
        url: '{{ url('/product-bill-patient-ajax') }}',
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            selectedPrescBillRows: selectedIds,
            patient_id: currentPatient,
            patient_user_id: currentPatientData?.user_id || ''
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            if (response.success) {
                toastr.success(response.message || 'Items billed successfully');
                // Refresh all prescription tables for live update
                refreshAllPrescTables();
                loadPrescriptionItems(currentStatusFilter);
                prescBillingTotal = 0;
                updatePrescBillingTotalPharmacy();
                // Update queue counts as items may have moved
                loadQueueCounts();
            } else {
                toastr.error(response.message || 'Failed to bill items');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            console.error('Billing failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to bill items');
        }
    });
};

// Override dismissPrescItems to show modal (instead of confirm())
window.dismissPrescItems = function(type) {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    // Just show the modal - the actual dismiss happens in confirmDismiss()
    showDismissModal(type);
};

// Actual dismiss function (called after modal confirmation)
window.dismissPrescItemsConfirmed = function(type) {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    const selectedIds = [];

    if (type === 'billing') {
        // From DataTable - use scoped selector and attr
        $('#presc_billing_table').find('.presc-billing-check:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
        // From card-based view
        $('.request-section[data-section="unbilled"] .prescription-checkbox:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
    } else if (type === 'pending') {
        // From DataTable - use scoped selector and attr
        $('#presc_pending_table').find('.presc-pending-check:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
        // From card-based view
        $('.request-section[data-section="billed"] .prescription-checkbox:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
    } else if (type === 'dispense') {
        // From DataTable - use scoped selector and attr
        $('#presc_dispense_table').find('.presc-dispense-check:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
        // From card-based view
        $('.request-section[data-section="ready"] .prescription-checkbox:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
    }

    console.log('Dismiss - Type:', type, 'Selected IDs:', selectedIds);

    if (selectedIds.length === 0) {
        toastr.warning('Please select at least one item to dismiss');
        return;
    }

    // Show loading on button
    const $btn = $('#confirm-dismiss-btn');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Dismissing...');

    $.ajax({
        url: '{{ url('/product-dismiss-patient-ajax') }}',
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            prescription_ids: selectedIds,
            patient_id: currentPatient
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            if (response.success) {
                toastr.success(response.message || 'Items dismissed successfully');
                // Clear stored data
                selectedItemsData[type] = [];
                // Reload DataTables
                initializePrescriptionDataTables(currentPatient);
                loadPrescriptionItems(currentStatusFilter);
                prescBillingTotal = 0;
                updatePrescBillingTotalPharmacy();
            } else {
                toastr.error(response.message || 'Failed to dismiss items');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            console.error('Dismiss failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to dismiss items');
        }
    });
};

// ===========================================
// CLINICAL CONTEXT FUNCTIONS
// Now handled by shared ClinicalContext module (clinical-context.js)
// Vitals, medications, allergies are loaded by ClinicalContext.load()
// Notes, injections, procedures are lazy-loaded by the shared modal IIFEs
// ===========================================

// ===========================================
// DISPENSE CART MANAGEMENT (MODAL-BASED)
// ===========================================

// Cart data structure
let dispenseCart = [];

// Open the dispense cart modal
function openDispenseCartModal() {
    renderDispenseCart();
    $('#dispenseCartModal').modal('show');

    // If store is selected and cart has items, fetch stock
    const storeId = $('#modal-store-select').val();
    if (storeId && dispenseCart.length> 0) {
        fetchCartStockLevels();
    }
}

// Add selected items to cart and open modal
function addSelectedToCartAndOpen() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Get selected items from DataTable
    const $checkedItems = $('#presc_dispense_table').find('.presc-dispense-check:checked');

    if ($checkedItems.length === 0) {
        toastr.warning('Please select items to add to cart');
        return;
    }

    let addedCount = 0;
    $checkedItems.each(function() {
        const $checkbox = $(this);
        const $card = $checkbox.closest('tr').find('.presc-card');
        const id = $checkbox.attr('data-id') || $checkbox.data('id');

        // Skip if already in cart
        if (dispenseCart.find(item => item.id == id)) {
            return;
        }

        // Get item details from card data-attributes (most reliable)
        const productId = $card.attr('data-product-id') || $checkbox.attr('data-product-id');
        const productName = $card.find('.presc-card-title').text().trim() || 'Unknown Product';
        const qty = parseInt($card.attr('data-qty')) || 1;
        const price = parseFloat($card.attr('data-total-price')) || 0;

        dispenseCart.push({
            id: id,
            product_id: productId,
            product_name: productName,
            qty: qty,
            price: price / qty, // Store as unit price for consistency in renderDispenseCart
            total_billed: price,
            stock: null,
            stock_status: 'pending' // Will check when store is selected
        });

        addedCount++;
        $checkbox.prop('checked', false);
    });

    // Uncheck select all
    $('#select-all-dispense').prop('checked', false);

    if (addedCount> 0) {
        toastr.success(`Added ${addedCount} item(s) to cart`);
    }

    // Open modal
    renderDispenseCart();
    $('#dispenseCartModal').modal('show');

    // If store already selected, fetch stock
    const storeId = $('#modal-store-select').val();
    if (storeId) {
        fetchCartStockLevels();
    }
}

// Legacy function for backward compatibility
function addSelectedToCart() {
    addSelectedToCartAndOpen();
}

// Render the dispense cart in modal
function renderDispenseCart() {
    const $cartBody = $('#modal-cart-body');
    const $cartEmpty = $('#modal-cart-empty');
    const $cartContent = $('#modal-cart-content');
    const $cartTotal = $('#modal-cart-total');
    const $modalCartCount = $('#modal-cart-count');
    const $headerCartCount = $('#header-cart-count');
    const $floatingCartCount = $('#floating-cart-count');

    // Update all cart count badges
    const cartCount = dispenseCart.length;
    $modalCartCount.text(cartCount);

    if (cartCount> 0) {
        $headerCartCount.text(cartCount).show();
        $floatingCartCount.text(cartCount).show();
    } else {
        $headerCartCount.hide();
        $floatingCartCount.hide();
    }

    if (dispenseCart.length === 0) {
        $cartEmpty.show();
        $cartContent.hide();
        updateCartStockStatus();
        return;
    }

    $cartEmpty.hide();
    $cartContent.show();

    let totalPrice = 0;
    let html = '';

    dispenseCart.forEach((item, index) => {
        totalPrice += item.price * item.qty;

        let batchDisplay = '';
        let statusBadge = '';
        let rowClass = '';

        if (item.stock_status === 'pending') {
            batchDisplay = '<span class="text-muted small">Select store first</span>';
            statusBadge = '<span class="badge bg-light text-dark badge-sm">Select store</span>';
        } else if (item.stock_status === 'loading') {
            batchDisplay = '<span class="text-muted"><i class="mdi mdi-loading mdi-spin"></i> Loading...</span>';
            statusBadge = '<span class="badge bg-secondary text-white badge-sm">...</span>';
        } else if (item.stock_status === 'sufficient') {
            // Build batch selection dropdown
            batchDisplay = buildBatchDropdown(item, index);
            statusBadge = '<span class="badge badge-stock-ok badge-sm"><i class="mdi mdi-check"></i></span>';
        } else if (item.stock_status === 'insufficient') {
            batchDisplay = `<span class="text-danger small"><i class="mdi mdi-alert-circle"></i> Only ${item.stock || 0} available (need ${item.qty})</span>`;
            statusBadge = '<span class="badge badge-stock-out badge-sm"><i class="mdi mdi-alert"></i></span>';
            rowClass = 'table-danger';
        } else {
            batchDisplay = '<span class="text-warning small"><i class="mdi mdi-help-circle"></i> Unknown</span>';
            statusBadge = '<span class="badge bg-warning text-dark badge-sm">?</span>';
        }

        html += `
            <tr class="${rowClass}" data-cart-index="${index}" data-item-id="${item.id}" data-product-id="${item.product_id || ''}">
                <td>
                    <strong class="d-block">${item.product_name}</strong>
                    <small class="text-muted">PR #${item.id} | Prod #${item.product_id || 'N/A'}</small>
                </td>
                <td class="text-center">${item.qty}</td>
                <td class="text-center cart-batch-cell">${batchDisplay}</td>
                <td class="text-end">₦${(item.price * item.qty).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(${index})" title="Remove">
                        <i class="mdi mdi-close-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    $cartBody.html(html);
    $cartTotal.text('₦' + totalPrice.toLocaleString('en-NG', {minimumFractionDigits: 2}));

    updateCartStockStatus();
}

// Remove item from cart
function removeFromCart(index) {
    dispenseCart.splice(index, 1);
    renderDispenseCart();

    if (dispenseCart.length === 0) {
        toastr.info('Cart is now empty');
    }
}

// Clear entire cart
function clearDispenseCart() {
    if (dispenseCart.length === 0) {
        toastr.info('Cart is already empty');
        return;
    }

    dispenseCart = [];
    renderDispenseCart();
    toastr.info('Cart cleared');
}

// Get current store ID from modal
function getCurrentStoreId() {
    return $('#modal-store-select').val();
}

// Fetch stock levels for all cart items
function fetchCartStockLevels() {
    const storeId = getCurrentStoreId();
    console.log('fetchCartStockLevels called, storeId:', storeId, 'cart length:', dispenseCart.length);

    if (!storeId) {
        $('#modal-store-status').html('<span class="text-warning"><i class="mdi mdi-store-alert"></i> Select a store above to check availability</span>');
        updateCartStockStatus();
        return;
    }

    const storeName = $('#modal-store-select option:selected').text();
    $('#modal-store-status').html(`<span class="text-info"><i class="mdi mdi-loading mdi-spin"></i> Checking stock at ${storeName}...</span>`);

    if (dispenseCart.length === 0) {
        $('#modal-store-status').html(`<span class="text-muted"><i class="mdi mdi-store"></i> Ready to dispense from: <strong>${storeName}</strong></span>`);
        return;
    }

    let pendingChecks = dispenseCart.length;

    dispenseCart.forEach((item, index) => {
        console.log('Checking item:', index, 'product_id:', item.product_id);

        if (item.product_id) {
            // Fetch batch info for this product
            fetchProductBatches(item.product_id, storeId, function(batchData) {
                console.log('Batch data received for product', item.product_id, ':', batchData);

                const totalAvailable = batchData.total_available || 0;
                const batches = batchData.batches || [];

                dispenseCart[index].stock = totalAvailable;
                dispenseCart[index].batches = batches;
                dispenseCart[index].stock_status = totalAvailable>= item.qty ? 'sufficient' : 'insufficient';

                updateCartRowStock(index);

                pendingChecks--;
                console.log('Pending checks remaining:', pendingChecks);

                if (pendingChecks <= 0) {
                    updateCartStockStatus();
                    $('#modal-store-status').html(`<span class="text-muted"><i class="mdi mdi-store"></i> Dispensing from: <strong>${storeName}</strong></span>`);
                }
            });
        } else {
            console.warn('Cart item missing product_id:', item);
            dispenseCart[index].stock = 0;
            dispenseCart[index].batches = [];
            dispenseCart[index].stock_status = 'insufficient';
            updateCartRowStock(index);
            pendingChecks--;
        }
    });
}

// Fetch product batches from server
function fetchProductBatches(productId, storeId, callback) {
    $.ajax({
        url: '{{ route("pharmacy.product-batches") }}',
        method: 'GET',
        data: {
            product_id: productId,
            store_id: storeId
        },
        success: function(response) {
            if (response.success) {
                callback({
                    total_available: response.total_available,
                    batches: response.batches.map(b => ({
                        id: b.id,
                        batch_number: b.batch_number || b.name || `BTH-${b.id}`,
                        current_qty: b.qty || b.current_qty,
                        expiry_date: b.expiry_date,
                        is_expiring_soon: b.is_expiring_soon || false,
                        is_expired: b.is_expired || false
                    }))
                });
            } else {
                callback({ total_available: 0, batches: [] });
            }
        },
        error: function() {
            // Fallback to old stock check method
            fetchPharmacyProductStock(productId, function(stockData) {
                const storeStock = stockData.stores.find(s => s.store_id == storeId);
                callback({
                    total_available: storeStock ? storeStock.quantity : 0,
                    batches: []
                });
            });
        }
    });
}

// Update a single cart row's stock/batch display
function updateCartRowStock(index) {
    const item = dispenseCart[index];
    const $row = $(`#modal-cart-body tr[data-cart-index="${index}"]`);

    if (!$row.length) return;

    let batchDisplay = '';
    let statusBadge = '';

    if (item.stock_status === 'sufficient') {
        batchDisplay = buildBatchDropdown(item, index);
        statusBadge = '<span class="badge badge-stock-ok badge-sm"><i class="mdi mdi-check"></i></span>';
        $row.removeClass('table-danger');
    } else if (item.stock_status === 'insufficient') {
        batchDisplay = `<span class="text-danger small"><i class="mdi mdi-alert-circle"></i> Only ${item.stock || 0} available (need ${item.qty})</span>`;
        statusBadge = '<span class="badge badge-stock-out badge-sm"><i class="mdi mdi-alert"></i></span>';
        $row.addClass('table-danger');
    }

    $row.find('.cart-batch-cell').html(batchDisplay);
    $row.find('td:eq(4)').html(statusBadge);
}

// Build batch selection dropdown for a cart item
function buildBatchDropdown(item, index) {
    const batches = item.batches || [];
    const useFifo = $('#use-fifo-auto').is(':checked');
    const selectedBatchId = item.selected_batch_id || '';

    if (batches.length === 0) {
        // No batch info, show simple stock count
        return `<span class="text-success small"><i class="mdi mdi-check-circle"></i> ${item.stock} in stock</span>`;
    }

    if (useFifo && !selectedBatchId) {
        // FIFO mode - show recommended batch
        const fifoBatch = batches[0]; // First batch is oldest (FIFO)
        return `
            <div class="batch-fifo-display">
                <span class="badge bg-info-subtle text-info small">
                    <i class="mdi mdi-sort-clock-ascending"></i> FIFO
                </span>
                <span class="small d-block text-muted mt-1">
                    ${fifoBatch.batch_number || 'Auto'}
                    ${fifoBatch.expiry_date ? `<br>Exp: ${fifoBatch.expiry_date}` : ''}
                </span>
                <button type="button" class="btn btn-link btn-sm p-0 small" onclick="toggleBatchManualSelection(${index})">
                    <i class="mdi mdi-pencil"></i> Change
                </button>
            </div>
        `;
    }

    // Manual selection mode - show dropdown
    let options = '<option value="">Auto (FIFO)</option>';
    batches.forEach(batch => {
        const isSelected = selectedBatchId == batch.id ? 'selected' : '';
        const expiryClass = batch.is_expiring_soon ? 'text-warning' : (batch.is_expired ? 'text-danger' : '');
        const expiryText = batch.expiry_date ? ` | Exp: ${batch.expiry_date}` : '';
        options += `<option value="${batch.id}" ${isSelected} class="${expiryClass}">
            ${batch.batch_number} (${batch.current_qty} avail)${expiryText}
        </option>`;
    });

    return `
        <select class="form-select form-select-sm batch-select" data-cart-index="${index}" onchange="onBatchSelected(this, ${index})">
            ${options}
        </select>
    `;
}

// Toggle manual batch selection for an item
function toggleBatchManualSelection(index) {
    const item = dispenseCart[index];
    item.manual_batch_mode = true;
    updateCartRowStock(index);
}

// Handle batch selection change
function onBatchSelected(selectEl, index) {
    const batchId = $(selectEl).val();
    dispenseCart[index].selected_batch_id = batchId || null;
}

// Update overall cart stock status
function updateCartStockStatus() {
    const $statusDiv = $('#modal-stock-status');
    const $warning = $('#modal-stock-warning');
    const $dispenseBtn = $('#btn-dispense-cart');

    if (dispenseCart.length === 0) {
        $statusDiv.html('<span class="badge bg-secondary">Cart empty</span>');
        $warning.hide();
        $dispenseBtn.prop('disabled', true);
        return;
    }

    const storeId = getCurrentStoreId();
    if (!storeId) {
        $statusDiv.html('<span class="badge bg-warning text-dark"><i class="mdi mdi-store-alert"></i> Select a store to check stock</span>');
        $warning.hide();
        $dispenseBtn.prop('disabled', true);
        return;
    }

    const hasPending = dispenseCart.some(i => i.stock_status === 'pending');
    const hasLoading = dispenseCart.some(i => i.stock_status === 'loading');
    const hasInsufficient = dispenseCart.some(i => i.stock_status === 'insufficient');
    const allSufficient = dispenseCart.every(i => i.stock_status === 'sufficient');

    if (hasPending) {
        // Stock not checked yet - trigger check
        fetchCartStockLevels();
        return;
    }

    if (hasLoading) {
        $statusDiv.html('<span class="badge bg-info"><i class="mdi mdi-loading mdi-spin"></i> Checking stock...</span>');
        $warning.hide();
        $dispenseBtn.prop('disabled', true);
    } else if (hasInsufficient) {
        const insufficientCount = dispenseCart.filter(i => i.stock_status === 'insufficient').length;
        $statusDiv.html(`<span class="badge badge-stock-out"><i class="mdi mdi-alert-circle"></i> ${insufficientCount} item(s) insufficient stock</span>`);
        $warning.show();
        $('#modal-stock-warning-text').text(`${insufficientCount} item(s) have insufficient stock to fulfill the order`);
        $dispenseBtn.prop('disabled', true);
    } else if (allSufficient) {
        $statusDiv.html(`<span class="badge badge-stock-ok"><i class="mdi mdi-check-circle"></i> All items ready</span>`);
        $warning.hide();
        $dispenseBtn.prop('disabled', false);
    }
}

// Dispense from cart
function dispenseFromCart() {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    const storeId = getCurrentStoreId();
    if (!storeId) {
        toastr.warning('Please select a dispensing store');
        return;
    }

    if (dispenseCart.length === 0) {
        toastr.warning('Cart is empty');
        return;
    }

    // Check for insufficient stock
    const insufficientItems = dispenseCart.filter(i => i.stock_status === 'insufficient');
    if (insufficientItems.length> 0) {
        toastr.error('Cannot dispense: Some items have insufficient stock');
        return;
    }

    // Check for loading items
    const loadingItems = dispenseCart.filter(i => i.stock_status === 'loading');
    if (loadingItems.length> 0) {
        toastr.warning('Please wait for stock check to complete');
        return;
    }

    const storeName = $('#modal-store-select option:selected').text();
    if (!confirm(`Dispense ${dispenseCart.length} item(s) from ${storeName}?`)) {
        return;
    }

    const itemIds = dispenseCart.map(i => i.id);

    // Build batch selections array for items with manual selection
    const batchSelections = [];
    dispenseCart.forEach(item => {
        if (item.selected_batch_id) {
            batchSelections.push({
                product_request_id: item.id,
                batch_id: item.selected_batch_id
            });
        }
    });

    const $btn = $('#btn-dispense-cart');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Dispensing...');

    $.ajax({
        url: '{{ route("pharmacy.dispense-with-batch") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            patient_id: currentPatient,
            product_request_ids: itemIds,
            store_id: storeId,
            batch_selections: batchSelections
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.success(response.message || 'Prescriptions dispensed successfully');

            // Clear the cart and close modal
            dispenseCart = [];
            renderDispenseCart();
            $('#dispenseCartModal').modal('hide');

            // Refresh tables
            refreshAllPrescTables();
            loadPrescriptionItems(currentStatusFilter);
            loadQueueCounts();
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);

            if (xhr.status === 422 && xhr.responseJSON?.validation_errors) {
                const errors = xhr.responseJSON.validation_errors;
                let errorHtml = '<strong>Cannot Dispense:</strong><ul class="mb-0 mt-1">';
                errors.forEach(err => {
                    errorHtml += `<li>${err.product || 'Item'}: ${err.error}</li>`;
                });
                errorHtml += '</ul>';

                toastr.error(errorHtml, 'Validation Failed', {
                    closeButton: true,
                    timeOut: 10000,
                    extendedTimeOut: 5000,
                    escapeHtml: false
                });

                fetchCartStockLevels();
            } else {
                toastr.error(xhr.responseJSON?.message || 'Failed to dispense prescriptions');
            }
        }
    });
}

// Print cart prescriptions
function printCartPrescriptions() {
    if (dispenseCart.length === 0) {
        toastr.warning('Cart is empty');
        return;
    }

    const itemIds = dispenseCart.map(i => i.id);
    printPrescription(itemIds);
}

// Modal store change handler - use event delegation to ensure it works
$(document).on('change', '#modal-store-select', function() {
    const storeId = $(this).val();
    console.log('Store changed to:', storeId); // Debug log

    if (dispenseCart.length> 0 && storeId) {
        // Reset stock status and re-fetch
        dispenseCart.forEach((item, index) => {
            dispenseCart[index].stock = null;
            dispenseCart[index].batches = [];
            dispenseCart[index].selected_batch_id = null;
            dispenseCart[index].stock_status = 'loading';
        });
        renderDispenseCart();
        fetchCartStockLevels();
    } else if (!storeId) {
        // No store selected - update status
        updateCartStockStatus();
    }
});

// FIFO mode toggle handler - re-render cart when toggled
$(document).on('change', '#use-fifo-auto', function() {
    // Re-render to show/hide batch dropdowns
    renderDispenseCart();
});

// ===========================================
// DISPENSE STORE SELECTION & STOCK DISPLAY
// ===========================================

// Fetch product stock by store
function fetchPharmacyProductStock(productId, callback) {
    const url = `/pharmacy-workbench/product/${productId}/stock`;
    console.log('Fetching stock from:', url);

    $.ajax({
        url: url,
        method: 'GET',
        success: function(response) {
            console.log('Stock API success for product', productId, ':', response);
            callback(response);
        },
        error: function(xhr, status, error) {
            console.error('Stock API error for product', productId, ':', status, error, xhr.responseText);
            callback({ global_stock: 0, stores: [] });
        }
    });
}
// Override dispensePrescItems to use cart flow instead
window.dispensePrescItems = function() {
    // Redirect to cart flow
    addSelectedToCartAndOpen();
};

// ===========================================
// PHARMACY REPORTS & ANALYTICS MODULE
// ===========================================

// Global report variables
window.pharmacyReportsInitialized = false;
window.pharmDispensingTable = null;
window.pharmRevenueTable = null;
window.pharmStockTable = null;
window.pharmPerformanceTable = null;
window.pharmHmoTable = null;
window.pharmTrendChart = null;
window.pharmRevenuePieChart = null;

// Current filter state
let pharmReportFilters = {
    date_from: null,
    date_to: null,
    status: '',
    store_id: '',
    payment_type: '',
    hmo_id: '',
    doctor_id: '',
    pharmacist_id: '',
    category_id: '',
    patient_search: '',
    min_amount: '',
    max_amount: ''
};

// Open pharmacy reports view
$('#btn-pharmacy-reports').on('click', function() {
    showPharmacyReports();
});

// Close pharmacy reports view
$('#btn-close-pharmacy-reports').on('click', function() {
    hidePharmacyReports();
});

// Initialize filter dropdowns
function initPharmacyReportsFilters() {
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    $('#pharm-report-date-from').val(today);
    $('#pharm-report-date-to').val(today);
    pharmReportFilters.date_from = today;
    pharmReportFilters.date_to = today;

    // Load filter options
    loadPharmReportFilterOptions();
}

// Load filter dropdown options
function loadPharmReportFilterOptions() {
    // Load stores
    $.get('/pharmacy-workbench/stores', function(stores) {
        const $storeSelect = $('#pharm-report-store, #stock-report-store-filter');
        $storeSelect.find('option:not(:first)').remove();
        stores.forEach(store => {
            $storeSelect.append(`<option value="${store.id}">${store.name}</option>`);
        });
    });

    // Load HMOs with optgroups
    $.get('/pharmacy-workbench/filter-hmos', function(hmoGroups) {
        const $hmoSelect = $('#pharm-report-hmo');
        $hmoSelect.find('option:not(:first)').remove();
        Object.keys(hmoGroups).forEach(function(schemeName) {
            let optgroup = `<optgroup label="${schemeName}">`;
            hmoGroups[schemeName].forEach(function(hmo) {
                optgroup += `<option value="${hmo.id}">${hmo.name}</option>`;
            });
            optgroup += '</optgroup>';
            $hmoSelect.append(optgroup);
        });
    });

    // Load doctors
    $.get('/pharmacy-workbench/filter-doctors', function(doctors) {
        const $doctorSelect = $('#pharm-report-doctor');
        $doctorSelect.find('option:not(:first)').remove();
        (doctors || []).forEach(doctor => {
            $doctorSelect.append(`<option value="${doctor.id}">${doctor.name}</option>`);
        });
    });

    // Load pharmacists (staff with pharmacy role)
    $.get('/pharmacy-workbench/pharmacists', function(pharmacists) {
        const $pharmSelect = $('#pharm-report-pharmacist');
        $pharmSelect.find('option:not(:first)').remove();
        (pharmacists || []).forEach(p => {
            $pharmSelect.append(`<option value="${p.id}">${p.name}</option>`);
        });
    });

    // Load product categories
    $.get('/pharmacy-workbench/product-categories', function(categories) {
        const $catSelect = $('#pharm-report-category, #stock-report-category-filter');
        $catSelect.find('option:not(:first)').remove();
        (categories || []).forEach(cat => {
            $catSelect.append(`<option value="${cat.id}">${cat.name}</option>`);
        });
    });
}

// Date preset buttons
$('.date-preset-btn').on('click', function() {
    $('.date-preset-btn').removeClass('active');
    $(this).addClass('active');

    const preset = $(this).data('preset');
    const today = new Date();
    let dateFrom, dateTo;

    switch(preset) {
        case 'today':
            dateFrom = dateTo = today;
            break;
        case 'yesterday':
            dateFrom = dateTo = new Date(today.setDate(today.getDate() - 1));
            break;
        case 'week':
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            dateFrom = weekStart;
            dateTo = new Date();
            break;
        case 'month':
            dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
            dateTo = new Date();
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            dateFrom = new Date(today.getFullYear(), quarter * 3, 1);
            dateTo = new Date();
            break;
        case 'year':
            dateFrom = new Date(today.getFullYear(), 0, 1);
            dateTo = new Date();
            break;
        case 'all':
            dateFrom = null;
            dateTo = null;
            break;
    }

    if (dateFrom && dateTo) {
        $('#pharm-report-date-from').val(formatDateInput(dateFrom));
        $('#pharm-report-date-to').val(formatDateInput(dateTo));
        pharmReportFilters.date_from = formatDateInput(dateFrom);
        pharmReportFilters.date_to = formatDateInput(dateTo);
    } else {
        $('#pharm-report-date-from').val('');
        $('#pharm-report-date-to').val('');
        pharmReportFilters.date_from = null;
        pharmReportFilters.date_to = null;
    }

    loadPharmacyReportsData();
});

function formatDateInput(date) {
    if (!date) return '';
    return date.toISOString().split('T')[0];
}

// Filter form submission
$('#pharmacy-reports-filter-form').on('submit', function(e) {
    e.preventDefault();
    collectFilters();
    loadPharmacyReportsData();
});

// Clear filters
$('#clear-pharmacy-report-filters').on('click', function() {
    $('#pharmacy-reports-filter-form')[0].reset();
    const today = new Date().toISOString().split('T')[0];
    $('#pharm-report-date-from').val(today);
    $('#pharm-report-date-to').val(today);
    collectFilters();
    loadPharmacyReportsData();
});

function collectFilters() {
    pharmReportFilters = {
        date_from: $('#pharm-report-date-from').val() || null,
        date_to: $('#pharm-report-date-to').val() || null,
        status: $('#pharm-report-status').val(),
        store_id: $('#pharm-report-store').val(),
        payment_type: $('#pharm-report-payment-type').val(),
        hmo_id: $('#pharm-report-hmo').val(),
        doctor_id: $('#pharm-report-doctor').val(),
        pharmacist_id: $('#pharm-report-pharmacist').val(),
        category_id: $('#pharm-report-category').val(),
        patient_search: $('#pharm-report-patient').val(),
        min_amount: $('#pharm-report-min-amount').val(),
        max_amount: $('#pharm-report-max-amount').val(),
        age_brackets: $('#pharm-report-age-brackets').val()
    };
}

// Main data loader
function loadPharmacyReportsData() {
    loadPharmacyStatistics();
    loadTopProducts();
    loadPaymentMethods();
    refreshPharmacyDataTables();
    loadExecutiveSummaryData();
}

// Load summary statistics
function loadPharmacyStatistics() {
    $.ajax({
        url: '{{ url('/pharmacy-workbench/reports/statistics') }}',
        method: 'GET',
        data: pharmReportFilters,
        success: function(stats) {
            $('#pharm-stat-dispensed').text(formatNumber(stats.total_dispensed || 0));
            $('#pharm-stat-revenue').text(formatCurrency(stats.total_revenue || 0));
            $('#pharm-stat-cash').text(formatCurrency(stats.cash_sales || 0));
            $('#pharm-stat-hmo').text(formatCurrency(stats.hmo_claims || 0));
            $('#pharm-stat-patients').text(formatNumber(stats.unique_patients || 0));
            $('#pharm-stat-pending').text(formatNumber(stats.pending_count || 0));

            // Update charts
            updateTrendChart(stats.trend_data || []);
            updateRevenuePieChart(stats.revenue_breakdown || {});
        },
        error: function() {
            console.error('Failed to load pharmacy statistics');
        }
    });
}

// Load top products
function loadTopProducts() {
    $.ajax({
        url: '{{ url('/pharmacy-workbench/reports/top-products') }}',
        method: 'GET',
        data: pharmReportFilters,
        success: function(products) {
            const $tbody = $('#pharm-top-products-tbody');
            $tbody.empty();

            if (!products.length) {
                $tbody.html('<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>');
                return;
            }

            products.forEach((p, i) => {
                $tbody.append(`
                    <tr>
                        <td><span class="badge bg-secondary">${i + 1}</span></td>
                        <td>${escapeHtml(p.product_name)}</td>
                        <td class="text-center">${formatNumber(p.quantity)}</td>
                        <td class="text-end">${formatCurrency(p.revenue)}</td>
                    </tr>
                `);
            });
        },
        error: function() {
            $('#pharm-top-products-tbody').html('<tr><td colspan="4" class="text-center text-danger">Failed to load</td></tr>');
        }
    });
}

// Load payment methods breakdown
function loadPaymentMethods() {
    $.ajax({
        url: '{{ url('/pharmacy-workbench/reports/payment-methods') }}',
        method: 'GET',
        data: pharmReportFilters,
        success: function(methods) {
            const $tbody = $('#pharm-payment-methods-tbody');
            $tbody.empty();

            if (!methods.length) {
                $tbody.html('<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>');
                return;
            }

            const total = methods.reduce((sum, m) => sum + parseFloat(m.amount || 0), 0);

            methods.forEach(m => {
                const percent = total> 0 ? ((parseFloat(m.amount || 0) / total) * 100).toFixed(1) : 0;
                const icon = getPaymentIcon(m.payment_type);
                $tbody.append(`
                    <tr>
                        <td><i class="mdi ${icon} me-1"></i>${m.payment_type || 'Unknown'}</td>
                        <td class="text-center">${formatNumber(m.count)}</td>
                        <td class="text-end">${formatCurrency(m.amount)}</td>
                        <td class="text-end"><span class="badge bg-info">${percent}%</span></td>
                    </tr>
                `);
            });
        },
        error: function() {
            $('#pharm-payment-methods-tbody').html('<tr><td colspan="4" class="text-center text-danger">Failed to load</td></tr>');
        }
    });
}

function getPaymentIcon(type) {
    const icons = {
        'CASH': 'mdi-cash',
        'CARD': 'mdi-credit-card',
        'TRANSFER': 'mdi-bank-transfer',
        'HMO': 'mdi-hospital-building',
        'ACCOUNT': 'mdi-wallet'
    };
    return icons[type] || 'mdi-cash-multiple';
}

// Load Executive Summary Data
function loadExecutiveSummaryData() {
    $('#executive-summary-loader').removeClass('d-none');
    $('#executive-summary-container').addClass('d-none');

    $.ajax({
        url: '{{ url('/pharmacy-workbench/reports/executive-summary') }}',
        method: 'GET',
        data: pharmReportFilters,
        success: function(data) {
            $('#executive-summary-loader').addClass('d-none');
            $('#executive-summary-container').removeClass('d-none');

            // Top level cards
            $('#exec-stock-value').text(formatCurrency(data.stock_valuation || 0));
            
            let totalCollectionsValue = 0;
            let totalCollectionsCount = 0;
            const $collectionsList = $('#exec-collections-list');
            $collectionsList.empty();
            
            if (data.collections_by_store && data.collections_by_store.length) {
                data.collections_by_store.forEach(c => {
                    totalCollectionsValue += parseFloat(c.value || 0);
                    totalCollectionsCount += parseInt(c.count || 0);
                    $collectionsList.append(`
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="mdi mdi-store text-primary me-2"></i> ${escapeHtml(c.store_name)}
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">${formatCurrency(c.value)}</div>
                                <small class="text-muted">${formatNumber(c.count)} items</small>
                            </div>
                        </li>
                    `);
                });
            } else {
                $collectionsList.html('<li class="list-group-item text-center text-muted">No collections recorded</li>');
            }
            
            $('#exec-collections-value').text(formatCurrency(totalCollectionsValue));
            $('#exec-collections-count').text(formatNumber(totalCollectionsCount) + ' items');

            const totalPatients = (data.patients_attended_to?.walk_in || 0) + 
                                  Object.values(data.patients_attended_to?.by_clinic || {}).reduce((a, b) => a + b, 0);
            $('#exec-patients-count').text(formatNumber(totalPatients));

            // Clinics List
            const $clinicsList = $('#exec-clinics-list');
            $clinicsList.empty();
            if (data.patients_attended_to?.walk_in) {
                $clinicsList.append(`
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><i class="mdi mdi-walk text-secondary me-2"></i> Walk-in</div>
                        <span class="badge bg-secondary rounded-pill">${formatNumber(data.patients_attended_to.walk_in)}</span>
                    </li>
                `);
            }
            if (data.patients_attended_to?.by_clinic && Object.keys(data.patients_attended_to.by_clinic).length) {
                Object.entries(data.patients_attended_to.by_clinic).forEach(([clinicName, count]) => {
                    $clinicsList.append(`
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="mdi mdi-hospital-building text-info me-2"></i> ${escapeHtml(clinicName)}</div>
                            <span class="badge bg-info rounded-pill">${formatNumber(count)}</span>
                        </li>
                    `);
                });
            } else if (!$clinicsList.children().length) {
                $clinicsList.html('<li class="list-group-item text-center text-muted">No clinic visits</li>');
            }

            // Accordions
            renderHmoAccordion('#accordion-exec-gender', data.gender_distribution || {}, 'gender');
            renderHmoAccordion('#accordion-exec-age', data.age_distribution || {}, 'age');
            renderHmoAccordion('#accordion-exec-class', data.patient_classifications || {}, 'class');
        },
        error: function() {
            $('#executive-summary-loader').addClass('d-none');
            // Show error state gracefully
        }
    });
}

function renderHmoAccordion(containerSelector, dataObj, prefix) {
    const $container = $(containerSelector);
    $container.empty();

    if (!Object.keys(dataObj).length) {
        $container.html('<div class="p-3 text-center text-muted">No data available</div>');
        return;
    }

    let index = 0;
    for (const [categoryName, categoryData] of Object.entries(dataObj)) {
        const catId = `heading-${prefix}-${index}`;
        const collapseId = `collapse-${prefix}-${index}`;
        
        let schemesHtml = '';
        if (categoryData.schemes && Object.keys(categoryData.schemes).length) {
            schemesHtml += '<div class="ms-3 mt-2">';
            for (const [schemeName, schemeData] of Object.entries(categoryData.schemes)) {
                schemesHtml += `
                    <div class="card-modern border-0 mb-1">
                        <div class="d-flex justify-content-between p-2 bg-light rounded align-items-center">
                            <span class="fw-bold text-secondary"><i class="mdi mdi-shield-check-outline me-1"></i> ${escapeHtml(schemeName)}</span>
                            <span class="badge bg-secondary">${formatNumber(schemeData.count)}</span>
                        </div>
                `;
                
                if (schemeData.hmos && Object.keys(schemeData.hmos).length) {
                    schemesHtml += '<ul class="list-group list-group-flush ms-4 border-start border-2 border-light mb-2 mt-1">';
                    for (const [hmoName, count] of Object.entries(schemeData.hmos)) {
                        schemesHtml += `
                            <li class="list-group-item border-0 py-1 ps-3 pe-2 bg-transparent d-flex justify-content-between align-items-center" style="font-size: 0.85rem;">
                                <span class="text-muted">${escapeHtml(hmoName)}</span>
                                <span class="badge bg-light text-dark border">${formatNumber(count)}</span>
                            </li>
                        `;
                    }
                    schemesHtml += '</ul>';
                }
                schemesHtml += '</div>';
            }
            schemesHtml += '</div>';
        }

        $container.append(`
            <div class="accordion-item border mb-2 rounded">
                <h2 class="accordion-header" id="${catId}">
                    <button class="accordion-button collapsed py-2 px-3 bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                            <span class="fw-bold">${escapeHtml(categoryName)}</span>
                            <span class="badge bg-primary rounded-pill">${formatNumber(categoryData.count)}</span>
                        </div>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${catId}" data-bs-parent="${containerSelector}">
                    <div class="accordion-body p-2 pt-0">
                        ${schemesHtml}
                    </div>
                </div>
            </div>
        `);
        index++;
    }
}

// Render deep financial breakdowns
function renderDeepFinancials(containerSelector, collectionsData) {
    const $container = $(containerSelector);
    $container.empty();

    if (!collectionsData || !collectionsData.length) {
        $container.html('<div class="p-4 text-center text-muted">No financial data available</div>');
        return;
    }

    let index = 0;
    collectionsData.forEach(store => {
        const storeId = `heading-fin-store-${index}`;
        const collapseId = `collapse-fin-store-${index}`;

        let schemesHtml = '';
        if (store.schemes && Object.keys(store.schemes).length) {
            schemesHtml += '<div class="ms-3 mt-2">';
            for (const [schemeName, schemeData] of Object.entries(store.schemes)) {
                schemesHtml += `
                    <div class="card-modern border-0 mb-2 shadow-sm">
                        <div class="d-flex justify-content-between p-2 bg-light rounded align-items-center border-start border-4 border-info">
                            <span class="fw-bold text-secondary"><i class="mdi mdi-shield-check-outline me-1"></i> ${escapeHtml(schemeName)}</span>
                            <div class="text-end">
                                <span class="fw-bold text-success me-2">${formatCurrency(schemeData.value)}</span>
                                <span class="badge bg-secondary">${formatNumber(schemeData.count)} items</span>
                            </div>
                        </div>
                `;
                
                if (schemeData.hmos && Object.keys(schemeData.hmos).length) {
                    schemesHtml += '<ul class="list-group list-group-flush ms-4 border-start border-2 border-light mb-2 mt-1">';
                    for (const [hmoName, hmoData] of Object.entries(schemeData.hmos)) {
                        schemesHtml += `
                            <li class="list-group-item border-0 py-2 ps-3 pe-2 bg-transparent d-flex justify-content-between align-items-center" style="font-size: 0.9rem;">
                                <span class="text-muted"><i class="mdi mdi-hospital-building me-1"></i> ${escapeHtml(hmoName)}</span>
                                <div class="text-end">
                                    <span class="fw-bold text-success d-block" style="font-size: 0.85rem;">${formatCurrency(hmoData.value)}</span>
                                    <small class="text-muted d-block">${formatNumber(hmoData.count)} items | Cash: ${formatCurrency(hmoData.cash)} | Claims: ${formatCurrency(hmoData.claims)}</small>
                                </div>
                            </li>
                        `;
                    }
                    schemesHtml += '</ul>';
                }
                schemesHtml += '</div>';
            }
            schemesHtml += '</div>';
        }

        $container.append(`
            <div class="accordion-item border border-primary mb-3 rounded overflow-hidden">
                <h2 class="accordion-header" id="${storeId}">
                    <button class="accordion-button collapsed py-3 px-3 bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                            <span class="fw-bold fs-5 text-primary"><i class="mdi mdi-store me-2"></i>${escapeHtml(store.store_name)}</span>
                            <div class="text-end">
                                <span class="fw-bold fs-5 text-success me-3">${formatCurrency(store.value)}</span>
                                <span class="badge bg-primary rounded-pill px-3 py-2">${formatNumber(store.count)} items total</span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${storeId}">
                    <div class="accordion-body p-3 pt-0 bg-white">
                        ${schemesHtml}
                    </div>
                </div>
            </div>
        `);
        index++;
    });
}

// Hook into existing success handler inside loadExecutiveSummaryData
const originalLoadExecSummaryAjaxSuccess = function(data) {
    // Populate detailed demographic renderers
    renderHmoAccordion('#detailed-gender-container', data.gender_distribution || {}, 'det-gender');
    renderHmoAccordion('#detailed-age-container', data.age_distribution || {}, 'det-age');
    renderHmoAccordion('#detailed-class-container', data.patient_classifications || {}, 'det-class');

    // Populate detailed financial renderer
    renderDeepFinancials('#exec-detailed-financials', data.collections_by_store || []);

    // Populate Financial Performance Summary
    $('#exec-det-opening-stock').text(formatCurrency(data.opening_stock || 0));
    $('#exec-det-purchases').text(formatCurrency(data.total_expenditure || 0));
    $('#exec-det-goods-available').text(formatCurrency((data.opening_stock || 0) + (data.total_expenditure || 0)));
    $('#exec-det-goods-used').text(formatCurrency(data.total_goods_used || 0));
    $('#exec-det-closing-stock').text(formatCurrency(data.stock_valuation || 0));

    // Populate Income by Scheme
    const $incomeList = $('#exec-det-income-scheme-list');
    $incomeList.empty();
    if (data.income_by_scheme && Object.keys(data.income_by_scheme).length) {
        for (const [schemeName, valObj] of Object.entries(data.income_by_scheme)) {
            let total = typeof valObj === 'object' ? (valObj.total || 0) : valObj;
            let cash = typeof valObj === 'object' ? (valObj.cash || 0) : 0;
            let claims = typeof valObj === 'object' ? (valObj.claims || 0) : 0;
            $incomeList.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted d-block">${escapeHtml(schemeName)}</span>
                        <small class="text-secondary" style="font-size: 0.75rem;">Cash: ${formatCurrency(cash)} | Claims: ${formatCurrency(claims)}</small>
                    </div>
                    <span class="fw-bold text-success">${formatCurrency(total)}</span>
                </li>
            `);
        }
    } else {
        $incomeList.html('<li class="list-group-item text-center text-muted">No data</li>');
    }

    // Populate Patients by Scheme
    const $patList = $('#exec-det-patients-scheme-list');
    $patList.empty();
    if (data.patients_by_scheme && Object.keys(data.patients_by_scheme).length) {
        for (const [schemeName, val] of Object.entries(data.patients_by_scheme)) {
            $patList.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted">${escapeHtml(schemeName)}</span>
                    <span class="badge bg-secondary rounded-pill">${formatNumber(val)}</span>
                </li>
            `);
        }
    } else {
        $patList.html('<li class="list-group-item text-center text-muted">No data</li>');
    }
};

$(document).on('click', '#btn-refresh-executive-summary', function() {
    collectFilters();
    loadExecutiveSummaryData();
});

// Intercept ajax call globally or we can just append to the end of loadExecutiveSummaryData
// For simplicity, we hook it via ajaxComplete for the specific URL
$(document).ajaxSuccess(function(event, xhr, settings) {
    if (settings.url.indexOf('/pharmacy-workbench/reports/executive-summary') === 0 && !settings.url.includes('print')) {
        originalLoadExecSummaryAjaxSuccess(xhr.responseJSON);
    }
});

// Toggle Print button visibility based on active sub-tab
$(document).on('shown.bs.tab', 'button[data-bs-toggle="pill"]', function (e) {
    if (e.target.id === 'exec-detailed-sub-tab') {
        $('#btn-print-executive-summary').removeClass('d-none');
    } else {
        $('#btn-print-executive-summary').addClass('d-none');
    }
});

// Print functionality
$(document).on('click', '#btn-print-executive-summary', function() {
    collectFilters();
    const printUrl = '/pharmacy-workbench/reports/executive-summary/print?' + $.param(pharmReportFilters);
    const printWindow = window.open(printUrl, '_blank', 'width=1000,height=800');
});


// Initialize DataTables
function initPharmacyReportsDataTables() {
    // Dispensing Report Table
    if ($.fn.DataTable.isDataTable('#pharm-dispensing-table')) {
        $('#pharm-dispensing-table').DataTable().destroy();
    }

    window.pharmDispensingTable = $('#pharm-dispensing-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ url('/pharmacy-workbench/reports/dispensing') }}',
            data: function(d) {
                return $.extend({}, d, pharmReportFilters);
            }
        },
        columns: [
            { data: 'dispensed_at', render: data => formatDateTimeShort(data) },
            { data: 'reference_no' },
            { data: 'patient_name' },
            { data: 'product_name' },
            { data: 'quantity', className: 'text-center' },
            { data: 'amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'payment_type', render: data => `<span class="badge bg-secondary">${data || 'N/A'}</span>` },
            { data: 'store_name' },
            { data: 'pharmacist_name' },
            {
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    return `<button class="btn btn-xs btn-outline-info" onclick="viewDispensingDetail(${data})" title="View Details">
                        <i class="mdi mdi-eye"></i>
                    </button>`;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Stock Report Table
    if ($.fn.DataTable.isDataTable('#pharm-stock-table')) {
        $('#pharm-stock-table').DataTable().destroy();
    }

    window.pharmStockTable = $('#pharm-stock-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ url('/pharmacy-workbench/reports/stock') }}',
            data: function(d) {
                return $.extend({}, d, {
                    store_id: $('#stock-report-store-filter').val(),
                    category_id: $('#stock-report-category-filter').val(),
                    low_stock_only: $('#stock-show-low-only').is(':checked') ? 1 : 0,
                    date_from: pharmReportFilters.date_from,
                    date_to: pharmReportFilters.date_to
                });
            }
        },
        columns: [
            { data: 'product_name' },
            { data: 'product_code' },
            { data: 'category_name' },
            { data: 'reorder_level', className: 'text-center' },
            { data: 'global_stock', className: 'text-center fw-bold' },
            {
                data: 'store_breakdown',
                orderable: false,
                render: function(data) {
                    if (!data || !data.length) return '<span class="text-muted">N/A</span>';
                    return '<div class="store-stock-breakdown">' +
                        data.map(s => {
                            const qtyClass = s.quantity <= 0 ? 'qty-out' : (s.quantity <= s.reorder_level ? 'qty-low' : 'qty-ok');
                            return `<span class="store-stock-item">
                                <span class="store-name">${escapeHtml(s.store_name)}:</span>
                                <span class="store-qty ${qtyClass}">${s.quantity}</span>
                            </span>`;
                        }).join('') + '</div>';
                }
            },
            { data: 'dispensed_qty', className: 'text-center' },
            { data: 'unit_price', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'stock_value', className: 'text-end', render: data => formatCurrency(data) },
            {
                data: 'status',
                className: 'text-center',
                render: function(data, type, row) {
                    const globalStock = row.global_stock || 0;
                    const reorder = row.reorder_level || 0;

                    if (globalStock <= 0) {
                        return '<span class="stock-status-badge out-of-stock"><i class="mdi mdi-alert-circle"></i> Out</span>';
                    } else if (globalStock <= reorder * 0.5) {
                        return '<span class="stock-status-badge critical"><i class="mdi mdi-alert"></i> Critical</span>';
                    } else if (globalStock <= reorder) {
                        return '<span class="stock-status-badge low-stock"><i class="mdi mdi-alert-outline"></i> Low</span>';
                    } else {
                        return '<span class="stock-status-badge in-stock"><i class="mdi mdi-check-circle"></i> OK</span>';
                    }
                }
            }
        ],
        order: [[4, 'asc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Performance Report Table
    if ($.fn.DataTable.isDataTable('#pharm-performance-table')) {
        $('#pharm-performance-table').DataTable().destroy();
    }

    window.pharmPerformanceTable = $('#pharm-performance-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ url('/pharmacy-workbench/reports/performance') }}',
            data: function(d) {
                return $.extend({}, d, pharmReportFilters);
            },
            dataSrc: function(json) {
                updatePerformanceTotals(json.totals || {});
                return json.data || [];
            }
        },
        columns: [
            { data: 'pharmacist_name' },
            { data: 'total_dispensed', className: 'text-center' },
            { data: 'total_revenue', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'cash_transactions', className: 'text-center' },
            { data: 'hmo_transactions', className: 'text-center' },
            { data: 'cash_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'hmo_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'avg_tat', className: 'text-center', render: data => data ? `${data} min` : '-' },
            { data: 'unique_patients', className: 'text-center' }
        ],
        order: [[2, 'desc']],
        pageLength: 25,
        footerCallback: function() {}
    });

    // HMO Claims Table
    if ($.fn.DataTable.isDataTable('#pharm-hmo-table')) {
        $('#pharm-hmo-table').DataTable().destroy();
    }

    window.pharmHmoTable = $('#pharm-hmo-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '{{ url('/pharmacy-workbench/reports/hmo-claims') }}',
            data: function(d) {
                return $.extend({}, d, pharmReportFilters);
            },
            dataSrc: function(json) {
                updateHmoTotals(json.totals || {});
                return json.data || [];
            }
        },
        columns: [
            { data: 'hmo_name' },
            { data: 'total_claims', className: 'text-center' },
            { data: 'total_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'validated_count', className: 'text-center' },
            { data: 'validated_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'pending_count', className: 'text-center' },
            { data: 'pending_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'rejected_count', className: 'text-center' },
            { data: 'rejected_amount', className: 'text-end', render: data => formatCurrency(data) }
        ],
        order: [[2, 'desc']],
        pageLength: 25
    });

    // Revenue Table (custom implementation)
    initRevenueTable();
}

// Revenue table with grouping
function initRevenueTable() {
    if ($.fn.DataTable.isDataTable('#pharm-revenue-table')) {
        $('#pharm-revenue-table').DataTable().destroy();
    }

    loadRevenueData('daily');
}

$('input[name="revenue-group"]').on('change', function() {
    loadRevenueData($(this).val());
});

function loadRevenueData(groupBy) {
    $.ajax({
        url: '{{ url('/pharmacy-workbench/reports/revenue') }}',
        method: 'GET',
        data: $.extend({}, pharmReportFilters, { group_by: groupBy }),
        success: function(response) {
            const $tbody = $('#pharm-revenue-table tbody');
            $tbody.empty();

            if (!response.data || !response.data.length) {
                $tbody.html('<tr><td colspan="8" class="text-center text-muted py-3">No revenue data available</td></tr>');
                updateRevenueTotals({});
                return;
            }

            response.data.forEach(row => {
                $tbody.append(`
                    <tr>
                        <td>${escapeHtml(row.period)}</td>
                        <td class="text-center">${formatNumber(row.transactions)}</td>
                        <td class="text-end">${formatCurrency(row.cash)}</td>
                        <td class="text-end">${formatCurrency(row.card)}</td>
                        <td class="text-end">${formatCurrency(row.transfer)}</td>
                        <td class="text-end">${formatCurrency(row.hmo)}</td>
                        <td class="text-end fw-bold">${formatCurrency(row.total)}</td>
                        <td class="text-end">${formatCurrency(row.avg_transaction)}</td>
                    </tr>
                `);
            });

            updateRevenueTotals(response.totals || {});
        }
    });
}

function updateRevenueTotals(totals) {
    $('#revenue-total-txn').text(formatNumber(totals.transactions || 0));
    $('#revenue-total-cash').text(formatCurrency(totals.cash || 0));
    $('#revenue-total-card').text(formatCurrency(totals.card || 0));
    $('#revenue-total-transfer').text(formatCurrency(totals.transfer || 0));
    $('#revenue-total-hmo').text(formatCurrency(totals.hmo || 0));
    $('#revenue-total-all').text(formatCurrency(totals.total || 0));
    $('#revenue-total-avg').text(formatCurrency(totals.avg_transaction || 0));
}

function updatePerformanceTotals(totals) {
    $('#perf-total-dispensed').text(formatNumber(totals.total_dispensed || 0));
    $('#perf-total-revenue').text(formatCurrency(totals.total_revenue || 0));
    $('#perf-total-cash-txn').text(formatNumber(totals.cash_transactions || 0));
    $('#perf-total-hmo-txn').text(formatNumber(totals.hmo_transactions || 0));
    $('#perf-total-cash-amt').text(formatCurrency(totals.cash_amount || 0));
    $('#perf-total-hmo-amt').text(formatCurrency(totals.hmo_amount || 0));
    $('#perf-avg-tat').text(totals.avg_tat ? `${totals.avg_tat} min` : '-');
    $('#perf-total-patients').text(formatNumber(totals.unique_patients || 0));
}

function updateHmoTotals(totals) {
    $('#hmo-total-claims').text(formatNumber(totals.total_claims || 0));
    $('#hmo-total-amount').text(formatCurrency(totals.total_amount || 0));
    $('#hmo-total-validated').text(formatNumber(totals.validated_count || 0));
    $('#hmo-total-validated-amt').text(formatCurrency(totals.validated_amount || 0));
    $('#hmo-total-pending').text(formatNumber(totals.pending_count || 0));
    $('#hmo-total-pending-amt').text(formatCurrency(totals.pending_amount || 0));
    $('#hmo-total-rejected').text(formatNumber(totals.rejected_count || 0));
    $('#hmo-total-rejected-amt').text(formatCurrency(totals.rejected_amount || 0));
}

// Refresh all DataTables
function refreshPharmacyDataTables() {
    if (window.pharmDispensingTable) window.pharmDispensingTable.ajax.reload();
    if (window.pharmStockTable) window.pharmStockTable.ajax.reload();
    if (window.pharmPerformanceTable) window.pharmPerformanceTable.ajax.reload();
    if (window.pharmHmoTable) window.pharmHmoTable.ajax.reload();
    loadRevenueData($('input[name="revenue-group"]:checked').val() || 'daily');
}

// Stock report filter handlers
$('#stock-report-store-filter, #stock-report-category-filter').on('change', function() {
    if (window.pharmStockTable) window.pharmStockTable.ajax.reload();
});

$('#stock-show-low-only').on('change', function() {
    if (window.pharmStockTable) window.pharmStockTable.ajax.reload();
});

// Initialize Charts
function initPharmacyReportsCharts() {
    // Trend Chart
    const trendCtx = document.getElementById('pharm-trend-chart');
    if (trendCtx) {
        window.pharmTrendChart = new Chart(trendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Dispensed Items',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Revenue (₦)',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 1) {
                                    return `Revenue: ${formatCurrency(context.raw)}`;
                                }
                                return `${context.dataset.label}: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Items' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Revenue (₦)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }

    // Revenue Pie Chart
    const pieCtx = document.getElementById('pharm-revenue-pie');
    if (pieCtx) {
        window.pharmRevenuePieChart = new Chart(pieCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'Card', 'Transfer', 'HMO', 'Account'],
                datasets: [{
                    data: [0, 0, 0, 0, 0],
                    backgroundColor: [
                        '#28a745',
                        '#17a2b8',
                        '#6f42c1',
                        '#fd7e14',
                        '#e83e8c'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total> 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${formatCurrency(context.raw)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

// Update charts with data
function updateTrendChart(data) {
    if (!window.pharmTrendChart || !data.length) return;

    window.pharmTrendChart.data.labels = data.map(d => d.date);
    window.pharmTrendChart.data.datasets[0].data = data.map(d => d.items);
    window.pharmTrendChart.data.datasets[1].data = data.map(d => d.revenue);
    window.pharmTrendChart.update();
}

function updateRevenuePieChart(breakdown) {
    if (!window.pharmRevenuePieChart) return;

    window.pharmRevenuePieChart.data.datasets[0].data = [
        breakdown.cash || 0,
        breakdown.card || 0,
        breakdown.transfer || 0,
        breakdown.hmo || 0,
        breakdown.account || 0
    ];
    window.pharmRevenuePieChart.update();
}

// Export functions
$('#export-reports-excel').on('click', function() {
    exportReportsToExcel();
});

$('#export-reports-pdf').on('click', function() {
    toastr.info('PDF export will be generated. Please use Print> Save as PDF for now.');
    printReports();
});

$('#print-reports').on('click', function() {
    printReports();
});

function exportReportsToExcel() {
    const activeTabId = $('#pharmacy-report-tabs .nav-link.active').attr('id');
    const tabName = $('#pharmacy-report-tabs .nav-link.active').text().trim();

    toastr.info('Preparing Excel export...');

    try {
        const wb = XLSX.utils.book_new();

        // Add summary sheet with statistics
        const summaryData = [
            ['Pharmacy Reports & Analytics'],
            ['Generated:', new Date().toLocaleString()],
            ['Report Type:', tabName],
            ['Date Range:', `${$('#pharm-report-date-from').val() || 'All'} to ${$('#pharm-report-date-to').val() || 'All'}`],
            [],
            ['Key Statistics:'],
            ['Dispensed:', $('#pharm-stat-dispensed').text()],
            ['Total Revenue:', $('#pharm-stat-revenue').text()],
            ['Cash Sales:', $('#pharm-stat-cash').text()],
            ['HMO Claims:', $('#pharm-stat-hmo').text()],
            ['Patients:', $('#pharm-stat-patients').text()],
            ['Pending:', $('#pharm-stat-pending').text()],
        ];

        const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
        XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

        // Export based on active tab
        switch(activeTabId) {
            case 'pharm-overview-tab':
                exportOverviewData(wb);
                break;
            case 'pharm-dispensing-tab':
                exportDispensingData(wb);
                break;
            case 'pharm-revenue-tab':
                exportRevenueData(wb);
                break;
            case 'pharm-stock-tab':
                exportStockData(wb);
                break;
            case 'pharm-performance-tab':
                exportPerformanceData(wb);
                break;
            case 'pharm-hmo-tab':
                exportHmoData(wb);
                break;
        }

        // Download file
        const fileName = `Pharmacy_${tabName.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.xlsx`;
        XLSX.writeFile(wb, fileName);
        toastr.success('Excel file downloaded successfully');

    } catch (error) {
        console.error('Export error:', error);
        toastr.error('Failed to export data');
    }
}

function exportOverviewData(wb) {
    // Top Products
    const topProducts = [];
    $('#pharm-top-products-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            topProducts.push({
                'Rank': $(this).find('td:eq(0)').text(),
                'Product': $(this).find('td:eq(1)').text(),
                'Quantity': $(this).find('td:eq(2)').text(),
                'Revenue': $(this).find('td:eq(3)').text()
            });
        }
    });
    if (topProducts.length> 0) {
        const ws1 = XLSX.utils.json_to_sheet(topProducts);
        XLSX.utils.book_append_sheet(wb, ws1, 'Top Products');
    }

    // Payment Methods
    const paymentMethods = [];
    $('#pharm-payment-methods-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            paymentMethods.push({
                'Method': $(this).find('td:eq(0)').text(),
                'Transactions': $(this).find('td:eq(1)').text(),
                'Amount': $(this).find('td:eq(2)').text(),
                'Percentage': $(this).find('td:eq(3)').text()
            });
        }
    });
    if (paymentMethods.length> 0) {
        const ws2 = XLSX.utils.json_to_sheet(paymentMethods);
        XLSX.utils.book_append_sheet(wb, ws2, 'Payment Methods');
    }
}

function exportDispensingData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-dispensing-table')) {
        const table = $('#pharm-dispensing-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Date/Time': row[0],
            'Ref #': row[1],
            'Patient': row[2],
            'File No': row[3],
            'Product': row[4],
            'Quantity': row[5],
            'Pharmacist': row[6],
            'Store': row[7],
            'Amount': row[8],
            'Payment': row[9],
            'Status': row[10]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Dispensing Records');
    }
}

function exportRevenueData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-revenue-table')) {
        const table = $('#pharm-revenue-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Date': row[0],
            'Ref #': row[1],
            'Patient': row[2],
            'File No': row[3],
            'Services': row[4],
            'Gross Amount': row[5],
            'Discount': row[6],
            'Net Amount': row[7],
            'Payment Method': row[8],
            'HMO': row[9]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Revenue Records');
    }
}

function exportStockData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-stock-table')) {
        const table = $('#pharm-stock-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Product': row[0],
            'Category': row[1],
            'Total Stock': row[2],
            'Available': row[3],
            'Allocated': row[4],
            'Reorder Level': row[5],
            'Status': row[6],
            'Store Breakdown': row[7]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Stock Status');
    }
}

function exportPerformanceData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-performance-table')) {
        const table = $('#pharm-performance-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Pharmacist': row[0],
            'Transactions': row[1],
            'Items Dispensed': row[2],
            'Total Revenue': row[3],
            'Avg Transaction': row[4],
            'Patients Served': row[5],
            'Work Hours': row[6],
            'Efficiency': row[7]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Performance Metrics');
    }
}

function exportHmoData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-hmo-table')) {
        const table = $('#pharm-hmo-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Date': row[0],
            'Ref #': row[1],
            'Patient': row[2],
            'File No': row[3],
            'HMO': row[4],
            'Services': row[5],
            'Amount': row[6],
            'Validation Status': row[7],
            'Validated By': row[8],
            'Remarks': row[9]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'HMO Claims');
    }
}

function printReports() {
    const activeTabId = $('#pharmacy-report-tabs .nav-link.active').attr('id');
    const tabName = $('#pharmacy-report-tabs .nav-link.active').text().trim();
    const dateFrom = $('#pharm-report-date-from').val() || 'All';
    const dateTo = $('#pharm-report-date-to').val() || 'All';

    // Get statistics
    const stats = {
        dispensed: $('#pharm-stat-dispensed').text(),
        revenue: $('#pharm-stat-revenue').text(),
        cash: $('#pharm-stat-cash').text(),
        hmo: $('#pharm-stat-hmo').text(),
        patients: $('#pharm-stat-patients').text(),
        pending: $('#pharm-stat-pending').text()
    };

    let reportContent = '';

    // Build content based on active tab
    switch(activeTabId) {
        case 'pharm-overview-tab':
            reportContent = buildOverviewPrintContent();
            break;
        case 'pharm-dispensing-tab':
            reportContent = buildDispensingPrintContent();
            break;
        case 'pharm-revenue-tab':
            reportContent = buildRevenuePrintContent();
            break;
        case 'pharm-stock-tab':
            reportContent = buildStockPrintContent();
            break;
        case 'pharm-performance-tab':
            reportContent = buildPerformancePrintContent();
            break;
        case 'pharm-hmo-tab':
            reportContent = buildHmoPrintContent();
            break;
    }

    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Pharmacy Report - ${tabName}</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    font-size: 11px;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 25px;
                    padding-bottom: 15px;
                    border-bottom: 3px solid #667eea;
                }
                .print-header h2 {
                    color: #667eea;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(6, 1fr);
                    gap: 10px;
                    margin-bottom: 20px;
                }
                .stat-box {
                    text-align: center;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 5px;
                    border-left: 3px solid #667eea;
                }
                .stat-box h5 {
                    margin: 0;
                    font-size: 16px;
                    color: #333;
                    font-weight: bold;
                }
                .stat-box small {
                    color: #666;
                    font-size: 10px;
                    text-transform: uppercase;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    font-size: 10px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 6px 8px;
                    text-align: left;
                }
                th {
                    background: #667eea;
                    color: white;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }
                .no-print {
                    display: none;
                }
                @media print {
                    body { padding: 10px; }
                    .no-print { display: none !important; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                    thead { display: table-header-group; }
                }
                .info-section {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #e7f1ff;
                    border-radius: 5px;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 2px solid #ddd;
                    text-align: center;
                    font-size: 9px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h2>Pharmacy Reports & Analytics</h2>
                <h4>${tabName}</h4>
                <p style="margin: 5px 0; color: #666;">
                    Date Range: ${dateFrom} to ${dateTo} |
                    Generated: ${new Date().toLocaleString()}
                </p>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <h5>${stats.dispensed}</h5>
                    <small>Dispensed</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.revenue}</h5>
                    <small>Revenue</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.cash}</h5>
                    <small>Cash</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.hmo}</h5>
                    <small>HMO</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.patients}</h5>
                    <small>Patients</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.pending}</h5>
                    <small>Pending</small>
                </div>
            </div>

            ${reportContent}

            <div class="footer">
                <p><strong>{{ appsettings('hos_name', 'Hospital Management System') }}</strong></p>
                <p>This is a system-generated report</p>
            </div>

            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function buildOverviewPrintContent() {
    let html = '<div class="info-section"><h5>Overview Report</h5></div>';

    // Top Products
    html += '<h6>Top 10 Products</h6><table><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead><tbody>';
    $('#pharm-top-products-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            html += '<tr>';
            $(this).find('td').each(function() {
                html += `<td>${$(this).text()}</td>`;
            });
            html += '</tr>';
        }
    });
    html += '</tbody></table>';

    // Payment Methods
    html += '<h6>Payment Methods</h6><table><thead><tr><th>Method</th><th>Transactions</th><th>Amount</th><th>%</th></tr></thead><tbody>';
    $('#pharm-payment-methods-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            html += '<tr>';
            $(this).find('td').each(function() {
                html += `<td>${$(this).text()}</td>`;
            });
            html += '</tr>';
        }
    });
    html += '</tbody></table>';

    return html;
}

function buildDispensingPrintContent() {
    let html = '<div class="info-section"><h5>Dispensing Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-dispensing-table')) {
        const table = $('#pharm-dispensing-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                // Strip HTML tags for clean printing
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildRevenuePrintContent() {
    let html = '<div class="info-section"><h5>Revenue Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-revenue-table')) {
        const table = $('#pharm-revenue-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildStockPrintContent() {
    let html = '<div class="info-section"><h5>Stock Status Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-stock-table')) {
        const table = $('#pharm-stock-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildPerformancePrintContent() {
    let html = '<div class="info-section"><h5>Performance Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-performance-table')) {
        const table = $('#pharm-performance-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildHmoPrintContent() {
    let html = '<div class="info-section"><h5>HMO Claims Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-hmo-table')) {
        const table = $('#pharm-hmo-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

// Helper functions
function formatNumber(num) {
    return new Intl.NumberFormat().format(num || 0);
}

function formatCurrency(amount) {
    return '₦' + new Intl.NumberFormat().format(parseFloat(amount || 0).toFixed(2));
}

function formatDateTimeShort(dateString) {
    if (!dateString) return 'N/A';
    const d = new Date(dateString);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
           ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function viewDispensingDetail(id) {
    // TODO: Implement detail view modal
    toastr.info('Detail view coming soon');
}

// Tab change handler - lazy load data
$('#pharmacy-report-tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
    const tabId = $(e.target).attr('id');
    // Tables are already initialized, just let DataTables handle it
});

// ===========================================
// END PHARMACY REPORTS MODULE
// ===========================================

// ===========================================
// PRODUCT ADAPTATION MODULE
// ===========================================

// Store selected new product data
let selectedNewProduct = null;

// Open product adaptation modal with billing status awareness
function openAdaptationModal(productRequestId, productName, dose, qty, price, status, payable, claims, isPaid, isValidated, coverageMode, productCode) {
    // Reset modal state
    selectedNewProduct = null;
    $('#adapt-product-request-id').val(productRequestId);
    $('#adapt-billing-status').val(status || 'unbilled');
    $('#adapt-coverage-mode').val(coverageMode || 'none');

    // Store original values for calculations
    const originalQty = parseInt(qty) || 1;
    const originalPrice = parseFloat(price) || 0;
    const originalTotal = originalPrice * originalQty;

    $('#adapt-original-price-value').val(originalPrice);
    $('#adapt-original-qty-value').val(originalQty);

    // Set original product info (enhanced layout)
    $('#adapt-original-product').text(productName);
    $('#adapt-original-code').text(productCode || '-');
    $('#adapt-original-dose').text(dose || 'N/A');
    $('#adapt-original-qty').text(originalQty);
    $('#adapt-original-price').text('₦' + formatMoneyPharmacy(originalPrice));
    $('#adapt-original-total').text('₦' + formatMoneyPharmacy(originalTotal));

    // Set status badge
    const statusBadge = status === 'billed' ?
        '<span class="badge bg-success">Billed</span>' :
        '<span class="badge bg-secondary">Unbilled</span>';
    $('#adapt-original-status-badge').html(statusBadge);

    // Set calculation summary original
    $('#adapt-calc-original').text('₦' + formatMoneyPharmacy(originalTotal));
    $('#adapt-calc-new').text('₦0.00');
    $('#adapt-calc-diff').html('<span class="text-muted">₦0.00</span>');
    $('#adapt-calc-note').hide();

    // Reset new product selection
    $('#adapt-new-product').val('').trigger('change');
    $('#adapt-new-qty').val(originalQty);
    $('#adapt-reason').val('');
    $('#adapt-new-product-details').hide();
    $('#adapt-no-product-selected').show();
    $('#adapt-store-stocks').empty();
    $('#adapt-hmo-info').hide();
    $('#confirm-adaptation').prop('disabled', true);
    $('#adapt-summary-text').text('Select a new product to see the changes');

    // Reset step indicators
    $('.adapt-steps .step').removeClass('active');
    $('#adapt-step-1').addClass('active');

    // Show/hide relevant notices and billing info based on status
    const isBilled = status === 'billed';
    $('#adapt-unbilled-notice').toggle(!isBilled);
    $('#adapt-billed-notice').toggle(isBilled);
    $('#adapt-current-billing').toggle(isBilled);
    $('#adapt-billing-impact').hide();

    if (isBilled) {
        $('#adapt-current-payable').text('₦' + formatMoneyPharmacy(payable || 0));
        $('#adapt-current-claims').text('₦' + formatMoneyPharmacy(claims || 0));
        $('#adapt-current-coverage').text((coverageMode || 'none').toUpperCase());
    }

    // Initialize Select2 for product search if not already done
    if (!$('#adapt-new-product').hasClass('select2-hidden-accessible')) {
        $('#adapt-new-product').select2({
            dropdownParent: $('#productAdaptationModal'),
            placeholder: 'Type to search products...',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ url('/pharmacy-workbench/search-products') }}',
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return {
                        term: params.term,
                        patient_id: currentPatient
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(p => ({
                            id: p.id,
                            text: `${p.product_name} (${p.product_code || 'N/A'}) - ₦${formatMoneyPharmacy(p.price || 0)} [Stock: ${p.stock_qty || 0}]`,
                            product: p
                        }))
                    };
                }
            }
        }).on('select2:select', function(e) {
            selectedNewProduct = e.params.data.product;
            updateAdaptationPreview();
        }).on('select2:clear', function() {
            selectedNewProduct = null;
            $('#adapt-new-product-details').hide();
            $('#adapt-no-product-selected').show();
            $('#adapt-billing-impact').hide();
            $('#adapt-hmo-info').hide();
            $('#confirm-adaptation').prop('disabled', true);
            $('#adapt-step-2').removeClass('active');
            $('#adapt-step-3').removeClass('active');
            $('#adapt-calc-new').text('₦0.00');
            $('#adapt-calc-diff').html('<span class="text-muted">₦0.00</span>');
            $('#adapt-calc-note').hide();
            $('#adapt-summary-text').text('Select a new product to see the changes');
        });
    }

    $('#productAdaptationModal').modal('show');
}

// Update adaptation preview when new product or quantity changes
function updateAdaptationPreview() {
    const isBilled = $('#adapt-billing-status').val() === 'billed';
    const newQty = parseInt($('#adapt-new-qty').val()) || 1;
    const originalPrice = parseFloat($('#adapt-original-price-value').val()) || 0;
    const originalQty = parseInt($('#adapt-original-qty-value').val()) || 1;
    const originalTotal = originalPrice * originalQty;

    if (!selectedNewProduct) {
        $('#adapt-new-product-details').hide();
        $('#adapt-no-product-selected').show();
        $('#adapt-billing-impact').hide();
        $('#adapt-hmo-info').hide();
        $('#confirm-adaptation').prop('disabled', true);
        return;
    }

    // Show new product details section
    $('#adapt-no-product-selected').hide();
    $('#adapt-new-product-details').show();

    // Show new product price
    const newPrice = selectedNewProduct.price || 0;
    const newTotal = newPrice * newQty;
    $('#adapt-new-price').text('₦' + formatMoneyPharmacy(newPrice));

    // Update step indicators
    $('#adapt-step-2').addClass('active');

    // ===== STORE STOCKS DISPLAY =====
    const storeStocks = selectedNewProduct.store_stocks || [];
    const globalStock = selectedNewProduct.stock_qty || 0;
    const $stockContainer = $('#adapt-store-stocks');
    $stockContainer.empty();

    // Update stock badge with intuitive thresholds
    // Out (0), Critical (1-5), Low (6-20), OK (>20)
    const $stockBadge = $('#adapt-stock-badge');
    $stockBadge.removeClass('bg-success bg-warning bg-danger badge-stock-ok badge-stock-low badge-stock-critical badge-stock-out');

    if (globalStock <= 0) {
        $stockBadge.addClass('badge-stock-out').html('<i class="mdi mdi-alert-circle"></i> Out of Stock');
    } else if (globalStock <= 5) {
        $stockBadge.addClass('badge-stock-critical').html(`<i class="mdi mdi-alert"></i> ${globalStock} only!`);
    } else if (globalStock <= 20) {
        $stockBadge.addClass('badge-stock-low').html(`<i class="mdi mdi-alert-outline"></i> ${globalStock} left`);
    } else {
        $stockBadge.addClass('badge-stock-ok').text(globalStock + ' in stock');
    }

    // Display store stocks with intuitive thresholds
    if (storeStocks.length> 0) {
        storeStocks.forEach(function(store) {
            let stockClass, stockIcon;
            if (store.quantity <= 0) {
                stockClass = 'text-danger';
                stockIcon = 'mdi-alert-circle';
            } else if (store.quantity <= 5) {
                stockClass = 'text-danger';
                stockIcon = 'mdi-alert';
            } else if (store.quantity <= 20) {
                stockClass = 'text-warning';
                stockIcon = 'mdi-alert-outline';
            } else {
                stockClass = 'text-success';
                stockIcon = 'mdi-check-circle';
            }
            $stockContainer.append(`
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><i class="mdi mdi-store text-muted"></i> ${store.store_name}</span>
                    <strong class="${stockClass}"><i class="mdi ${stockIcon} small"></i> ${store.quantity}</strong>
                </div>
            `);
        });
    } else {
        $stockContainer.html('<div class="text-center text-muted py-2"><i class="mdi mdi-alert-circle-outline"></i> No stock available</div>');
    }

    // ===== PRICE CALCULATION SUMMARY =====
    $('#adapt-calc-original').text('₦' + formatMoneyPharmacy(originalTotal));
    $('#adapt-calc-new').text('₦' + formatMoneyPharmacy(newTotal));

    const priceDiff = newTotal - originalTotal;
    let diffHtml = '';
    let calcNote = '';

    if (priceDiff> 0) {
        diffHtml = `<span class="text-danger fw-bold">+₦${formatMoneyPharmacy(priceDiff)}</span>`;
        calcNote = '<i class="mdi mdi-arrow-up text-danger"></i> Patient will pay more';
    } else if (priceDiff < 0) {
        diffHtml = `<span class="text-success fw-bold">-₦${formatMoneyPharmacy(Math.abs(priceDiff))}</span>`;
        calcNote = '<i class="mdi mdi-arrow-down text-success"></i> Patient saves money!';
    } else {
        diffHtml = '<span class="text-muted">₦0.00</span>';
        calcNote = '<i class="mdi mdi-equal text-muted"></i> No price change';
    }

    $('#adapt-calc-diff').html(diffHtml);
    if (calcNote) {
        $('#adapt-calc-note').html(calcNote).show();
    } else {
        $('#adapt-calc-note').hide();
    }

    // ===== HMO COVERAGE INFO =====
    const coverageMode = selectedNewProduct.coverage_mode || $('#adapt-coverage-mode').val();
    const newPayable = selectedNewProduct.payable_amount || newTotal;
    const newClaims = selectedNewProduct.claims_amount || 0;

    if (coverageMode && coverageMode !== 'none') {
        $('#adapt-coverage-badge').text(coverageMode.toUpperCase());
        $('#adapt-new-payable').text('₦' + formatMoneyPharmacy(newPayable * newQty));
        $('#adapt-new-claims').text('₦' + formatMoneyPharmacy(newClaims * newQty));
        $('#adapt-hmo-info').show();
    } else {
        $('#adapt-hmo-info').hide();
    }

    // ===== BILLING IMPACT FOR BILLED ITEMS =====
    if (isBilled) {
        const currentPayable = parseFloat($('#adapt-current-payable').text().replace(/[₦,]/g, '')) || 0;
        const currentClaims = parseFloat($('#adapt-current-claims').text().replace(/[₦,]/g, '')) || 0;
        const currentTotal = currentPayable + currentClaims;

        let impactPayable = newTotal;
        let impactClaims = 0;

        // Apply same coverage ratio if HMO coverage exists
        if (coverageMode && coverageMode !== 'none' && currentTotal> 0) {
            const payableRatio = currentPayable / currentTotal;
            const claimsRatio = currentClaims / currentTotal;
            impactPayable = newTotal * payableRatio;
            impactClaims = newTotal * claimsRatio;
        }

        // Update impact table
        $('#adapt-impact-payable-old').text('₦' + formatMoneyPharmacy(currentPayable));
        $('#adapt-impact-payable-new').text('₦' + formatMoneyPharmacy(impactPayable));
        updateDiffBadge('#adapt-impact-payable-diff', impactPayable - currentPayable);

        $('#adapt-impact-claims-old').text('₦' + formatMoneyPharmacy(currentClaims));
        $('#adapt-impact-claims-new').text('₦' + formatMoneyPharmacy(impactClaims));
        updateDiffBadge('#adapt-impact-claims-diff', impactClaims - currentClaims);

        $('#adapt-impact-total-old').text('₦' + formatMoneyPharmacy(currentTotal));
        $('#adapt-impact-total-new').text('₦' + formatMoneyPharmacy(newTotal));
        updateDiffBadge('#adapt-impact-total-diff', newTotal - currentTotal);

        // Add note about billing update
        let note = 'The billing record will be automatically updated with the new amounts.';
        if (newTotal> currentTotal) {
            note += ' The patient/HMO will owe an additional amount.';
        } else if (newTotal < currentTotal) {
            note += ' A credit/refund will be recorded.';
        }
        $('#adapt-impact-note').html('<i class="mdi mdi-information-outline"></i> ' + note);

        $('#adapt-billing-impact').show();
    }

    // ===== UPDATE SUMMARY TEXT =====
    const productName = selectedNewProduct.product_name || 'selected product';
    let summaryText = `Adapting to "${productName}" × ${newQty} = ₦${formatMoneyPharmacy(newTotal)}`;
    if (priceDiff !== 0) {
        summaryText += ` (${priceDiff> 0 ? '+' : ''}₦${formatMoneyPharmacy(priceDiff)})`;
    }
    $('#adapt-summary-text').html(summaryText);

    // Enable confirm button
    $('#confirm-adaptation').prop('disabled', false);
    $('#adapt-step-3').addClass('active');
}

// Helper to update diff badge with color
function updateDiffBadge(selector, diff) {
    const formatted = (diff>= 0 ? '+' : '') + '₦' + formatMoneyPharmacy(Math.abs(diff));
    const badgeClass = diff> 0 ? 'text-danger' : (diff < 0 ? 'text-success' : 'text-muted');
    $(selector).html(`<span class="${badgeClass}">${formatted}</span>`);
}

// Listen for qty change to update preview
$('#adapt-new-qty').on('change input', function() {
    updateAdaptationPreview();
});

// Quantity +/- buttons
$('#adapt-qty-minus').on('click', function() {
    const $input = $('#adapt-new-qty');
    const current = parseInt($input.val()) || 1;
    if (current> 1) {
        $input.val(current - 1);
        updateAdaptationPreview();
    }
});

$('#adapt-qty-plus').on('click', function() {
    const $input = $('#adapt-new-qty');
    const current = parseInt($input.val()) || 1;
    $input.val(current + 1);
    updateAdaptationPreview();
});

// Quick reason buttons
$(document).on('click', '.adapt-quick-reason', function() {
    const reason = $(this).data('reason');
    const $textarea = $('#adapt-reason');
    const currentText = $textarea.val().trim();

    if (currentText) {
        $textarea.val(currentText + '; ' + reason);
    } else {
        $textarea.val(reason);
    }

    // Highlight the button
    $(this).addClass('btn-secondary').removeClass('btn-outline-secondary');
});

// Confirm product adaptation
$('#confirm-adaptation').on('click', function() {
    const productRequestId = $('#adapt-product-request-id').val();
    const newProductId = $('#adapt-new-product').val();
    const newQty = $('#adapt-new-qty').val();
    const reason = $('#adapt-reason').val().trim();

    if (!newProductId) {
        toastr.warning('Please select a new product');
        return;
    }

    if (!reason) {
        toastr.warning('Please enter a reason for adaptation');
        $('#adapt-reason').focus();
        return;
    }

    const $btn = $(this);
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: `{{ url('/pharmacy-workbench/prescription/${productRequestId}/adapt') }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            new_product_id: newProductId,
            new_qty: newQty,
            adaptation_note: reason
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.success(response.message || 'Prescription adapted successfully');
            $('#productAdaptationModal').modal('hide');

            // Refresh prescription lists
            loadPrescriptionItems(currentStatusFilter);
            refreshAllPrescTables();
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to adapt prescription');
        }
    });
});

// ===========================================
// END PRODUCT ADAPTATION MODULE
// ===========================================

// ===========================================
// QUANTITY ADJUSTMENT MODULE
// ===========================================

// Open quantity adjustment modal with billing status awareness
function openQtyAdjustmentModal(productRequestId, productName, currentQty, price, status, payable, claims, isPaid, isValidated, coverageMode) {
    $('#qty-adjust-request-id').val(productRequestId);
    $('#qty-adjust-billing-status').val(status || 'unbilled');
    $('#qty-adjust-price').val(price || 0);
    $('#qty-adjust-coverage-mode').val(coverageMode || 'none');

    // Set product info
    $('#qty-adjust-product-name').text(productName);
    $('#qty-adjust-unit-price').text('₦' + formatMoneyPharmacy(price || 0));
    $('#qty-adjust-current').text(currentQty || 1);
    $('#qty-adjust-new').val(currentQty || 1);
    $('#qty-adjust-reason').val('');

    // Show/hide relevant notices and billing info based on status
    const isBilled = status === 'billed';
    $('#qty-unbilled-notice').toggle(!isBilled);
    $('#qty-billed-notice').toggle(isBilled);
    $('#qty-current-billing').toggle(isBilled);
    $('#qty-billing-impact').toggle(isBilled);

    if (isBilled) {
        $('#qty-current-payable').text('₦' + formatMoneyPharmacy(payable || 0));
        $('#qty-current-claims').text('₦' + formatMoneyPharmacy(claims || 0));
        // Trigger initial preview
        updateQtyAdjustmentPreview();
    }

    $('#qtyAdjustmentModal').modal('show');
}

// Increment/decrement helpers
function adjustQtyIncrement() {
    const $input = $('#qty-adjust-new');
    $input.val(parseInt($input.val() || 0) + 1);
    updateQtyAdjustmentPreview();
}

function adjustQtyDecrement() {
    const $input = $('#qty-adjust-new');
    const current = parseInt($input.val() || 0);
    if (current> 1) {
        $input.val(current - 1);
        updateQtyAdjustmentPreview();
    }
}

// Update quantity adjustment preview
function updateQtyAdjustmentPreview() {
    const isBilled = $('#qty-adjust-billing-status').val() === 'billed';
    if (!isBilled) return;

    const currentQty = parseInt($('#qty-adjust-current').text()) || 1;
    const newQty = parseInt($('#qty-adjust-new').val()) || 1;
    const unitPrice = parseFloat($('#qty-adjust-price').val()) || 0;
    const coverageMode = $('#qty-adjust-coverage-mode').val();

    const currentPayable = parseFloat($('#qty-current-payable').text().replace(/[₦,]/g, '')) || 0;
    const currentClaims = parseFloat($('#qty-current-claims').text().replace(/[₦,]/g, '')) || 0;
    const currentTotal = currentPayable + currentClaims;

    // Calculate new amounts
    const newTotal = unitPrice * newQty;
    let newPayable = newTotal;
    let newClaims = 0;

    // Apply same coverage ratio if HMO coverage
    if (coverageMode && coverageMode !== 'none' && currentTotal> 0) {
        const payableRatio = currentPayable / currentTotal;
        const claimsRatio = currentClaims / currentTotal;
        newPayable = newTotal * payableRatio;
        newClaims = newTotal * claimsRatio;
    }

    // Update impact display
    $('#qty-impact-payable-old').text('₦' + formatMoneyPharmacy(currentPayable));
    $('#qty-impact-payable-new').text('₦' + formatMoneyPharmacy(newPayable));
    updateQtyDiffBadge('#qty-impact-payable-diff', newPayable - currentPayable);

    $('#qty-impact-claims-old').text('₦' + formatMoneyPharmacy(currentClaims));
    $('#qty-impact-claims-new').text('₦' + formatMoneyPharmacy(newClaims));
    updateQtyDiffBadge('#qty-impact-claims-diff', newClaims - currentClaims);
}

function updateQtyDiffBadge(selector, diff) {
    const formatted = (diff>= 0 ? '+' : '-') + '₦' + formatMoneyPharmacy(Math.abs(diff));
    const badgeClass = diff> 0 ? 'bg-danger' : (diff < 0 ? 'bg-success' : 'bg-secondary');
    $(selector).removeClass('bg-danger bg-success bg-secondary').addClass(badgeClass).text(formatted);
}

// Listen for qty input change
$('#qty-adjust-new').on('change input', function() {
    updateQtyAdjustmentPreview();
});

// Confirm quantity adjustment
$('#confirm-qty-adjustment').on('click', function() {
    const productRequestId = $('#qty-adjust-request-id').val();
    const newQty = $('#qty-adjust-new').val();
    const currentQty = $('#qty-adjust-current').text();
    const reason = $('#qty-adjust-reason').val().trim();

    if (!newQty || newQty < 1) {
        toastr.warning('Please enter a valid quantity (minimum 1)');
        return;
    }

    if (newQty == currentQty) {
        toastr.warning('New quantity is the same as current quantity');
        return;
    }

    if (!reason) {
        toastr.warning('Please enter a reason for the quantity adjustment');
        $('#qty-adjust-reason').focus();
        return;
    }

    const $btn = $(this);
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: `{{ url('/pharmacy-workbench/prescription/${productRequestId}/adjust-quantity') }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            new_qty: newQty,
            adjustment_reason: reason
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.success(response.message || 'Quantity adjusted successfully');
            $('#qtyAdjustmentModal').modal('hide');

            // Refresh prescription lists
            loadPrescriptionItems(currentStatusFilter);
            refreshAllPrescTables();
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to adjust quantity');
        }
    });
});

// ===========================================
// END QUANTITY ADJUSTMENT MODULE
// ===========================================

// ===========================================
// PRE-BILLING PRICE ADJUSTMENT MODULE
// ===========================================

function openPriceAdjustmentModal(requestId, productName, productCode, currentPrice, qty, coverageMode, tariffPayable, tariffClaims, priceOverride, priceOverrideReason, priceOverrideBy, priceOverrideAt) {
    $('#price-adjust-request-id').val(requestId);
    $('#price-adjust-original-price').val(currentPrice || 0);
    $('#price-adjust-coverage-mode').val(coverageMode || 'none');

    // Product info
    $('#price-adjust-product-name').text(productName || 'Unknown');
    $('#price-adjust-product-code').text(productCode ? '[' + productCode + ']' : '');
    const unitPrice = parseFloat(currentPrice) || 0;
    const itemQty = parseInt(qty) || 1;
    $('#price-adjust-unit-price').text('₦' + formatMoneyPharmacy(unitPrice));
    $('#price-adjust-qty').text(itemQty);
    $('#price-adjust-line-total').text('₦' + formatMoneyPharmacy(unitPrice * itemQty));

    // HMO tariff info
    const tariffPay = parseFloat(tariffPayable) || 0;
    const tariffCl = parseFloat(tariffClaims) || 0;
    const hasTariff = tariffPay> 0 || tariffCl> 0;
    $('#price-adjust-tariff-info').toggle(hasTariff);
    $('#price-adjust-tariff-payable').text('₦' + formatMoneyPharmacy(tariffPay));
    $('#price-adjust-tariff-claims').text('₦' + formatMoneyPharmacy(tariffCl));
    $('#price-adjust-tariff-payable-unit').val(tariffPay);
    $('#price-adjust-tariff-claims-unit').val(tariffCl);

    // Existing override notice
    const hasOverride = priceOverride !== null && priceOverride !== undefined && priceOverride !== '';
    $('#price-adjust-existing-override').toggle(hasOverride);
    if (hasOverride) {
        $('#price-adjust-existing-value').text('₦' + formatMoneyPharmacy(parseFloat(priceOverride)));
        let meta = '';
        if (priceOverrideBy) meta += ' by ' + priceOverrideBy;
        if (priceOverrideAt) meta += ' on ' + priceOverrideAt;
        if (priceOverrideReason) meta += ' — ' + priceOverrideReason;
        $('#price-adjust-existing-meta').text(meta);
    }

    // Set initial value: use existing override if present, otherwise sale price
    const initialPrice = hasOverride ? parseFloat(priceOverride) : unitPrice;
    $('#price-adjust-new').val(initialPrice.toFixed(2));
    $('#price-adjust-reason').val('');

    // Trigger initial preview
    updatePriceAdjustmentPreview();

    $('#priceAdjustmentModal').modal('show');
}

function updatePriceAdjustmentPreview() {
    const originalPrice = parseFloat($('#price-adjust-original-price').val()) || 0;
    const qty = parseInt($('#price-adjust-qty').text()) || 1;
    const newPrice = parseFloat($('#price-adjust-new').val()) || 0;
    const coverageMode = $('#price-adjust-coverage-mode').val();
    const tariffPayUnit = parseFloat($('#price-adjust-tariff-payable-unit').val()) || 0;
    const tariffClaimsUnit = parseFloat($('#price-adjust-tariff-claims-unit').val()) || 0;
    const hasTariff = tariffPayUnit> 0 || tariffClaimsUnit> 0;

    const oldPayable = hasTariff ? tariffPayUnit * qty : originalPrice * qty;
    const oldClaims = hasTariff ? tariffClaimsUnit * qty : 0;
    const oldTotal = oldPayable + oldClaims;

    const newPayable = newPrice * qty;
    let newClaims = 0;
    if (hasTariff) {
        const tariffTotal = (tariffPayUnit + tariffClaimsUnit) * qty;
        newClaims = Math.max(0, tariffTotal - newPayable);
    }
    const newTotal = newPayable + newClaims;

    // Update preview
    $('#price-impact-payable-old').text('₦' + formatMoneyPharmacy(oldPayable));
    $('#price-impact-payable-new').text('₦' + formatMoneyPharmacy(newPayable));
    updatePriceDiffBadge('#price-impact-payable-diff', newPayable - oldPayable);

    // Show claims row only for HMO patients
    $('#price-impact-claims-row').toggle(hasTariff);
    if (hasTariff) {
        $('#price-impact-claims-old').text('₦' + formatMoneyPharmacy(oldClaims));
        $('#price-impact-claims-new').text('₦' + formatMoneyPharmacy(newClaims));
        updatePriceDiffBadge('#price-impact-claims-diff', newClaims - oldClaims);
    }

    $('#price-impact-total-new').text('₦' + formatMoneyPharmacy(newTotal));

    // Warnings
    let warning = '';
    if (newPrice> originalPrice * 2) {
        warning = 'New price is more than double the original price. Please verify.';
    } else if (newPrice <= 0) {
        warning = 'Setting price to zero will make this item free for the patient.';
    } else if (hasTariff && newPayable> (tariffPayUnit + tariffClaimsUnit) * qty) {
        warning = 'New patient payable exceeds the total tariff amount. HMO claims will be ₦0.';
    }
    $('#price-adjust-warning').toggle(!!warning);
    $('#price-adjust-warning-text').text(warning);
}

function updatePriceDiffBadge(selector, diff) {
    const formatted = (diff>= 0 ? '+' : '-') + '₦' + formatMoneyPharmacy(Math.abs(diff));
    const badgeClass = diff> 0 ? 'bg-danger' : (diff < 0 ? 'bg-success' : 'bg-secondary');
    $(selector).removeClass('bg-danger bg-success bg-secondary').addClass(badgeClass).text(formatted);
}

// Listen for price input change
$('#price-adjust-new').on('change input', function() {
    updatePriceAdjustmentPreview();
});

// Confirm price adjustment
$('#confirm-price-adjustment').on('click', function() {
    const requestId = $('#price-adjust-request-id').val();
    const newPrice = parseFloat($('#price-adjust-new').val());
    const reason = $('#price-adjust-reason').val().trim();

    if (isNaN(newPrice) || newPrice < 0) {
        toastr.warning('Please enter a valid price (minimum ₦0)');
        return;
    }

    if (!reason) {
        toastr.warning('Please enter a reason for the price adjustment');
        $('#price-adjust-reason').focus();
        return;
    }

    const $btn = $(this);
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: `{{ url('/pharmacy-workbench/prescription/${requestId}/adjust-price') }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            new_price: newPrice,
            adjustment_reason: reason
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.success(response.message || 'Price adjusted successfully');
            $('#priceAdjustmentModal').modal('hide');

            // Refresh prescription lists
            loadPrescriptionItems(currentStatusFilter);
            refreshAllPrescTables();
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to adjust price');
        }
    });
});

// ===========================================
// END PRE-BILLING PRICE ADJUSTMENT MODULE
// ===========================================

// ===========================================
// POST-TRANSACTION ACTIONS MODULE
// Returns, Damages & Stock Reports
// ===========================================

// --------------- Helper: hide ALL full-screen views ---------------
function hideAllPanelViews() {
    hideAllViews();
}

function showMobileMainWorkspace() {
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }
}

function backToEmptyState() {
    hideAllPanelViews();
    $('#empty-state').show();
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

// ==========================================
// RETURNS PANEL
// ==========================================
var returnsTableInstance = null;
window.pharmacyReturnsInitialized = false;

function showPharmacyReturns() {
    hideAllPanelViews();
    $('#pharmacy-returns-view').show().addClass('active');
    showMobileMainWorkspace();

    if (!window.pharmacyReturnsInitialized) {
        initReturnsDataTable();
        loadReturnsStats();
        window.pharmacyReturnsInitialized = true;
    } else {
        if (returnsTableInstance) returnsTableInstance.ajax.reload(null, false);
        loadReturnsStats();
    }
}

function hidePharmacyReturns() {
    $('#pharmacy-returns-view').removeClass('active').hide();
    backToEmptyState();
}

function showReturnCreateForm() {
    $('#pharmacy-returns-view').removeClass('active').hide();
    $('#pharmacy-return-create-view').show().addClass('active');
    loadDispensedItemsTable();
    $('#returnDetailsSection').hide();
    $('#return-je-preview').hide();
    $('#createReturnForm')[0].reset();
}

function hideReturnCreateForm() {
    $('#pharmacy-return-create-view').removeClass('active').hide();
    showPharmacyReturns();
}

function initReturnsDataTable() {
    returnsTableInstance = $('#returnsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("pharmacy.returns.datatables") }}',
            data: function(d) {
                d.status = $('#returns-status-filter').val();
                d.from_date = $('#returns-date-from').val();
                d.to_date = $('#returns-date-to').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, width: '30px' },
            { data: 'item_info', name: 'product_name', orderable: false },
            { data: 'details_info', orderable: false },
            { data: 'status_info', orderable: false },
            { data: 'actions', orderable: false, width: '90px' }
        ],
        order: [],
        pageLength: 15,
        stateSave: true,
        stateLoadCallback: function(settings, callback) {
            return JSON.parse(localStorage.getItem('DT_returnsTable_v2') || 'null');
        },
        stateSaveCallback: function(settings, data) {
            localStorage.setItem('DT_returnsTable_v2', JSON.stringify(data));
        },
        responsive: true,
        language: {
            emptyTable: '<div class="text-center p-3"><i class="mdi mdi-undo-variant mdi-36px text-muted"></i><br><span class="text-muted">No returns found</span></div>',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

function loadReturnsStats() {
    // Show skeleton state
    $('#returns-stat-pending, #returns-stat-approved, #returns-stat-rejected').addClass('stat-skeleton');
    $('#returns-stat-refunded').addClass('stat-skeleton');

    $.get('{{ route("pharmacy.returns.index") }}', { stats_only: 1 })
        .done(function(res) {
            if (res.stats) {
                $('#returns-stat-pending').text(res.stats.pending || 0).removeClass('stat-skeleton');
                $('#returns-stat-approved').text(res.stats.approved || 0).removeClass('stat-skeleton');
                $('#returns-stat-rejected').text(res.stats.rejected || 0).removeClass('stat-skeleton');
                $('#returns-stat-refunded').text('₦' + formatMoneyPharmacy(res.stats.total_value || 0)).removeClass('stat-skeleton');
            }
        });
}

// Returns: Search dispensed items (DataTable)
var dtDispensedItems = null;
function loadDispensedItemsTable() {
    if (dtDispensedItems) {
        dtDispensedItems.ajax.reload();
        return;
    }
    dtDispensedItems = $('#dt-dispensed-items').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("pharmacy.returns.search-dispensed") }}',
            data: function (d) {
                d.start_date = $('#dispensed-search-start').val();
                d.end_date = $('#dispensed-search-end').val();
            }
        },
        columns: [
            { data: 'date', name: 'dispense_date' },
            { data: 'patient', name: 'patient_id', orderable: false, searchable: false },
            { data: 'product', name: 'product.product_name', orderable: false, searchable: false },
            { data: 'qty', name: 'qty', orderable: false, searchable: false },
            { data: 'amount', name: 'amount', orderable: false, searchable: false },
            { data: 'store', name: 'dispensedFromStore.store_name', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 5,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    });
}

$('#btn-search-dispensed').on('click', function(e) {
    e.preventDefault();
    loadDispensedItemsTable();
});

// Returns: Select item from search
$(document).on('click', '.return-item-select', function(e) {
    e.preventDefault();
    var $el = $(this);
    var infoHtml = '<div class="row">' +
        '<div class="col-md-6"><strong>Product:</strong> ' + $el.data('product') + '</div>' +
        '<div class="col-md-6"><strong>Patient:</strong> ' + $el.data('patient') + '</div>' +
        '</div>' +
        '<div class="row mt-1">' +
        '<div class="col-md-4"><strong>Qty Dispensed:</strong> ' + $el.data('qty') + '</div>' +
        '<div class="col-md-4"><strong>Total Amount:</strong> ₦' + formatMoneyPharmacy($el.data('amount')) + '</div>' +
        '<div class="col-md-4"><strong>Store:</strong> ' + $el.data('store') + '</div>' +
        '</div>';
    if ($el.data('claims') > 0) {
        infoHtml += '<div class="mt-1"><span class="badge badge-info">HMO Split</span> Patient: ₦' +
            formatMoneyPharmacy($el.data('payable')) + ' | HMO: ₦' + formatMoneyPharmacy($el.data('claims')) + '</div>';
    }
    $('#selectedReturnItemInfo').html(infoHtml);
    $('#return_product_request_id').val($el.data('id'));
    $('#return_qty_returned').attr('max', $el.data('qty')).val($el.data('qty'));
    $('#return_max_qty').text($el.data('qty'));
    // Store amount data for JE preview
    $('#return_qty_returned').data('total-amount', $el.data('amount'));
    $('#return_qty_returned').data('original-qty', $el.data('qty'));
    $('#return_qty_returned').data('payable', $el.data('payable'));
    $('#return_qty_returned').data('claims', $el.data('claims'));
    $('#returnDetailsSection').slideDown(200);
    
    $('#pharmacy-return-create-view .queue-view-content').animate({
        scrollTop: $('#returnDetailsSection').position().top
    }, 500);
    
    updateReturnJEPreview();
});

// Returns: Condition change hint + JE preview update
$('#return_condition').on('change', function() {
    var val = $(this).val();
    var hints = {
        'good': 'Item will be restocked. DR: Inventory (1300) / CR: Customer Deposits (2200)',
        'expired': 'Item cannot be restocked. DR: Loss on Returns (5060) / CR: Customer Deposits (2200)',
        'damaged': 'Item cannot be restocked. DR: Loss on Returns (5060) / CR: Customer Deposits (2200)',
        'wrong_item': 'Item will be restocked. DR: Inventory (1300) / CR: Customer Deposits (2200)'
    };
    $('#return_condition_hint').text(hints[val] || '');
    updateReturnJEPreview();
});

$('#return_qty_returned').on('input', function() { updateReturnJEPreview(); });

function updateReturnJEPreview() {
    var condition = $('#return_condition').val();
    var qtyReturned = parseFloat($('#return_qty_returned').val()) || 0;
    var originalQty = parseFloat($('#return_qty_returned').data('original-qty')) || 1;
    var totalAmount = parseFloat($('#return_qty_returned').data('total-amount')) || 0;
    var payable = parseFloat($('#return_qty_returned').data('payable')) || 0;
    var claims = parseFloat($('#return_qty_returned').data('claims')) || 0;

    if (!condition || !qtyReturned) { $('#return-je-preview').hide(); return; }

    var refundAmt = (totalAmount / originalQty) * qtyReturned;
    var isRestock = (condition === 'good' || condition === 'wrong_item');
    var debitAcct = isRestock ? 'Inventory - Pharmacy (1300)' : 'Loss on Returns (5060)';
    var hasHmo = claims> 0;

    var rows = '<tr><td>' + debitAcct + '</td><td class="text-right">₦' + formatMoneyPharmacy(refundAmt) + '</td><td class="text-right">—</td></tr>';

    if (hasHmo) {
        var patientRefund = (payable / totalAmount) * refundAmt;
        var hmoRefund = (claims / totalAmount) * refundAmt;
        rows += '<tr><td>Customer Deposits (2200) — Patient Wallet</td><td class="text-right">—</td><td class="text-right">₦' + formatMoneyPharmacy(patientRefund) + '</td></tr>';
        rows += '<tr><td>AR - HMO (1110)</td><td class="text-right">—</td><td class="text-right">₦' + formatMoneyPharmacy(hmoRefund) + '</td></tr>';
    } else {
        rows += '<tr><td>Customer Deposits (2200) — Patient Wallet</td><td class="text-right">—</td><td class="text-right">₦' + formatMoneyPharmacy(refundAmt) + '</td></tr>';
    }

    $('#return-je-preview-body').html(rows);
    $('#return-je-preview').slideDown(200);
}

// Returns: Submit
$('#createReturnForm').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#submitReturnBtn');
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: '{{ route("pharmacy.returns.store") }}',
        method: 'POST',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(res) {
            toastr.success(res.message || 'Return created');
            hideReturnCreateForm();
            if (returnsTableInstance) returnsTableInstance.ajax.reload(null, false);
            loadReturnsStats();
        },
        error: function(xhr) {
            var msg = xhr.responseJSON?.message || 'Failed to create return';
            if (xhr.responseJSON?.errors) {
                var errs = Object.values(xhr.responseJSON.errors).flat();
                msg = errs.join('. ');
            }
            toastr.error(msg);
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Submit Return for Approval');
        }
    });
});

// Returns: Reset form
$('#resetReturnForm').on('click', function() {
    $('#createReturnForm')[0].reset();
    $('#returnDetailsSection').hide();
    $('#return-je-preview').hide();
    $('#dispensedItemResults').html('');
    $('#return_condition_hint').text('');
});

// Returns: View detail
$(document).on('click', '.btn-view-return', function() {
    var id = $(this).data('id');
    var $body = $('#viewReturnModalBody');
    $body.html('<div class="text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>');
    $('#viewReturnModal').modal('show');

    $.get('/pharmacy/returns/' + id)
        .done(function(res) {
            if (!res.success) { $body.html('<p class="text-danger">Failed to load</p>'); return; }
            var r = res['return'];
            var html = '<div class="row">';
            html += '<div class="col-md-6"><table class="table table-sm table-borderless">';
            html += '<tr><th width="40%">Return #</th><td>' + r.id + '</td></tr>';
            html += '<tr><th>Patient</th><td>' + r.patient_name + ' <small class="text-muted">(' + r.file_no + ')</small></td></tr>';
            html += '<tr><th>Product</th><td>' + r.product_name + '</td></tr>';
            html += '<tr><th>Store</th><td>' + r.store_name + '</td></tr>';
            html += '<tr><th>Batch</th><td>' + r.batch_number + '</td></tr>';
            html += '</table></div>';
            html += '<div class="col-md-6"><table class="table table-sm table-borderless">';
            html += '<tr><th width="40%">Qty Returned</th><td>' + r.qty_returned + ' / ' + r.original_qty + '</td></tr>';
            html += '<tr><th>Refund Amount</th><td class="text-success fw-bold">₦' + formatMoneyPharmacy(r.refund_amount) + '</td></tr>';
            if (r.refund_to_hmo> 0) {
                html += '<tr><th>Patient Portion</th><td>₦' + formatMoneyPharmacy(r.refund_to_patient) + '</td></tr>';
                html += '<tr><th>HMO Portion</th><td>₦' + formatMoneyPharmacy(r.refund_to_hmo) + '</td></tr>';
            }
            html += '<tr><th>Condition</th><td>' + r.return_condition + (r.restock ? ' <span class="badge badge-success badge-sm">Restockable</span>' : ' <span class="badge badge-secondary badge-sm">Non-restockable</span>') + '</td></tr>';
            html += '<tr><th>Status</th><td><span class="badge badge-' + ({pending:'warning',approved:'success',rejected:'danger',completed:'info'}[r.status]||'secondary') + '">' + r.status + '</span></td></tr>';
            html += '</table></div></div>';

            html += '<div class="border-top pt-2 mt-2"><strong>Reason:</strong> ' + r.return_reason + '</div>';
            html += '<div class="row mt-2"><div class="col-md-6"><small class="text-muted">Created by ' + r.created_by + ' on ' + r.created_at + '</small></div>';
            if (r.approved_by) {
                html += '<div class="col-md-6"><small class="text-muted">' + (r.status === 'rejected' ? 'Rejected' : 'Approved') + ' by ' + r.approved_by + ' on ' + r.approved_at + '</small></div>';
            }
            html += '</div>';
            if (r.approval_notes) {
                html += '<div class="mt-1"><small><strong>' + (r.status === 'rejected' ? 'Rejection' : 'Approval') + ' Notes:</strong> ' + r.approval_notes + '</small></div>';
            }

            // JE section
            if (r.journal_entry) {
                html += '<div class="border-top pt-2 mt-3">';
                html += '<div class="d-flex justify-content-between align-items-center mb-2">';
                html += '<h6 class="mb-0"><i class="mdi mdi-book-open-page-variant text-primary"></i> Journal Entry</h6>';
                html += '<div>';
                if (r.journal_entry.status) {
                    var jeBadge = {draft:'secondary',submitted:'info',approved:'primary',posted:'success',rejected:'danger'}[r.journal_entry.status] || 'secondary';
                    html += '<span class="badge badge-' + jeBadge + ' mr-2">' + r.journal_entry.status.toUpperCase() + '</span>';
                }
                html += '<a href="/accounting/journal-entries/' + r.journal_entry.id + '" target="_blank" class="btn btn-sm btn-outline-primary" title="Open in Accounting Module">';
                html += '<i class="mdi mdi-open-in-new"></i> ' + r.journal_entry.reference + '</a>';
                html += '</div></div>';
                html += '<table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">';
                html += '<thead class="thead-light"><tr><th>Account</th><th>Code</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody>';
                var totalDebit = 0, totalCredit = 0;
                r.journal_entry.lines.forEach(function(line) {
                    html += '<tr><td>' + line.account_name + '</td><td><code>' + line.account_code + '</code></td>';
                    html += '<td class="text-right">' + (line.debit> 0 ? '₦' + formatMoneyPharmacy(line.debit) : '—') + '</td>';
                    html += '<td class="text-right">' + (line.credit> 0 ? '₦' + formatMoneyPharmacy(line.credit) : '—') + '</td></tr>';
                    totalDebit += parseFloat(line.debit) || 0;
                    totalCredit += parseFloat(line.credit) || 0;
                });
                html += '<tr class="font-weight-bold bg-light"><td colspan="2" class="text-right">Totals</td>';
                html += '<td class="text-right">₦' + formatMoneyPharmacy(totalDebit) + '</td>';
                html += '<td class="text-right">₦' + formatMoneyPharmacy(totalCredit) + '</td></tr>';
                html += '</tbody></table></div>';
            }

            // Stock / Batch link section
            if (r.status === 'approved' && r.restock && r.product_id) {
                html += '<div class="border-top pt-2 mt-2">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="mdi mdi-package-variant text-success mr-2"></i>';
                html += '<span class="mr-2"><strong>Stock restocked</strong> — ' + r.qty_returned + ' unit(s) returned to <em>' + r.batch_number + '</em></span>';
                html += '<a href="/inventory/store-workbench/product/' + r.product_id + '/batches" target="_blank" class="btn btn-sm btn-outline-success ml-auto" title="View batches in Store Workbench">';
                html += '<i class="mdi mdi-store"></i> View Batches</a>';
                html += '</div></div>';
            } else if (r.status === 'approved' && !r.restock) {
                html += '<div class="border-top pt-2 mt-2">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="mdi mdi-package-variant-closed text-secondary mr-2"></i>';
                html += '<span><strong>Non-restockable</strong> — recorded as loss (stock not returned)</span>';
                html += '</div></div>';
            }

            $body.html(html);
        })
        .fail(function() { $body.html('<p class="text-danger text-center">Failed to load return details</p>'); });
});

// Returns: Approve / Reject (event delegation for DataTable-rendered buttons)
$(document).on('click', '.btn-approve-return, .approve-return', function() {
    var id = $(this).data('id');
    $('#approve_return_id').val(id);
    // Load summary for confirmation
    $.get('/pharmacy/returns/' + id).done(function(res) {
        if (res.success) {
            var r = res['return'];
            var summary = '<strong>' + r.product_name + '</strong> — ' + r.qty_returned + ' unit(s)<br>' +
                '<span class="text-success">Refund: ₦' + formatMoneyPharmacy(r.refund_amount) + '</span>';
            if (r.restock) {
                $('#approve-return-restock-note').html('<strong>Restock</strong> inventory automatically (good condition)');
            } else {
                $('#approve-return-restock-note').html('Record as loss (non-restockable)');
            }
            $('#approve-return-summary').html(summary);
        }
    });
    $('#approveReturnModal').modal('show');
});

$(document).on('click', '.btn-reject-return, .reject-return', function() {
    var id = $(this).data('id');
    $('#reject_return_id').val(id);
    $('#reject_return_reason').val('');
    $('#rejectReturnModal').modal('show');
});

$('#approveReturnForm').on('submit', function(e) {
    e.preventDefault();
    var id = $('#approve_return_id').val();
    var notes = $('#approve_return_notes').val();
    var $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Approving...');
    $.ajax({
        url: '{{ url('/pharmacy/returns') }}/' + id + '/approve',
        method: 'POST',
        data: { approval_notes: notes },
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(res) {
            toastr.success(res.message || 'Return approved');
            $('#approveReturnModal').modal('hide');
            if (returnsTableInstance) returnsTableInstance.ajax.reload(null, false);
            loadReturnsStats();
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Approval failed'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Confirm Approval'); }
    });
});

$('#rejectReturnForm').on('submit', function(e) {
    e.preventDefault();
    var id = $('#reject_return_id').val();
    var reason = $('#reject_return_reason').val();
    if (!reason || reason.length < 10) { toastr.warning('Enter a rejection reason (min 10 characters)'); return; }
    var $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Rejecting...');
    $.ajax({
        url: '{{ url('/pharmacy/returns') }}/' + id + '/reject',
        method: 'POST',
        data: { rejection_reason: reason },
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(res) {
            toastr.success(res.message || 'Return rejected');
            $('#rejectReturnModal').modal('hide');
            if (returnsTableInstance) returnsTableInstance.ajax.reload(null, false);
            loadReturnsStats();
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Rejection failed'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-close"></i> Reject Return'); }
    });
});

// Returns: Filter buttons
$('#apply-returns-filters').on('click', function() {
    if (returnsTableInstance) returnsTableInstance.ajax.reload();
});

// Returns: Button handlers
$('#btn-pharmacy-returns').on('click', function() { showPharmacyReturns(); });
$('#btn-close-returns').on('click', function() { hidePharmacyReturns(); });
$('#btn-create-return').on('click', function() { showReturnCreateForm(); });
$('#btn-cancel-create-return').on('click', function() { hideReturnCreateForm(); });

// ==========================================
// DAMAGES PANEL
// ==========================================
var damagesTableInstance = null;
window.pharmacyDamagesInitialized = false;

function showPharmacyDamages() {
    hideAllPanelViews();
    $('#pharmacy-damages-view').show().addClass('active');
    showMobileMainWorkspace();

    if (!window.pharmacyDamagesInitialized) {
        initDamagesDataTable();
        loadDamagesStats();
        window.pharmacyDamagesInitialized = true;
    } else {
        if (damagesTableInstance) damagesTableInstance.ajax.reload(null, false);
        loadDamagesStats();
    }
}

function hidePharmacyDamages() {
    $('#pharmacy-damages-view').removeClass('active').hide();
    backToEmptyState();
}

function showDamageCreateForm() {
    $('#pharmacy-damages-view').removeClass('active').hide();
    $('#pharmacy-damage-create-view').show().addClass('active');
    $('#createDamageForm')[0].reset();
    $('#damage_total_display').val('₦0.00');
    $('#damage_available_stock').val('-');
    $('#damage-je-preview').hide();
    $('#damage-type-hint').text('');
    initDamageSelect2();
    loadDamageStores();
}

function hideDamageCreateForm() {
    $('#pharmacy-damage-create-view').removeClass('active').hide();
    showPharmacyDamages();
}

function initDamagesDataTable() {
    damagesTableInstance = $('#damagesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("pharmacy.damages.datatables") }}',
            data: function(d) {
                d.status = $('#damages-status-filter').val();
                d.damage_type = $('#damages-type-filter').val();
                d.date_from = $('#damages-date-from').val();
                d.date_to = $('#damages-date-to').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, width: '30px' },
            { data: 'item_info', name: 'product_name', orderable: false },
            { data: 'details_info', orderable: false },
            { data: 'total_value', render: function(d) { return '₦' + formatMoneyPharmacy(d); }},
            { data: 'status_info', orderable: false },
            { data: 'actions', orderable: false, width: '90px' }
        ],
        order: [],
        pageLength: 15,
        stateSave: true,
        stateLoadCallback: function(settings, callback) {
            return JSON.parse(localStorage.getItem('DT_damagesTable_v2') || 'null');
        },
        stateSaveCallback: function(settings, data) {
            localStorage.setItem('DT_damagesTable_v2', JSON.stringify(data));
        },
        responsive: true,
        language: {
            emptyTable: '<div class="text-center p-3"><i class="mdi mdi-alert-octagon mdi-36px text-muted"></i><br><span class="text-muted">No damage reports found</span></div>',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

function loadDamagesStats() {
    // Show skeleton state
    $('#damages-stat-pending, #damages-stat-approved, #damages-stat-deducted').addClass('stat-skeleton');
    $('#damages-stat-value').addClass('stat-skeleton');

    $.get('{{ route("pharmacy.damages.index") }}', { stats_only: 1 })
        .done(function(res) {
            if (res.stats) {
                $('#damages-stat-pending').text(res.stats.pending || 0).removeClass('stat-skeleton');
                $('#damages-stat-approved').text(res.stats.approved || 0).removeClass('stat-skeleton');
                $('#damages-stat-value').text('₦' + formatMoneyPharmacy(res.stats.total_value || 0)).removeClass('stat-skeleton');
                $('#damages-stat-deducted').text(res.stats.stock_deducted || 0).removeClass('stat-skeleton');
            }
        });
}

// Damages: Initialize Select2 for store, product, and batch dropdowns
function initDamageSelect2() {
    var $panel = $('#pharmacy-damage-create-view');

    // Destroy existing Select2 instances to allow re-init
    if ($('#damage_store_id').hasClass('select2-hidden-accessible')) {
        $('#damage_store_id').select2('destroy');
    }
    if ($('#damage_product_id').hasClass('select2-hidden-accessible')) {
        $('#damage_product_id').select2('destroy');
    }
    if ($('#damage_batch_id').hasClass('select2-hidden-accessible')) {
        $('#damage_batch_id').select2('destroy');
    }

    // Store — simple Select2 (options loaded via loadDamageStores)
    $('#damage_store_id').select2({
        dropdownParent: $panel,
        placeholder: '-- Select store --',
        allowClear: true,
        width: '100%'
    });

    // Product — Select2 with AJAX search (searches by name/code within selected store)
    $('#damage_product_id').select2({
        dropdownParent: $panel,
        placeholder: 'Select or search product...',
        allowClear: true,
        minimumInputLength: 0,
        width: '100%',
        ajax: {
            url: '{{ route("pharmacy.damages.search-products") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term || '',
                    store_id: $('#damage_store_id').val()
                };
            },
            processResults: function(data) {
                var items = data.products || data;
                return {
                    results: items.map(function(p) {
                        return {
                            id: p.id,
                            text: (p.product_name || p.name) + (p.product_code ? ' (' + p.product_code + ')' : '') + ' — Stock: ' + (p.current_quantity || 0),
                            unit_cost: p.unit_cost || 0,
                            current_quantity: p.current_quantity || 0
                        };
                    })
                };
            },
            cache: true
        }
    }).prop('disabled', true);

    // Batch — simple Select2 (options loaded after product selection)
    $('#damage_batch_id').select2({
        dropdownParent: $panel,
        placeholder: '-- Select batch (optional) --',
        allowClear: true,
        width: '100%'
    }).prop('disabled', true);
}

// Damages: Load stores for create form
function loadDamageStores() {
    $.get('/pharmacy-workbench/stores')
        .done(function(res) {
            var stores = res.stores || res;
            var opts = '<option value="">-- Select store --</option>';
            stores.forEach(function(s) {
                opts += '<option value="' + s.id + '">' + (s.store_name || s.name) + '</option>';
            });
            $('#damage_store_id').html(opts).trigger('change.select2');
        });
}

// Damages: Store change → enable/reset product Select2
$('#damage_store_id').on('change', function() {
    var storeId = $(this).val();

    // Reset product and batch
    $('#damage_product_id').val(null).trigger('change.select2');
    $('#damage_batch_id').html('<option value="">-- Select batch (optional) --</option>').trigger('change.select2').prop('disabled', true);
    $('#damage_available_stock').val('-');
    $('#damage_unit_cost').val('');
    $('#damage_total_display').val('₦0.00');
    $('#damage-je-preview').hide();

    if (storeId) {
        $('#damage_product_id').prop('disabled', false);
    } else {
        $('#damage_product_id').prop('disabled', true);
    }
});

// Damages: Product selection → auto-fill cost, load batches
$('#damage_product_id').on('select2:select', function(e) {
    var data = e.params.data;
    var productId = data.id;
    var storeId = $('#damage_store_id').val();
    var productCost = parseFloat(data.unit_cost) || 0;
    var productQty = parseFloat(data.current_quantity) || 0;

    // Set product-level cost as default (batch will override if selected)
    if (productCost> 0) {
        $('#damage_unit_cost').val(productCost.toFixed(2));
        recalcDamageTotal();
    }
    $('#damage_available_stock').val(productQty || '-');

    // Load batches for this product
    $('#damage_batch_id').html('<option value="">-- Loading... --</option>').prop('disabled', true);

    $.get('{{ route("pharmacy.damages.get-batches") }}', { product_id: productId, store_id: storeId })
        .done(function(res) {
            var batches = res.batches || res;
            var opts = '<option value="">-- No batch (use store stock) --</option>';
            batches.forEach(function(b) {
                opts += '<option value="' + b.id + '" data-cost="' + (b.unit_cost||0) + '" data-qty="' + (b.quantity_available||b.quantity||0) + '">' +
                    'Batch: ' + (b.batch_number||'N/A') + ' | Exp: ' + (b.expiry_date||'N/A') + ' | Qty: ' + (b.quantity_available||b.quantity||0) + ' | ₦' + formatMoneyPharmacy(b.unit_cost||0) +
                    '</option>';
            });
            $('#damage_batch_id').html(opts).prop('disabled', false).trigger('change.select2');
        })
        .fail(function() { toastr.error('Could not load batches'); });
});

// Damages: Product cleared → reset downstream
$('#damage_product_id').on('select2:clear', function() {
    $('#damage_batch_id').html('<option value="">-- Select product first --</option>').prop('disabled', true).trigger('change.select2');
    $('#damage_available_stock').val('-');
    $('#damage_unit_cost').val('');
    $('#damage_total_display').val('₦0.00');
    $('#damage-je-preview').hide();
});

// Damages: Batch selection → auto-fill cost & available stock
$('#damage_batch_id').on('change', function() {
    var $opt = $(this).find(':selected');
    var cost = parseFloat($opt.data('cost')) || 0;
    var qty = parseFloat($opt.data('qty')) || 0;
    if (cost> 0) $('#damage_unit_cost').val(cost.toFixed(2));
    if (qty> 0) $('#damage_available_stock').val(qty);
    recalcDamageTotal();
    updateDamageJEPreview();
});

// Damages: Damage type hint + JE preview
$('#damage_type').on('change', function() {
    var val = $(this).val();
    var hints = {
        'expired': 'DR: Expired Stock Write-off (5040) / CR: Inventory (1300)',
        'broken': 'DR: Damaged Goods Write-off (5030) / CR: Inventory (1300)',
        'contaminated': 'DR: Damaged Goods Write-off (5030) / CR: Inventory (1300)',
        'spoiled': 'DR: Damaged Goods Write-off (5030) / CR: Inventory (1300)',
        'theft': 'DR: Theft/Shrinkage (5050) / CR: Inventory (1300)',
        'other': 'DR: Damaged Goods Write-off (5030) / CR: Inventory (1300)'
    };
    $('#damage-type-hint').text(hints[val] || '');
    updateDamageJEPreview();
});

// Damages: Recalculate total + JE preview
function recalcDamageTotal() {
    var qty = parseFloat($('#damage_qty').val()) || 0;
    var cost = parseFloat($('#damage_unit_cost').val()) || 0;
    var total = qty * cost;
    $('#damage_total_display').val('₦' + formatMoneyPharmacy(total));
    updateDamageJEPreview();
}
$('#damage_qty, #damage_unit_cost').on('input', recalcDamageTotal);

function updateDamageJEPreview() {
    var damageType = $('#damage_type').val();
    var qty = parseFloat($('#damage_qty').val()) || 0;
    var cost = parseFloat($('#damage_unit_cost').val()) || 0;
    var total = qty * cost;

    if (!damageType || total <= 0) { $('#damage-je-preview').hide(); return; }

    var debitAccounts = {
        'expired': 'Expired Stock Write-off (5040)',
        'broken': 'Damaged Goods Write-off (5030)',
        'contaminated': 'Damaged Goods Write-off (5030)',
        'spoiled': 'Damaged Goods Write-off (5030)',
        'theft': 'Theft/Shrinkage (5050)',
        'other': 'Damaged Goods Write-off (5030)'
    };

    var rows = '<tr><td>' + debitAccounts[damageType] + '</td><td class="text-right">₦' + formatMoneyPharmacy(total) + '</td><td class="text-right">—</td></tr>';
    rows += '<tr><td>Inventory - Pharmacy (1300)</td><td class="text-right">—</td><td class="text-right">₦' + formatMoneyPharmacy(total) + '</td></tr>';

    $('#damage-je-preview-body').html(rows);
    $('#damage-je-preview').slideDown(200);
}

// Damages: Submit
$('#createDamageForm').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#submitDamageBtn');
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: '{{ route("pharmacy.damages.store") }}',
        method: 'POST',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(res) {
            toastr.success(res.message || 'Damage report created');
            hideDamageCreateForm();
            if (damagesTableInstance) damagesTableInstance.ajax.reload(null, false);
            loadDamagesStats();
        },
        error: function(xhr) {
            var msg = xhr.responseJSON?.message || 'Failed to create damage report';
            if (xhr.responseJSON?.errors) {
                var errs = Object.values(xhr.responseJSON.errors).flat();
                msg = errs.join('. ');
            }
            toastr.error(msg);
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="mdi mdi-alert-octagon"></i> Submit Damage Report');
        }
    });
});

// Damages: Reset
$('#resetDamageForm').on('click', function() {
    $('#createDamageForm')[0].reset();
    // Reset Select2 dropdowns
    $('#damage_store_id').val(null).trigger('change');
    $('#damage_product_id').val(null).trigger('change.select2').prop('disabled', true);
    $('#damage_batch_id').html('<option value="">-- Select product first --</option>').trigger('change.select2').prop('disabled', true);
    $('#damage_total_display').val('₦0.00');
    $('#damage_available_stock').val('-');
    $('#damage-je-preview').hide();
    $('#damage-type-hint').text('');
});

// Damages: View detail
$(document).on('click', '.btn-view-damage', function() {
    var id = $(this).data('id');
    var $body = $('#viewDamageModalBody');
    $body.html('<div class="text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>');
    $('#viewDamageModal').modal('show');

    $.get('/pharmacy/damages/' + id)
        .done(function(res) {
            if (!res.success) { $body.html('<p class="text-danger">Failed to load</p>'); return; }
            var d = res.damage;
            var html = '<div class="row">';
            html += '<div class="col-md-6"><table class="table table-sm table-borderless">';
            html += '<tr><th width="40%">Damage #</th><td>' + d.id + '</td></tr>';
            html += '<tr><th>Product</th><td>' + d.product_name + '</td></tr>';
            html += '<tr><th>Store</th><td>' + d.store_name + '</td></tr>';
            html += '<tr><th>Batch</th><td>' + (d.batch_id ? d.batch_number : '<span class="text-muted">Store-level (no batch selected)</span>') + '</td></tr>';
            html += '<tr><th>Type</th><td><span class="badge badge-' + ({'expired':'warning','theft':'dark','broken':'danger','contaminated':'danger','spoiled':'info'}[d.damage_type]||'secondary') + '">' + d.damage_type + '</span></td></tr>';
            html += '</table></div>';
            html += '<div class="col-md-6"><table class="table table-sm table-borderless">';
            html += '<tr><th width="40%">Qty Damaged</th><td>' + d.qty_damaged + '</td></tr>';
            html += '<tr><th>Unit Cost</th><td>₦' + formatMoneyPharmacy(d.unit_cost) + '</td></tr>';
            html += '<tr><th>Total Value</th><td class="text-danger fw-bold">₦' + formatMoneyPharmacy(d.total_value) + '</td></tr>';
            html += '<tr><th>Status</th><td><span class="badge badge-' + ({pending:'warning',approved:'success',rejected:'danger'}[d.status]||'secondary') + '">' + d.status + '</span></td></tr>';
            html += '<tr><th>Stock Deducted</th><td>' + (d.stock_deducted ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>') + '</td></tr>';
            html += '</table></div></div>';

            html += '<div class="border-top pt-2 mt-2"><strong>Reason:</strong> ' + d.damage_reason + '</div>';
            html += '<div class="mt-1"><small class="text-muted">Discovered: ' + d.discovered_date + ' | Reported by ' + d.created_by + ' on ' + d.created_at + '</small></div>';
            if (d.approved_by) {
                html += '<div><small class="text-muted">' + (d.status === 'rejected' ? 'Rejected' : 'Approved') + ' by ' + d.approved_by + ' on ' + d.approved_at + '</small></div>';
            }
            if (d.approval_notes) {
                html += '<div class="mt-1"><small><strong>Notes:</strong> ' + d.approval_notes + '</small></div>';
            }

            if (d.journal_entry) {
                html += '<div class="border-top pt-2 mt-3">';
                html += '<div class="d-flex justify-content-between align-items-center mb-2">';
                html += '<h6 class="mb-0"><i class="mdi mdi-book-open-page-variant text-primary"></i> Journal Entry</h6>';
                html += '<div>';
                if (d.journal_entry.status) {
                    var jeBadge = {draft:'secondary',submitted:'info',approved:'primary',posted:'success',rejected:'danger'}[d.journal_entry.status] || 'secondary';
                    html += '<span class="badge badge-' + jeBadge + ' mr-2">' + d.journal_entry.status.toUpperCase() + '</span>';
                }
                html += '<a href="/accounting/journal-entries/' + d.journal_entry.id + '" target="_blank" class="btn btn-sm btn-outline-primary" title="Open in Accounting Module">';
                html += '<i class="mdi mdi-open-in-new"></i> ' + d.journal_entry.reference + '</a>';
                html += '</div></div>';
                html += '<table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">';
                html += '<thead class="thead-light"><tr><th>Account</th><th>Code</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead><tbody>';
                var totalDebit = 0, totalCredit = 0;
                d.journal_entry.lines.forEach(function(line) {
                    html += '<tr><td>' + line.account_name + '</td><td><code>' + line.account_code + '</code></td>';
                    html += '<td class="text-right">' + (line.debit> 0 ? '₦' + formatMoneyPharmacy(line.debit) : '—') + '</td>';
                    html += '<td class="text-right">' + (line.credit> 0 ? '₦' + formatMoneyPharmacy(line.credit) : '—') + '</td></tr>';
                    totalDebit += parseFloat(line.debit) || 0;
                    totalCredit += parseFloat(line.credit) || 0;
                });
                html += '<tr class="font-weight-bold bg-light"><td colspan="2" class="text-right">Totals</td>';
                html += '<td class="text-right">₦' + formatMoneyPharmacy(totalDebit) + '</td>';
                html += '<td class="text-right">₦' + formatMoneyPharmacy(totalCredit) + '</td></tr>';
                html += '</tbody></table></div>';
            }

            // Stock / Batch link section
            if (d.stock_deducted && d.product_id) {
                html += '<div class="border-top pt-2 mt-2">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="mdi mdi-package-variant-closed text-danger mr-2"></i>';
                html += '<span class="mr-2"><strong>Stock deducted</strong> — ' + d.qty_damaged + ' unit(s) removed' + (d.batch_id ? ' from <em>' + d.batch_number + '</em>' : ' (store-level)') + '</span>';
                html += '<a href="/inventory/store-workbench/product/' + d.product_id + '/batches" target="_blank" class="btn btn-sm btn-outline-info ml-auto" title="View batches in Store Workbench">';
                html += '<i class="mdi mdi-store"></i> View Batches</a>';
                html += '</div></div>';
            } else if (d.status === 'approved' && !d.stock_deducted) {
                html += '<div class="border-top pt-2 mt-2">';
                html += '<div class="d-flex align-items-center">';
                html += '<i class="mdi mdi-alert-circle-outline text-warning mr-2"></i>';
                html += '<span><strong>Pending stock deduction</strong> — stock has not yet been deducted</span>';
                html += '</div></div>';
            }

            $body.html(html);
        })
        .fail(function() { $body.html('<p class="text-danger text-center">Failed to load damage details</p>'); });
});

// Damages: Approve / Reject
$(document).on('click', '.btn-approve-damage, .approve-damage', function() {
    $('#approve_damage_id').val($(this).data('id'));
    $('#approve_damage_notes').val('');
    $('#approveDamageModal').modal('show');
});
$(document).on('click', '.btn-reject-damage, .reject-damage', function() {
    $('#reject_damage_id').val($(this).data('id'));
    $('#reject_damage_reason').val('');
    $('#rejectDamageModal').modal('show');
});

$('#approveDamageForm').on('submit', function(e) {
    e.preventDefault();
    var id = $('#approve_damage_id').val();
    var notes = $('#approve_damage_notes').val();
    var $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Approving...');
    $.ajax({
        url: '{{ url('/pharmacy/damages') }}/' + id + '/approve',
        method: 'POST',
        data: { approval_notes: notes },
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(res) {
            toastr.success(res.message || 'Damage approved');
            $('#approveDamageModal').modal('hide');
            if (damagesTableInstance) damagesTableInstance.ajax.reload(null, false);
            loadDamagesStats();
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Approval failed'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Confirm Approval'); }
    });
});

$('#rejectDamageForm').on('submit', function(e) {
    e.preventDefault();
    var id = $('#reject_damage_id').val();
    var reason = $('#reject_damage_reason').val();
    if (!reason || reason.length < 10) { toastr.warning('Enter a rejection reason (min 10 characters)'); return; }
    var $btn = $(this).find('button[type="submit"]');
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Rejecting...');
    $.ajax({
        url: '{{ url('/pharmacy/damages') }}/' + id + '/reject',
        method: 'POST',
        data: { rejection_reason: reason },
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function(res) {
            toastr.success(res.message || 'Damage rejected');
            $('#rejectDamageModal').modal('hide');
            if (damagesTableInstance) damagesTableInstance.ajax.reload(null, false);
            loadDamagesStats();
        },
        error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Rejection failed'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-close"></i> Reject Report'); }
    });
});

// Damages: Filter buttons
$('#apply-damages-filters').on('click', function() {
    if (damagesTableInstance) damagesTableInstance.ajax.reload();
});

// Damages: Button handlers
$('#btn-pharmacy-damages').on('click', function() { showPharmacyDamages(); });
$('#btn-close-damages').on('click', function() { hidePharmacyDamages(); });
$('#btn-create-damage').on('click', function() { showDamageCreateForm(); });
$('#btn-cancel-create-damage').on('click', function() { hideDamageCreateForm(); });

// ==========================================
// STOCK REPORTS PANEL
// ==========================================
var stockOverviewTableInstance = null;
var expiringStockTableInstance = null;
var _stockDropdownsCached = false;
window.pharmacyStockReportsInitialized = false;

function showPharmacyStockReports() {
    hideAllPanelViews();
    $('#pharmacy-stock-reports-view').show().addClass('active');
    showMobileMainWorkspace();

    if (!window.pharmacyStockReportsInitialized) {
        initStockOverviewTable();
        initExpiringStockTable();
        loadStockReportsStats();
        if (!_stockDropdownsCached) { loadStockFilterDropdowns(); _stockDropdownsCached = true; }
        window.pharmacyStockReportsInitialized = true;
    } else {
        if (stockOverviewTableInstance) stockOverviewTableInstance.ajax.reload(null, false);
        loadStockReportsStats();
    }
}

function hidePharmacyStockReports() {
    $('#pharmacy-stock-reports-view').removeClass('active').hide();
    backToEmptyState();
}

function initStockOverviewTable() {
    stockOverviewTableInstance = $('#stockOverviewTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("pharmacy.reports.stock-overview") }}',
            data: function(d) {
                d.store_id = $('#stock-store-filter').val();
                d.category_id = $('#stock-category-filter').val();
                d.stock_level = $('#stock-level-filter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '30px' },
            { data: 'product_name', name: 'products.product_name' },
            { data: 'category_name', name: 'product_categories.category_name', defaultContent: 'N/A' },
            { data: 'store_name', name: 'stores.store_name' },
            { data: 'current_quantity', name: 'store_stocks.current_quantity' },
            { data: 'reorder_level', name: 'store_stocks.reorder_level' },
            { data: 'unit_cost', name: 'prices.pr_buy_price', searchable: false, render: function(d) { return '₦' + formatMoneyPharmacy(d); }},
            { data: 'total_value', name: 'total_value', searchable: false, orderable: false, render: function(d) { return '₦' + formatMoneyPharmacy(d); }},
            { data: 'stock_status', name: 'stock_status', orderable: false, searchable: false }
        ],
        order: [[4, 'asc']],
        pageLength: 20,
        stateSave: true,
        responsive: true,
        language: { emptyTable: 'No stock data', processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...' },
        createdRow: function(row, data) {
            if (data.current_quantity <= 0) {
                $(row).addClass('table-danger');
            } else if (data.current_quantity <= data.reorder_level) {
                $(row).addClass('table-warning');
            }
        }
    });
}

function initExpiringStockTable() {
    expiringStockTableInstance = $('#expiringStockTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("pharmacy.reports.expiring") }}',
            data: function(d) {
                d.days = $('.expiry-range-btn.active').data('days') || 30;
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '30px' },
            { data: 'product_name', name: 'products.product_name' },
            { data: 'store_name', name: 'stores.store_name' },
            { data: 'batch_number', name: 'stock_batches.batch_number' },
            { data: 'expiry_date', name: 'stock_batches.expiry_date' },
            {
                data: 'days_to_expiry',
                name: 'days_to_expiry',
                searchable: false,
                render: function(d) {
                    var cls = d <= 15 ? 'text-danger fw-bold' : d <= 30 ? 'text-warning fw-bold' : '';
                    return '<span class="'+cls+'">' + d + ' days</span>';
                }
            },
            { data: 'quantity_available', name: 'stock_batches.current_qty' },
            { data: 'total_value', name: 'total_value', searchable: false, orderable: false, render: function(d) { return '₦' + formatMoneyPharmacy(d); }},
            { data: 'expiry_status', name: 'expiry_status', orderable: false, searchable: false }
        ],
        order: [[5, 'asc']],
        pageLength: 20,
        stateSave: true,
        responsive: true,
        language: { emptyTable: 'No expiring stock found', processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...' },
        createdRow: function(row, data) {
            if (data.days_to_expiry <= 30) {
                $(row).addClass('table-danger');
            } else if (data.days_to_expiry <= 60) {
                $(row).addClass('table-warning');
            }
        }
    });
}

function loadStockReportsStats() {
    $('#stock-stat-products, #stock-stat-low, #stock-stat-out').addClass('stat-skeleton');
    $('#stock-stat-value').addClass('stat-skeleton');

    $.get('{{ route("pharmacy.reports.index") }}', { stats_only: 1 })
        .done(function(res) {
            if (res.stats) {
                $('#stock-stat-products').text(res.stats.products || 0).removeClass('stat-skeleton');
                $('#stock-stat-value').text('₦' + formatMoneyPharmacy(res.stats.total_value || 0)).removeClass('stat-skeleton');
                $('#stock-stat-low').text(res.stats.low_stock || 0).removeClass('stat-skeleton');
                $('#stock-stat-out').text(res.stats.out_of_stock || 0).removeClass('stat-skeleton');
            }
        });
}

function loadStockFilterDropdowns() {
    $.get('/pharmacy-workbench/stores')
        .done(function(res) {
            var stores = res.stores || res;
            var opts = '<option value="">All Stores</option>';
            stores.forEach(function(s) {
                opts += '<option value="' + s.id + '">' + (s.store_name || s.name) + '</option>';
            });
            $('#stock-store-filter').html(opts);
            $('#category-store-filter').html(opts);
        });
    $.get('{{ route("pharmacy.reports.by-category") }}', { list_only: 1 })
        .done(function(res) {
            var cats = res.categories || res;
            var opts = '<option value="">All Categories</option>';
            cats.forEach(function(c) {
                opts += '<option value="' + c.id + '">' + (c.category_name || c.name) + '</option>';
            });
            $('#stock-category-filter').html(opts);
        });
}

// Stock Reports: Filter handlers
$('#apply-stock-filters').on('click', function() {
    if (stockOverviewTableInstance) stockOverviewTableInstance.ajax.reload();
});

// Stock Reports: Expiry range toggle
$('.expiry-range-btn').on('click', function() {
    $('.expiry-range-btn').removeClass('active');
    $(this).addClass('active');
    if (expiringStockTableInstance) expiringStockTableInstance.ajax.reload();
});

// Stock Reports: By Store tab refresh
$('#refresh-store-summary').on('click', function() {
    var $container = $('#storeSummaryCards');
    $container.html('<div class="col-12 text-center p-4"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div>');
    $.get('{{ route("pharmacy.reports.by-store") }}')
        .done(function(res) {
            var stores = res.stores || res;
            if (!stores.length) {
                $container.html('<div class="col-12 text-center text-muted p-4">No data found</div>');
                return;
            }
            var html = '';
            stores.forEach(function(s) {
                html += '<div class="col-md-4 col-sm-6">' +
                    '<div class="store-summary-card">' +
                    '<h6><i class="mdi mdi-store"></i> ' + (s.store_name || s.name) + '</h6>' +
                    '<div class="store-metric"><span>Products</span><strong>' + (s.total_products || 0) + '</strong></div>' +
                    '<div class="store-metric"><span>Total Qty</span><strong>' + (s.total_quantity || 0) + '</strong></div>' +
                    '<div class="store-metric"><span>Value</span><strong>₦' + formatMoneyPharmacy(s.total_value || 0) + '</strong></div>' +
                    '<div class="store-metric"><span>Low Stock</span><strong class="text-warning">' + (s.low_stock_count || 0) + '</strong></div>' +
                    '<div class="store-metric"><span>Out of Stock</span><strong class="text-danger">' + (s.out_of_stock_count || 0) + '</strong></div>' +
                    '</div></div>';
            });
            $container.html(html);
        })
        .fail(function() { $container.html('<div class="col-12 text-center text-danger p-4">Failed to load</div>'); });
});

// Stock Reports: By Category tab refresh
$('#refresh-category-summary').on('click', function() {
    var $body = $('#categorySummaryBody');
    var storeId = $('#category-store-filter').val();
    $body.html('<tr><td colspan="4" class="text-center p-3"><i class="mdi mdi-loading mdi-spin"></i> Loading...</td></tr>');

    $.get('{{ route("pharmacy.reports.by-category") }}', { store_id: storeId })
        .done(function(res) {
            var cats = res.categories || res;
            if (!cats.length) {
                $body.html('<tr><td colspan="4" class="text-center text-muted p-3">No data found</td></tr>');
                return;
            }
            var html = '';
            var totalQty = 0, totalVal = 0;
            cats.forEach(function(c) {
                var qty = c.total_quantity || 0;
                var val = c.total_value || 0;
                totalQty += parseFloat(qty);
                totalVal += parseFloat(val);
                html += '<tr><td>' + (c.category_name || c.name || 'Uncategorized') + '</td><td>' + (c.total_products || 0) + '</td><td>' + qty + '</td><td>₦' + formatMoneyPharmacy(val) + '</td></tr>';
            });
            // Add totals row
            html += '<tr class="table-active fw-bold"><td>TOTAL</td><td>' + cats.length + ' categories</td><td>' + totalQty + '</td><td>₦' + formatMoneyPharmacy(totalVal) + '</td></tr>';
            $body.html(html);
        })
        .fail(function() { $body.html('<tr><td colspan="4" class="text-center text-danger p-3">Failed to load</td></tr>'); });
});

// Stock Reports: CSV Export
$('#btn-export-stock-csv').on('click', function() {
    window.location.href = '{{ route("pharmacy.reports.export-stock") }}?' + $.param({
        store_id: $('#stock-store-filter').val(),
        category_id: $('#stock-category-filter').val()
    });
});

// Stock Reports: Button handlers
$('#btn-pharmacy-stock-reports').on('click', function() { showPharmacyStockReports(); });
$('#btn-close-stock-reports').on('click', function() { hidePharmacyStockReports(); });

// ===========================================
// END POST-TRANSACTION ACTIONS MODULE
// ===========================================
</script>

{{-- Clinical Context Modal --}}
@include('admin.partials.clinical_context_modal')
@include('admin.partials.treatment-plan-viewer-modal')

{{-- Investigation Result View Modal --}}
@include('admin.partials.invest_res_view_modal')
@include('admin.partials.invest_res_view_js')

{{--
    ══════════════════════════════════════════════════════════════
    Store Governance — Readiness Chip JS (Plan §7.5.1, §B12)
    Reads validateCartStock() response fields:
      - validation_results[].readiness_chip  (ready|billing_pending|hmo_blocked|stock_short|governance_blocked)
      - store_governance_blocked             (bool)
      - store_governance_message             (string)
    Renders coloured chips on dispense queue rows.
    Called by: renderChipForRow(row, containerId)
    ══════════════════════════════════════════════════════════════
--}}
<style>
    /* Plan §7.5.1 — Readiness Chip styles */
    .readiness-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .readiness-chip.chip-ready            { background:#d1fae5; color:#065f46; }
    .readiness-chip.chip-billing_pending  { background:#fef9c3; color:#713f12; }
    .readiness-chip.chip-hmo_blocked      { background:#fee2e2; color:#7f1d1d; }
    .readiness-chip.chip-stock_short      { background:#ffedd5; color:#7c2d12; }
    .readiness-chip.chip-governance_blocked { background:#e0e7ff; color:#3730a3; }
    .readiness-chip.chip-blocked          { background:#f3f4f6; color:#374151; }
</style>
<script>
/**
 * Store Governance — Readiness Chip Renderer (Plan §7.5.1, §B12)
 *
 * Usage after validateCartStock() resolves:
 *   applyReadinessChips(validationResponse, rowSelector);
 *
 * @param {Object} response   — full JSON from validateCartStock()
 * @param {string} rowAttr    — data attribute name on TR that holds product_request_id
 */
window.applyReadinessChips = function(response, rowAttr) {
    rowAttr = rowAttr || 'data-pr-id';

    // If the entire store is governance-blocked, show a toast and stop
    if (response.store_governance_blocked) {
        if (typeof toastr !== 'undefined') {
            toastr.error(response.store_governance_message || 'You do not have permission to dispense from this store.', 'Store Blocked', { timeOut: 6000 });
        }
    }

    (response.validation_results || []).forEach(function(r) {
        const $row = $('[' + rowAttr + '="' + r.product_request_id + '"]');
        if (!$row.length) return;

        const chip   = r.readiness_chip || 'blocked';
        const labels = {
            ready:               '<i class="fas fa-check-circle"></i> Ready',
            billing_pending:     '<i class="fas fa-file-invoice"></i> Bill First',
            hmo_blocked:         '<i class="fas fa-shield-alt"></i> HMO Block',
            stock_short:         '<i class="fas fa-exclamation-triangle"></i> Stock Short',
            governance_blocked:  '<i class="fas fa-lock"></i> Store Locked',
            blocked:             '<i class="fas fa-ban"></i> Blocked',
        };

        const html = `<span class="readiness-chip chip-${chip}" title="${(r.error || '').replace(/"/g, '&quot;')}">${labels[chip] || chip}</span>`;

        // Replace or append chip into a designated .readiness-chip-cell
        const $cell = $row.find('.readiness-chip-cell');
        if ($cell.length) {
            $cell.html(html);
        } else {
            // Fallback: append after the last td
            $row.find('td:last').prepend(html + ' ');
        }
    });
};

/**
 * Helper: derive chip label HTML directly from an error_type string.
 * Useful for inline rendering without a full response object.
 */
window.readinessChipHtml = function(chipState, tooltip) {
    const labels = {
        ready:              '<i class="fas fa-check-circle"></i> Ready',
        billing_pending:    '<i class="fas fa-file-invoice"></i> Bill First',
        hmo_blocked:        '<i class="fas fa-shield-alt"></i> HMO Block',
        stock_short:        '<i class="fas fa-exclamation-triangle"></i> Stock Short',
        governance_blocked: '<i class="fas fa-lock"></i> Store Locked',
        blocked:            '<i class="fas fa-ban"></i> Blocked',
    };
    const t = (tooltip || '').replace(/"/g, '&quot;');
    return `<span class="readiness-chip chip-${chipState}" title="${t}">${labels[chipState] || chipState}</span>`;
};

// ── Store Context Override (Plan §10 Step 1) ──────────────────────────────
function openStoreContextOverride() {
    $('#storeContextOverrideModal').modal('show');
}

function confirmStoreContextOverride() {
    const storeId = $('#ctx-store-select').val();
    if (!storeId) {
        $('#ctx-override-error').text('Please select a store.').removeClass('d-none');
        return;
    }
    $('#ctx-override-error').addClass('d-none');
    $('#ctx-override-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Applying...');

    $.ajax({
        url: '{{ route("store-context.set") }}',
        method: 'POST',
        data: { store_id: storeId, context: 'pharmacy', _token: '{{ csrf_token() }}' },
        success: function () {
            window.location.reload();
        },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message ?? 'Failed to update store context.';
            $('#ctx-override-error').text(msg).removeClass('d-none');
            $('#ctx-override-btn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Apply');
        }
    });
}

// --- Walk-in Registration ---
function openWalkInRegistration() {
    // Extend config to preserve registerUrl and other defaults
    window.patientFormConfig = window.patientFormConfig || {};
    window.patientFormConfig.onSuccess = function(patientId) {
        loadPatient(patientId);
        $('#patientFormModal').modal('hide');
        toastr.success('Patient registered successfully.');
        
        // Clear search if open
        if ($('#patient_search_results').is(':visible')) {
            $('#patient_search').val('');
            $('#patient_search_results').hide();
        }
    };
    window.patientFormConfig.onCancel = function() {
        disableWalkInMode();
    };
    window.patientFormConfig.hmos = @json(\App\Models\Hmo::with('scheme')->orderBy('name')->get()->map(fn($h) => ['id' => $h->id, 'name' => $h->name, 'scheme_name' => $h->scheme->name ?? 'Other']));

    // Show modal first
    showPatientFormModal('create');

    // After modal is shown, enable walk-in mode
    $('#patientFormModal').one('shown.bs.modal', function() {
        enableWalkInMode('PHARM-');
    });
}

// Ensure walk-in mode is disabled when modal is hidden
$('#patientFormModal').on('hidden.bs.modal', function() {
    disableWalkInMode();
});

$(document).on('click', '#btn-register-walkin', function() {
    openWalkInRegistration();
});

// --- Free-Form Dispensing ---
$(document).on('click', '.dispense-freeform-inline-btn', function(e) {
    e.preventDefault();
    const requestId = $(this).data('request-id');
    const card = $(this).closest('.presc-card');
    const itemName = card.find('.presc-card-title').text();
    const qty = card.data('qty') || 1;
    
    $('#ff-dispense-request-id').val(requestId);
    $('#ff-dispense-item-name').text(itemName);
    $('#ff-dispense-qty').val(qty);
    
    $('#dispenseFreeFormModal').modal('show');
});

$(document).on('click', '#btn-confirm-ff-dispense', function() {
    const requestId = $('#ff-dispense-request-id').val();
    const qtyDispensed = $('#ff-dispense-qty').val();
    const $btn = $(this);
    
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Dispensing...');
    
    $.ajax({
        url: "{{ route('pharmacy.dispense-free-form') }}",
        method: 'POST',
        data: {
            _token: "{{ csrf_token() }}",
            request_id: requestId,
            qty_dispensed: qtyDispensed
        },
        success: function(response) {
            $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Confirm Dispense');
            $('#dispenseFreeFormModal').modal('hide');
            toastr.success(response.message || 'Item dispensed successfully');
            
            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }
            if (queueDataTable) {
                queueDataTable.ajax.reload(null, false);
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Confirm Dispense');
            toastr.error(xhr.responseJSON?.message || 'Error dispensing item');
        }
    });
});
</script>

<!-- Free-Form Dispense Modal -->
<div class="modal fade" id="dispenseFreeFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 bg-success text-white">
                <h6 class="modal-title mb-0"><i class="mdi mdi-pill me-1"></i> Dispense External Request</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-2">
                <input type="hidden" id="ff-dispense-request-id">
                
                <div class="alert alert-info mb-3">
                    <i class="mdi mdi-information-outline"></i> You are dispensing an external/free-form request. No billing or stock logic is tied to this action.
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold mb-1">Item to Dispense</label>
                    <div id="ff-dispense-item-name" class="fw-bold" style="font-size: 1.1rem;"></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold mb-1">Quantity Dispensed</label>
                    <input type="number" class="form-control" id="ff-dispense-qty" min="1" step="1">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-confirm-ff-dispense" class="btn btn-success">
                    <i class="mdi mdi-check me-1"></i> Confirm Dispense
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Store Context Override Modal -->
<div class="modal fade" id="storeContextOverrideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0"><i class="fas fa-exchange-alt me-1"></i> Change Active Store</h6>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-2">
                <label class="form-label small fw-bold mb-1">Select Store</label>
                <select id="ctx-store-select" class="form-select form-select-sm">
                    <option value="">-- Select --</option>
                    @foreach($stores as $ctxStore)
                    <option value="{{ $ctxStore->id }}" {{ $resolvedStore && $resolvedStore->id === $ctxStore->id ? 'selected' : '' }}>
                        {{ $ctxStore->store_name }}
                    </option>
                    @endforeach
                </select>
                <div id="ctx-override-error" class="text-danger small mt-1 d-none"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="ctx-override-btn" class="btn btn-primary btn-sm" onclick="confirmStoreContextOverride()">
                    <i class="fas fa-check me-1"></i> Apply
                </button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.bundle_view_modal')
@include('admin.partials.bundle_remove_modal')
<script>
function showBundleRemove(btn) {
    var parentId = btn.dataset.parentId;
    var bundleName = btn.dataset.bundleName;
    var items = JSON.parse(btn.dataset.items || '[]');
    var removeUrl = btn.dataset.removeUrl;
    BundleRemoveModal.show({
        bundleId: parentId,
        bundleName: bundleName,
        items: items,
        onConfirm: function(callback) {
            $.ajax({
                url: removeUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ parent_request_id: parentId }),
                success: function(r) {
                    if (r.success) {
                        toastr.success(r.message || 'Combo removed');
                        callback(false);
                        if ($.fn.DataTable.isDataTable('#presc_history_table')) { $('#presc_history_table').DataTable().ajax.reload(null, false); }
                    } else {
                        toastr.error(r.message || 'Failed to remove combo');
                        callback(true);
                    }
                },
                error: function() { toastr.error('Error removing combo'); callback(true); }
            });
        }
    });
}
</script>

@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal', ['products_only' => true])
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>


<script src="{{ asset('js/clinical-orders-shared.js') }}?v={{ filemtime(public_path('js/clinical-orders-shared.js')) }}"></script>
@endsection
