/**
 * WeCare247 Integration Tab Scripts for Hospital Configuration
 *
 * Handles live preview of integration state, credential copying,
 * and AJAX connection probing to the remote WeCare247 platform instance.
 */

/* global $, toastr */

(function () {
    'use strict';

    window.toggleWeCareActiveBadge = function (active) {
        var badge = $('#wecare-header-badge');
        var box = $('#wecare-master-switch-box');
        if (active) {
            badge.removeClass('bg-secondary').addClass('bg-success').html('<i class="mdi mdi-check-circle mr-1"></i> Integration Active');
            box.css({ 'background': '#f0fdf4', 'border-color': '#bbf7d0' });
        } else {
            badge.removeClass('bg-success').addClass('bg-secondary').html('<i class="mdi mdi-power-standby mr-1"></i> Integration Turned Off');
            box.css({ 'background': '#f8f9fa', 'border-color': '#e9ecef' });
        }
    };

    window.copyWeCareCredential = function (text, label) {
        if (!navigator.clipboard) {
            var temp = document.createElement('input');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
        } else {
            navigator.clipboard.writeText(text);
        }
        if (typeof toastr !== 'undefined') {
            toastr.success((label || 'Credential') + ' copied to clipboard!');
        } else {
            alert((label || 'Credential') + ' copied to clipboard!');
        }
    };

    window.runWeCareProbe = function () {
        var btn = $('#btn-test-wecare-probe');
        var resDiv = $('#wecare-probe-result');
        var url = $('#wecare_url_input').val();
        var csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();
        var probeUrl = window.WECARE_PROBE_URL || '/admin/hospital-config/test-wecare';

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Probing...');
        resDiv.hide().empty();

        $.ajax({
            url: probeUrl,
            type: 'POST',
            data: {
                _token: csrfToken,
                wecare_url: url
            },
            success: function (resp) {
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh mr-1"></i> Test Connection');
                resDiv.show().html(
                    '<div class="alert alert-success py-2 px-3 mb-0 text-sm" style="border-radius: 8px;">' +
                    '<i class="mdi mdi-check-circle mr-1"></i> ' + (resp.message || 'Connection successful') +
                    ' <strong>(' + (resp.latency_ms || 0) + ' ms)</strong>' +
                    '</div>'
                );
            },
            error: function (xhr) {
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh mr-1"></i> Test Connection');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Connection failed or timed out.';
                resDiv.show().html(
                    '<div class="alert alert-danger py-2 px-3 mb-0 text-sm" style="border-radius: 8px;">' +
                    '<i class="mdi mdi-alert-circle mr-1"></i> ' + msg +
                    '</div>'
                );
            }
        });
    };
})();
