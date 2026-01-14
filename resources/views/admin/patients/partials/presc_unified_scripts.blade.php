{{-- Unified Prescription Management Scripts --}}
{{-- Used by: patient show1.blade.php, pharmacy workbench.blade.php --}}
{{-- Dependencies: jQuery, DataTables, toastr --}}

<script>
// ========== PRESCRIPTION MANAGEMENT GLOBALS ==========
let prescPatientId = null;
let prescPatientUserId = null;
let prescBillingTotal = 0;

// Initialize on document ready or when called
function initPrescManagement(patientId, patientUserId) {
    prescPatientId = patientId || $('.presc-management-container').data('patient-id');
    prescPatientUserId = patientUserId || $('.presc-management-container').data('patient-user-id');

    if (!prescPatientId) {
        console.warn('Patient ID not set for prescription management');
        return;
    }

    console.log('Initializing prescription management for patient:', prescPatientId);

    // Initialize all DataTables
    initPrescBillingTable();
    initPrescPendingTable();
    initPrescDispenseTable();
    initPrescHistoryTable();

    // Reset totals
    prescBillingTotal = 0;
    updatePrescBillingTotal();
}

// ========== DATATABLE INITIALIZATION ==========

function initPrescBillingTable() {
    if ($.fn.DataTable.isDataTable('#presc_billing_table')) {
        $('#presc_billing_table').DataTable().destroy();
    }

    $('#presc_billing_table').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: `/prescBillList/${prescPatientId}`,
            type: 'GET'
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    const price = row.price || 0;
                    return `<input type="checkbox" class="presc-card-checkbox presc-billing-check"
                            data-id="${row.id}" data-price="${price}"
                            onclick="handlePrescBillingCheck(this)">`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCard(row, 'billing');
                }
            }
        ],
        drawCallback: function(settings) {
            const info = this.api().page.info();
            $('#presc-billing-count').text(info.recordsTotal);
        }
    });
}

function initPrescPendingTable() {
    if ($.fn.DataTable.isDataTable('#presc_pending_table')) {
        $('#presc_pending_table').DataTable().destroy();
    }

    $('#presc_pending_table').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: `/prescPendingList/${prescPatientId}`,
            type: 'GET'
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `<input type="checkbox" class="presc-card-checkbox presc-pending-check"
                            data-id="${row.id}"
                            onclick="handlePrescPendingCheck(this)">`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCard(row, 'pending');
                }
            }
        ],
        drawCallback: function(settings) {
            const info = this.api().page.info();
            $('#presc-pending-count').text(info.recordsTotal);
        }
    });
}

function initPrescDispenseTable() {
    if ($.fn.DataTable.isDataTable('#presc_dispense_table')) {
        $('#presc_dispense_table').DataTable().destroy();
    }

    $('#presc_dispense_table').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: `/prescReadyList/${prescPatientId}`,
            type: 'GET'
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    // All items in this tab are ready, so always enable checkbox
                    return `<input type="checkbox" class="presc-card-checkbox presc-dispense-check"
                            data-id="${row.id}" data-product-id="${row.product_id || ''}"
                            onclick="handlePrescDispenseCheck(this)">`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCard(row, 'dispense');
                }
            }
        ],
        drawCallback: function(settings) {
            const info = this.api().page.info();
            $('#presc-dispense-count').text(info.recordsTotal);
        }
    });
}

function initPrescHistoryTable() {
    if ($.fn.DataTable.isDataTable('#presc_history_table')) {
        $('#presc_history_table').DataTable().destroy();
    }

    $('#presc_history_table').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: `/prescHistoryList/${prescPatientId}`,
            type: 'GET'
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return renderPrescCard(row, 'history');
                }
            }
        ],
        drawCallback: function(settings) {
            const info = this.api().page.info();
            $('#presc-history-count').text(info.recordsTotal);
        }
    });
}

// ========== CARD RENDERING ==========

