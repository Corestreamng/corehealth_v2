            (function() {
                'use strict';

                /* ── Config ── */
                const cfg = window.SURGERY_WORKBENCH_CONFIG || {};
                const ROUTES = cfg.routes || {
                    queueCounts: '/surgery-workbench/queue-counts',
                    queue: '/surgery-workbench/queue',
                    searchPatients: '/surgery-workbench/search-patients',
                };

                /* ── State ── */
                let currentStatus = 'requested';
                let currentPriority = '';
                let currentConsent = '';
                let currentDate = '';
                let searchTerm = '';
                let selectedPatientId = null;
                let viewMode = 'grid'; // 'grid' | 'list'
                let searchTimer = null;
                let refreshTimer = null;

                /* ── Helpers ── */
                function initials(name) {
                    return name ? name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() : '?';
                }

                function statusLabel(s) {
                    const map = {
                        requested: 'Requested',
                        scheduled: 'Scheduled',
                        in_progress: 'In Progress',
                        completed: 'Completed',
                        cancelled: 'Cancelled'
                    };
                    return map[s] || s;
                }

                function consentLabel(c) {
                    const map = {
                        pending: 'Consent Pending',
                        obtained: 'Consent OK',
                        waived: 'Waived',
                        not_required: 'N/A'
                    };
                    return map[c] || c;
                }

                /* ── Load Stats ── */
                function loadStats() {
                    $.get(ROUTES.queueCounts, function(data) {
                        const total = (data.requested || 0) + (data.scheduled || 0) + (data.in_progress || 0) + (
                            data.completed || 0) + (data.cancelled || 0);
                        $('#count-all').text(total);
                        $('#count-requested').text(data.requested || 0);
                        $('#count-scheduled').text(data.scheduled || 0);
                        $('#count-in-progress').text(data.in_progress || 0);
                        $('#count-completed').text(data.completed || 0);
                        $('#count-cancelled').text(data.cancelled || 0);
                    });
                }

                /* ── Load Queue ── */
                function loadQueue() {
                    $('#sw-loading').show();
                    $('#sw-procedures-container').empty();

                    const params = {};
                    if (currentStatus && currentStatus !== 'all') params.status = currentStatus;
                    if (currentPriority) params.priority = currentPriority;
                    if (currentConsent) params.consent = currentConsent;
                    if (currentDate) params.date = currentDate;
                    if (searchTerm) params.search = searchTerm;
                    if (selectedPatientId) params.patient_id = selectedPatientId;

                    $.get(ROUTES.queue, params, function(resp) {
                        $('#sw-loading').hide();
                        renderProcedures(resp.data || []);
                    }).fail(function() {
                        $('#sw-loading').hide();
                        $('#sw-procedures-container').html(
                            '<div class="sw-empty"><i class="mdi mdi-alert-circle-outline"></i><h4>Failed to load</h4><p>Please try refreshing.</p></div>'
                            );
                    });
                }

                /* ── Render Procedures ── */
                function renderProcedures(procedures) {
                    _lastData = procedures || [];
                    const $container = $('#sw-procedures-container');
                    $container.empty();

                    if (!procedures.length) {
                        $container.html(`
            <div class="sw-empty">
                <i class="mdi mdi-calendar-blank-outline"></i>
                <h4>No procedures found</h4>
                <p>Try adjusting your filters or status tab</p>
            </div>`);
                        return;
                    }

                    // When showing "all", group by status
                    if (currentStatus === 'all') {
                        const order = ['in_progress', 'scheduled', 'requested', 'completed', 'cancelled'];
                        const grouped = {};
                        order.forEach(s => grouped[s] = []);
                        procedures.forEach(p => {
                            const s = p.procedure_status;
                            if (!grouped[s]) grouped[s] = [];
                            grouped[s].push(p);
                        });

                        let hasAny = false;
                        order.forEach(status => {
                            const group = grouped[status];
                            if (!group.length) return;
                            hasAny = true;
                            const $section = $('<div class="sw-status-section"></div>');
                            $section.append(`
                <div class="sw-status-section-header">
                    <span class="sw-status-dot ${status}"></span>
                    ${statusLabel(status)}
                    <span style="color:#adb5bd;font-weight:400;">(${group.length})</span>
                </div>
            `);
                            const $grid = $('<div></div>').addClass(viewMode === 'list' ? 'sw-proc-list' :
                                'sw-proc-grid');
                            group.forEach(p => $grid.append(buildCard(p)));
                            $section.append($grid);
                            $container.append($section);
                        });

                        if (!hasAny) {
                            $container.html(
                                '<div class="sw-empty"><i class="mdi mdi-calendar-blank-outline"></i><h4>No procedures found</h4><p>Try adjusting your filters</p></div>'
                                );
                        }
                        return;
                    }

                    const $grid = $('<div></div>').addClass(viewMode === 'list' ? 'sw-proc-list' : 'sw-proc-grid');
                    procedures.forEach(p => $grid.append(buildCard(p)));
                    $container.append($grid);
                }

                /* ── Build a single procedure card ── */
                function buildCard(p) {
                    const priorityClass = `priority-${p.priority || 'routine'}`;
                    const statusClass = p.procedure_status.replace('_', '-');
                    const consentClass = p.consent_status || 'pending';
                    const avatarInitials = initials(p.patient_name);

                    const priorityBadge = (p.priority && p.priority !== 'routine') ?
                        `<span class="badge-priority ${p.priority}">${p.priority.toUpperCase()}</span>` :
                        '';

                    const consentBadge = p.consent_status ?
                        `<span class="badge-consent ${consentClass}">${consentLabel(p.consent_status)}</span>` :
                        '';

                    const scheduledLine = p.scheduled_date ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-calendar"></i>${p.scheduled_date}${p.scheduled_time ? ' ' + p.scheduled_time : ''}</span>` :
                        '';

                    const orLine = p.operating_room ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-map-marker-outline"></i>${p.operating_room}</span>` :
                        '';

                    const hmoLine = p.hmo ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-card-account-details-outline"></i>${p.hmo}</span>` :
                        '';

                    const requestedLine = p.requested_on ?
                        `<span class="proc-card-detail-item"><i class="mdi mdi-clock-outline"></i>Requested ${p.requested_on}</span>` :
                        '';

                    return `
    <div class="proc-card ${priorityClass}" data-proc-id="${p.id}" data-show-url="${p.show_url}">
        <div class="proc-card-header">
            <div>
                <div class="proc-card-name">${escHtml(p.service_name)}</div>
                ${p.category ? `<div class="proc-card-category">${escHtml(p.category)}</div>` : ''}
            </div>
            <div class="proc-card-badges">
                <span class="badge-status ${p.procedure_status}">${statusLabel(p.procedure_status)}</span>
                ${priorityBadge}
                ${consentBadge}
            </div>
        </div>
        <div class="proc-card-patient">
            <div class="proc-card-patient-avatar">${avatarInitials}</div>
            <div>
                <div class="proc-card-patient-name">${escHtml(p.patient_name)}</div>
                <div class="proc-card-patient-meta">
                    File: ${escHtml(p.file_no || '—')}
                    ${p.hmo ? ' · ' + escHtml(p.hmo) : ''}
                </div>
            </div>
        </div>
        <div class="proc-card-details">
            ${scheduledLine}
            ${orLine}
            ${hmoLine}
            ${requestedLine}
        </div>
        <div class="proc-card-footer">
            <div class="proc-card-footer-meta">
                ${p.team_count ? `<span><i class="mdi mdi-account-group-outline"></i> ${p.team_count} team member${p.team_count !== 1 ? 's' : ''}</span>` : '<span style="color:#c62828;font-weight:600;"><i class="mdi mdi-account-group-outline"></i> No team assigned</span>'}
                ${p.requested_by ? `<span><i class="mdi mdi-doctor"></i>${escHtml(p.requested_by)}</span>` : ''}
            </div>
            <a class="btn-open-proc" href="${p.show_url}" target="_blank" onclick="event.stopPropagation();">
                <i class="mdi mdi-open-in-new"></i> Open
            </a>
        </div>
    </div>`;
                }

                function escHtml(str) {
                    if (!str) return '';
                    return String(str)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                }

                /* ── Patient Search Integration ── */
                $('#sw-patient-search-widget-input').on('patient-selected', function(e, patientData) {
                    const name    = patientData.name;
                    const fileNo  = patientData.file_no || '';
                    const age     = patientData.age || '';
                    const gender  = patientData.gender || '';
                    const hmo     = patientData.hmo || '';
                    const total   = parseInt(patientData.total_procedures || 0);
                    const active  = parseInt(patientData.active_procedures || 0);
                    
                    searchTerm = '';
                    selectedPatientId = patientData.id;
                    
                    currentStatus = 'all';
                    $('.sw-stat-tab').removeClass('active');
                    $('#tab-all').addClass('active');
                    
                    // Build banner content
                    let meta = [];
                    if (fileNo) meta.push('<i class="mdi mdi-identifier"></i> ' + fileNo);
                    if (age)    meta.push(age + ' yrs');
                    if (gender && gender !== 'N/A') meta.push(gender);
                    if (hmo)    meta.push('<i class="mdi mdi-shield-plus-outline"></i> ' + hmo);
                    if (patientData.registration_workbench_label) meta.push('<i class="mdi mdi-source-branch text-primary"></i> ' + patientData.registration_workbench_label);
                    if (patientData.registered_on) meta.push('<i class="mdi mdi-calendar-clock"></i> ' + patientData.registered_on);
                    if (patientData.registered_by_name) meta.push('<i class="mdi mdi-account-tie"></i> ' + patientData.registered_by_name);
                    
                    let badges = '';
                    if (active > 0) badges += '<span class="spb-badge"><i class="mdi mdi-progress-clock"></i> ' + active + ' In OR</span>';
                    if (total  > 0) badges += '<span class="spb-badge">' + total + ' procedure' + (total > 1 ? 's' : '') + '</span>';
                    
                    $('#spb-name').text(name);
                    $('#spb-meta').html(meta.join(' &nbsp;&middot;&nbsp; ') + (badges ? '&nbsp;&nbsp;' + badges : ''));
                    if (typeof patientData.can_edit !== 'undefined') {
                        $('#btn-edit-patient').toggle(!!patientData.can_edit);
                    } else {
                        $('#btn-edit-patient').show();
                    }
                    $('#sw-patient-banner').addClass('visible');

                    // Initialize ClinicalOrdersKit for this patient
                    if (window.ClinicalOrdersKit && typeof window.ClinicalOrdersKit.init === 'function') {
                        window.ClinicalOrdersKit.init({
                            patientId: patientData.id
                        });
                    }

                    loadQueue();
                });

                function clearPatientFilter() {
                    searchTerm = '';
                    selectedPatientId = null;
                    $('#sw-patient-banner').removeClass('visible');
                    currentStatus = 'requested';
                    $('.sw-stat-tab').removeClass('active');
                    $('#tab-requested').addClass('active');
                    loadQueue();
                }

                $('#sw-patient-search-widget-input').on('patient-cleared', function() {
                    clearPatientFilter();
                });

                // Exit patient view (banner button)
                $('#spb-exit-btn').on('click', function() {
                    $('#sw-patient-search-widget-clear').trigger('click'); // Let widget handle UI state
                    clearPatientFilter();
                });

                // Book Procedure button
                $('#btn-book-procedure').on('click', function() {
                    if (window.ClinicalOrdersKit) {
                        $('#clinical_orders_modal').modal('show');
                        setTimeout(function() {
                            if (typeof window.ClinicalOrdersKit.switchCpSubTab === 'function') {
                                window.ClinicalOrdersKit.switchCpSubTab('cp-procedures');
                            } else {
                                $('#cp-procedures-tab').tab('show');
                            }
                        }, 200);
                    }
                });

                // Refresh queue when clinical orders modal is closed
                $('#clinical_orders_modal').on('hidden.bs.modal', function () {
                    if (refreshTimer) {
                        loadStats();
                        loadQueue();
                    }
                });

                /* ── Cache for re-render without reload ── */
                let _lastData = [];

                function renderFromCache() {
                    renderProcedures(_lastData);
                }

                /* ── Auto-refresh every 90s ── */
                function startAutoRefresh() {
                    clearInterval(refreshTimer);
                    refreshTimer = setInterval(function() {
                        loadStats();
                        // Silently reload queue in background
                        const params = {};
                        if (currentStatus && currentStatus !== 'all') params.status = currentStatus;
                        if (currentPriority) params.priority = currentPriority;
                        if (currentConsent) params.consent = currentConsent;
                        if (currentDate) params.date = currentDate;
                        if (searchTerm) params.search = searchTerm;
                        $.get(ROUTES.queue, params, function(resp) {
                            renderProcedures(resp.data || []);
                        });
                    }, 90000);
                }

                /* ── Init ── */
                $(function() {
                    loadStats();
                    loadQueue();
                    startAutoRefresh();

                    // Highlight in_progress tab if there are any in-progress procedures
                    setTimeout(function() {
                        const ipCount = parseInt($('#count-in-progress').text()) || 0;
                        if (ipCount > 0 && currentStatus === 'requested') {
                            // Visual pulse on in_progress tab
                            $('#tab-in-progress').css('animation', 'none');
                        }
                    }, 2000);
                    $('#btn-edit-patient').on('click', function() {
                        if (selectedPatientId) {
                            showPatientFormModal('edit', selectedPatientId);
                        }
                    });
                });

            })();
