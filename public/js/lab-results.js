function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('clinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('📊 Clinical Context ×');
    }
}

// Action handlers for lab requests
function recordBilling(requestIds) {
    $.ajax({
        url: wbRoute('lab.recordBilling', '/lab-workbench/record-billing'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            request_ids: requestIds,
            patient_id: currentPatient
        },
        beforeSend: function() {
            toastr.info(`Recording billing for ${requestIds.length} item(s)...`);
        },
        success: function(response) {
            toastr.success('Billing recorded successfully!');
            $('#cartReviewModal').modal('hide');
            $('#floating-cart').fadeOut(200);
            clearCheckedItems('billing');
            loadPatient(currentPatient);
        },
        error: function(xhr) {
            toastr.error('Error recording billing: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function collectSample(requestIds) {
    // Store IDs for the modal confirm action
    window._pendingSampleIds = requestIds;

    // Reset modal state
    $('#sample_lab_number').val('').prop('readonly', true);
    $('#labNumberError').addClass('d-none').text('');
    $('#labNumberDuplicateWarning').addClass('d-none').text('');
    $('#labNumberFormatHint').text('');
    $('#recentLabNumbersBox').addClass('d-none');
    $('#btnConfirmCollectSample').prop('disabled', true);

    // Build items preview list
    let itemsHtml = '';
    requestIds.forEach(function(id) {
        let card = $(`.request-card[data-request-id="${id}"]`);
        let name = card.find('.request-service-name').text() || `Request #${id}`;
        itemsHtml += `<div class="d-flex align-items-center gap-2 py-1 border-bottom">
            <i class="mdi mdi-flask-outline text-info"></i>
            <span class="small">${name}</span>
        </div>`;
    });
    $('#sampleItemsList').html(itemsHtml);
    $('#sampleItemCount').text(requestIds.length);

    // Auto-generate lab number
    autoGenerateLabNumber();

    // Show modal
    $('#sampleCollectionModal').modal('show');
}

// Auto-generate next lab number from server
function autoGenerateLabNumber() {
    $('#sample_lab_number').prop('readonly', true);
    $('#btnConfirmCollectSample').prop('disabled', true);
    $('#labNumberError').addClass('d-none');
    $('#labNumberDuplicateWarning').addClass('d-none');

    $.ajax({
        url: wbRoute('lab.nextLabNumber', '/lab-workbench/next-lab-number'),
        method: 'GET',
        success: function(data) {
            $('#sample_lab_number').val(data.lab_number);
            $('#labNumberFormatHint').text('Format: ' + (data.format_example || data.lab_number));
            $('#btnConfirmCollectSample').prop('disabled', false);

            // Show recent lab numbers
            if (data.recent_lab_numbers && data.recent_lab_numbers.length> 0) {
                let recentHtml = '';
                data.recent_lab_numbers.forEach(function(num) {
                    recentHtml += `<span class="badge bg-light text-dark border">${num}</span>`;
                });
                $('#recentLabNumbersList').html(recentHtml);
                $('#recentLabNumbersBox').removeClass('d-none');
            }
        },
        error: function(xhr) {
            $('#labNumberError').removeClass('d-none').text('Could not generate lab number. Please enter manually.');
            $('#sample_lab_number').prop('readonly', false);
        }
    });
}

// Confirm sample collection from modal
function confirmCollectSample() {
    let labNumber = $('#sample_lab_number').val().trim();
    if (!labNumber) {
        $('#labNumberError').removeClass('d-none').text('Lab number is required.');
        return;
    }


    let requestIds = window._pendingSampleIds || [];
    if (requestIds.length === 0) return;

    $('#btnConfirmCollectSample').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Collecting...');

    $.ajax({
        url: wbRoute('lab.collectSample', '/lab-workbench/collect-sample'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            request_ids: requestIds,
            patient_id: currentPatient,
            lab_number: labNumber
        },
        success: function(response) {
            toastr.success('Sample collection recorded — Lab# ' + labNumber);
            $('#sampleCollectionModal').modal('hide');
            $('#cartReviewModal').modal('hide');
            $('#floating-cart').fadeOut(200);
            clearCheckedItems('sample');
            loadPatient(currentPatient);
        },
        error: function(xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : (xhr.status === 403 ? 'Service requires HMO approval or authorization before sample collection.' : 'Error recording sample');
            toastr.error(msg);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sample Collection Blocked',
                    text: msg,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6'
                });
            }
            $('#btnConfirmCollectSample').prop('disabled', false).html('<i class="mdi mdi-test-tube"></i> Collect Sample');
        }
    });
}

function dismissRequests(requestIds, section) {
    $.ajax({
        url: wbRoute('lab.dismissRequests', '/lab-workbench/dismiss-requests'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            request_ids: requestIds,
            patient_id: currentPatient
        },
        beforeSend: function() {
            toastr.info(`Dismissing ${requestIds.length} request(s)...`);
        },
        success: function(response) {
            toastr.success('Requests dismissed successfully!');
            $('#cartReviewModal').modal('hide');
            $('#floating-cart').fadeOut(200);
            clearCheckedItems();
            loadPatient(currentPatient);
        },
        error: function(xhr) {
            toastr.error('Error dismissing requests: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function enterResult(requestId) {
    window._investResultContext = { type: 'lab', id: requestId };
    InvestResultEntry.enterResult(requestId,
        '/lab-workbench/lab-service-requests/' + requestId,
        '/lab-workbench/lab-service-requests/' + requestId + '/attachments');
}
window.enterLabResult = enterResult;

// ── Floating Cart Logic ──────────────────────────────────────────────
function getSelectedItems() {
    const items = { billing: [], sample: [] };

    $('.request-checkbox[data-section="billing"]:checked').each(function() {
        const $card = $(this).closest('.request-card');
        items.billing.push({
            id: $(this).data('request-id'),
            name: $card.find('.request-service-name').text().trim() || 'Unknown Service',
            doctor: $card.find('.request-meta-item:first span').text().trim(),
            date: $card.find('.request-meta-item:last span').text().trim(),
            price: parseFloat($(this).data('price')) || 0
        });
    });

    $('.request-checkbox[data-section="sample"]:checked').each(function() {
        const $card = $(this).closest('.request-card');
        items.sample.push({
            id: $(this).data('request-id'),
            name: $card.find('.request-service-name').text().trim() || 'Unknown Service',
            doctor: $card.find('.request-meta-item:first span').text().trim(),
            date: $card.find('.request-meta-item:last span').text().trim(),
            price: parseFloat($(this).data('price')) || 0
        });
    });

    return items;
}

function updateFloatingCart() {
    const items = getSelectedItems();
    const totalCount = items.billing.length + items.sample.length;

    if (totalCount === 0) {
        $('#floating-cart').fadeOut(200);
        return;
    }


    const totalPrice = [...items.billing, ...items.sample].reduce((sum, i) => sum + i.price, 0);
    $('#cart-item-count').text(totalCount);
    if (totalPrice> 0) {
        $('#cart-total-display').text('₦' + Number(totalPrice).toLocaleString()).show();
    } else {
        $('#cart-total-display').hide();
    }
    $('#floating-cart').fadeIn(300);
    $('.floating-cart-btn').addClass('pulse');
    setTimeout(() => $('.floating-cart-btn').removeClass('pulse'), 300);
}

function openCartReviewModal() {
    const items = getSelectedItems();
    const totalCount = items.billing.length + items.sample.length;
    $('#modal-cart-count').text(totalCount);

    let html = '';

    if (totalCount === 0) {
        html = `<div class="cart-empty">
            <i class="mdi mdi-cart-outline"></i>
            <p>No items selected</p>
            <p class="small">Check items in the queue, then open the cart</p>
        </div>`;
    } else {
        // Billing section
        if (items.billing.length> 0) {
            const billingTotal = items.billing.reduce((sum, i) => sum + i.price, 0);
            html += `<div class="cart-section">
                <div class="cart-section-header">
                    <span><i class="mdi mdi-cash-register text-success"></i> Awaiting Billing</span>
                    <span class="badge bg-success">${items.billing.length}</span>
                </div>`;
            items.billing.forEach(item => {
                html += `<div class="cart-item">
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta"><i class="mdi mdi-doctor"></i> ${item.doctor} &middot; ${item.date}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        ${item.price> 0 ? `<span class="cart-item-price">₦${Number(item.price).toLocaleString()}</span>` : ''}
                        <button class="cart-item-remove" onclick="uncheckItem(${item.id}, 'billing')" title="Remove">
                            <i class="mdi mdi-close-circle"></i>
                        </button>
                    </div>
                </div>`;
            });
            if (billingTotal> 0) {
                html += `<div class="cart-section-total text-end small text-muted pe-2">Subtotal: <strong>₦${Number(billingTotal).toLocaleString()}</strong></div>`;
            }
            html += `<div class="cart-action-row">
                <button class="btn btn-success btn-sm" onclick="cartRecordBilling()">
                    <i class="mdi mdi-check-circle"></i> Record Billing (${items.billing.length})
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="cartDismiss('billing')">
                    <i class="mdi mdi-close-circle"></i> Dismiss (${items.billing.length})
                </button>
            </div></div>`;
        }

        // Sample section
        if (items.sample.length> 0) {
            html += `<div class="cart-section">
                <div class="cart-section-header">
                    <span><i class="mdi mdi-test-tube text-info"></i> Sample Collection</span>
                    <span class="badge bg-info">${items.sample.length}</span>
                </div>`;
            items.sample.forEach(item => {
                html += `<div class="cart-item">
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta"><i class="mdi mdi-doctor"></i> ${item.doctor} &middot; ${item.date}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        ${item.price> 0 ? `<span class="cart-item-price">₦${Number(item.price).toLocaleString()}</span>` : ''}
                        <button class="cart-item-remove" onclick="uncheckItem(${item.id}, 'sample')" title="Remove">
                            <i class="mdi mdi-close-circle"></i>
                        </button>
                    </div>
                </div>`;
            });
            html += `<div class="cart-action-row">
                <button class="btn btn-info btn-sm text-white" onclick="cartCollectSample()">
                    <i class="mdi mdi-check-circle"></i> Collect Sample (${items.sample.length})
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="cartDismiss('sample')">
                    <i class="mdi mdi-close-circle"></i> Dismiss (${items.sample.length})
                </button>
            </div></div>`;
        }
    }

    $('#cart-review-body').html(html);
    $('#cartReviewModal').modal('show');
}

function uncheckItem(requestId, section) {
    $(`.request-checkbox[data-section="${section}"][data-request-id="${requestId}"]`).prop('checked', false).trigger('change');
    // Re-render the modal
    openCartReviewModal();
}

function cartRecordBilling() {
    const ids = $('.request-checkbox[data-section="billing"]:checked').map(function() {
        return $(this).data('request-id');
    }).get();
    if (ids.length> 0) recordBilling(ids);
}

function cartCollectSample() {
    const ids = $('.request-checkbox[data-section="sample"]:checked').map(function() {
        return $(this).data('request-id');
    }).get();
    if (ids.length> 0) collectSample(ids);
}

function cartDismiss(section) {
    const ids = $(`.request-checkbox[data-section="${section}"]:checked`).map(function() {
        return $(this).data('request-id');
    }).get();
    if (ids.length> 0) dismissRequests(ids, section);
}
// ── End Floating Cart Logic ──────────────────────────────────────────

// ── Result Entry (delegates to shared InvestResultEntry module) ──────
function setResTempInModal(request) {
    // Kept as a thin wrapper for backward compat — enterResult already calls the module
    InvestResultEntry.enterResult(request.id,
        '/lab-workbench/lab-service-requests/' + request.id,
        '/lab-workbench/lab-service-requests/' + request.id + '/attachments');
}

function editLabResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(requestId,
        '/lab-workbench/lab-service-requests/' + requestId,
        '/lab-workbench/lab-service-requests/' + requestId + '/attachments');
}
// ── End Result Entry ─────────────────────────────────────────────────

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
        toastr.warning('Please provide a detailed reason (minimum 10 characters)');
        return;
    }


    $.ajax({
        url: wbUrl(`/lab-workbench/lab-service-requests/${deleteRequestId}`),
        method: 'DELETE',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            reason: reason
        },
        success: function(response) {
            $('#deleteReasonModal').modal('hide');
            toastr.success(response.message || 'Request deleted successfully');

            // Reload patient data if we're on a patient
            if (currentPatient) {
                loadPatient(currentPatient);
            }

            // Refresh trash panel if it's open
            if ($('#trashPanel').is(':visible')) {
                loadTrashData();
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to delete request');
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
        toastr.warning('Please provide a detailed reason (minimum 10 characters)');
        return;
    }


    $.ajax({
        url: wbUrl(`/lab-workbench/lab-service-requests/${dismissRequestId}/dismiss`),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            reason: reason
        },
        success: function(response) {
            $('#dismissReasonModal').modal('hide');
            toastr.success(response.message || 'Request dismissed successfully');

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }

            // Refresh trash panel
            if ($('#trashPanel').is(':visible')) {
                loadTrashData();
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to dismiss request');
        }
    });
});

// Restore Request (from deleted or dismissed)
function restoreRequest(requestId, type) {
    const url = type === 'deleted'
        ? `/lab-workbench/lab-service-requests/${requestId}/restore`
        : `/lab-workbench/lab-service-requests/${requestId}/undismiss`;

    toastr.info('Restoring request...');
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))
        },
        success: function(response) {
            toastr.success(response.message || 'Request restored successfully');

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }

            // Refresh trash panel
            loadTrashData();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to restore request');
        }
    });
}

