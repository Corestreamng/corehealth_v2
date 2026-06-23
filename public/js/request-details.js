function showRequestDetails(type, id) {
    // Reset modal
    $('#request-details-loading').show();
    $('#request-details-content').hide();

    // Set header color based on type
    const headerClass = type + '-header';
    $('#request-details-header').removeClass('lab-header imaging-header product-header').addClass(headerClass);

    // Update title icon
    const icons = {
        'lab': 'mdi-test-tube',
        'imaging': 'mdi-x-ray',
        'product': 'mdi-pill',
        'service': 'mdi-stethoscope'
    };
    $('#request-details-title').html(`<i class="mdi ${icons[type] || 'mdi-clipboard-text'}"></i> Request Details`);

    // Show modal
    $('#requestDetailsModal').modal('show');

    // Fetch details
    $.ajax({
        url: `/shared/request/${type}/${id}/details`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.details) {
                populateRequestDetails(response.details);
                $('#request-details-loading').hide();
                $('#request-details-content').show();
            } else {
                toastr.error('Failed to load request details');
                $('#requestDetailsModal').modal('hide');
            }
        },
        error: function(xhr) {
            toastr.error('Failed to load request details');
            $('#requestDetailsModal').modal('hide');
        }
    });
}

function populateRequestDetails(details) {
    // Request number and type badge
    $('#detail-request-no').text(details.request_no);

    const typeBadgeColors = {
        'lab': 'badge-info',
        'imaging': 'badge-warning',
        'product': 'badge-success',
        'service': 'badge-primary'
    };
    $('#detail-type-badge').removeClass('badge-info badge-warning badge-success badge-primary badge-secondary')
        .addClass(typeBadgeColors[details.type] || 'badge-secondary')
        .text(details.type_label);

    // Billing & Delivery badges
    const billingBadgeClass = getBillingStatusClass(details.billing_status);
    const deliveryBadgeClass = getDeliveryStatusClass(details.delivery_status);
    $('#detail-billing-badge').html(`<span class="billing-badge ${billingBadgeClass}">${details.billing_status}</span>`);
    $('#detail-delivery-badge').html(`<span class="delivery-badge ${deliveryBadgeClass}">${details.delivery_status}</span>`);

    // Requested at
    $('#detail-requested-at').text('Requested: ' + details.requested_at);

    // Service/Product info
    if (details.type === 'product') {
        $('#detail-info-title').text('Product Information');
        $('#detail-item-name').text(details.product_name);
        $('#detail-item-category').text(details.product_category);
        $('#detail-dose-section').toggle(!!details.dose);
        $('#detail-dose').text(details.dose || '');
        $('#detail-quantity-section').show();
        $('#detail-quantity').text(details.quantity);
        $('#detail-unit-price').text(numberFormat(details.unit_price));
    } else {
        $('#detail-info-title').text('Service Information');
        $('#detail-item-name').text(details.service_name);
        $('#detail-item-category').text(details.service_category);
        $('#detail-dose-section').hide();
        $('#detail-quantity-section').hide();
    }

    // Pricing
    $('#detail-price').text('₦' + numberFormat(details.price));
    $('#detail-hmo-row').toggle(details.hmo_covers > 0);
    $('#detail-hmo-covers').text('-₦' + numberFormat(details.hmo_covers));
    $('#detail-payable').text('₦' + numberFormat(details.payable));

    // Clinical note
    if (details.clinical_note) {
        $('#detail-note-card').show();
        $('#detail-clinical-note').text(details.clinical_note);
    } else {
        $('#detail-note-card').hide();
    }

    // Build timeline
    buildRequestTimeline(details);

    // Result section (lab/imaging only)
    if (details.type === 'lab' || details.type === 'imaging') {
        $('#detail-result-card').show();
        if (details.has_result) {
            $('#detail-result-content').show();
            $('#detail-no-result').hide();
            $('#detail-result-summary').text(details.result_summary || 'Result available - view in ' + details.type_label + ' workbench');
        } else {
            $('#detail-result-content').hide();
            $('#detail-no-result').show();
        }
    } else {
        $('#detail-result-card').hide();
    }

    // Payment info
    if (details.payment_reference) {
        $('#detail-payment-card').show();
        $('#detail-payment-ref').text(details.payment_reference);
        $('#detail-payment-date').text(details.payment_date);
    } else {
        $('#detail-payment-card').hide();
    }
}

