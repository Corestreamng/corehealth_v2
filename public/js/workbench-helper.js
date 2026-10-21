window.WORKBENCH_CONFIG = window.WORKBENCH_CONFIG || {
    csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    baseUrl: '',
    routes: {}
};

window.wbUrl = function(path) {
    var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
    base = base.replace(/\/$/, '');
    var cleanPath = (path || '').replace(/^\//, '');
    return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
};

window.wbRoute = function(name, fallbackPath) {
    if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) {
        if (window.WORKBENCH_CONFIG.routes[name]) {
            return window.WORKBENCH_CONFIG.routes[name];
        }
        var dotKey = name.replace(/_/g, '.');
        if (window.WORKBENCH_CONFIG.routes[dotKey]) {
            return window.WORKBENCH_CONFIG.routes[dotKey];
        }
        var underscoreKey = name.replace(/\./g, '_');
        if (window.WORKBENCH_CONFIG.routes[underscoreKey]) {
            return window.WORKBENCH_CONFIG.routes[underscoreKey];
        }
    }
    return window.wbUrl(fallbackPath || '');
};

window.openModalSafely = function(target) {
    var $el = $(target);
    if (!$el.length) return;
    var el = $el[0];

    if (typeof bootstrap !== 'undefined' && bootstrap && typeof bootstrap.Modal === 'function' && typeof bootstrap.Modal.getInstance === 'function') {
        try {
            var bsInst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
            if (bsInst && typeof bsInst.show === 'function') {
                bsInst.show();
                return;
            }
        } catch (e) {
            console.warn('BS5 modal show failed, falling back to jQuery:', e);
        }
    }

    if (typeof $.fn.modal === 'function') {
        try {
            $el.modal('show');
            return;
        } catch (e) {
            console.warn('jQuery modal show failed:', e);
        }
    }
};

window.closeModalSafely = function(target) {
    var $el = $(target);
    if (!$el.length) return;
    var el = $el[0];

    if (typeof bootstrap !== 'undefined' && bootstrap && typeof bootstrap.Modal === 'function' && typeof bootstrap.Modal.getInstance === 'function') {
        try {
            var bsInst = bootstrap.Modal.getInstance(el);
            if (bsInst && typeof bsInst.hide === 'function') {
                bsInst.hide();
                return;
            }
        } catch (e) {}
    }

    if (typeof $.fn.modal === 'function') {
        try {
            $el.modal('hide');
            return;
        } catch (e) {}
    }
};

window.escapeHtml = window.escapeHtml || function(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

window.CoreHealth = window.CoreHealth || {};

window.CoreHealth.paymentMethodLabels = {
    'CASH': 'Cash',
    'POS': 'POS',
    'TRANSFER': 'Bank Transfer',
    'BANK_TRANSFER': 'Bank Transfer',
    'MOBILE': 'Mobile Money',
    'BILL_TO_STAFF': 'Bill to Staff',
    'BILL_TO_ORGANIZATION': 'Bill to Organization',
    'ACCOUNT': 'Patient Account',
    'DEPOSIT': 'Patient Deposit',
    'ACC_DEPOSIT': 'Account Deposit',
    'ACC_WITHDRAW': 'Patient Account',
    'ACC_ADJUSTMENT': 'Account Adjustment',
    'SPLIT': 'Split Payment',
    'CHEQUE': 'Cheque',
    'TELLER': 'Teller'
};

window.CoreHealth.formatMethodName = function(method) {
    if (!method) return 'Unknown';
    var upper = String(method).toUpperCase().trim();
    if (window.CoreHealth.paymentMethodLabels[upper]) {
        return window.CoreHealth.paymentMethodLabels[upper];
    }
    return upper.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, function(l) { return l.toUpperCase(); });
};

window.CoreHealth.formatPaymentMethod = function(tx) {
    if (!tx) return 'Unknown';
    if (tx.is_split_payment && tx.splits && tx.splits.length) {
        return 'Split: ' + tx.splits.map(function(s) {
            var m = window.CoreHealth.formatMethodName(s.payment_method);
            var amt = parseFloat(s.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return m + ' ₦' + amt;
        }).join(' + ');
    }
    var m = tx.payment_method || tx.payment_type || 'Unknown';
    return window.CoreHealth.formatMethodName(m);
};

window.CoreHealth.getItemDisplayName = function(item) {
    if (!item) return 'Unknown Item';
    if (item.is_free_form && item.free_form_name) {
        return item.free_form_name;
    }
    if (item.service && item.service.service_name) {
        return item.service.service_name;
    }
    if (item.product && item.product.product_name) {
        return item.product.product_name;
    }
    return item.service_name || item.product_name || item.name || 'Custom Item';
};


