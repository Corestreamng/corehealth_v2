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
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes && window.WORKBENCH_CONFIG.routes[name]) {
            return window.WORKBENCH_CONFIG.routes[name];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

(function($) {
    'use strict';

    $(function() {
        function toggleFields() {
            var method = $('#payment_method').val();
            if (method === 'cheque' || method === 'check') {
                $('.bank-fields').show();
                $('.cheque-fields').show();
            } else if (method === 'bank_transfer' || method === 'pos' || method === 'transfer') {
                $('.bank-fields').show();
                $('.cheque-fields').hide();
            } else {
                $('.bank-fields').hide();
                $('.cheque-fields').hide();
            }
        }

        $('#payment_method').on('change', toggleFields);
        toggleFields();
    });

})(jQuery);