function buildRequestTimeline(details) {
    let timelineHtml = '';

    // 1. Request Created
    timelineHtml += `
        <div class="timeline-item completed">
            <div class="timeline-title"><i class="mdi mdi-plus-circle text-primary"></i> Request Created</div>
            <div class="timeline-subtitle">${details.requested_by}</div>
            <div class="timeline-meta">${details.requested_at}</div>
        </div>
    `;

    // 2. Billing step
    if (details.billing_status_code === 'billed' || details.billing_status_code === 'paid') {
        timelineHtml += `
            <div class="timeline-item completed">
                <div class="timeline-title"><i class="mdi mdi-receipt text-info"></i> Billed</div>
                <div class="timeline-subtitle">${details.billed_by || 'System'}</div>
                <div class="timeline-meta">${details.billed_at || '-'}</div>
            </div>
        `;
    } else {
        timelineHtml += `
            <div class="timeline-item pending">
                <div class="timeline-title"><i class="mdi mdi-receipt text-muted"></i> Awaiting Billing</div>
                <div class="timeline-subtitle text-muted">Not yet billed</div>
            </div>
        `;
    }

    // 3. Payment step (if applicable)
    if (details.billing_status_code === 'paid') {
        timelineHtml += `
            <div class="timeline-item completed">
                <div class="timeline-title"><i class="mdi mdi-cash-check text-success"></i> Paid</div>
                <div class="timeline-subtitle">${details.payment_reference || 'Payment received'}</div>
                <div class="timeline-meta">${details.payment_date || '-'}</div>
            </div>
        `;
    } else if (details.billing_status_code === 'billed') {
        timelineHtml += `
            <div class="timeline-item in-progress">
                <div class="timeline-title"><i class="mdi mdi-cash text-warning"></i> Awaiting Payment</div>
                <div class="timeline-subtitle text-muted">Patient to pay</div>
            </div>
        `;
    }

    // Type-specific steps
    if (details.type === 'lab') {
        // Sample collection step
        if (details.sample_taken) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-test-tube text-info"></i> Sample Collected</div>
                    <div class="timeline-subtitle">${details.sample_taken_by || 'Lab Staff'}</div>
                    <div class="timeline-meta">${details.sample_date || '-'}</div>
                </div>
            `;
        } else if (details.billing_status_code === 'paid' || details.billing_status_code === 'billed') {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-test-tube text-muted"></i> Awaiting Sample</div>
                    <div class="timeline-subtitle text-muted">Sample not yet collected</div>
                </div>
            `;
        }

        // Results step
        if (details.has_result) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-file-document text-success"></i> Result Available</div>
                    <div class="timeline-subtitle">${details.result_by || 'Lab Scientist'}</div>
                    <div class="timeline-meta">${details.result_date || '-'}</div>
                </div>
            `;
        } else if (details.sample_taken) {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-file-document text-muted"></i> Awaiting Results</div>
                    <div class="timeline-subtitle text-muted">Processing in lab</div>
                </div>
            `;
        }
    } else if (details.type === 'imaging') {
        // Results step
        if (details.has_result) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-file-image text-success"></i> Result Available</div>
                    <div class="timeline-subtitle">${details.result_by || 'Radiologist'}</div>
                    <div class="timeline-meta">${details.result_date || '-'}</div>
                </div>
            `;

            if (details.has_attachments && details.attachment_count > 0) {
                timelineHtml += `
                    <div class="timeline-item completed">
                        <div class="timeline-title"><i class="mdi mdi-image-multiple text-info"></i> Images Attached</div>
                        <div class="timeline-subtitle">${details.attachment_count} image(s) uploaded</div>
                    </div>
                `;
            }
        } else if (details.billing_status_code === 'paid' || details.billing_status_code === 'billed') {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-file-image text-muted"></i> Awaiting Results</div>
                    <div class="timeline-subtitle text-muted">Processing in imaging</div>
                </div>
            `;
        }
    } else if (details.type === 'product') {
        // Dispensing step
        if (details.dispensed_by) {
            timelineHtml += `
                <div class="timeline-item completed">
                    <div class="timeline-title"><i class="mdi mdi-pill text-success"></i> Dispensed</div>
                    <div class="timeline-subtitle">${details.dispensed_by}</div>
                    <div class="timeline-meta">${details.dispense_date || '-'}</div>
                </div>
            `;
        } else if (details.billing_status_code === 'paid' || details.billing_status_code === 'billed') {
            timelineHtml += `
                <div class="timeline-item in-progress">
                    <div class="timeline-title"><i class="mdi mdi-pill text-muted"></i> Awaiting Dispensing</div>
                    <div class="timeline-subtitle text-muted">Ready for pickup</div>
                </div>
            `;
        }
    }

    $('#detail-timeline').html(timelineHtml);
}

function getBillingStatusClass(status) {
    status = (status || '').toLowerCase();
    if (status.includes('paid')) return 'billing-paid';
    if (status.includes('billed')) return 'billing-billed';
    return 'billing-pending';
}

function getDeliveryStatusClass(status) {
    status = (status || '').toLowerCase();
    if (status.includes('completed') || status.includes('resulted') || status.includes('dispensed')) return 'delivery-completed';
    if (status.includes('progress') || status.includes('sample')) return 'delivery-progress';
    return 'delivery-pending';
}

function numberFormat(number) {
    if (number === null || number === undefined) return '0.00';
    return parseFloat(number).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Global click listener for view request button
$(document).on('click', '.view-request-btn', function() {
    const id = $(this).data('id');
    const type = $(this).data('type');
    if (id && type) {
        showRequestDetails(type, id);
    }
});