// Trash Panel Management
$('#openTrashPanel').on('click', function() {
    $('#trashPanel').fadeIn(300);
    loadTrashData();
});

$('#closeTrashPanel').on('click', function() {
    $('#trashPanel').fadeOut(300);
});

$('.trash-tab').on('click', function() {
    const tab = $(this).data('trash-tab');
    $('.trash-tab').removeClass('active');
    $(this).addClass('active');
    $('.trash-tab-content').removeClass('active');
    $(`#${tab}-content`).addClass('active');
});

function loadTrashData() {
    const patientId = currentPatient || null;

    // Load dismissed requests
    $.ajax({
        url: wbUrl(`/lab-workbench/dismissed-requests/${patientId || ''}`),
        method: 'GET',
        success: function(data) {
            $('#dismissed-count').text(data.length);
            updateTrashTotalCount();

            // Check if DataTables is loaded
            if (typeof $.fn.DataTable === 'undefined') {
                console.error('DataTables library is not loaded');
                $('#dismissed-table').html('<tr><td class="text-danger">Error: DataTables library not loaded</td></tr>');
                return;
            }

            if ($.fn.DataTable.isDataTable('#dismissed-table')) {
                $('#dismissed-table').DataTable().destroy();
            }

            $('#dismissed-table').DataTable({
                data: data,
                columns: [{
                    data: null,
                    render: function(data) {
                        return createTrashCard(data, 'dismissed');
                    }
                }],
                ordering: false,
                searching: true,
                pageLength: 10,
                language: {
                    emptyTable: "No dismissed requests found"
                }
            });
        }
    });

    // Load deleted requests
    $.ajax({
        url: wbUrl(`/lab-workbench/deleted-requests/${patientId || ''}`),
        method: 'GET',
        success: function(data) {
            $('#deleted-count').text(data.length);
            updateTrashTotalCount();

            // Check if DataTables is loaded
            if (typeof $.fn.DataTable === 'undefined') {
                console.error('DataTables library is not loaded');
                $('#deleted-table').html('<tr><td class="text-danger">Error: DataTables library not loaded</td></tr>');
                return;
            }

            if ($.fn.DataTable.isDataTable('#deleted-table')) {
                $('#deleted-table').DataTable().destroy();
            }

            $('#deleted-table').DataTable({
                data: data,
                columns: [{
                    data: null,
                    render: function(data) {
                        return createTrashCard(data, 'deleted');
                    }
                }],
                ordering: false,
                searching: true,
                pageLength: 10,
                language: {
                    emptyTable: "No deleted requests found"
                }
            });
        }
    });
}

