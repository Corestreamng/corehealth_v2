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
