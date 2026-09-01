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
            url: wbUrl(`/patient-procedures/list-by-patient/${patientId}`),
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
        url: wbUrl(`/billing-workbench/patient/${patientId}/account-summary`),
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
        url: wbUrl(`/billing-workbench/patient/${currentPatient}/account-transactions`),
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
        url: wbUrl('/billing-workbench/account-transaction'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/billing-workbench/patient/${currentPatient}/receipts`),
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
        url: wbUrl('/billing-workbench/create-account'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/pharmacy-workbench/patient/${currentPatient}/prescription-data`),
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
                url: wbRoute('pharmacy.dispense-free-form', '/pharmacy/dispense-free-form'),
                method: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbRoute('pharmacy.record-billing', '/pharmacy/record-billing'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbRoute('pharmacy.dismiss', '/pharmacy/dismiss'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbRoute('pharmacy.dispense', '/pharmacy/dispense'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/pharmacy-workbench/prescription/${itemId}/mark-billed`),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))
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
        url: wbRoute('pharmacy.print-prescription-slip', '/pharmacy/print-prescription-slip'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbRoute('pharmacy.dispense', '/pharmacy/dispense'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/pharmacy-workbench/patient/${currentPatient}/dispensing-history`),
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
        url: wbUrl('/billing-workbench/process-payment'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/billing-workbench/patient/${currentPatient}/receipts`),
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
        url: wbUrl('/billing-workbench/print-receipt'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl('/pharmacy-workbench/my-transactions'),
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
        url: wbUrl(`/lab-workbench/lab-service-requests/${requestId}/attachments`),
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
        url: wbUrl(`/lab-workbench/lab-service-requests/${requestId}`),
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
        url: wbUrl(`/lab-workbench/lab-service-requests/${deleteRequestId}`),
        method: 'DELETE',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/lab-workbench/lab-service-requests/${dismissRequestId}/dismiss`),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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