function createTrashCard(data, type) {
    const serviceName = data.service ? data.service.service_name : 'N/A';
    const patientName = data.patient ? `${data.patient.user.firstname} ${data.patient.user.surname}` : 'N/A';
    const fileNo = data.patient ? data.patient.file_no : 'N/A';
    const doctorName = data.doctor ? `${data.doctor.firstname} ${data.doctor.surname}` : 'N/A';

    const date = type === 'deleted'
        ? new Date(data.deleted_at).toLocaleString()
        : new Date(data.dismissed_at).toLocaleString();

    const reason = type === 'deleted' ? data.deletion_reason : data.dismiss_reason;

    const badgeClass = type === 'deleted' ? 'badge-danger' : 'badge-warning';
    const icon = type === 'deleted' ? 'fa-trash' : 'fa-ban';

    let html = `
        <div class="card-modern mb-2" style="border-left: 4px solid ${type === 'deleted' ? '#dc3545' : '#ffc107'};">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0">
                        <span class="badge ${badgeClass}">
                            <i class="fa ${icon}"></i> ${serviceName}
                        </span>
                    </h6>
                    <button class="btn btn-sm btn-success" onclick="restoreRequest(${data.id}, '${type}')">
                        <i class="fa fa-undo"></i> Restore
                    </button>
                </div>
                <small>
                    <div><strong>Patient:</strong> ${patientName} (${fileNo})</div>
                    <div><strong>Doctor:</strong> ${doctorName}</div>
                    <div><strong>${type === 'deleted' ? 'Deleted' : 'Dismissed'}:</strong> ${date}</div>
                    <div><strong>Reason:</strong> ${reason}</div>
                </small>
            </div>
        </div>
    `;

    return html;
}

