<script>
$(document).on('click', '#confirm-payment-btn', function() {
    processPayment();
});

$(document).on('change', '#payment-method', function() {
    const method = $(this).val();
    if (method === 'ACCOUNT') {
        $('#account-payment-note').show();
        $('#bank-selection-section').hide();
        $('#staff-selection-section').hide();
        $('#org-selection-section').hide();
    } else if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
        $('#account-payment-note').hide();
        $('#bank-selection-section').show();
        $('#staff-selection-section').hide();
        $('#org-selection-section').hide();
    } else if (method === 'BILL_TO_STAFF') {
        $('#account-payment-note').hide();
        $('#bank-selection-section').hide();
        $('#staff-selection-section').show();
        $('#org-selection-section').hide();
    } else if (method === 'BILL_TO_ORGANIZATION') {
        $('#account-payment-note').hide();
        $('#bank-selection-section').hide();
        $('#staff-selection-section').hide();
        $('#org-selection-section').show();
    } else {
        $('#account-payment-note').hide();
        $('#bank-selection-section').hide();
        $('#staff-selection-section').hide();
        $('#org-selection-section').hide();
    }
});

function processPayment() {
    let items = [];
    
    if (window.pendingPaymentItems && window.pendingPaymentItems.length > 0) {
        items = window.pendingPaymentItems;
    } else {
        const selectedItems = $('.billing-item-checkbox:checked');

        if (selectedItems.length === 0) {
            toastr.warning('Please select items to process payment');
            $('#paymentModal').modal('hide');
            return;
        }

        selectedItems.each(function() {
            const row = $(this).closest('tr');
            items.push({
                id: $(this).data('id'),
                qty: parseFloat(row.find('.item-qty-input').val()) || 1,
                discount: parseFloat(row.find('.item-discount-input').val()) || 0
            });
        });
    }

    const paymentPatientId = window.pendingPaymentPatientId || currentPatient;

    const paymentType = $('#payment-method').val();
    const referenceNo = $('#payment-reference').val();
    const bankId = $('#payment-bank').val();
    const totalPayable = parseFloat($('#summary-total').text().replace('₦', '').replace(/,/g, ''));

    // Validate bank selection for non-cash payments
    if (['POS', 'TRANSFER', 'MOBILE'].includes(paymentType) && !bankId) {
        toastr.warning('Please select a bank for this payment method');
        return;
    }

    // Validate staff selection
    if (paymentType === 'BILL_TO_STAFF') {
        const staffId = $('#payment-staff-id').val();
        if (!staffId) {
            toastr.warning('Please select a staff member to bill');
            return;
        }
    }

    // Validate organization selection
    if (paymentType === 'BILL_TO_ORGANIZATION') {
        const orgId = $('#payment-organization-id').val();
        if (!orgId) {
            toastr.warning('Please select an organization to bill');
            return;
        }
    }

    // Validate account balance payment (Credit facility: allow negative balance with warning)
    if (paymentType === 'ACCOUNT') {
        const accBalance = typeof currentAccountBalance !== 'undefined' ? currentAccountBalance : (window.pendingAccountBalance || 0);
        const balanceAfter = accBalance - totalPayable;

        if (totalPayable > accBalance) {
            // Show warning for credit/negative balance
            const warningMsg = accBalance >= 0
                ? `This payment of ₦${totalPayable.toLocaleString()} exceeds the available balance of ₦${accBalance.toLocaleString()}.\n\nBalance after payment: ₦${balanceAfter.toLocaleString()} (CREDIT/DEBIT)\n\nDo you want to proceed with credit facility?`
                : `Current balance is already ₦${accBalance.toLocaleString()} (debit).\n\nThis payment will increase the debit to ₦${balanceAfter.toLocaleString()}.\n\nDo you want to proceed?`;

            if (!confirm(warningMsg)) {
                return;
            }
        } else {
            // Normal deduction - show confirmation
            if (!confirm(`Deduct ₦${totalPayable.toLocaleString()} from account balance?\n\nBalance after: ₦${balanceAfter.toLocaleString()}`)) {
                return;
            }
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
            patient_id: paymentPatientId,
            payment_type: paymentType,
            payment_method: paymentType,
            bank_id: bankId || null,
            staff_user_id: paymentType === 'BILL_TO_STAFF' ? $('#payment-staff-id').val() : null,
            organization_id: paymentType === 'BILL_TO_ORGANIZATION' ? $('#payment-organization-id').val() : null,
            reference_no: referenceNo,
            items: items
        },
        success: function(response) {
            // Clear pending items
            window.pendingPaymentItems = null;
            window.pendingPaymentPatientId = null;
            window.pendingAccountBalance = null;

            // Reset button state
            $confirmBtn.prop('disabled', false).html(originalText);

            toastr.success('Payment processed successfully!');

            // Close payment modal
            $('#paymentModal').modal('hide');

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

            // Show receipt modal
            $('#receiptPreviewModal').modal('show');

            // Hide floating cart
            $('#floating-cart').fadeOut(200);

            // Clear all billing selections and reset summary
            $('.billing-item-checkbox').prop('checked', false);
            $('#select-all-items').prop('checked', false);
            $('#summary-subtotal').text('₦0.00');
            $('#summary-discount').text('₦0.00');
            $('#summary-total').text('₦0.00');

            // Execute callbacks if they exist (only in Billing Workbench)
            if (typeof loadBillingItems === 'function') loadBillingItems();
            if (typeof loadAccountBalance === 'function') loadAccountBalance(paymentPatientId);
            if (typeof loadPatientReceipts === 'function') loadPatientReceipts();
            if (typeof loadAccountSummary === 'function' && $('#account-tab').hasClass('active')) loadAccountSummary();
            if (typeof loadQueueCounts === 'function') loadQueueCounts();
            
            // Execute reception workbench callback if exists
            if (typeof onPaymentSuccess === 'function') onPaymentSuccess();
        },
        error: function(xhr) {
            // Clear pending items
            window.pendingPaymentItems = null;
            window.pendingPaymentPatientId = null;
            window.pendingAccountBalance = null;

            // Reset button state on error
            $confirmBtn.prop('disabled', false).html(originalText);

            toastr.error(xhr.responseJSON?.message || 'Payment processing failed');
        }
    });
}

