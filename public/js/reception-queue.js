// =============================================
// WALK-IN SALES
// =============================================
function initializeWalkinSales() {
    // Add to cart
    $(document).on('click', '.add-to-cart', function() {
        const serviceId = $(this).data('id');
        const serviceType = $(this).data('type');
        const serviceName = $(this).data('name');
        const servicePrice = $(this).data('price');
        addToWalkinCart(serviceId, serviceType, serviceName, servicePrice);
    });

    // Remove from cart
    $(document).on('click', '.remove-from-cart', function() {
        const index = $(this).data('index');
        removeFromWalkinCart(index);
    });

    // Submit walk-in
    $('#btn-submit-walkin').on('click', function() {
        submitWalkinServices();
    });
}

let walkinCart = [];

function searchWalkinServices(query, type) {
    const $container = $('#walkin-search-results');
    $container.empty();

    let services = [];
    if (type === 'lab') {
        services = (cachedServices.lab || []).map(s => ({ ...s, type: 'lab' }));
    } else if (type === 'imaging') {
        services = (cachedServices.imaging || []).map(s => ({ ...s, type: 'imaging' }));
    } else if (type === 'product') {
        services = (cachedProducts || []).map(p => ({ ...p, type: 'product' }));
    }

    // Filter by query if provided
    if (query) {
        services = services.filter(s => s.name.toLowerCase().includes(query));
    }

    if (services.length === 0) {
        $container.html('<p class="text-muted text-center py-3">No services found</p>');
        return;
    }

    services.slice(0, 20).forEach(service => {
        const price = parseFloat(service.price || 0);
        const typeLabel = type === 'lab' ? 'Lab' : (type === 'imaging' ? 'Imaging' : 'Product');
        const typeClass = type === 'lab' ? 'info' : (type === 'imaging' ? 'warning' : 'success');

        $container.append(`
            <div class="walkin-service-item d-flex justify-content-between align-items-center p-2 border-bottom">
                <div>
                    <span class="badge badge-${typeClass}">${typeLabel}</span>
                    <span class="ml-2">${service.name}</span>
                </div>
                <div>
                    <span class="text-muted mr-2">₦${price.toLocaleString()}</span>
                    <button class="btn btn-sm btn-primary add-to-cart" data-id="${service.id}" data-type="${type}" data-name="${service.name}" data-price="${price}">
                        <i class="mdi mdi-plus"></i>
                    </button>
                </div>
            </div>
        `);
    });
}

function addToWalkinCart(id, type, name, price) {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Check if item already in cart
    const existingIndex = walkinCart.findIndex(item => item.id == id && item.type == type);
    if (existingIndex>= 0) {
        toastr.info('Item already in cart');
        return;
    }

    // Fetch tariff preview with HMO calculations
    const isProduct = type === 'product';
    $.ajax({
        url: wbRoute('reception.tariff-preview', '/reception/tariff-preview'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            patient_id: currentPatient,
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
                payable_amount: parseFloat(data.payable_amount || price),
                claims_amount: parseFloat(data.claims_amount || 0),
                coverage_mode: data.coverage_mode || null,
                hmo_name: data.hmo_name || 'Private',
                quantity: 1
            });
            updateWalkinCartUI();
        },
        error: function() {
            // Fallback without HMO
            walkinCart.push({
                id: id,
                type: type,
                name: name,
                base_price: parseFloat(price),
                payable_amount: parseFloat(price),
                claims_amount: 0,
                coverage_mode: null,
                hmo_name: 'Private',
                quantity: 1
            });
            updateWalkinCartUI();
        }
    });
}

function removeFromWalkinCart(index) {
    walkinCart.splice(index, 1);
    updateWalkinCartUI();
}

function updateWalkinCartUI() {
    const $container = $('#walkin-cart-body');
    $container.empty();

    // Update cart count badge
    $('#cart-count-badge').text(walkinCart.length);

    if (walkinCart.length === 0) {
        $container.html(`
            <tr id="walkin-cart-empty">
                <td colspan="5" class="text-center text-muted py-4">
                    <i class="mdi mdi-cart-outline" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">No items selected</p>
                </td>
            </tr>
        `);
        $('#walkin-subtotal').text('₦0');
        $('#walkin-cart-total').text('₦0');
        $('#walkin-hmo-row').hide();
        $('#btn-submit-walkin').prop('disabled', true);
        return;
    }

    let subtotal = 0;
    let totalPayable = 0;
    let totalClaims = 0;
    let hmoName = 'Private';

    walkinCart.forEach((item, index) => {
        const itemSubtotal = item.base_price * item.quantity;
        const itemPayable = item.payable_amount * item.quantity;
        const itemClaims = item.claims_amount * item.quantity;

        subtotal += itemSubtotal;
        totalPayable += itemPayable;
        totalClaims += itemClaims;

        if (item.hmo_name && item.hmo_name !== 'Private') {
            hmoName = item.hmo_name;
        }

        // Coverage info
        const hasHmoCoverage = itemClaims> 0;
        const coverageLabel = item.coverage_mode ? item.coverage_mode.charAt(0).toUpperCase() + item.coverage_mode.slice(1) : '';
        const coverageBadge = hasHmoCoverage
            ? `<span class="badge badge-success" style="font-size: 0.7rem;">${coverageLabel}</span>`
            : '';

        $container.append(`
            <tr>
                <td>
                    <strong>${item.name}</strong>
                    <br>
                    <small class="text-muted">${item.type}</small>
                    ${coverageBadge}
                </td>
                <td class="text-right">
                    <span>₦${itemSubtotal.toLocaleString()}</span>
                </td>
                <td class="text-right text-success">
                    ${hasHmoCoverage ? `<span>-₦${itemClaims.toLocaleString()}</span>` : '<span class="text-muted">-</span>'}
                </td>
                <td class="text-right text-primary">
                    <strong>₦${itemPayable.toLocaleString()}</strong>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger remove-from-cart" data-index="${index}" title="Remove">
                        <i class="mdi mdi-close"></i>
                    </button>
                </td>
            </tr>
        `);
    });

    // Update summary
    $('#walkin-subtotal').text(`₦${subtotal.toLocaleString()}`);

    if (totalClaims> 0) {
        $('#walkin-hmo-row').show();
        $('#walkin-hmo-name').text(hmoName);
        $('#walkin-hmo-amount').text(`-₦${totalClaims.toLocaleString()}`);
    } else {
        $('#walkin-hmo-row').hide();
    }

    $('#walkin-cart-total').text(`₦${totalPayable.toLocaleString()}`);
    $('#btn-submit-walkin').prop('disabled', false);
}