function updateTrashTotalCount() {
    const dismissed = parseInt($('#dismissed-count').text()) || 0;
    const deleted = parseInt($('#deleted-count').text()) || 0;
    const total = dismissed + deleted;
    $('#trash-total-count').text(total);

    if (total> 0) {
        $('#trash-total-count').show();
    } else {
        $('#trash-total-count').hide();
    }
}

// Audit Log Management
let auditLogTable = null;

$('#openAuditLog').on('click', function() {
    $('#auditLogModal').modal('show');
    loadAuditLogs();
});

$('#applyAuditFilter').on('click', function() {
    loadAuditLogs();
});

function loadAuditLogs() {
    const filters = {
        action: $('#audit_action_filter').val(),
        from_date: $('#audit_from_date').val(),
        to_date: $('#audit_to_date').val()
    };

    if (currentPatient) {
        filters.patient_id = currentPatient;
    }

    if (auditLogTable) {
        auditLogTable.destroy();
    }

    auditLogTable = $('#audit-log-table').DataTable({
        ajax: {
            url: wbUrl('/lab-workbench/audit-logs'),
            data: filters
        },
        columns: [
            {
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleString();
                }
            },
            {
                data: 'user',
                render: function(data) {
                    return data ? `${data.firstname} ${data.surname}` : 'N/A';
                }
            },
            {
                data: 'action',
                render: function(data) {
                    const badges = {
                        'view': 'badge-info',
                        'edit': 'badge-warning',
                        'delete': 'badge-danger',
                        'restore': 'badge-success',
                        'dismiss': 'badge-warning',
                        'undismiss': 'badge-success',
                        'billing': 'badge-primary',
                        'sample_collection': 'badge-info',
                        'result_entry': 'badge-success'
                    };
                    const badgeClass = badges[data] || 'badge-secondary';
                    return `<span class="badge ${badgeClass}">${data.toUpperCase()}</span>`;
                }
            },
            {
                data: 'description',
                render: function(data, type, row) {
                    let desc = data || 'No description';
                    if (row.new_values && row.new_values.reason) {
                        desc += `<br><small class="text-muted">Reason: ${row.new_values.reason}</small>`;
                    }
                    return desc;
                }
            },
            { data: 'ip_address' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No audit logs found"
        }
    });
}

