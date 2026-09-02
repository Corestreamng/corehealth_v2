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