$(document).on('click', '#print-thermal-receipt', function() {
    printReceipt('receipt-content-thermal');
});

$(document).on('click', '#print-a4-receipt', function() {
    printReceipt('receipt-content-a4');
});

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

function printDepositReceiptFromList(depositId) {
    if (!depositId) {
        toastr.warning('Invalid deposit ID');
        return;
    }

    toastr.info('Generating deposit receipt...');

    $.ajax({
        url: `{{ url('/billing-workbench/print-deposit-receipt/${depositId}') }}`,
        method: 'GET',
        success: function(response) {
            if (response.receipt_a4 && response.receipt_thermal) {
                $('#modal-receipt-a4').html(response.receipt_a4);
                $('#modal-receipt-thermal').html(response.receipt_thermal);

                // Reset tabs to A4
                $('.receipt-modal-tab').removeClass('active');
                $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                $('#modal-receipt-a4').show();
                $('#modal-receipt-thermal').hide();

                // Show modal
                $('#receiptPreviewModal').modal('show');
            } else {
                toastr.error('Failed to generate deposit receipt');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to generate deposit receipt');
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

// ==========================================
// PRINT INVOICE FUNCTIONALITY (For Unpaid Items)
// ==========================================


$(document).ready(function() {
    if (typeof loadBanks === 'function') loadBanks();
    if (typeof loadStaffList === 'function') loadStaffList();
    if (typeof loadOrganizationList === 'function') loadOrganizationList();
});

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

// Global staff cache
let activeStaffList = [];

function loadStaffList() {
    if (activeStaffList.length > 0) {
        return; // Already loaded
    }

    $.ajax({
        url: '{{ url('/billing-workbench/staff-list') }}',
        method: 'GET',
        success: function(response) {
            if (response) {
                activeStaffList = response;
                populateStaffDropdown();
            }
        },
        error: function() {
            console.error('Failed to load staff list');
        }
    });
}

function populateStaffDropdown() {
    const $paymentStaff = $('#payment-staff-id');
    $paymentStaff.find('option:not(:first)').remove();

    activeStaffList.forEach(staff => {
        const option = `<option value="${staff.id}">${staff.text}</option>`;
        $paymentStaff.append(option);
    });

    // Initialize Select2 if it exists
    if ($.fn.select2) {
        $paymentStaff.select2({
            dropdownParent: $('#paymentModal'),
            placeholder: '-- Select Staff --',
            allowClear: true
        });
    }
}


// Global organizations cache
let activeOrganizationList = [];

function loadOrganizationList() {
    if (activeOrganizationList.length > 0) {
        return; // Already loaded
    }

    $.ajax({
        url: '{{ url('/billing-workbench/organization-list') }}',
        method: 'GET',
        success: function(response) {
            if (response) {
                activeOrganizationList = response;
                populateOrganizationDropdown();
            }
        },
        error: function() {
            console.error('Failed to load organization list');
        }
    });
}

function populateOrganizationDropdown() {
    const $paymentOrg = $('#payment-organization-id');
    $paymentOrg.find('option:not(:first)').remove();

    activeOrganizationList.forEach(org => {
        const option = `<option value="${org.id}">${org.text}</option>`;
        $paymentOrg.append(option);
    });

    // Initialize Select2 if it exists
    if ($.fn.select2) {
        $paymentOrg.select2({
            dropdownParent: $('#paymentModal'),
            placeholder: '-- Select Organization --',
            allowClear: true
        });
    }
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


$(document).on('show.bs.modal', '#paymentModal', function () {
    if (typeof generateReferenceNumber === 'function') {
        generateReferenceNumber();
    }
});
</script>