$('#exportAuditLog').on('click', function() {
    // TODO: Implement export to Excel functionality
    toastr.info('Export feature coming soon!');
});

// Load trash counts on page load
$(document).ready(function() {
    loadTrashData();

    // Refresh trash counts every 60 seconds
    setInterval(function() {
        if (!$('#trashPanel').is(':visible')) {
            const patientId = currentPatient || null;
            $.ajax({
                url: wbUrl(`/lab-workbench/dismissed-requests/${patientId || ''}`),
                method: 'GET',
                success: function(data) {
                    $('#dismissed-count').text(data.length);
                    updateTrashTotalCount();
                }
            });
            $.ajax({
                url: wbUrl(`/lab-workbench/deleted-requests/${patientId || ''}`),
                method: 'GET',
                success: function(data) {
                    $('#deleted-count').text(data.length);
                    updateTrashTotalCount();
                }
            });
        }
    }, 60000);
});

// ============================================
// ENHANCEMENT FUNCTIONS
// ============================================

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
    $('#reports-view').removeClass('active').hide();
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
        'billing': '🟡 Awaiting Billing',
        'sample': '🟠 Awaiting Sample Collection',
        'results': '🔴 Awaiting Result Entry',
        'completed': '🟢 Completed Requests',
        'approval': '🟣 Awaiting Approval',
        'all': '📋 All Pending Requests'
    };
    $('#queue-view-title').html(`<i class="mdi mdi-format-list-bulleted"></i> ${titles[filter] || titles['all']}`);

    // Update current queue title display
    const titleNames = {
        'billing': 'Awaiting Billing',
        'sample': 'Sample Collection',
        'results': 'Result Entry',
        'completed': 'Completed',
        'approval': 'Awaiting Approval',
        'freeform': 'Free-Form / External',
        'all': 'All Pending'
    };
    $('#current-queue-title').html(`<i class="mdi mdi-filter"></i> Viewing: <span style="font-weight: 700; color: #667eea;">${titleNames[filter] || titleNames['all']}</span>`);

    if (filter === 'approval') {
        $('#approval-queue-hint').show();
    } else {
        $('#approval-queue-hint').hide();
    }

    // Update active state on queue buttons
    $('.queue-item').removeClass('active');
    if (filter !== 'all') {
        $(`.queue-item[data-filter="${filter}"]`).addClass('active');
    }

    // Set default date range to current month if not already set
    if (!$('#queue-start-date').val()) {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        
        const formatDate = (d) => {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        };
        
        $('#queue-start-date').val(formatDate(firstDay));
        $('#queue-end-date').val(formatDate(lastDay));
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

    // Map filter to status codes
    // billing = 1, sample = 2, results = 3, completed = 4, all = show all pending (1,2,3)
    const filterMap = {
        'billing': '1',
        'sample': '2',
        'results': '3',
        'completed': '4',
        'approval': 'approval',
        'freeform': 'freeform',
        'all': 'all'
    };
    const status = filterMap[filter] || 'all';

    // Get date range values
    const startDate = $('#queue-start-date').val();
    const endDate = $('#queue-end-date').val();

    // Initialize DataTable for lab queue
    queueDataTable = $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl('/lab-workbench/queue'),
            data: function(d) {
                d.status = status;
                if (startDate && endDate) {
                    d.start_date = startDate;
                    d.end_date = endDate;
                }
                return d;
            },
            dataSrc: 'data'
        },
        columns: [
            {
                data: 'card_data',
                orderable: false,
                render: function(data, type, row) {
                    // Use card_data from the controller response
                    const cardData = row.card_data;

                    let statusBadge = '';
                    let reverseBtn = '';
                    if (cardData.status === 1) {
                        statusBadge = '<span class="badge badge-warning"><i class="mdi mdi-currency-usd"></i> Awaiting Payment</span>';
                    } else if (cardData.status === 2) {
                        statusBadge = '<span class="badge badge-info"><i class="mdi mdi-test-tube"></i> Sample Collection</span>';
                    } else if (cardData.status === 3) {
                        statusBadge = '<span class="badge badge-danger"><i class="mdi mdi-clipboard-text"></i> Awaiting Results</span>';
                    } else if (cardData.status === 4 && cardData.approved_by) {
                        statusBadge = '<span class="badge badge-success"><i class="mdi mdi-check-circle"></i> Approved</span>';
                        if (cardData.approved_by == currentUserId) {
                            reverseBtn = `<button class="btn btn-sm btn-outline-warning reverse-approval-btn" data-request-id="${cardData.id}" style="margin-left: auto; font-size: 0.75rem; padding: 2px 8px;" title="Reverse this approval"><i class="mdi mdi-undo-variant"></i> Reverse</button>`;
                        }
                    } else if (cardData.status === 4) {
                        statusBadge = '<span class="badge badge-success"><i class="mdi mdi-check-circle"></i> Completed</span>';
                    } else if (cardData.status === 5) {
                        statusBadge = '<span class="badge badge-purple"><i class="mdi mdi-check-decagram"></i> Pending Approval</span>';
                    } else if (cardData.status === 6) {
                        statusBadge = '<span class="badge" style="background:#dc3545;color:#fff;"><i class="mdi mdi-close-circle"></i> Rejected</span>';
                    }

                    let inlineActionBtn = '';
                    if (cardData.is_free_form) {
                        statusBadge = '<span class="badge badge-secondary"><i class="mdi mdi-file-document-edit"></i> Free-Form</span>';
                        inlineActionBtn = `<button class="btn btn-sm btn-success enter-result-inline-btn" data-request-id="${cardData.id}" style="margin-left: auto;"><i class="mdi mdi-check"></i> Record Result</button>`;
                    }

                    let tpBadge = '';
                    if (cardData.treatment_plan_id && cardData.treatment_plan_name) {
                        tpBadge = ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${cardData.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(cardData.treatment_plan_name)}</a>`;
                    }

                    return `
                        <div class="queue-patient-item" data-patient-id="${cardData.patient_id}" style="cursor: pointer; padding: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="font-weight: 600; font-size: 1rem; color: #212529;">${escapeHtml(cardData.patient_name)}</div>
                                <span class="badge badge-primary">${cardData.file_no}</span>
                                ${reverseBtn}
                                ${inlineActionBtn}
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #6c757d;">
                                <i class="mdi mdi-flask-outline"></i> ${escapeHtml(cardData.service_name)}${tpBadge}
                                ${statusBadge ? '<br>' + statusBadge : ''}
                                <br><small class="text-muted"><i class="mdi mdi-account-clock"></i> Req: ${escapeHtml(cardData.requested_by)} on ${cardData.requested_at}</small>
                                ${cardData.approved_at ? `<br><small class="text-muted"><i class="mdi mdi-clock-check-outline"></i> Approved: ${cardData.approved_at}</small>` : ''}
                                ${cardData.hmo && cardData.hmo !== 'N/A' ? `<br><small><i class="mdi mdi-hospital-building"></i> ${escapeHtml(cardData.hmo)}</small>` : ''}
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
    $('#queue-datatable tbody').off('click').on('click', '.queue-patient-item', function() {
        const patientId = $(this).data('patient-id');
        hideQueue();
        loadPatient(patientId);
    });
}

// ==========================================