function submitWalkinServices() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    if (walkinCart.length === 0) {
        toastr.warning('Cart is empty');
        return;
    }

    const $btn = $('#btn-submit-walkin');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: wbRoute('reception.book-walkin', '/reception/book-walkin'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            patient_id: currentPatient,
            items: walkinCart
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Services created successfully');
                walkinCart = [];
                updateWalkinCartUI();
                // Refresh recent requests
                loadRecentRequests();
                // Switch to recent tab to show the new request
                $('#walkin-cart-tabs a[href="#walkin-recent-pane"]').tab('show');
            } else {
                toastr.error(response.message || 'Failed to create services');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create services');
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// =============================================
// RECENT REQUESTS (Last 24 hours)
// =============================================
function loadRecentRequests() {
    if (!currentPatient) return;

    const $container = $('#recent-requests-container');
    $container.html('<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div>');

    $.ajax({
        url: wbUrl(`reception/patient/${currentPatient}/recent-requests`),
        method: 'GET',
        success: function(response) {
            if (response.success && response.requests && response.requests.length> 0) {
                let html = '';
                response.requests.forEach(req => {
                    const typeClass = getTypeClass(req.type);
                    const billingClass = getBillingStatusClass(req.billing_status);
                    const deliveryClass = getDeliveryStatusClass(req.delivery_status);
                    const coverageBadge = req.coverage_mode ? `<span class="badge badge-outline-success ml-1">${req.coverage_mode}</span>` : '';
                    const createdAt = new Date(req.created_at).toLocaleString('en-GB', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'});

                    html += `
                        <div class="recent-request-item">
                            <div class="recent-request-header">
                                <div>
                                    <span class="recent-request-name">${req.name}</span>
                                    <span class="badge badge-${typeClass} recent-request-type ml-2">${req.type_label}</span>
                                    ${coverageBadge}
                                </div>
                                <small class="text-muted">${createdAt}</small>
                            </div>
                            <div class="recent-request-details">
                                <div class="recent-request-pricing">
                                    <span>
                                        <span class="price-label">Price</span>
                                        <span class="price-value">₦${parseFloat(req.price || 0).toLocaleString()}</span>
                                    </span>
                                    <span>
                                        <span class="price-label">HMO</span>
                                        <span class="price-value text-success">${req.hmo_covers> 0 ? '-₦' + parseFloat(req.hmo_covers).toLocaleString() : '-'}</span>
                                    </span>
                                    <span>
                                        <span class="price-label">Payable</span>
                                        <span class="price-value text-primary">₦${parseFloat(req.payable || 0).toLocaleString()}</span>
                                    </span>
                                </div>
                                <div class="recent-request-status">
                                    <span class="billing-badge ${billingClass}">${req.billing_status || 'Pending'}</span>
                                    <span class="delivery-badge ${deliveryClass}">${req.delivery_status || 'Pending'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                $container.html(html);
            } else {
                $container.html(`
                    <div class="text-center text-muted py-4">
                        <i class="mdi mdi-clock-outline" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-2">No recent requests</p>
                    </div>
                `);
            }
        },
        error: function() {
            $container.html(`
                <div class="text-center text-muted py-4">
                    <i class="mdi mdi-alert-circle" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">Failed to load recent requests</p>
                </div>
            `);
        }
    });
}

function getTypeClass(type) {
    const classes = {
        'lab': 'info',
        'imaging': 'warning',
        'product': 'success',
        'consultation': 'primary',
        'procedure': 'secondary'
    };
    return classes[type?.toLowerCase()] || 'secondary';
}

function getBillingStatusClass(status) {
    if (!status) return 'billing-pending';
    const statusLower = status.toLowerCase();
    if (statusLower.includes('paid')) return 'billing-paid';
    if (statusLower.includes('billed')) return 'billing-billed';
    return 'billing-pending';
}

function getDeliveryStatusClass(status) {
    if (!status) return 'delivery-pending';
    const statusLower = status.toLowerCase();
    if (statusLower.includes('completed') || statusLower.includes('dispensed')) return 'delivery-completed';
    if (statusLower.includes('progress') || statusLower.includes('sample') || statusLower.includes('awaiting')) return 'delivery-progress';
    return 'delivery-pending';
}

// =============================================
// SERVICE REQUESTS TAB
// =============================================
let serviceRequestsTable = null;

function initializeServiceRequestsTable(patientId) {
    if (serviceRequestsTable) {
        serviceRequestsTable.destroy();
    }

    // Set default date range to this month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    if (!$('#req-date-from').val()) {
        $('#req-date-from').val(firstDay.toISOString().split('T')[0]);
    }
    if (!$('#req-date-to').val()) {
        $('#req-date-to').val(lastDay.toISOString().split('T')[0]);
    }

    serviceRequestsTable = $('#service-requests-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`reception/patient/${patientId}/service-requests`),
            type: 'GET',
            data: function(d) {
                d.date_from = $('#req-date-from').val();
                d.date_to = $('#req-date-to').val();
                d.type_filter = $('#req-type-filter').val();
                d.billing_filter = $('#req-billing-filter').val();
                d.delivery_filter = $('#req-delivery-filter').val();
            }
        },
        columns: [
            { data: 'date_formatted', name: 'created_at' },
            { data: 'request_no', name: 'request_no' },
            { data: 'type_badge', name: 'type' },
            { data: 'name', name: 'name' },
            { data: 'price_formatted', name: 'price', className: 'text-right' },
            { data: 'hmo_covers_formatted', name: 'hmo_covers', className: 'text-right text-success' },
            { data: 'payable_formatted', name: 'payable', className: 'text-right text-primary font-weight-bold' },
            { data: 'billing_badge', name: 'billing_status' },
            { data: 'delivery_badge', name: 'delivery_status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip',
        language: {
            emptyTable: 'No service requests found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        },
        drawCallback: function() {
            // Update summary stats after table loads
            loadServiceRequestsStats(patientId);
        }
    });
}

function loadServiceRequestsStats(patientId) {
    $.ajax({
        url: wbUrl(`reception/patient/${patientId}/service-requests-stats`),
        method: 'GET',
        data: {
            date_from: $('#req-date-from').val(),
            date_to: $('#req-date-to').val(),
            type_filter: $('#req-type-filter').val(),
            billing_filter: $('#req-billing-filter').val(),
            delivery_filter: $('#req-delivery-filter').val()
        },
        success: function(response) {
            if (response.success && response.stats) {
                $('#req-total-requests').text(response.stats.total_requests || 0);
                $('#req-hmo-covered').text(response.stats.hmo_covered || '₦0');
                $('#req-patient-payable').text(response.stats.patient_payable || '₦0');
                $('#req-completed-count').text(response.stats.completed || 0);
            }
        }
    });
}

function reloadServiceRequestsData() {
    if (serviceRequestsTable) {
        serviceRequestsTable.ajax.reload();
    }
}

// Event handlers for service requests
$(document).on('submit', '#service-requests-filter-form', function(e) {
    e.preventDefault();
    reloadServiceRequestsData();
});

$(document).on('click', '#clear-req-filters', function() {
    // Reset to this month defaults
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    $('#req-date-from').val(firstDay.toISOString().split('T')[0]);
    $('#req-date-to').val(lastDay.toISOString().split('T')[0]);
    $('#req-type-filter, #req-billing-filter, #req-delivery-filter').val('');
    reloadServiceRequestsData();
});

// Export handlers
$(document).on('click', '#export-requests-excel', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val(),
        format: 'excel'
    });
    window.location.href = wbUrl(`reception/patient/${currentPatient}/service-requests/export?${params}`);
});

$(document).on('click', '#export-requests-pdf', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val(),
        format: 'pdf'
    });
    window.location.href = wbUrl(`reception/patient/${currentPatient}/service-requests/export?${params}`);
});

$(document).on('click', '#print-requests', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val()
    });
    window.open(wbUrl(`reception/patient/${currentPatient}/service-requests/print?${params}`), '_blank');
});

// View Request Details Handler
$(document).on('click', '.view-request-btn', function() {
    const type = $(this).data('type');
    const id = $(this).data('id');

    showRequestDetails(type, id);
});

// Discard Request Handler
let discardRequestType = null;
let discardRequestId = null;

$(document).on('click', '.discard-request-btn', function() {
    discardRequestType = $(this).data('type');
    discardRequestId = $(this).data('id');
    const serviceName = $(this).data('name');
    const requestNo = $(this).data('request-no');

    $('#discard_service_name').text(serviceName);
    $('#discard_request_no').text(requestNo);
    $('#discard_reason').val('');
    $('#discardRequestModal').modal('show');
});

$(document).on('click', '.btn-print-routing', function(e) {
    e.preventDefault();
    const queueId = $(this).data('queue-id');
    
    toastr.info('Generating routing slip...');
    
    $.ajax({
        url: wbUrl(`reception/queue/${queueId}/routing-slip`),
        method: 'GET',
        success: function(response) {
            if (response.success && response.html) {
                const printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write(response.html);
                printWindow.document.close();
                printWindow.print();
            } else {
                toastr.error('Failed to generate routing slip');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to print routing slip');
        }
    });
});

$(document).on('click', '.btn-delete-queue', function(e) {
    e.preventDefault();
    const queueId = $(this).data('queue-id');
    const serviceRequestId = $(this).data('service-request-id');
    const serviceName = $(this).closest('.card-body').find('h6').text().trim();
    
    discardRequestType = 'service';
    discardRequestId = serviceRequestId; // Note: if serviceRequestId is null (skipped billing), this might need handling
    
    // If no service request (e.g. skipped billing), we might need to delete the queue directly
    if (!serviceRequestId) {
        // Fallback for cycle-duration skipped billings
        discardRequestType = 'queue';
        discardRequestId = queueId;
    }

    $('#discard_service_name').text(serviceName + ' (Queue Booking)');
    $('#discard_request_no').text('Q-' + queueId);
    $('#discard_reason').val('');
    $('#discardRequestModal').modal('show');
});

$(document).on('click', '.btn-print-routing', function(e) {
    e.preventDefault();
    const queueId = $(this).data('queue-id');
    
    toastr.info('Generating routing slip...');
    
    $.ajax({
        url: wbUrl(`reception/queue/${queueId}/routing-slip`),
        method: 'GET',
        success: function(response) {
            if (response.success && response.html) {
                const printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write(response.html);
                printWindow.document.close();
                printWindow.print();
            } else {
                toastr.error('Failed to generate routing slip');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to print routing slip');
        }
    });
});

$('#discardRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#discard_reason').val();

    if (reason.length < 10) {
        toastr.warning('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $('#confirmDiscardBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Discarding...');

    $.ajax({
        url: wbUrl(`reception/request/${discardRequestType}/${discardRequestId}/discard`),
        method: 'DELETE',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            reason: reason
        },
        success: function(response) {
            $('#discardRequestModal').modal('hide');
            toastr.success(response.message || 'Request discarded successfully');

            // Reload the service requests table
            reloadServiceRequestsData();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to discard request');
        },
        complete: function() {
            $('#confirmDiscardBtn').prop('disabled', false).html('<i class="mdi mdi-delete"></i> Discard Request');
        }
    });
});

// =============================================
// VISIT HISTORY
// =============================================
function initializeVisitHistoryTable(patientId) {
    if (visitHistoryTable) {
        visitHistoryTable.destroy();
    }

    visitHistoryTable = $('#visit-history-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`reception/patient/${patientId}/visits`),
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'date', name: 'created_at' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'service_name', name: 'service_name' },
            { data: 'reason', name: 'reason' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            emptyTable: 'No visit history found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// =============================================
// QUICK REGISTRATION
// =============================================
function showQuickRegisterModal() {
    $('#quickRegisterModal').modal('show');
    // Reset mode buttons
    $('#qr-mode-auto').addClass('active');
    $('#qr-mode-manual').removeClass('active');
    $('#quick-register-file-no').prop('readonly', true).removeClass('status-valid status-checking status-duplicate');
    $('#qr-duplicate-warning').hide();
    $('#qr-file-no-hint').removeClass('manual-mode');
    // Generate new file number from server
    generateFileNumber();
}

function generateFileNumber() {
    const $input = $('#quick-register-file-no');
    $input.removeClass('status-valid status-checking status-duplicate');

    $.ajax({
        url: wbUrl('/reception/patient/next-file-number'),
        method: 'GET',
        success: function(response) {
            $input.val(response.file_no).addClass('status-valid');
            $('#qr-next-file-no').text(response.file_no);

            // Update format pattern display
            if (response.format_pattern) {
                $('#qr-format-pattern').text(response.format_pattern);
            } else {
                $('#qr-format-pattern').text('Sequential');
            }

            // Populate recent file numbers
            const $recentList = $('#qr-recent-file-nos');
            $recentList.empty();
            if (response.recent_file_nos && response.recent_file_nos.length> 0) {
                response.recent_file_nos.forEach(fileNo => {
                    $recentList.append(`<span class="file-no-recent-item qr-recent-item" data-file-no="${fileNo}">${fileNo}</span>`);
                });
            }

            $('#qr-duplicate-warning').hide();
        },
        error: function() {
            // Fallback: use timestamp-based number if server fails
            const now = new Date();
            const fallbackNo = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(Math.floor(Math.random() * 10000)).padStart(4, '0')}`;
            $('#quick-register-file-no').val(fallbackNo);
            $('#qr-next-file-no').text(fallbackNo);
            $('#qr-format-pattern').text('Auto-generated');
            $('#qr-recent-file-nos').empty();
            toastr.warning('Could not fetch next file number, using auto-generated');
        }
    });
}

// Toggle file number mode for quick register (Auto/Manual buttons)
function toggleQRFileNumberMode(mode) {
    const $input = $('#quick-register-file-no');
    const $hint = $('#qr-file-no-hint');

    // Update button states
    $('#qr-mode-auto, #qr-mode-manual').removeClass('active');
    if (mode === 'manual') {
        $('#qr-mode-manual').addClass('active');
    } else {
        $('#qr-mode-auto').addClass('active');
    }

    if (mode === 'manual') {
        // Manual mode - allow editing
        $input.prop('readonly', false).attr('placeholder', 'Enter file number');
        $hint.addClass('manual-mode');
        $input.focus().select();

        // Check current value for duplicates
        if ($input.val()) {
            checkQRFileNumberDuplicate($input.val());
        }
    } else {
        // Auto mode - readonly with generated number
        $input.prop('readonly', true).attr('placeholder', 'Auto-generated');
        $hint.removeClass('manual-mode');
        generateFileNumber();
    }
}

// Click handlers for mode buttons
$('#qr-mode-auto').on('click', function() {
    toggleQRFileNumberMode('auto');
});

$('#qr-mode-manual').on('click', function() {
    toggleQRFileNumberMode('manual');
});

// Quick register refresh button
$('#qr-file-no-refresh').on('click', function() {
    toggleQRFileNumberMode('auto');
    toastr.info('File number regenerated');
});

// Click handler for recent file numbers in quick register (copy to input)
$(document).on('click', '.qr-recent-item', function() {
    const fileNo = $(this).data('file-no');
    const $input = $('#quick-register-file-no');

    // Switch to manual mode
    toggleQRFileNumberMode('manual');

    // Set the value
    $input.val(fileNo);

    // Check for duplicates
    checkQRFileNumberDuplicate(fileNo);

    toastr.info(`Copied "${fileNo}" - you can edit it now`);
});

// Debounced file number duplicate check for quick register
let quickRegisterCheckTimeout = null;
function checkQRFileNumberDuplicate(fileNo) {
    const $input = $('#quick-register-file-no');

    // Clear previous timeout
    if (quickRegisterCheckTimeout) {
        clearTimeout(quickRegisterCheckTimeout);
    }

    if (!fileNo || fileNo.trim() === '') {
        $input.removeClass('status-valid status-checking status-duplicate');
        $('#qr-duplicate-warning').hide();
        return;
    }

    // Show checking state
    $input.removeClass('status-valid status-duplicate').addClass('status-checking');

    // Debounce the AJAX call
    quickRegisterCheckTimeout = setTimeout(function() {
        $.ajax({
            url: wbUrl('/reception/patient/check-file-number'),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                file_no: fileNo
            },
            success: function(response) {
                $input.removeClass('status-checking');

                if (response.exists) {
                    // Show warning (not blocking, just informative)
                    $input.addClass('status-duplicate');
                    const $warning = $('#qr-duplicate-warning');
                    const $patients = $('#qr-duplicate-patients');

                    let html = '';
                    response.patients.forEach(p => {
                        html += `<div class="duplicate-patient"><i class="mdi mdi-account"></i> ${p.name} (${p.file_no})</div>`;
                    });
                    if (response.count> 3) {
                        html += `<div class="duplicate-patient text-muted">...and ${response.count - 3} more</div>`;
                    }
                    $patients.html(html);
                    $warning.show();
                } else {
                    // File number is unique
                    $input.addClass('status-valid');
                    $('#qr-duplicate-warning').hide();
                }
            },
            error: function() {
                $input.removeClass('status-checking');
            }
        });
    }, 400); // 400ms debounce
}

// Quick register file number input change (for duplicate check)
$('#quick-register-file-no').on('input', function() {
    const $input = $(this);
    if (!$input.prop('readonly')) {
        checkQRFileNumberDuplicate($input.val());
    }
});

function submitQuickRegister() {
    const $form = $('#quick-register-form');
    const $btn = $form.find('button[type="submit"]');
    const originalHtml = $btn.html();

    // Basic validation
    const firstName = $('#quick-register-firstname').val().trim();
    const lastName = $('#quick-register-lastname').val().trim();
    const phone = $('#quick-register-phone').val().trim();
    const gender = $('#quick-register-gender').val();

    if (!firstName || !lastName) {
        toastr.warning('First name and last name are required');
        return;
    }

    if (!gender) {
        toastr.warning('Please select gender');
        return;
    }

    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Registering...');

    $.ajax({
        url: wbRoute('reception.patient.quick-register', '/reception/patient/quick-register'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            surname: lastName,
            firstname: firstName,
            phone_no: phone,
            gender: gender,
            dob: $('#quick-register-dob').val(),
            hmo_id: $('#quick-register-hmo').val(),
            hmo_no: $('#quick-register-hmo-no').val()
        },
        success: function(response) {
            if (response.success) {
                toastr.success('Patient registered successfully');
                $('#quickRegisterModal').modal('hide');
                $form[0].reset();

                // Load the newly registered patient
                if (response.patient && response.patient.id) {
                    loadPatient(response.patient.id);
                }
            } else {
                toastr.error(response.message || 'Registration failed');
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(err => {
                    toastr.error(err[0]);
                });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Registration failed');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// =============================================
// TODAY'S STATS
// =============================================
function showTodayStats() {
    $.ajax({
        url: wbRoute('reception.today-stats', '/reception/today-stats'),
        method: 'GET',
        success: function(data) {
            displayTodayStats(data);
        },
        error: function() {
            toastr.error('Failed to load statistics');
        }
    });
}

function displayTodayStats(data) {
    const html = `
        <div class="modal fade" id="todayStatsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="mdi mdi-chart-bar"></i> Today's Statistics</h5>
                        <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-primary text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.total_queued || 0}</h3>
                                    <small>Total Queued Today</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-success text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.new_registrations || 0}</h3>
                                    <small>New Registrations</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-info text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.consultations_done || 0}</h3>
                                    <small>Consultations Done</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-warning text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.pending_services || 0}</h3>
                                    <small>Pending Services</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#todayStatsModal').remove();

    // Add and show modal
    $('body').append(html);
    $('#todayStatsModal').modal('show');

    // Clean up on close
    $('#todayStatsModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

// =============================================
// UTILITY FUNCTIONS
// =============================================
function updateSyncIndicator() {
    const $indicator = $('#sync-indicator');
    if ($indicator.length) {
        $indicator.html(`
            <i class="mdi mdi-check-circle text-success"></i>
            <small class="text-muted">Synced</small>
        `);
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
           ' ' + date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

// Cleanup on page unload
$(window).on('beforeunload', function() {
    if (queueRefreshInterval) {
        clearInterval(queueRefreshInterval);
    }
});

// =============================================
// HOSPITAL CARD FUNCTIONS
// =============================================
function showHospitalCard(patientData) {
    const defaultAvatar = wbUrl('assets/images/default-avatar.png');

    // Populate FRONT card data
    $('#card-patient-photo').attr('src', patientData.photo || defaultAvatar);
    $('#card-patient-name').text(patientData.name || 'Patient Name');
    $('#card-patient-id').text(patientData.file_no || 'N/A');
    $('#card-dob').text(patientData.dob || 'N/A');
    $('#card-blood-type').text(patientData.blood_group || 'N/A');
    $('#card-genotype').text(patientData.genotype || 'N/A');
    $('#card-barcode-number').text(patientData.file_no || '');

    // Populate BACK card data
    $('#card-gender').text(patientData.gender || 'N/A');
    $('#card-phone').text(patientData.phone_no || 'N/A');
    $('#card-address').text(patientData.address || 'Not provided');

    // Handle allergies (may be array or string)
    let allergiesText = 'None known';
    if (patientData.allergies) {
        if (Array.isArray(patientData.allergies)) {
            allergiesText = patientData.allergies.length> 0 ? patientData.allergies.join(', ') : 'None known';
        } else {
            allergiesText = patientData.allergies;
        }
    }
    $('#card-allergies').text(allergiesText);

    $('#card-nok-name').text(patientData.next_of_kin_name || 'Not provided');
    $('#card-nok-phone').text(patientData.next_of_kin_phone || 'N/A');

    // Generate barcode using JsBarcode if available, otherwise use simple display
    if (typeof JsBarcode !== 'undefined' && patientData.file_no) {
        try {
            JsBarcode('#card-barcode', patientData.file_no, {
                format: 'CODE128',
                width: 1.5,
                height: 30,
                displayValue: false,
                margin: 0,
                background: 'transparent'
            });
        } catch (e) {
            console.error('Barcode generation failed:', e);
            // Fallback: show text-based barcode
            generateTextBarcode(patientData.file_no);
        }
    } else {
        // Fallback: generate text-based barcode representation
        generateTextBarcode(patientData.file_no);
    }

    // Show modal
    $('#hospitalCardModal').modal('show');
}

function generateTextBarcode(code) {
    // Create a simple CSS-based barcode representation
    if (!code) return;

    const svg = document.getElementById('card-barcode');
    const width = 200;
    const height = 30;

    // Create simple bars based on character codes
    let bars = '';
    const barWidth = width / (code.length * 11 + 2);
    let x = barWidth;

    for (let i = 0; i < code.length; i++) {
        const charCode = code.charCodeAt(i);
        // Generate pattern based on character
        const pattern = charCode.toString(2).padStart(8, '0');

        for (let j = 0; j < pattern.length; j++) {
            if (pattern[j] === '1') {
                bars += `<rect x="${x}" y="0" width="${barWidth}" height="${height}" fill="#000"/>`;
            }
            x += barWidth;
        }
        x += barWidth; // Space between characters
    }

    svg.innerHTML = bars;
    svg.setAttribute('width', width);
    svg.setAttribute('height', height);
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
}

function printHospitalCard() {
    var activeTab = $('#cardViewTabs .nav-link.active').data('card-tab') || 'combined';
    var cardContent = '';
    if (activeTab === 'front') {
        cardContent = document.getElementById('hospital-card-preview').outerHTML;
    } else if (activeTab === 'back') {
        cardContent = document.getElementById('hospital-card-back').outerHTML;
    } else {
        cardContent = document.getElementById('hospital-card-container').innerHTML;
    }
    var pageHeight = (activeTab === 'combined') ? '120mm' : '60mm';
    const hosColor = '';
    const printWindow = window.open('', '_blank', 'width=500,height=700');

    // Use mm-based sizing for consistent print output (ISO ID-1 card: 85.6mm × 54mm)
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Hospital Patient Card</title>
            <style>
                @page {
                    size: 90mm ${pageHeight};
                    margin: 2mm;
                }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    margin: 0;
                    padding: 0;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                /* === CARD BASE === */
                .hospital-card {
                    width: 85.6mm;
                    height: 54mm;
                    background: #fff;
                    border-radius: 2.5mm;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    border: 0.3mm solid #ccc;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .hospital-card-back {
                    margin-top: 3mm;
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                }

                /* === FRONT HEADER === */
                .card-header-section {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 2mm 2.5mm;
                    display: flex;
                    align-items: center;
                    gap: 2mm;
                    flex-shrink: 0;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .hospital-logo-section { width: 9mm; height: 9mm; flex-shrink: 0; }
                .hospital-logo { width: 9mm; height: 9mm; object-fit: contain; background: white; border-radius: 1mm; padding: 0.5mm; }
                .hospital-logo-placeholder { width: 9mm; height: 9mm; background: rgba(255,255,255,0.2); border-radius: 1mm; display: flex; align-items: center; justify-content: center; font-size: 5mm; }
                .hospital-info-section { flex: 1; line-height: 1.2; }
                .hospital-name-text { font-size: 3mm; font-weight: 800; text-transform: uppercase; }
                .hospital-address-text { font-size: 2.2mm; font-weight: 600; opacity: 0.9; }
                .hospital-phone-text { font-size: 2.2mm; font-weight: 600; opacity: 0.9; }

                /* === FRONT BODY === */
                .card-body-section {
                    display: flex;
                    padding: 2mm 2.5mm;
                    gap: 2.5mm;
                    flex: 1;
                    min-height: 0;
                }
                .patient-photo-section { flex-shrink: 0; }
                .patient-photo {
                    width: 16mm;
                    height: 20mm;
                    object-fit: cover;
                    border-radius: 1.5mm;
                    border: 0.5mm solid ${hosColor};
                    background: #f0f0f0;
                }
                .patient-info-section { flex: 1; text-align: left; overflow: visible; }
                .patient-name-text {
                    font-size: 3.5mm;
                    font-weight: 800;
                    color: #222;
                    margin-bottom: 0.8mm;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    border-bottom: 0.2mm solid #ddd;
                    padding-bottom: 1mm;
                }
                .patient-details-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 1mm 2mm;
                }
                .detail-item { display: flex; flex-direction: column; }
                .detail-label { font-size: 2mm; font-weight: 700; color: #666; text-transform: uppercase; }
                .detail-value { font-size: 2.8mm; font-weight: 700; color: #222; white-space: nowrap; overflow: visible; }

                /* === BARCODE === */
                .card-barcode-section {
                    padding: 0.5mm 2.5mm;
                    text-align: center;
                    flex-shrink: 0;
                    background: #fff;
                }
                .card-barcode-section svg { height: 5mm; width: auto; max-width: 100%; }
                .barcode-number {
                    font-size: 2.5mm;
                    font-family: 'Courier New', monospace;
                    font-weight: 700;
                    color: #222;
                    letter-spacing: 0.5mm;
                }

                /* === FRONT FOOTER === */
                .card-footer-section {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 1mm 2.5mm;
                    flex-shrink: 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .card-type-badge { font-size: 2.2mm; font-weight: 800; letter-spacing: 0.3mm; }
                .powered-by { font-size: 1.8mm; font-weight: 600; opacity: 0.8; }

                /* === BACK CARD === */
                .card-back-header {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 1.5mm 2.5mm;
                    text-align: center;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .back-title { font-size: 3mm; font-weight: 800; letter-spacing: 0.3mm; }
                .card-back-body { padding: 2mm 2.5mm; flex: 1; }
                .back-info-row { display: flex; gap: 1.5mm; margin-bottom: 1mm; font-size: 2.5mm; }
                .back-info-row.full-width { flex-direction: column; gap: 0.3mm; }
                .back-label { font-weight: 700; color: #444; min-width: 13mm; }
                .back-value { color: #333; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .back-info-row.full-width .back-value { white-space: normal; font-size: 2.2mm; font-weight: 600; line-height: 1.3; }
                .back-divider { border-top: 0.2mm dashed #ccc; margin: 1.5mm 0; }
                .back-section-title {
                    font-size: 2.5mm;
                    font-weight: 800;
                    color: ${hosColor};
                    margin-bottom: 1mm;
                    text-transform: uppercase;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .card-back-footer {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 1mm 2.5mm;
                    text-align: center;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .emergency-note { font-size: 2.2mm; font-weight: 600; opacity: 0.9; }
                .powered-by-back { font-size: 1.8mm; font-weight: 600; opacity: 0.7; margin-top: 0.5mm; }
            </style>
        </head>
        <body>
            ${cardContent}
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

// =============================================
// APPOINTMENT SCHEDULING ENHANCEMENTS
// =============================================

// Toggle schedule fields visibility
$('input[name="booking_type"]').on('change', function() {
    var isSchedule = $(this).val() === 'schedule';
    $('#schedule-fields').toggle(isSchedule);
    var $btn = $('#btn-book-consultation');
    if (isSchedule) {
        $btn.html('<i class="mdi mdi-calendar-plus"></i> Schedule Appointment');
        $btn.removeClass('btn-primary').addClass('btn-purple');
    } else {
        $btn.html('<i class="mdi mdi-send"></i> Send to Queue');
        $btn.removeClass('btn-purple').addClass('btn-primary');
    }
});

// Load available slots when date/clinic/doctor changes
$('#booking-appointment-date').on('change', function() {
    loadAvailableSlots();
});

// Custom time toggle
$('#booking-custom-time-toggle').on('change', function() {
    if ($(this).is(':checked')) {
        $('#booking-appointment-time').hide();
        $('#booking-appointment-time-manual').show().val('');
        $('#custom-time-hint').remove();
    } else {
        $('#booking-appointment-time-manual').hide().val('');
        $('#booking-appointment-time').show();
        $('#custom-time-hint').remove();
    }
});

function getBookingTime() {
    if ($('#booking-custom-time-toggle').is(':checked')) {
        return $('#booking-appointment-time-manual').val();
    }
    return $('#booking-appointment-time').val();
}

function loadAvailableSlots() {
    var date = $('#booking-appointment-date').val();
    var clinicId = $('#booking-clinic').val();
    var doctorId = $('#booking-doctor').val();

    if (!date || !clinicId) {
        $('#booking-appointment-time').empty().append('<option value="">-- Select date & clinic first --</option>');
        return;
    }

    // Remove any previous hint
    $('#custom-time-hint').remove();

    $.get(wbRoute('appointments.available-slots', '/appointments/available-slots'), {
        date: date,
        clinic_id: clinicId,
        doctor_id: doctorId
    }, function(response) {
        var $select = $('#booking-appointment-time');
        $select.empty().append('<option value="">-- Select Time --</option>');

        var availableCount = 0;
        if (response.success && response.slots && response.slots.length> 0) {
            response.slots.forEach(function(slot) {
                if (slot.available) {
                    $select.append('<option value="' + slot.time + '">' + slot.time + '</option>');
                    availableCount++;
                } else {
                    // Show booked slots as disabled to give full schedule visibility
                    $select.append('<option value="' + slot.time + '" disabled class="text-muted">' + slot.time + ' (booked)</option>');
                }
            });
        }

        // Auto-suggest custom time when no slots are available
        if (availableCount === 0) {
            $select.empty().append('<option value="" disabled>No preset slots available</option>');

            // Auto-enable custom time
            $('#booking-custom-time-toggle').prop('checked', true).trigger('change');

            // Show helpful hint
            var hintHtml = '<div id="custom-time-hint" class="alert alert-info py-1 px-2 mt-1 mb-0 small">' +
                '<i class="mdi mdi-information-outline"></i> ' +
                'No preset slots available for this date. Enter a custom time below.' +
                '</div>';
            $('#booking-appointment-time-manual').after(hintHtml);
        } else {
            // If custom time was auto-enabled, reset back to dropdown
            // (only if user didn't manually check it)
            if ($('#booking-custom-time-toggle').data('auto-enabled')) {
                $('#booking-custom-time-toggle').prop('checked', false).trigger('change');
                $('#booking-custom-time-toggle').removeData('auto-enabled');
            }
        }

        // Track that it was auto-enabled
        if (availableCount === 0) {
            $('#booking-custom-time-toggle').data('auto-enabled', true);
        }

    }).fail(function() {
        $('#booking-appointment-time').empty().append('<option value="">Error loading slots</option>');
    });
}

// ─── Queue filter handlers for new items ─────────────
$(document).on('click', '.queue-item[data-filter="appointments-today"]', function() {
    showAppointmentsCalendarView();
});

$(document).on('click', '.queue-item[data-filter="referrals"]', function() {
    showReferralsQueueView();
});

// HMO Pending Validation queue item click
$(document).on('click', '.queue-item[data-filter="hmo-pending-validation"]', function() {
    showHmoValidationPanel();
});

// Close HMO validation panel
$('#btn-close-hmo-validation').on('click', function() {
    $('#hmo-validation-view').removeClass('active').hide();
    if (currentPatient) {
        $('#workspace-content').addClass('active').show();
        $('#patient-header').addClass('active');
    } else {
        $('#empty-state').show();
    }
});

// Refresh HMO validation panel
$('#btn-hmo-refresh-validation').on('click', function() {
    loadHmoValidationList();
});

// Search filter
$('#hmo-validation-search').on('keyup', _.debounce(function() {
    loadHmoValidationList();
}, 300));

// Select all checkbox
$('#hmo-select-all').on('change', function() {
    var checked = $(this).prop('checked');
    $('#hmo-validation-body .hmo-row-check').prop('checked', checked);
    updateHmoBatchCount();
});

// Row checkbox change
$(document).on('change', '.hmo-row-check', function() {
    updateHmoBatchCount();
});

// ── HMO Validate Confirm Modal helpers ──────────────────────────
var _hvcMode = null;   // 'single' | 'batch'
var _hvcId = null;     // single request id
var _hvcRow = null;    // single row jQuery element
var _hvcIds = [];      // batch ids

// Single validate button → open confirm modal
$(document).on('click', '.hmo-validate-btn', function() {
    var btn = $(this);
    _hvcMode = 'single';
    _hvcId = btn.data('id');
    _hvcRow = btn.closest('tr');
    _hvcIds = [];

    // Populate modal
    $('#hvc-title').text('Confirm Validation');
    $('#hvc-patient').text(btn.data('patient'));
    $('#hvc-file-no').text(btn.data('fileno'));
    $('#hvc-hmo').text(btn.data('hmo'));
    $('#hvc-item').text(btn.data('item'));
    var coverage = btn.data('coverage');
    $('#hvc-coverage').html(coverage === 'primary'
        ? '<span class="badge badge-warning">PRIMARY</span>'
        : '<span class="badge badge-danger">SECONDARY</span>');
    $('#hvc-amount').text('₦' + Number(btn.data('amount')).toLocaleString());
    $('#hvc-outcome-info').html(coverage === 'secondary'
        ? '<i class="mdi mdi-information-outline"></i> Secondary coverage — will be set to <strong>Awaiting Auth Code</strong>.'
        : '<i class="mdi mdi-information-outline"></i> Primary coverage — will be <strong>Approved</strong> immediately.');

    $('#hvc-single-details').show();
    $('#hvc-batch-details').hide();
    $('#hvc-notes').val('');
    $('#hvc-confirm-btn').prop('disabled', false).html('<i class="mdi mdi-check"></i> Confirm Validation');
    $('#hmoValidateConfirmModal').modal('show');
});

// Batch validate → open confirm modal
$('#btn-hmo-batch-validate').on('click', function() {
    _hvcIds = [];
    $('#hmo-validation-body .hmo-row-check:checked').each(function() {
        _hvcIds.push($(this).data('id'));
    });
    if (_hvcIds.length === 0) return;

    _hvcMode = 'batch';
    _hvcId = null;
    _hvcRow = null;

    $('#hvc-title').text('Confirm Batch Validation');
    $('#hvc-batch-total').text(_hvcIds.length);
    $('#hvc-single-details').hide();
    $('#hvc-batch-details').show();
    $('#hvc-notes').val('');
    $('#hvc-confirm-btn').prop('disabled', false).html('<i class="mdi mdi-check-all"></i> Confirm Validation');
    $('#hmoValidateConfirmModal').modal('show');
});

// Confirm button inside modal → execute
$('#hvc-confirm-btn').on('click', function() {
    var confirmBtn = $(this);
    var notes = $('#hvc-notes').val();
    confirmBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    if (_hvcMode === 'single' && _hvcId) {
        $.ajax({
        url: wbUrl('reception/hmo-validate/' + _hvcId),
            method: 'POST',
            data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), notes: notes },
            success: function(resp) {
                $('#hmoValidateConfirmModal').modal('hide');
                if (resp.success) {
                    if (_hvcRow) _hvcRow.fadeOut(400, function() { $(this).remove(); });
                    toastr.success(resp.message);
                    loadQueueCounts();
                    updateHmoBatchCount();
                } else {
                    toastr.warning(resp.message);
                }
            },
            error: function(xhr) {
                $('#hmoValidateConfirmModal').modal('hide');
                toastr.error(xhr.responseJSON?.message || 'Validation failed');
            }
        });
    } else if (_hvcMode === 'batch' && _hvcIds.length) {
        $.ajax({
            url: wbRoute('reception.hmo-batch-validate', '/reception/hmo-batch-validate'),
            method: 'POST',
            data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), ids: _hvcIds, notes: notes },
            success: function(resp) {
                $('#hmoValidateConfirmModal').modal('hide');
                if (resp.success) {
                    toastr.success(resp.message);
                    loadHmoValidationList();
                    loadQueueCounts();
                } else {
                    toastr.warning(resp.message);
                }
            },
            error: function(xhr) {
                $('#hmoValidateConfirmModal').modal('hide');
                toastr.error(xhr.responseJSON?.message || 'Batch validation failed');
            }
        });
    }
});

function showHmoValidationPanel() {
    hideAllViews();
    $('#hmo-validation-view').show().addClass('active');
    $('.queue-item').removeClass('active');
    $('.queue-item[data-filter="hmo-pending-validation"]').addClass('active');
    loadHmoValidationList();
}

function loadHmoValidationList() {
    var search = $('#hmo-validation-search').val() || '';
    $('#hmo-validation-body').html('<tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> Loading...</td></tr>');

    $.get(wbRoute('reception.hmo-pending-validation', '/reception/hmo-pending-validation'), { search: search }, function(resp) {
        var data = resp.data || [];
        $('#hmo-validation-total').text(data.length + ' pending');
        $('#hmo-select-all').prop('checked', false);

        if (data.length === 0) {
            $('#hmo-validation-body').html('<tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-check-circle text-success"></i> No pending HMO requests</td></tr>');
            return;
        }

        var html = '';
        data.forEach(function(r) {
            var coverageBadge = r.coverage_mode === 'primary'
                ? '<span class="badge badge-warning">PRIMARY</span>'
                : '<span class="badge badge-danger">SECONDARY</span>';
            var hours = r.hours_pending || 0;
            var slaBadge = hours < 2
                ? '<span class="badge badge-success">' + hours + 'h</span>'
                : (hours < 4 ? '<span class="badge badge-warning">' + hours + 'h</span>' : '<span class="badge badge-danger">' + hours + 'h</span>');

            html += '<tr>';
            html += '<td><input type="checkbox" class="hmo-row-check" data-id="' + r.id + '" style="transform:scale(1.3);cursor:pointer;"></td>';
            html += '<td><strong>' + r.patient_name + '</strong><br><small class="text-muted">' + r.file_no + '</small></td>';
            html += '<td><small>' + r.hmo_name + '</small>' + (r.hmo_no ? '<br><small class="text-info">HMO#: ' + r.hmo_no + '</small>' : '') + '</td>';
            html += '<td><span class="badge badge-' + (r.item_type === 'Product' ? 'success' : 'info') + '">' + r.item_type + '</span><br><small>' + r.item_name + '</small></td>';
            html += '<td>' + coverageBadge + '</td>';
            html += '<td><strong>₦' + Number(r.claims_amount).toLocaleString() + '</strong></td>';
            html += '<td>' + slaBadge + '<br><small class="text-muted">' + (r.created_at || '') + '</small></td>';
            html += '<td><button class="btn btn-sm btn-success hmo-validate-btn" data-id="' + r.id + '"' + ' data-patient="' + (r.patient_name || '').replace(/"/g,'&quot;') + '"' + ' data-fileno="' + (r.file_no || '') + '"' + ' data-hmo="' + (r.hmo_name || '').replace(/"/g,'&quot;') + '"' + ' data-item="' + (r.item_name || '').replace(/"/g,'&quot;') + '"' + ' data-coverage="' + (r.coverage_mode || '') + '"' + ' data-amount="' + (r.claims_amount || 0) + '"' + '><i class="mdi mdi-check"></i> Validate</button></td>';
            html += '</tr>';
        });

        $('#hmo-validation-body').html(html);
    }).fail(function() {
        $('#hmo-validation-body').html('<tr><td colspan="8" class="text-center text-danger py-4">Failed to load requests</td></tr>');
    });
}

function updateHmoBatchCount() {
    var count = $('#hmo-validation-body .hmo-row-check:checked').length;
    $('#hmo-batch-count').text(count);
    $('#btn-hmo-batch-validate').prop('disabled', count === 0);
}

var appointmentsDataTable = null;
var appointmentsGlobalDataTable = null;
var referralsDataTable = null;
var appointmentsCalendar = null;
var currentApptCalendarView = 'calendar'; // 'calendar' or 'table'