function renderPrescCard(row, type) {
    const productName = row.product_name || 'Unknown Product';
    const productCode = row.product_code || '';
    const dose = row.dose || 'N/A';
    const qty = row.qty || 1;
    const price = row.price || 0;
    const payable = row.payable_amount || price;
    const claims = row.claims_amount || 0;
    const coverageMode = row.coverage_mode || 'none';
    const requestedBy = row.requested_by || 'N/A';
    const requestedAt = row.requested_at || '';
    const billedBy = row.billed_by || null;
    const billedAt = row.billed_at || '';
    const dispensedBy = row.dispensed_by || null;
    const dispensedAt = row.dispensed_at || '';
    const isPaid = row.is_paid || false;
    const isValidated = row.is_validated || false;
    const pendingReason = row.pending_reason || '';

    let statusBadges = '';
    let pendingAlert = '';

    // Different status display based on tab type
    if (type === 'pending') {
        // Show clear indication of what's pending
        if (payable > 0 && !isPaid) {
            statusBadges += `<span class="badge bg-danger">Awaiting Payment</span>`;
            pendingAlert = `
                <div class="alert alert-danger py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                    <i class="mdi mdi-cash-clock"></i> <strong>Payment Required:</strong> ₦${formatMoney(payable)}
                </div>
            `;
        }
        if (claims > 0 && !isValidated) {
            statusBadges += ` <span class="badge bg-info">Awaiting HMO Validation</span>`;
            pendingAlert += `
                <div class="alert alert-info py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                    <i class="mdi mdi-shield-alert"></i> <strong>HMO Validation Required:</strong> ₦${formatMoney(claims)} claim pending
                </div>
            `;
        }
    } else if (type === 'dispense') {
        // Items in dispense tab are ready - show green badges
        if (payable > 0) {
            statusBadges += '<span class="presc-card-status paid"><i class="mdi mdi-check"></i> Paid</span>';
        }
        if (claims > 0) {
            statusBadges += ' <span class="presc-card-status validated"><i class="mdi mdi-check"></i> HMO Validated</span>';
        }
        if (payable == 0 && claims == 0) {
            statusBadges += '<span class="presc-card-status paid"><i class="mdi mdi-check"></i> Ready</span>';
        }
    } else if (type === 'history') {
        // History shows all requests - determine status badge based on actual status
        const status = parseInt(row.status || 0);

        if (status === 0) {
            statusBadges = '<span class="badge bg-danger">Dismissed</span>';
        } else if (status === 1) {
            statusBadges = '<span class="badge bg-warning text-dark">Unbilled</span>';
        } else if (status === 2) {
            // Check if ready to dispense or awaiting something
            const pendingReasons = [];
            if (payable > 0 && !isPaid) {
                pendingReasons.push('Payment');
            }
            if (claims > 0 && !isValidated) {
                pendingReasons.push('HMO Validation');
            }

            if (pendingReasons.length > 0) {
                statusBadges = `<span class="badge bg-info">Awaiting ${pendingReasons.join(' & ')}</span>`;
            } else {
                statusBadges = '<span class="badge bg-success">Ready to Dispense</span>';
            }
        } else if (status === 3) {
            statusBadges = '<span class="badge bg-secondary">Dispensed</span>';
        }
    }

    let hmoInfo = '';
    if (coverageMode !== 'none' && claims > 0) {
        hmoInfo = `
            <div class="presc-card-hmo-info">
                <span class="badge bg-info me-2">${coverageMode.toUpperCase()}</span>
                <span class="text-danger">Pay: ₦${formatMoney(payable)}</span>
                <span class="text-success ms-2">HMO Claim: ₦${formatMoney(claims)}</span>
            </div>
        `;
    }

    let metaInfo = `
        <div class="presc-card-meta">
            <div class="presc-card-meta-item">
                <i class="mdi mdi-account"></i>
                <span>By: ${requestedBy}</span>
            </div>
            <div class="presc-card-meta-item">
                <i class="mdi mdi-clock-outline"></i>
                <span>${requestedAt}</span>
            </div>
    `;

    if (billedBy) {
        metaInfo += `
            <div class="presc-card-meta-item">
                <i class="mdi mdi-cash-register"></i>
                <span>Billed: ${billedBy} (${billedAt})</span>
            </div>
        `;
    }

    if (dispensedBy) {
        metaInfo += `
            <div class="presc-card-meta-item">
                <i class="mdi mdi-pill"></i>
                <span>Dispensed: ${dispensedBy} (${dispensedAt})</span>
            </div>
        `;
    }

    metaInfo += '</div>';

    // Card border color based on type
    let cardClass = 'presc-card';
    if (type === 'pending') {
        cardClass += ' border-warning';
    } else if (type === 'dispense') {
        cardClass += ' border-success';
    }

    return `
        <div class="${cardClass}" data-id="${row.id}" data-product-id="${row.product_id || ''}" style="${type === 'pending' ? 'border-left: 4px solid #ffc107;' : (type === 'dispense' ? 'border-left: 4px solid #28a745;' : '')}">
            <div class="presc-card-header">
                <div>
                    <div class="presc-card-title">${productName}</div>
                    <div class="presc-card-code">[${productCode}]</div>
                </div>
                <div class="text-end">
                    <div class="presc-card-price">₦${formatMoney(payable)}</div>
                    ${statusBadges}
                </div>
            </div>
            ${pendingAlert}
            <div class="presc-card-body">
                <div><strong>Dose/Freq:</strong> ${dose}</div>
                <div><strong>Qty:</strong> ${qty}</div>
                ${hmoInfo}
            </div>
            ${metaInfo}
        </div>
    `;
}

