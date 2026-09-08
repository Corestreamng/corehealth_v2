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
        url: wbUrl(`/pharmacy-workbench/prescription/${productRequestId}/adjust-quantity`),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/pharmacy-workbench/prescription/${requestId}/adjust-price`),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
            url: wbRoute('pharmacy.returns.datatables', '/pharmacy/returns/datatables'),
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

    $.get(wbRoute('pharmacy.returns.index', '/pharmacy/returns/index'), { stats_only: 1 })
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
            url: wbRoute('pharmacy.returns.search-dispensed', '/pharmacy/returns/search-dispensed'),
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
        url: wbRoute('pharmacy.returns.store', '/pharmacy/returns/store'),
        method: 'POST',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
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
        url: wbUrl('/pharmacy/returns/' + id + '/approve'),
        method: 'POST',
        data: { approval_notes: notes },
        headers: { 'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
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
        url: wbUrl('/pharmacy/returns/' + id + '/reject'),
        method: 'POST',
        data: { rejection_reason: reason },
        headers: { 'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
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
            url: wbRoute('pharmacy.damages.datatables', '/pharmacy/damages/datatables'),
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

    $.get(wbRoute('pharmacy.damages.index', '/pharmacy/damages/index'), { stats_only: 1 })
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
            url: wbRoute('pharmacy.damages.search-products', '/pharmacy/damages/search-products'),
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

    $.get(wbRoute('pharmacy.damages.get-batches', '/pharmacy/damages/get-batches'), { product_id: productId, store_id: storeId })
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
        url: wbRoute('pharmacy.damages.store', '/pharmacy/damages/store'),
        method: 'POST',
        data: $(this).serialize(),
        headers: { 'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
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
        url: wbUrl('/pharmacy/damages/' + id + '/approve'),
        method: 'POST',
        data: { approval_notes: notes },
        headers: { 'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
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
        url: wbUrl('/pharmacy/damages/' + id + '/reject'),
        method: 'POST',
        data: { rejection_reason: reason },
        headers: { 'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
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
            url: wbRoute('pharmacy.reports.stock-overview', '/pharmacy/reports/stock-overview'),
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
            url: wbRoute('pharmacy.reports.expiring', '/pharmacy/reports/expiring'),
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

    $.get(wbRoute('pharmacy.reports.index', '/pharmacy/reports/index'), { stats_only: 1 })
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
    $.get(wbRoute('pharmacy.reports.by-category', '/pharmacy/reports/by-category'), { list_only: 1 })
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
    $.get(wbRoute('pharmacy.reports.by-store', '/pharmacy/reports/by-store'))
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

    $.get(wbRoute('pharmacy.reports.by-category', '/pharmacy/reports/by-category'), { store_id: storeId })
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
    window.location.href = wbRoute('pharmacy.reports.export-stock', '/pharmacy-workbench/reports/export-stock') + '?' + $.param({
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
        url: wbRoute('store-context.set', '/store-context/set'),
        method: 'POST',
        data: { store_id: storeId, context: 'pharmacy', _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
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
    window.patientFormConfig.hmos = (window.WORKBENCH_CONFIG?.hmos || []);

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
        url: wbRoute('pharmacy.dispense-free-form', '/pharmacy-workbench/dispense-free-form'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
