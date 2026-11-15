/**
 * CoreHealth v2 - Hospital Configuration: Partner EMR SDK & AI Scribe Management
 */

function copySecretToClipboard(inputId) {
    const copyText = document.getElementById(inputId);
    if (!copyText) return;
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(copyText.value).then(function() {
            alert('Client secret copied to clipboard!');
        }).catch(function() {
            document.execCommand('copy');
            alert('Client secret copied to clipboard!');
        });
    } else {
        document.execCommand('copy');
        alert('Client secret copied to clipboard!');
    }
}

function openEnvSnippetModal(clientId, clientName, baseUrl) {
    const coreHealthUrl = baseUrl || window.location.origin;
    const snippet = "# ====================================================================\n" +
                  "# CoreHealth Partner EMR Scribe SDK Configuration\n" +
                  "# Client: " + clientName + "\n" +
                  "# ====================================================================\n\n" +
                  "COREHEALTH_URL=" + coreHealthUrl + "\n" +
                  "COREHEALTH_CLIENT_ID=" + clientId + "\n" +
                  "COREHEALTH_CLIENT_SECRET=your_client_secret_here\n" +
                  "PORT=3001\n";

    const el = document.getElementById('envSnippetContent');
    if (el) {
        el.textContent = snippet;
    }
    if (window.$ && typeof window.$('#envSnippetModal').modal === 'function') {
        window.$('#envSnippetModal').modal('show');
    }
}

function copySnippetToClipboard() {
    const el = document.getElementById('envSnippetContent');
    if (!el) return;
    const snippetText = el.textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(snippetText).then(function() {
            alert('.env configuration snippet copied to clipboard!');
        }).catch(function() {
            alert('Failed to copy to clipboard.');
        });
    } else {
        alert('Clipboard API not available. Please select and copy manually.');
    }
}

function confirmRotateSdkSecret(clientId, actionUrl) {
    if (window.confirm("Rotate API secret for client '" + clientId + "'? Existing integration tokens will remain valid until expiry, but new authorization requests will require the new secret.")) {
        const form = document.getElementById('rotateSdkClientForm');
        if (form) {
            form.action = actionUrl;
            form.submit();
        }
    }
}

function toggleSdkClientStatus(clientId, actionUrl, isCurrentlyActive) {
    const action = isCurrentlyActive ? 'deactivate' : 'activate';
    if (window.confirm("Are you sure you want to " + action + " SDK client '" + clientId + "'?")) {
        const form = document.getElementById('toggleSdkClientForm');
        if (form) {
            form.action = actionUrl;
            form.submit();
        }
    }
}

function confirmDeleteSdkClient(clientId, actionUrl) {
    if (window.confirm("Permanently delete SDK client '" + clientId + "'? This action cannot be undone.")) {
        const form = document.getElementById('deleteSdkClientForm');
        if (form) {
            form.action = actionUrl;
            form.submit();
        }
    }
}

function confirmRevokeSdkToken(jti, actionUrl) {
    if (window.confirm("Immediately revoke SDK token session '" + jti + "'? The client session will be terminated instantly.")) {
        const form = document.getElementById('revokeSdkTokenForm');
        if (form) {
            form.action = actionUrl;
            form.submit();
        }
    }
}

function initSdkConfigTab(hasNewSecret) {
    if (window.$) {
        window.$(document).ready(function() {
            if (window.location.hash === '#sdk-settings' || hasNewSecret) {
                window.$('#configTabs a[href="#sdk-settings"]').tab('show');
            }
        });
    }
}

// Global modal dismiss handler for project standard compatibility (BS4 & BS5)
if (typeof document !== 'undefined') {
    document.addEventListener('click', function(e) {
        const dismissBtn = e.target.closest('[data-dismiss="modal"], [data-bs-dismiss="modal"]');
        if (!dismissBtn) return;

        const modalEl = dismissBtn.closest('.modal');
        if (!modalEl) return;

        e.preventDefault();

        // 1. Try jQuery / Bootstrap 4 & 5 Modal plugin
        if (window.$ && typeof window.$(modalEl).modal === 'function') {
            window.$(modalEl).modal('hide');
            return;
        }

        // 2. Try Bootstrap 5 native Modal instance
        if (window.bootstrap && window.bootstrap.Modal) {
            const bsModal = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
            if (bsModal) {
                bsModal.hide();
                return;
            }
        }

        // 3. Direct DOM fallback
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        const backdrops = document.querySelectorAll('.modal-backdrop');
        for (let i = 0; i < backdrops.length; i++) {
            backdrops[i].remove();
        }
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';
    });
}


