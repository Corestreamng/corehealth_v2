/**
 * CoreHealth v2 - Authentication & Login Page Interactive Module
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tabStandard = document.getElementById('tab-standard-login');
        var tabLocum = document.getElementById('tab-locum-login');
        var paneStandard = document.getElementById('pane-standard-login');
        var paneLocum = document.getElementById('pane-locum-login');

        if (!tabStandard || !tabLocum || !paneStandard || !paneLocum) {
            return;
        }

        function switchLoginMode(isLocum) {
            if (isLocum) {
                tabStandard.classList.remove('active');
                tabStandard.setAttribute('aria-selected', 'false');
                tabLocum.classList.add('active');
                tabLocum.setAttribute('aria-selected', 'true');

                paneStandard.classList.remove('show', 'active');
                paneLocum.classList.add('show', 'active');

                var pinInput = document.getElementById('locum_pin');
                var idInput = document.getElementById('locum_identifier');
                if (idInput && !idInput.value) {
                    setTimeout(function () { idInput.focus(); }, 150);
                } else if (pinInput) {
                    setTimeout(function () { pinInput.focus(); }, 150);
                }
            } else {
                tabLocum.classList.remove('active');
                tabLocum.setAttribute('aria-selected', 'false');
                tabStandard.classList.add('active');
                tabStandard.setAttribute('aria-selected', 'true');

                paneLocum.classList.remove('show', 'active');
                paneStandard.classList.add('show', 'active');

                var emailInput = document.getElementById('email');
                if (emailInput) {
                    setTimeout(function () { emailInput.focus(); }, 150);
                }
            }
        }

        tabStandard.addEventListener('click', function (e) {
            e.preventDefault();
            switchLoginMode(false);
        });

        tabLocum.addEventListener('click', function (e) {
            e.preventDefault();
            switchLoginMode(true);
        });
    });
})();
