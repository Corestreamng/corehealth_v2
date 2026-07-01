/**
 * Patient Search Widget Shared Logic
 */

const PatientSearchWidget = (function() {
    const RECENT_KEY = 'global_recent_patients';
    const RECENT_MAX = 5;

    // Helper to escape HTML to prevent XSS
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getRecentPatients() {
        try {
            return JSON.parse(localStorage.getItem(RECENT_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    function addRecentPatient(patient) {
        let list = getRecentPatients().filter(p => p.id !== patient.id);
        list.unshift({
            id: patient.id,
            name: patient.name,
            photo: patient.photo,
            file_no: patient.file_no || ''
        });
        if (list.length > RECENT_MAX) list = list.slice(0, RECENT_MAX);
        localStorage.setItem(RECENT_KEY, JSON.stringify(list));
    }

    function renderRecentChips(containerId) {
        const $container = $(`#${containerId}`);
        if (!$container.length) return;

        const list = getRecentPatients();
        const $bar = $container.closest('.ps-recent-bar');

        if (!list.length) {
            $bar.hide();
            return;
        }

        $container.empty();
        list.forEach(p => {
            const shortName = escHtml(p.name.split(' ')[0]);
            const $chip = $(`
                <span class="ps-recent-chip" data-id="${p.id}" data-patient='${JSON.stringify(p).replace(/'/g, "&apos;")}' style="
                    display: inline-flex; align-items: center; background: #f1f3f5; padding: 4px 10px; border-radius: 16px; cursor: pointer; font-size: 0.85rem; transition: background 0.2s;">
                    <img src="${escHtml(p.photo)}" alt="" style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover; margin-right: 6px;">
                    <span style="font-weight: 500; color: #333;">${shortName}</span>
                    ${p.file_no ? `<small style="color: #6c757d; margin-left: 4px;">${escHtml(p.file_no)}</small>` : ''}
                </span>
            `);
            
            $chip.on('mouseenter', function() { $(this).css('background', '#e2e6ea'); })
                 .on('mouseleave', function() { $(this).css('background', '#f1f3f5'); });

            $container.append($chip);
        });
        $bar.show();
    }

    function init() {
        // Initialize recent chips for all widgets on the page
        $('.ps-recent-chips').each(function() {
            renderRecentChips(this.id);
        });

        // Delegate click for recent chips
        $(document).on('click', '.ps-recent-chip', function() {
            const patientData = $(this).data('patient');
            const $widget = $(this).closest('.patient-search-widget-container');
            const $input = $widget.find('.ps-search-input');
            const callbackName = $input.data('callback');

            if (callbackName && typeof window[callbackName] === 'function') {
                window[callbackName](patientData);
            }
        });

        // Setup inputs
        $('.ps-search-input').each(function() {
            const $input = $(this);
            const $widget = $input.closest('.patient-search-widget-container');
            const $dropdown = $widget.find('.ps-search-dropdown');
            const $clearBtn = $widget.find('.ps-search-clear');
            const route = $input.data('route');
            const callbackName = $input.data('callback');
            let context = $input.data('context');
            if (!context) {
                const path = window.location.pathname;
                if (path.includes('reception')) context = 'reception';
                else if (path.includes('nursing')) context = 'nursing';
                else if (path.includes('pharmacy')) context = 'pharmacy';
                else if (path.includes('lab')) context = 'lab';
                else if (path.includes('imaging')) context = 'imaging';
                else if (path.includes('billing')) context = 'billing';
                else if (path.includes('maternity')) context = 'maternity';
            }

            let searchTimer = null;
            let lastInputTime = 0;
            let inputBuffer = '';

            function doSearch(term, autoSelect = false) {
                $.get(route, { term: term }, function(res) {
                    $dropdown.empty();
                    
                    if (autoSelect && res && res.length === 1) {
                        selectPatient(res[0]);
                        return;
                    }
                    
                    if (!res || res.length === 0) {
                        $dropdown.append('<div style="padding: 10px; text-align: center; color: #6c757d;">No patients found</div>');
                    } else {
                        res.forEach(p => {
                            const photo = escHtml(p.photo);
                            const name = escHtml(p.name);
                            const fileNo = escHtml(p.file_no || '');
                            const phone = escHtml(p.phone || '');
                            
                            // Badge rendering based on pending items
                            let badgeHtml = '';
                            if (p.pending_count > 0) {
                                let badgeLabel = 'Pending';
                                if (context === 'surgery') badgeLabel = 'Procedures';
                                else if (context === 'nursing') badgeLabel = 'Tasks';
                                else if (context === 'pharmacy') badgeLabel = 'Meds';
                                else if (context === 'lab') badgeLabel = 'Labs';
                                else if (context === 'imaging') badgeLabel = 'Imaging';
                                else if (context === 'billing') badgeLabel = 'Bills';

                                badgeHtml = `<span style="background: var(--bs-danger, #dc3545); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; margin-left: auto;">${p.pending_count} ${badgeLabel}</span>`;
                            }

                            const $item = $(`
                                <div class="ps-search-item" data-patient='${JSON.stringify(p).replace(/'/g, "&apos;")}' style="padding: 10px; border-bottom: 1px solid #f1f3f5; cursor: pointer; display: flex; align-items: center; transition: background 0.2s;">
                                    <img src="${photo}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 12px;" alt="">
                                    <div style="display: flex; flex-direction: column; flex: 1;">
                                        <span style="font-weight: 600; color: #333;">${name}</span>
                                        <span style="font-size: 0.8rem; color: #6c757d;">
                                            ${fileNo ? `<strong>${fileNo}</strong> &bull; ` : ''} 
                                            ${phone} &bull; ${escHtml(p.gender || 'N/A')}
                                        </span>
                                    </div>
                                    ${badgeHtml}
                                </div>
                            `);

                            $item.on('mouseenter', function() { $(this).css('background', '#f8f9fa'); })
                                 .on('mouseleave', function() { $(this).css('background', 'transparent'); });

                            $dropdown.append($item);
                        });
                    }
                    $dropdown.show();
                });
            }

            function selectPatient(patientData) {
                // Save to recent
                addRecentPatient(patientData);
                
                // Update all recent chips on page
                $('.ps-recent-chips').each(function() {
                    renderRecentChips(this.id);
                });

                $input.val(patientData.name);
                $dropdown.hide();
                $input.trigger('patient-selected', [patientData]);

                if (callbackName && typeof window[callbackName] === 'function') {
                    window[callbackName](patientData);
                }
            }

            $input.on('input', function() {
                const term = $(this).val().trim();
                const currentTime = Date.now();
                clearTimeout(searchTimer);

                if (term.length > 0) {
                    $clearBtn.show();
                } else {
                    $clearBtn.hide();
                    $dropdown.hide().empty();
                    return;
                }

                // Barcode scanner detection
                if (currentTime - lastInputTime < 50 && inputBuffer.length > 0) {
                    inputBuffer = term;
                    searchTimer = setTimeout(() => {
                        if (inputBuffer.length >= 3) doSearch(inputBuffer, true);
                        inputBuffer = '';
                    }, 100);
                } else {
                    inputBuffer = term;
                    if (term.length < 2) {
                        $dropdown.hide().empty();
                        lastInputTime = currentTime;
                        return;
                    }
                    searchTimer = setTimeout(() => {
                        doSearch(term, false);
                    }, 300);
                }
                lastInputTime = currentTime;
            });
            
            // Enter key (barcode terminator)
            $input.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const term = $(this).val().trim();
                    if (term.length >= 2) doSearch(term, true);
                }
            });

            $dropdown.on('click', '.ps-search-item', function() {
                const patientData = $(this).data('patient');
                selectPatient(patientData);
            });

            $clearBtn.on('click', function() {
                $input.val('').trigger('input');
                $input.trigger('patient-cleared');
                $dropdown.hide();
                $input.focus();
            });

            // Close dropdown on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest($widget).length) {
                    $dropdown.hide();
                }
            });
            
            // Re-open on focus if it has value
            $input.on('focus', function() {
                if ($(this).val().trim().length >= 2 && $dropdown.children().length > 0) {
                    $dropdown.show();
                }
            });
        });
    }

    return {
        init: init,
        addRecentPatient: addRecentPatient,
        renderRecentChips: renderRecentChips
    };
})();

$(document).ready(function() {
    PatientSearchWidget.init();
});