function formatMoney(amount) {
    return parseFloat(amount || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ========== CHECKBOX HANDLERS ==========

function handlePrescBillingCheck(checkbox) {
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
    updatePrescBillingTotal();
}

function handlePrescDispenseCheck(checkbox) {
    const card = $(checkbox).closest('tr').find('.presc-card');
    if ($(checkbox).is(':checked')) {
        card.addClass('selected');
    } else {
        card.removeClass('selected');
    }
}

function handlePrescPendingCheck(checkbox) {
    const card = $(checkbox).closest('tr').find('.presc-card');
    if ($(checkbox).is(':checked')) {
        card.addClass('selected');
    } else {
        card.removeClass('selected');
    }
}

function toggleAllPrescBilling(masterCheckbox) {
    const isChecked = $(masterCheckbox).is(':checked');
    prescBillingTotal = 0;

    $('.presc-billing-check').each(function() {
        $(this).prop('checked', isChecked);
        const card = $(this).closest('tr').find('.presc-card');

        if (isChecked) {
            prescBillingTotal += parseFloat($(this).data('price')) || 0;
            card.addClass('selected');
        } else {
            card.removeClass('selected');
        }
    });

    updatePrescBillingTotal();
}

function toggleAllPrescDispense(masterCheckbox) {
    const isChecked = $(masterCheckbox).is(':checked');

    $('.presc-dispense-check:not(:disabled)').each(function() {
        $(this).prop('checked', isChecked);
        const card = $(this).closest('tr').find('.presc-card');

        if (isChecked) {
            card.addClass('selected');
        } else {
            card.removeClass('selected');
        }
    });
}

function toggleAllPrescPending(masterCheckbox) {
    const isChecked = $(masterCheckbox).is(':checked');

    $('.presc-pending-check').each(function() {
        $(this).prop('checked', isChecked);
        const card = $(this).closest('tr').find('.presc-card');

        if (isChecked) {
            card.addClass('selected');
        } else {
            card.removeClass('selected');
        }
    });
}

function updatePrescBillingTotal() {
    $('#presc_billing_total').text('₦' + formatMoney(prescBillingTotal));
    $('#presc_billing_total_val').val(prescBillingTotal);
}

// ========== PRODUCT SEARCH ==========

function searchProductsForPresc(query) {
    if (!query || query.length < 2) {
        $('#presc_product_results').html('').hide();
        return;
    }

    $.ajax({
        url: '/live-search-products',
        method: 'GET',
        dataType: 'json',
        data: { term: query, patient_id: prescPatientId },
        success: function(data) {
            $('#presc_product_results').html('');

            if (data.length === 0) {
                $('#presc_product_results').html('<li class="list-group-item text-muted">No products found</li>').show();
                return;
            }

            data.forEach(function(item) {
                const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                const name = item.product_name || 'Unknown';
                const code = item.product_code || '';
                const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                const price = item.price && item.price.current_sale_price !== undefined ? item.price.current_sale_price : 0;
                const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                const claims = item.claims_amount || 0;
                const mode = item.coverage_mode || null;

                const stockClass = qty > 0 ? 'text-success' : 'text-danger';
                const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ₦${payable}</span> <span class='text-success ms-1'>Claim: ₦${claims}</span>` : '';
                const displayName = `${name}[${code}](${qty} avail.)`;

                const li = `
                    <li class='list-group-item list-group-item-action' style="cursor: pointer;"
                        onclick="addPrescProduct('${name.replace(/'/g, "\\'")}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}', '${code}')">
                        [${category}] <b>${name}</b> [${code}] <span class="${stockClass}">(${qty} avail.)</span> ₦${price} ${coverageBadge}
                    </li>`;
                $('#presc_product_results').append(li);
            });

            $('#presc_product_results').show();
        },
        error: function(xhr) {
            console.error('Product search failed', xhr);
            $('#presc_product_results').html('<li class="list-group-item text-danger">Search failed</li>').show();
        }
    });
}

function addPrescProduct(name, id, price, coverageMode, claims, payable, code) {
    const actualPayable = payable || price;
    const coverageBadge = coverageMode && coverageMode !== 'null' ?
        `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ₦${actualPayable}</span> <span class="text-success">Claims: ₦${claims || 0}</span></div>` : '';

    const row = `
        <tr data-product-id="${id}" data-price="${actualPayable}">
            <td><input type='checkbox' class='form-check-input presc-added-check' data-price='${actualPayable}' value='${id}' checked onclick="handleAddedProductCheck(this)"></td>
            <td>${name} [${code}]${coverageBadge}</td>
            <td>₦${actualPayable}</td>
            <td><input type='text' class='form-control form-control-sm presc-added-dose' placeholder="Enter dose/frequency" required></td>
            <td><button type='button' class='btn btn-danger btn-sm' onclick="removePrescProduct(this, ${actualPayable})"><i class="mdi mdi-close"></i></button></td>
        </tr>
    `;

    $('#presc_added_products').append(row);
    $('#presc_product_results').html('').hide();
    $('#presc_product_search').val('');

    // Add to total
    prescBillingTotal += parseFloat(actualPayable);
    updatePrescBillingTotal();
}

function removePrescProduct(btn, price) {
    prescBillingTotal -= parseFloat(price);
    if (prescBillingTotal < 0) prescBillingTotal = 0;
    updatePrescBillingTotal();
    $(btn).closest('tr').remove();
}

function handleAddedProductCheck(checkbox) {
    const price = parseFloat($(checkbox).data('price')) || 0;
    if ($(checkbox).is(':checked')) {
        prescBillingTotal += price;
    } else {
        prescBillingTotal -= price;
    }
    if (prescBillingTotal < 0) prescBillingTotal = 0;
    updatePrescBillingTotal();
}

// ========== BILLING OPERATIONS (AJAX) ==========

function billPrescItems() {
    if (!prescPatientId || !prescPatientUserId) {
        toastr.error('Patient information not available');
        return;
    }

    // Get selected existing items
    const selectedIds = [];
    $('.presc-billing-check:checked').each(function() {
        selectedIds.push($(this).data('id'));
    });

    // Get added products
    const addedProducts = [];
    const addedDoses = [];
    $('#presc_added_products tr').each(function() {
        const checkbox = $(this).find('.presc-added-check');
        if (checkbox.is(':checked')) {
            addedProducts.push(checkbox.val());
            addedDoses.push($(this).find('.presc-added-dose').val());
        }
    });

    // Validate
    if (selectedIds.length === 0 && addedProducts.length === 0) {
        toastr.warning('Please select at least one item to bill');
        return;
    }

    // Validate doses for added items
    for (let i = 0; i < addedDoses.length; i++) {
        if (!addedDoses[i] || addedDoses[i].trim() === '') {
            toastr.error('Please enter dose/frequency for all added medications');
            return;
        }
    }

    if (!confirm('Are you sure you want to bill the selected items?')) {
        return;
    }

    const $btn = $('#btn-bill-presc');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Billing...');

    $.ajax({
        url: '/product-bill-patient-ajax',
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            selectedPrescBillRows: selectedIds,
            addedPrescBillRows: addedProducts,
            consult_presc_dose: addedDoses,
            patient_user_id: prescPatientUserId,
            patient_id: prescPatientId
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Items billed successfully');
                // Clear added products
                $('#presc_added_products').empty();
                prescBillingTotal = 0;
                updatePrescBillingTotal();
                // Reload DataTables
                $('#presc_billing_table').DataTable().ajax.reload();
                $('#presc_dispense_table').DataTable().ajax.reload();
            } else {
                toastr.error(response.message || 'Failed to bill items');
            }
        },
        error: function(xhr) {
            console.error('Billing failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to bill items');
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

function dispensePrescItems() {
    if (!prescPatientId) {
        toastr.error('Patient information not available');
        return;
    }

    const selectedIds = [];
    $('.presc-dispense-check:checked').each(function() {
        selectedIds.push($(this).data('id'));
    });

    if (selectedIds.length === 0) {
        toastr.warning('Please select at least one item to dispense');
        return;
    }

    if (!confirm('Are you sure you want to dispense the selected items?')) {
        return;
    }

    const $btn = $('#btn-dispense-presc');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Dispensing...');

    $.ajax({
        url: '/product-dispense-patient-ajax',
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            selectedPrescDispenseRows: selectedIds,
            patient_user_id: prescPatientUserId,
            patient_id: prescPatientId
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Items dispensed successfully');
                // Reload DataTables
                $('#presc_dispense_table').DataTable().ajax.reload();
                $('#presc_history_table').DataTable().ajax.reload();
            } else {
                toastr.error(response.message || 'Failed to dispense items');
            }
        },
        error: function(xhr) {
            console.error('Dispense failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to dispense items');
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

function dismissPrescItems(type) {
    if (!prescPatientId) {
        toastr.error('Patient information not available');
        return;
    }

    const checkboxClass = type === 'billing' ? '.presc-billing-check' : '.presc-dispense-check';
    const selectedIds = [];
    $(checkboxClass + ':checked').each(function() {
        selectedIds.push($(this).data('id'));
    });

    if (selectedIds.length === 0) {
        toastr.warning('Please select at least one item to dismiss');
        return;
    }

    if (!confirm('Are you sure you want to dismiss the selected items? This action cannot be undone.')) {
        return;
    }

    $.ajax({
        url: '/product-dismiss-patient-ajax',
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            prescription_ids: selectedIds,
            patient_id: prescPatientId
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Items dismissed successfully');
                // Reload DataTables
                $('#presc_billing_table').DataTable().ajax.reload();
                $('#presc_dispense_table').DataTable().ajax.reload();
                prescBillingTotal = 0;
                updatePrescBillingTotal();
            } else {
                toastr.error(response.message || 'Failed to dismiss items');
            }
        },
        error: function(xhr) {
            console.error('Dismiss failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to dismiss items');
        }
    });
}

// Auto-initialize if patient data is available in the DOM
$(document).ready(function() {
    const container = $('.presc-management-container');
    if (container.length > 0) {
        const patientId = container.data('patient-id');
        const patientUserId = container.data('patient-user-id');
        if (patientId) {
            initPrescManagement(patientId, patientUserId);
        }
    }
});
</script>
