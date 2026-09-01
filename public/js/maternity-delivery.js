    // ═══════════════════════════════════════════════════════════════
    // IMMUNIZATION TAB
    // ═══════════════════════════════════════════════════════════════
    function loadImmunizationTab() {
        if (!currentEnrollmentId) {
            $('#immunization-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        if (typeof ImmunizationModule === 'undefined') {
            $('#immunization-content').html('<div class="alert alert-danger">Shared immunization module not loaded.</div>');
            return;
        }

        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
            if (!resp.success) return;

            const enrollment = resp.enrollment || {};
            const mother = enrollment.patient || null;
            const babies = enrollment.babies || [];

            if (!mother) {
                $('#immunization-content').html('<p class="text-muted text-center py-4">Mother patient record not found.</p>');
                return;
            }

            const people = [];
            people.push({
                key: 'mother',
                label: 'Mother',
                name: mother.user ? `${mother.user.surname || ''} ${mother.user.firstname || ''}`.trim() : 'Mother',
                scheduleUrl: `/maternity-workbench/enrollment/${currentEnrollmentId}/mother-schedule`,
                generateUrl: `/maternity-workbench/enrollment/${currentEnrollmentId}/generate-mother-schedule`,
                historyUrl: `/maternity-workbench/enrollment/${currentEnrollmentId}/mother-immunization-history`,
                patientId: mother.id
            });

            babies.forEach(function(baby, idx) {
                const babyName = baby.patient && baby.patient.user ?
                    `${baby.patient.user.surname || ''} ${baby.patient.user.firstname || ''}`.trim() :
                    `Baby ${idx + 1}`;
                people.push({
                    key: `baby-${baby.id}`,
                    label: `Baby ${idx + 1}`,
                    name: babyName,
                    scheduleUrl: `/maternity-workbench/baby/${baby.id}/schedule`,
                    generateUrl: `/maternity-workbench/baby/${baby.id}/generate-schedule`,
                    historyUrl: `/maternity-workbench/baby/${baby.id}/immunization-history`,
                    patientId: baby.patient_id
                });
            });

            let tabsHtml = '<ul class="nav nav-tabs mb-3" id="imm-person-tabs" role="tablist">';
            let panesHtml = '<div class="tab-content" id="imm-person-content">';

            people.forEach(function(person, idx) {
                let isActive = false;
                if (isBabyContext()) {
                    if (person.patientId == currentPatient) isActive = true;
                } else {
                    if (person.key === 'mother') isActive = true;
                }
                // Fallback for first person if no context match found
                if (!isActive && idx === 0 && !people.some(p => isBabyContext() ? p.patientId == currentPatient : p.key === 'mother')) {
                    isActive = true;
                }

                const activeClass = isActive ? 'active' : '';
                const showClass = isActive ? 'show active' : '';
                tabsHtml += `
                <li class="nav-item">
                    <a class="nav-link ${activeClass}" data-toggle="tab" href="#imm-person-${person.key}" role="tab">
                        <i class="mdi ${person.key === 'mother' ? 'mdi-mother-nurse' : 'mdi-baby-face'}"></i> ${person.label}
                    </a>
                </li>`;

                panesHtml += `
                <div class="tab-pane fade ${showClass}" id="imm-person-${person.key}" role="tabpanel">
                    <div class="card-modern mb-3">
                        <div class="card-header py-2"><h6 class="mb-0"><i class="mdi mdi-account-circle"></i> ${person.name}</h6></div>
                        <div class="card-body py-2">
                            <ul class="nav nav-pills mb-3" id="imm-subtabs-${person.key}" role="tablist">
                                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#imm-schedule-pane-${person.key}" role="tab"><i class="mdi mdi-calendar-check"></i> Schedule & Administer</a></li>
                                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#imm-history-pane-${person.key}" role="tab"><i class="mdi mdi-history"></i> History</a></li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="imm-schedule-pane-${person.key}" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <select class="form-control form-control-sm mr-2" id="imm-template-${person.key}" style="width: 230px;"><option value="">Select Schedule Template...</option></select>
                                            <button type="button" class="btn btn-sm btn-primary" id="imm-add-schedule-${person.key}"><i class="mdi mdi-plus"></i> Add Schedule</button>
                                        </div>
                                    </div>
                                    <div class="mb-3" id="imm-active-${person.key}"><div class="alert alert-info py-2 mb-2"><i class="mdi mdi-information"></i> Loading active schedules...</div></div>
                                    <div class="mb-3 d-flex flex-wrap align-items-center">
                                        <span class="mr-3 small text-muted">Status:</span>
                                        <span class="badge badge-secondary mr-2">Pending</span>
                                        <span class="badge badge-warning mr-2">Due</span>
                                        <span class="badge badge-danger mr-2">Overdue</span>
                                        <span class="badge badge-success mr-2">Administered</span>
                                        <span class="badge badge-info mr-2">Skipped</span>
                                        <span class="badge badge-dark">Contraindicated</span>
                                    </div>
                                    <div id="imm-schedule-${person.key}"><div class="text-center text-muted py-3">Loading schedule...</div></div>
                                </div>

                                <div class="tab-pane fade" id="imm-history-pane-${person.key}" role="tabpanel">
                                    <div class="d-flex justify-content-end mb-2">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary active" data-view="timeline" data-person="${person.key}" onclick="switchImmunizationHistoryView(this)"><i class="mdi mdi-chart-timeline-variant"></i> Timeline</button>
                                            <button type="button" class="btn btn-outline-primary" data-view="calendar" data-person="${person.key}" onclick="switchImmunizationHistoryView(this)"><i class="mdi mdi-calendar-month"></i> Calendar</button>
                                            <button type="button" class="btn btn-outline-primary" data-view="table" data-person="${person.key}" onclick="switchImmunizationHistoryView(this)"><i class="mdi mdi-table"></i> Table</button>
                                        </div>
                                    </div>
                                    <div id="imm-history-timeline-${person.key}" class="imm-history-view-pane-${person.key}"></div>
                                    <div id="imm-history-calendar-${person.key}" class="imm-history-view-pane-${person.key} d-none"></div>
                                    <div id="imm-history-table-wrap-${person.key}" class="imm-history-view-pane-${person.key} d-none">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover" id="imm-history-table-${person.key}" style="width:100%">
                                                <thead>
                                                    <tr><th>Date</th><th>Vaccine</th><th>Dose #</th><th>Dose</th><th>Batch</th><th>Site</th><th>Nurse</th></tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            });

            tabsHtml += '</ul>';
            panesHtml += '</div>';
            $('#immunization-content').html(tabsHtml + panesHtml);

            ImmunizationModule.initModalEvents();

            people.forEach(function(person) {
                ImmunizationModule.configure({
                    baseUrl: '/maternity-workbench',
                    csrfToken: CSRF_TOKEN,
                    currentPatientId: person.patientId,
                    productBatchesUrl: '/maternity-workbench/product-batches'
                });
                ImmunizationModule.loadTemplates(`#imm-template-${person.key}`);

                const reloadSchedule = function() {
                    ImmunizationModule.configure({
                        baseUrl: '/maternity-workbench',
                        csrfToken: CSRF_TOKEN,
                        currentPatientId: person.patientId,
                        productBatchesUrl: '/maternity-workbench/product-batches',
                        onScheduleReload: reloadSchedule,
                        onHistoryReload: reloadTimeline
                    });
                    ImmunizationModule.loadSchedule(person.patientId, `#imm-schedule-${person.key}`, person.scheduleUrl, {
                        activeSchedulesId: `#imm-active-${person.key}`
                    });
                };

                const reloadTimeline = function() {
                    ImmunizationModule.configure({
                        baseUrl: '/maternity-workbench',
                        csrfToken: CSRF_TOKEN,
                        currentPatientId: person.patientId,
                        productBatchesUrl: '/maternity-workbench/product-batches',
                        onScheduleReload: reloadSchedule,
                        onHistoryReload: reloadTimeline
                    });
                    ImmunizationModule.loadTimeline(person.patientId, `#imm-history-timeline-${person.key}`, person.historyUrl);
                };

                reloadSchedule();
                reloadTimeline();

                $(document).off(`click.immAdd${person.key}`, `#imm-add-schedule-${person.key}`)
                    .on(`click.immAdd${person.key}`, `#imm-add-schedule-${person.key}`, function() {
                        const templateId = $(`#imm-template-${person.key}`).val() || null;
                        ImmunizationModule.configure({
                            baseUrl: '/maternity-workbench',
                            csrfToken: CSRF_TOKEN,
                            currentPatientId: person.patientId,
                            productBatchesUrl: '/maternity-workbench/product-batches',
                            onScheduleReload: reloadSchedule,
                            onHistoryReload: reloadTimeline
                        });
                        ImmunizationModule.generateSchedule(person.patientId, person.generateUrl, templateId, function() {
                            reloadSchedule();
                        });
                    });
            });
        });
    }

    function switchImmunizationHistoryView(btn) {
        const person = $(btn).data('person');
        const view = $(btn).data('view');
        const paneClass = `.imm-history-view-pane-${person}`;

        $(`#imm-history-pane-${person} .btn`).removeClass('active');
        $(btn).addClass('active');
        $(paneClass).addClass('d-none');

        const historyUrl = person === 'mother' ?
            `/maternity-workbench/enrollment/${currentEnrollmentId}/mother-immunization-history` :
            `/maternity-workbench/baby/${person.replace('baby-', '')}/immunization-history`;

        if (view === 'timeline') {
            $(`#imm-history-timeline-${person}`).removeClass('d-none');
            ImmunizationModule.loadTimeline(null, `#imm-history-timeline-${person}`, historyUrl);
        } else if (view === 'calendar') {
            $(`#imm-history-calendar-${person}`).removeClass('d-none');
            ImmunizationModule.loadCalendar(null, `#imm-history-calendar-${person}`, historyUrl);
        } else {
            $(`#imm-history-table-wrap-${person}`).removeClass('d-none');
            ImmunizationModule.loadHistoryTable(null, `#imm-history-table-wrap-${person}`, `#imm-history-table-${person}`, historyUrl);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // NOTES TAB (shares pattern with nursing notes)
    // ═══════════════════════════════════════════════════════════════
    function loadNotesTab() {
        if (!currentEnrollmentId) {
            $('#maternity-notes-timeline').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/notes`, { patient_id: currentPatient }, function(resp) {
            if (!resp.success) return;
            _notesCache = resp.notes; // cache for edit

            // Populate note types dropdown if available (saves other notes type primarily)
            if (resp.note_types) {
                let opts = '';
                resp.note_types.forEach(t => {
                    const selected = t.id == 5 ? 'selected' : '';
                    opts += `<option value="${t.id}" ${selected}>${t.name}</option>`;
                });
                $('#maternity-note-type-select').html(opts);
                // Also cache window._matNoteTypeOptions for compatibility
                let oldOpts = '';
                resp.note_types.forEach(t => oldOpts += `<option value="${t.id}">${t.name}</option>`);
                window._matNoteTypeOptions = oldOpts;
            }

            let html = '';
            if (resp.notes.length === 0) {
                html += '<p class="text-muted text-center py-4">No notes yet</p>';
            } else {
                resp.notes.forEach(function(n) {
                    const actions = n.can_edit ? `<div class="mt-1"><button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editNote(${n.id})" title="Edit note"><i class="mdi mdi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteNote(${n.id})" title="Delete note"><i class="mdi mdi-delete"></i></button></div>` : '';
                    const draftBadge = n.completed === false ? '<span class="badge badge-warning mr-1">Draft</span>' : '';
                    html += `<div class="note-card border rounded-3 p-3 mb-3 bg-white shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="note-author fw-semibold text-dark">${n.created_by} ${draftBadge}<span class="badge bg-secondary ms-1">${n.type}</span></span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="note-time text-muted small">${n.time_ago}</span>
                            ${actions}
                        </div>
                    </div>
                    <div class="note-body text-secondary">${n.note}</div>
                </div>`;
                });
            }
            $('#maternity-notes-timeline').html(html);
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // AUDIT TAB
    // ═══════════════════════════════════════════════════════════════
    function loadAuditTab() {
        if (!currentEnrollmentId) {
            $('#audit-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        $('#audit-content').html('<div class="text-center p-4 text-muted"><i class="mdi mdi-loading mdi-spin mdi-36px"></i><br>Loading audit trail...</div>');

        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/audit-trail`, function(resp) {
            if (!resp.success) {
                $('#audit-content').html('<p class="text-danger text-center py-3">Failed to load audit trail</p>');
                return;
            }

            if (!resp.audits || resp.audits.length === 0) {
                $('#audit-content').html('<p class="text-muted text-center py-3">No audit records yet</p>');
                return;
            }

            let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-shield-search"></i> Audit Trail (${resp.total})</h5>
            <span class="badge bg-secondary">Enrollment #${resp.enrollment_id}</span>
        </div>`;

            html += '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Date/Time</th><th>Module</th><th>Event</th><th>User</th><th>Changes</th></tr></thead><tbody>';
            resp.audits.forEach(function(a) {
                const oldVals = a.old_values ? JSON.stringify(a.old_values) : '';
                const newVals = a.new_values ? JSON.stringify(a.new_values) : '';
                const changes = `${oldVals ? '<div><strong>Old:</strong> ' + oldVals + '</div>' : ''}${newVals ? '<div><strong>New:</strong> ' + newVals + '</div>' : ''}` || '-';
                html += `<tr>
                <td>${a.created_at || '-'}</td>
                <td><span class="badge bg-light text-dark">${a.module}</span></td>
                <td><span class="badge bg-info text-dark">${(a.event || '').toUpperCase()}</span></td>
                <td>${a.user || 'System'}</td>
                <td class="small">${changes}</td>
            </tr>`;
            });
            html += '</tbody></table></div>';

            $('#audit-content').html(html);
        }).fail(function() {
            $('#audit-content').html('<p class="text-danger text-center py-3">Failed to load audit trail</p>');
        });
    }

    function initMaternityNotesCKEditor() {
        WorkbenchNotesKit.initEditor({
            prefix: 'maternity',
            editorSelector: '#maternity-note-editor',
            formSelector: '#maternity-note-form',
            statusSelector: '#maternity-note-autosave-status',
            csrfToken: CSRF_TOKEN,
            getSaveUrl: function(patientId, enrollmentId) {
                return _editId 
                    ? `/maternity-workbench/note/${_editId}` 
                    : `/maternity-workbench/enrollment/${enrollmentId}/note`;
            },
            getMethod: function() {
                return _editId ? 'PUT' : 'POST';
            },
            getPatientId: function() {
                return currentPatient;
            },
            getEnrollmentId: function() {
                return currentEnrollmentId;
            },
            noteTypeId: 5,
            onSaveSuccess: function() {
                _editId = null;
                _editMode = null;
                loadNotesTab();
                // Switch to History sub-tab
                $('#notes-history-tab-link').tab('show');
            }
        });
    }

    // Ensure editor is initialized when tab shown
    $('a[data-toggle="tab"][href="#notes-tab"]').on('shown.bs.tab', function (e) {
        initMaternityNotesCKEditor();
    });
    $('a[data-toggle="tab"][href="#notes-add"]').on('shown.bs.tab', function (e) {
        initMaternityNotesCKEditor();
    });
    // Also try to init on page load after a brief delay
    setTimeout(initMaternityNotesCKEditor, 1000);

    function editNote(id) {
        const n = _notesCache.find(x => x.id === id);
        if (!n) {
            toastr.error('Note not found');
            return;
        }
        if (!n.can_edit) {
            toastr.warning('This note can no longer be edited');
            return;
        }
        
        _editId = id;
        _editMode = 'note';
        
        // Show the Add Note tab
        $('#notes-add-tab').tab('show');
        
        // Populate the editor content
        const editor = WorkbenchNotesKit.editors['maternity'];
        if (editor) {
            editor.setData(n.note || '');
        }
        
        // Populate note type dropdown
        if (n.note_type_id) {
            $('#maternity-note-type-select').val(n.note_type_id);
        }
    }

    function deleteNote(id) {
        if (!confirm('Delete this note?')) return;
        $.ajax({
            url: wbUrl(`/maternity-workbench/note/${id}`),
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(r) {
                if (r.success) {
                    toastr.success(r.message || 'Note deleted');
                    loadNotesTab();
                } else toastr.error(r.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to delete note');
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // REPORTS
    // ═══════════════════════════════════════════════════════════════
    function showReports() {
        hideAllViews();
        $('#reports-view').addClass('active').css('display', 'flex');
        if (window.innerWidth < 768) {
            $('#left-panel').addClass('hidden');
            $('#main-workspace').addClass('active');
        }
        
        if (!window.matReportsInitialized) {
            initMaternityReportFilters();
            window.matReportsInitialized = true;
        }

        loadMaternityReportsData();
    }

    function initMaternityReportFilters() {
        $('.date-preset-btn').on('click', function() {
            $('.date-preset-btn').removeClass('active');
            $(this).addClass('active');
            
            const preset = $(this).data('preset');
            const today = moment();
            
            if (preset === 'all') {
                $('#mat-report-date-from').val('');
                $('#mat-report-date-to').val('');
            } else if (preset === 'today') {
                $('#mat-report-date-from').val(today.format('YYYY-MM-DD'));
                $('#mat-report-date-to').val(today.format('YYYY-MM-DD'));
            } else if (preset === 'week') {
                $('#mat-report-date-from').val(today.clone().startOf('isoWeek').format('YYYY-MM-DD'));
                $('#mat-report-date-to').val(today.clone().endOf('isoWeek').format('YYYY-MM-DD'));
            } else if (preset === 'month') {
                $('#mat-report-date-from').val(today.clone().startOf('month').format('YYYY-MM-DD'));
                $('#mat-report-date-to').val(today.clone().endOf('month').format('YYYY-MM-DD'));
            } else if (preset === 'year') {
                $('#mat-report-date-from').val(today.clone().startOf('year').format('YYYY-MM-DD'));
                $('#mat-report-date-to').val(today.clone().endOf('year').format('YYYY-MM-DD'));
            }
            
            loadMaternityReportsData();
        });

        $('#btn-apply-mat-dates').on('click', function() {
            $('.date-preset-btn').removeClass('active');
            loadMaternityReportsData();
        });
    }

    function loadMaternityReportsData() {
        const startDate = $('#mat-report-date-from').val();
        const endDate = $('#mat-report-date-to').val();
        const params = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        const qs = new URLSearchParams(params).toString();

        $('#reports-summary-cards').html('<div class="col-12 text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-24px"></i> Loading...</div>');
        $('#reports-clinical-cards').html('<div class="col-lg-6 mb-3"><div class="card-modern shadow-sm h-100"><div class="card-header bg-white py-2"><h6 class="mb-0">Immunization Coverage</h6></div><div class="card-body" id="imm-coverage-body"></div></div></div><div class="col-lg-6 mb-3"><div class="card-modern shadow-sm h-100"><div class="card-header bg-white py-2"><h6 class="mb-0">ANC Defaulters</h6></div><div class="card-body" id="defaulters-body"></div></div></div><div class="col-12 mt-3"><div class="card-modern shadow-sm"><div class="card-header bg-white py-2"><h6 class="mb-0">High Risk Register</h6></div><div class="card-body" id="high-risk-body"></div></div></div>');
        
        $('#admissions-total-kpi').text('Loading...');
        $('#admissions-alos-kpi').text('Loading...');

        // Overview
        $.get(wbRoute('maternity-workbench.reports.summary', '/maternity-workbench/reports/summary') + '?' + qs, function(resp) {
            if (!resp.success) return;
            const d = resp.data;
            let html = '<div class="row mb-3">';
            const stats = [
                { label: 'Total Enrollments', value: d.total_enrollments, icon: 'mdi-clipboard-list', cls: 'mat-stat-pink' },
                { label: 'Active ANC', value: d.active_enrollments, icon: 'mdi-mother-nurse', cls: 'mat-stat-green' },
                { label: 'Deliveries', value: d.deliveries_filtered, icon: 'mdi-baby-carriage', cls: 'mat-stat-blue' },
                { label: 'Total Babies', value: d.total_babies, icon: 'mdi-baby-face', cls: 'mat-stat-orange' },
            ];
            stats.forEach(s => {
                html += `<div class="col-lg-3 col-md-6 mb-3"><div class="mat-stat-card ${s.cls}"><div class="mat-stat-icon"><i class="mdi ${s.icon}" style="font-size:1.5rem;"></i></div><div><div class="mat-stat-value">${s.value}</div><div class="mat-stat-label">${s.label}</div></div></div></div>`;
            });
            html += '</div>';
            html += '<div class="row"><div class="col-12 mb-3"><div class="card-modern shadow-sm"><div class="card-header bg-white py-2"><h6 class="mb-0">Delivery Stats</h6></div><div class="card-body" id="delivery-stats-body"><p class="text-muted">Loading...</p></div></div></div></div>';
            $('#reports-summary-cards').html(html);

            // Load Deliveries Chart
            $.get(wbRoute('maternity-workbench.reports.delivery-stats', '/maternity-workbench/reports/delivery-stats') + '?' + qs, function(r) {
                if (!r.success) return;
                const types = Object.keys(r.by_type);
                const counts = Object.values(r.by_type);
                const chartColors = ['#e91e63', '#4caf50', '#2196f3', '#ff9800', '#9c27b0', '#00bcd4', '#795548'];

                let tHtml = '<div class="row"><div class="col-md-6"><div style="position:relative; height:250px;"><canvas id="delivery-donut-chart"></canvas></div></div><div class="col-md-6">';
                tHtml += '<table class="table table-sm mb-0"><thead><tr><th>Type</th><th>Count</th><th>%</th></tr></thead><tbody>';
                const total = counts.reduce((a, b) => a + b, 0) || 1;
                types.forEach((type, i) => {
                    tHtml += `<tr><td><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${chartColors[i % chartColors.length]};margin-right:6px;"></span>${type.toUpperCase()}</td><td class="fw-bold">${r.by_type[type]}</td><td>${((r.by_type[type] / total) * 100).toFixed(1)}%</td></tr>`;
                });
                tHtml += '</tbody></table>';
                tHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'delivery_stats')"><i class="mdi mdi-download"></i> Export CSV</button>`;
                tHtml += '</div></div>';
                $('#delivery-stats-body').html(tHtml);

                if (typeof Chart !== 'undefined' && types.length > 0) {
                    new Chart(document.getElementById('delivery-donut-chart').getContext('2d'), {
                        type: 'doughnut',
                        data: { labels: types.map(t => t.toUpperCase()), datasets: [{ data: counts, backgroundColor: chartColors.slice(0, types.length), borderWidth: 2, borderColor: '#fff' }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, usePointStyle: true, padding: 8 } } } }
                    });
                }
            });
        });

        // Immunization
        $.get(wbRoute('maternity-workbench.reports.immunization-coverage', '/maternity-workbench/reports/immunization-coverage') + '?' + qs, function(r) {
            if (!r.success || !Object.keys(r.coverage).length) {
                $('#imm-coverage-body').html('<p class="text-muted mb-0">No data</p>');
                return;
            }
            const vaccines = Object.keys(r.coverage);
            const givenArr = vaccines.map(v => r.coverage[v].given);
            const pendingArr = vaccines.map(v => r.coverage[v].total - r.coverage[v].given);

            let cHtml = '<div style="position:relative; height:250px;"><canvas id="imm-bar-chart"></canvas></div>';
            cHtml += '<table class="table table-sm mt-2 mb-0"><thead><tr><th>Vaccine</th><th>Given</th><th>Total</th><th>%</th></tr></thead><tbody>';
            vaccines.forEach(vaccine => {
                const data = r.coverage[vaccine];
                const color = data.percentage >= 80 ? 'text-success' : (data.percentage >= 50 ? 'text-warning' : 'text-danger');
                cHtml += `<tr><td>${vaccine}</td><td>${data.given}</td><td>${data.total}</td><td class="fw-bold ${color}">${data.percentage}%</td></tr>`;
            });
            cHtml += '</tbody></table>';
            cHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'immunization_coverage')"><i class="mdi mdi-download"></i> Export CSV</button>`;
            $('#imm-coverage-body').html(cHtml);

            if (typeof Chart !== 'undefined') {
                new Chart(document.getElementById('imm-bar-chart').getContext('2d'), {
                    type: 'bar',
                    data: { labels: vaccines, datasets: [{ label: 'Given', data: givenArr, backgroundColor: 'rgba(76,175,80,0.7)', borderColor: '#4caf50', borderWidth: 1 }, { label: 'Pending', data: pendingArr, backgroundColor: 'rgba(255,152,0,0.5)', borderColor: '#ff9800', borderWidth: 1 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { font: { size: 10 }, usePointStyle: true } } }, scales: { x: { stacked: true, ticks: { font: { size: 9 } } }, y: { stacked: true, beginAtZero: true } } }
                });
            }
        });

        // Defaulters
        $.get(wbRoute('maternity-workbench.reports.anc-defaulters', '/maternity-workbench/reports/anc-defaulters') + '?' + qs, function(r) {
            if (!r.success) return;
            if (r.defaulters.length === 0) {
                $('#defaulters-body').html('<p class="text-muted mb-0">No defaulters</p>');
                return;
            }
            let dHtml = '<div style="position:relative; height:' + Math.max(200, r.defaulters.length * 30) + 'px;"><canvas id="defaulters-bar-chart"></canvas></div>';
            dHtml += '<table class="table table-sm mt-2 mb-0"><thead><tr><th>Name</th><th>File No</th><th>Missed</th><th>Days Overdue</th></tr></thead><tbody>';
            r.defaulters.forEach(d => {
                dHtml += `<tr><td>${d.name}</td><td>${d.file_no}</td><td>${d.missed_date}</td><td class="text-danger fw-bold">${d.days_overdue}</td></tr>`;
            });
            dHtml += '</tbody></table>';
            dHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'anc_defaulters')"><i class="mdi mdi-download"></i> Export CSV</button>`;
            $('#defaulters-body').html(dHtml);

            if (typeof Chart !== 'undefined') {
                new Chart(document.getElementById('defaulters-bar-chart').getContext('2d'), {
                    type: 'bar',
                    data: { labels: r.defaulters.map(d => d.name.length > 20 ? d.name.substr(0, 18) + '...' : d.name), datasets: [{ label: 'Days Overdue', data: r.defaulters.map(d => d.days_overdue), backgroundColor: r.defaulters.map(d => d.days_overdue > 30 ? 'rgba(220,53,69,0.7)' : (d.days_overdue > 14 ? 'rgba(255,152,0,0.7)' : 'rgba(255,193,7,0.7)')), borderWidth: 1 }] },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, title: { display: true, text: 'Days' } } } }
                });
            }
        });

        // High Risk Register
        $.get(wbRoute('maternity-workbench.reports.high-risk-register', '/maternity-workbench/reports/high-risk-register') + '?' + qs, function(r) {
            if (!r.success) return;
            if (r.register.length === 0) {
                $('#high-risk-body').html('<p class="text-muted mb-0">No high-risk patients</p>');
                return;
            }
            const riskDist = {};
            r.register.forEach(p => {
                const lvl = p.risk_level || 'high';
                riskDist[lvl] = (riskDist[lvl] || 0) + 1;
            });

            let hHtml = '';
            if (Object.keys(riskDist).length > 0) {
                const riskLabels = Object.keys(riskDist).map(k => k.replace('_', ' ').toUpperCase());
                const riskCounts = Object.values(riskDist);
                const riskClrs = Object.keys(riskDist).map(k => k === 'very_high' ? '#dc3545' : (k === 'high' ? '#fd7e14' : '#ffc107'));
                hHtml += '<div class="d-flex justify-content-center mb-2">';
                riskLabels.forEach((label, i) => { hHtml += `<span class="badge me-2" style="background:${riskClrs[i]}; font-size:0.75rem;">${label}: ${riskCounts[i]}</span>`; });
                hHtml += '</div>';
            }

            hHtml += '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>File No</th><th>Risk</th><th>Factors</th><th>Status</th></tr></thead><tbody>';
            r.register.forEach(p => {
                const risks = Array.isArray(p.risk_factors) ? p.risk_factors.join(', ') : (p.risk_factors || 'N/A');
                const riskClr = (p.risk_level === 'very_high') ? 'bg-danger' : 'bg-warning text-dark';
                hHtml += `<tr><td>${p.name}</td><td>${p.file_no}</td><td><span class="badge ${riskClr}">${(p.risk_level || 'high').replace('_', ' ')}</span></td><td class="small">${risks}</td><td><span class="enrollment-badge ${p.status}">${p.status}</span></td></tr>`;
            });
            hHtml += '</tbody></table></div>';
            hHtml += `<button class="btn btn-sm btn-outline-secondary mt-2" onclick="exportReportTable(this, 'high_risk_register')"><i class="mdi mdi-download"></i> Export CSV</button>`;
            $('#high-risk-body').html(hHtml);
        });

        // Admissions Stats
        $.get(wbRoute('maternity-workbench.reports.admissions-stats', '/maternity-workbench/reports/admissions-stats') + '?' + qs, function(resp) {
            if (resp.success) {
                $('#admissions-total-kpi').text(resp.data.total_admissions);
                $('#admissions-alos-kpi').text(resp.data.average_length_of_stay + ' Days');
                
                const classes = Object.keys(resp.data.class_breakdown);
                const classCounts = Object.values(resp.data.class_breakdown);
                
                if (window.admissionsClassChart) window.admissionsClassChart.destroy();
                
                if (typeof Chart !== 'undefined' && classes.length > 0) {
                    window.admissionsClassChart = new Chart(document.getElementById('admissions-class-chart').getContext('2d'), {
                        type: 'pie',
                        data: { 
                            labels: classes.map(c => c.toUpperCase()), 
                            datasets: [{ 
                                data: classCounts, 
                                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6610f2'] 
                            }] 
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            }
        });
    }

    // Export table to CSV
    function exportReportTable(btn, filename) {
        const card = $(btn).closest('.card-body');
        const table = card.find('table');
        if (!table.length) {
            toastr.warning('No table to export');
            return;
        }

        let csv = '';
        table.find('tr').each(function() {
            const row = [];
            $(this).find('th, td').each(function() {
                row.push('"' + $(this).text().replace(/"/g, '""').trim() + '"');
            });
            csv += row.join(',') + '\n';
        });

        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `maternity_${filename}_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
        toastr.success('CSV exported');
    }

    // ═══════════════════════════════════════════════════════════════
    // EVENT BINDINGS (SHARED pattern with nursing workbench)
    // ═══════════════════════════════════════════════════════════════
    $(document).ready(function() {
        // Initialize BillingKit
        if (window.BillingKit && window.BILLING_KIT_CONFIG) {
            BillingKit.init(window.BILLING_KIT_CONFIG);
        }

        // Initialize shared patient search module
        PatientSearch.init();

        // Load queue counts on page load
        loadQueueCounts();

        // Handle queue filter deep-linking from dashboard
        const urlParams = new URLSearchParams(window.location.search);
        const queueFilter = urlParams.get('queue_filter');
        if (queueFilter) {
             // For maternity, 'anc', 'edd', 'postnatal', 'overdue', 'high-risk', 'due-visit'
             // are handled by showQueue(filter)
             setTimeout(() => {
                 showQueue(queueFilter);
             }, 500);
        }

        // Queue item clicks (SHARED)
        $('.queue-item').on('click', function() {
            const filter = $(this).data('filter');
            if (filter) showQueue(filter);
        });

        // Refresh queues
        $('#refresh-queues-btn').on('click', loadQueueCounts);

        // Close queue (SHARED)
        $('#btn-close-queue').on('click', hideQueue);

        // View queue from empty state
        $('#view-queue-btn').on('click', function() {
            showQueue('active-anc');
        });

        // Workspace tab switching (SHARED)
        $(document).on('click', '.workspace-tab', function() {
            const tab = $(this).data('tab');
            switchWorkspaceTab(tab);
        });

        // Mobile back button (SHARED)
        $('#btn-back-to-search').on('click', function() {
            hideAllViews();
            $('#empty-state').show();
            $('#main-workspace').removeClass('active');
            $('#left-panel').removeClass('hidden');
        });

        // View work pane (SHARED)
        $('#btn-view-work-pane').on('click', function() {
            if (currentPatient) {
                hideAllViews();
                $('#patient-header').addClass('active');
                $('#workspace-content').show().addClass('active');
            }
            $('#left-panel').addClass('hidden');
            $('#main-workspace').addClass('active');
        });

        // Toggle search (SHARED)
        $('#btn-toggle-search').on('click', function() {
            $('#left-panel').toggleClass('hidden');
        });

        // Clinical Context (SHARED — same as nursing workbench)
        $('#btn-clinical-context').on('click', function() {
            if (!currentPatient) {
                toastr.warning('Please select a patient first');
                return;
            }
            ClinicalContext.load(currentPatient);
        });

        // Quick action: New ANC patient enrollment
        $('#btn-enroll-patient').on('click', function() {
            openAncPatientRegistration();
        });

        // Quick action: Quick vitals
        $('#btn-quick-vitals').on('click', function() {
            if (currentPatient) switchWorkspaceTab('vitals');
        });

        // Reports button
        $('#btn-maternity-reports').on('click', showReports);

        // Print buttons
        $('#btn-print-anc-card').on('click', function() {
            if (!currentEnrollmentId) {
                toastr.warning('No enrollment selected');
                return;
            }
            window.open(`/maternity-workbench/enrollment/${currentEnrollmentId}/print-anc-card`, '_blank');
        });

        $('#btn-print-road-card').on('click', function() {
            if (!currentEnrollmentId) {
                toastr.warning('No enrollment selected');
                return;
            }
            window.open(`/maternity-workbench/enrollment/${currentEnrollmentId}/print-road-health-card`, '_blank');
        });

        $('#btn-maternity-audit').on('click', function() {
            if (!currentEnrollmentId) {
                toastr.warning('No enrollment selected');
                return;
            }
            switchWorkspaceTab('audit');
        });

        // Quick action: Discharge (Maternity Enrollment)
        $('#btn-discharge-patient').on('click', function() {
            showDischargeModal();
        });

        // Quick action: Admit to Ward
        $('#btn-admit-to-ward').on('click', function() {
            if (!currentPatientData) return;
            openAdmitModal(currentPatientData.id, currentPatientData.name, null);
        });

        // Quick action: Discharge from Ward
        $('#btn-discharge-from-ward').on('click', function() {
            if (!currentPatientData || !currentPatientData.admission_request) return;
            openDischargeModal(currentPatientData.id, currentPatientData.name, currentPatientData.admission_request.id);
        });

        $('#btn-close-reports').on('click', function() {
            $('#reports-view').removeClass('active').hide();
            if (currentPatient) {
                $('#patient-header').addClass('active');
                $('#workspace-content').show().addClass('active');
            } else {
                $('#empty-state').show();
            }
        });

        // Auto-refresh queues every 5 minutes
        setInterval(loadQueueCounts, 300000);
    });

    // ═══════════════════════════════════════════════════════════════
    // DISCHARGE ENROLLMENT
    // ═══════════════════════════════════════════════════════════════
    function showDischargeModal() {
        if (!currentEnrollmentId || !currentEnrollment) {
            toastr.warning('No enrollment selected');
            return;
        }
        if (['completed', 'transferred', 'deceased'].includes(currentEnrollment.status)) {
            toastr.info('This enrollment is already ' + currentEnrollment.status);
            return;
        }

        // Reset modal state
        $('#discharge-outcome-summary').val('');
        $('#discharge-warnings-container').hide().html('');
        $('#btn-confirm-discharge').prop('disabled', true).data('confirmed', false);
        $('#discharge-patient-name').text($('#patient-name').text().split('(')[0].trim());
        $('#discharge-current-status').html(`<span class="enrollment-badge ${currentEnrollment.status}">${currentEnrollment.status.toUpperCase()}</span>`);

        // Phase 1: Fetch warnings
        $.ajax({
            url: wbUrl(`/maternity-workbench/enrollment/${currentEnrollmentId}/discharge`),
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            data: {
                outcome_summary: 'checking warnings',
                confirm: 0
            },
            success: function(resp) {
                if (resp.confirm && resp.warnings && resp.warnings.length> 0) {
                    let wHtml = '<div class="alert alert-warning py-2 px-3 mb-0"><h6 class="mb-2 small fw-bold"><i class="mdi mdi-alert"></i> Please review before discharging:</h6><ul class="mb-0 small">';
                    resp.warnings.forEach(w => {
                        wHtml += `<li>${w}</li>`;
                    });
                    wHtml += '</ul></div>';
                    $('#discharge-warnings-container').html(wHtml).show();
                }
                $('#btn-confirm-discharge').prop('disabled', false).data('confirmed', true);
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Cannot discharge this enrollment';
                toastr.error(msg);
                $('#modal-discharge').modal('hide');
            }
        });

        const dischargeModalEl = document.getElementById('dischargeModal');
        new bootstrap.Modal(dischargeModalEl).show();

        // Bind confirm handler (off first to avoid duplicates)
        $('#btn-confirm-discharge').off('click').on('click', function() {
            const summary = $('#discharge-outcome-summary').val().trim();
            if (summary.length < 5) {
                toastr.warning('Outcome summary must be at least 5 characters');
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Discharging...');

            $.ajax({
                url: wbUrl(`/maternity-workbench/enrollment/${currentEnrollmentId}/discharge`),
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                data: {
                    outcome_summary: summary,
                    confirm: 1
                },
                success: function(resp) {
                    if (resp.success) {
                        toastr.success(resp.message || 'Patient discharged successfully');
                        bootstrap.Modal.getInstance(dischargeModalEl).hide();
                        // Update local state
                        currentEnrollment.status = 'completed';
                        currentEnrollment.completed_at = resp.enrollment?.completed_at || new Date().toISOString().split('T')[0];
                        currentEnrollment.outcome_summary = summary;
                        // Refresh UI
                        renderEnrollmentDetails();
                        populateOverviewTab(currentPatientData);
                        loadQueueCounts();
                        // Update patient header badge
                        displayPatientInfo(currentPatientData);
                        // Hide discharge button in quick actions
                        $('#btn-discharge-patient').hide();
                    } else {
                        toastr.error(resp.message || 'Discharge failed');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to discharge');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="mdi mdi-exit-run"></i> Discharge Patient');
                }
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // LAB & IMAGING RESULT ENTRY / EDIT  (shared InvestResultEntry)
    // ═══════════════════════════════════════════════════════════════

    // Lab result entry (called from investigation history DataTable "Enter Result" button)
    function enterLabResult(requestId) {
        window._investResultContext = { type: 'lab', id: requestId };
        InvestResultEntry.enterResult(
            requestId,
            `/lab-workbench/lab-service-requests/${requestId}`,
            `/lab-workbench/lab-service-requests/${requestId}/attachments`,
            wbRoute('lab.saveResult', '/lab/saveResult')
        );
    }

    // Lab result edit (called from investigation history DataTable "Edit" button)
    function editLabResult(obj) {
        const requestId = $(obj).data('id');
        InvestResultEntry.editResult(
            requestId,
            `/lab-workbench/lab-service-requests/${requestId}`,
            `/lab-workbench/lab-service-requests/${requestId}/attachments`,
            wbRoute('lab.saveResult', '/lab/saveResult')
        );
    }

    // Imaging result entry (called from imaging history DataTable "Enter Result" button)
    function enterImagingResult(requestId) {
        window._investResultContext = { type: 'imaging', id: requestId };
        InvestResultEntry.enterResult(
            requestId,
            `/imaging-workbench/imaging-service-requests/${requestId}`,
            `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
            wbRoute('imaging.saveResult', '/imaging/saveResult')
        );
    }

    // Imaging result edit (called from imaging history DataTable "Edit" button)
    function editImagingResult(obj) {
        const requestId = $(obj).data('id');
        InvestResultEntry.editResult(
            requestId,
            `/imaging-workbench/imaging-service-requests/${requestId}`,
            `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
            wbRoute('imaging.saveResult', '/imaging/saveResult')
        );
    }

    var _PI_LAB_REQ_APPROVAL = '';
    var _PI_IMG_REQ_APPROVAL = '';
    var _PI_DR_SELF_LAB      = '';
    var _PI_NR_SELF_LAB      = '';
    var _PI_DR_SELF_IMG      = '';
    var _PI_NR_SELF_IMG      = '';

    function _autoApproveIfEnabled(requestId, type) {
        if ($('#invest_res_is_edit').val() == '1') { return; }
        var reqApproval = (type === 'lab') ? _PI_LAB_REQ_APPROVAL : _PI_IMG_REQ_APPROVAL;
        if (!reqApproval) { return; }
        var canSelf = (type === 'lab') ? (_PI_DR_SELF_LAB || _PI_NR_SELF_LAB) : (_PI_DR_SELF_IMG || _PI_NR_SELF_IMG);
        if (!canSelf) { return; }
        var url = (type === 'lab') ? '/lab-workbench/self-approve/' + requestId : '/imaging-workbench/self-approve/' + requestId;
        $.post(url, { _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function (res) {
                if (res && res.success) { toastr.success('Result approved automatically.'); }
                else { toastr.warning('Result saved. Auto-approval failed: ' + ((res && res.message) || '')); }
            })
            .fail(function () { toastr.warning('Result saved but auto-approval could not be completed.'); });
    }

    // Initialize shared result entry module — refresh maternity DataTables on save
    InvestResultEntry.bindFormSubmit(function() {
        if ($.fn.DataTable.isDataTable('#mco_lab_history_list')) {
            $('#mco_lab_history_list').DataTable().ajax.reload(null, false);
        }
        if ($.fn.DataTable.isDataTable('#mco_imaging_history_list')) {
            $('#mco_imaging_history_list').DataTable().ajax.reload(null, false);
        }
        var ctx = window._investResultContext;
        if (ctx) { _autoApproveIfEnabled(ctx.id, ctx.type); window._investResultContext = null; }
    });

    // setResViewInModal, PrintElem, getFileIcon now provided by invest_res_view_js partial
    function openStoreContextOverride() {
        $('#storeContextOverrideModal').modal('show');
    }

    function confirmStoreContextOverride() {
        const storeId = $('#ctx-store-select').val();
        if (!storeId) {
            $('#ctx-override-error').text('Please select a store.').removeClass('d-none');
            return;
        }
        $('#ctx-override-error').addClass('d-none');
        $('#ctx-override-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Applying...');
        $.ajax({
            url: wbRoute('store-context.set', '/store-context/set'),
            method: 'POST',
            data: {
                store_id: storeId,
                context: 'ward',
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')
            },
            success: function() {
                window.location.reload();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message ?? 'Failed to update store context.';
                $('#ctx-override-error').text(msg).removeClass('d-none');
                $('#ctx-override-btn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Apply');
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════
       PARTOGRAPH TAB — enrollment-level (pre & post delivery)
       ══════════════════════════════════════════════════════════════ */

    let matPartographEntries = [];     // cached entries for edit pre-fill
    let matPartographChartInstance = null;

    function loadMatPartographTab() {
        if (!currentEnrollment) {
            $('#partograph-tab-content').html('<p class="text-muted text-center py-3">Select an enrolled patient to view partograph.</p>');
            return;
        }
        const enrollmentId = currentEnrollment.id;
        $('#partograph-tab-content').html('<div class="text-center py-4"><div class="spinner-border text-success"></div></div>');

        $.get(`/maternity-workbench/enrollment/${enrollmentId}/maternity-partograph`)
            .done(function(res) {
                if (!res.success) {
                    $('#partograph-tab-content').html('<div class="alert alert-danger">Failed to load partograph data.</div>');
                    return;
                }
                matPartographEntries = res.entries || [];
                renderMatPartographTab(enrollmentId, res);
            })
            .fail(function() {
                $('#partograph-tab-content').html('<div class="alert alert-danger">Server error loading partograph.</div>');
            });
    }

    function renderMatPartographTab(enrollmentId, res) {
        const entries       = res.entries || [];
        const legacyEntries = res.legacy_entries || [];
        const preEntries    = entries.filter(e => e.phase === 'pre_delivery');
        const postEntries   = entries.filter(e => e.phase === 'post_delivery');
        // All entries combined for the chart (chronological)
        const allForChart   = [...entries, ...legacyEntries].sort((a, b) =>
            new Date(a.recorded_at) - new Date(b.recorded_at));

        const legacyTab = legacyEntries.length
            ? `<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#mparto-legacy-pane">
                Delivery Record <span class="badge bg-warning text-dark ms-1">${legacyEntries.length}</span>
               </a></li>`
            : '';

        let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="mdi mdi-chart-timeline-variant text-success me-1"></i> Partograph</h6>
            <div>
                <button class="btn btn-sm btn-success me-1" onclick="showMatPartographForm('pre_delivery')">
                    <i class="mdi mdi-plus"></i> Labour Entry
                </button>
                ${res.has_delivery ? `<button class="btn btn-sm btn-outline-success" onclick="showMatPartographForm('post_delivery')"><i class="mdi mdi-plus"></i> Post-Delivery Entry</button>` : ''}
            </div>
        </div>
        <ul class="nav nav-tabs mb-3" id="matParto-subtabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#mparto-labour-pane">
                Labour Monitoring <span class="badge bg-success ms-1">${preEntries.length}</span>
            </a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#mparto-post-pane">
                Post-Delivery <span class="badge bg-secondary ms-1">${postEntries.length}</span>
            </a></li>
            ${legacyTab}
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#mparto-chart-pane">
                <i class="mdi mdi-chart-line"></i> Chart
            </a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="mparto-labour-pane">
                ${buildMatPartoTable(preEntries, enrollmentId, 'Labour Monitoring', true)}
            </div>
            <div class="tab-pane fade" id="mparto-post-pane">
                ${buildMatPartoTable(postEntries, enrollmentId, 'Post-Delivery', true)}
            </div>
            ${legacyEntries.length ? `<div class="tab-pane fade" id="mparto-legacy-pane">
                <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-2">
                    <i class="mdi mdi-lock-outline"></i>
                    <span>These entries were recorded via the Delivery tab. They are shown here for reference and cannot be edited from this view.</span>
                </div>
                ${buildMatPartoTable(legacyEntries, enrollmentId, 'Delivery Record', false)}
            </div>` : ''}
            <div class="tab-pane fade" id="mparto-chart-pane">
                <div style="position:relative;height:400px;max-width:960px;margin:auto">
                    <canvas id="mat-partograph-chart"></canvas>
                </div>
                <p class="text-muted text-center small mt-2">
                    <span class="badge bg-success me-1">●</span> Labour Monitoring &nbsp;
                    <span class="badge bg-secondary me-1">●</span> Post-Delivery &nbsp;
                    ${legacyEntries.length ? '<span class="badge bg-warning text-dark me-1">●</span> Delivery Record &nbsp;' : ''}
                    Alert line = 4 h after active phase onset; Action line = 4 h after alert line
                </p>
            </div>
        </div>`;

        $('#partograph-tab-content').html(html);

        $('#matParto-subtabs .nav-link').on('shown.bs.tab', function(e) {
            if ($(e.target).attr('href') === '#mparto-chart-pane') {
                renderMatPartographChart(allForChart);
            }
        });
    }

    /**
     * Build a partograph data table.
     * @param {Array}   entries      - normalized entry objects
     * @param {number}  enrollmentId
     * @param {string}  label        - used for empty-state text
     * @param {boolean} editable     - show edit/delete buttons when true
     */
    function buildMatPartoTable(entries, enrollmentId, label, editable) {
        if (!entries.length) {
            return `<p class="text-muted text-center py-3">No ${label} entries yet.</p>`;
        }
        let rows = entries.map(e => {
            const fhrNum = parseFloat(e.fetal_heart_rate);
            const fhrClass = !isNaN(fhrNum) && (fhrNum < 110 || fhrNum > 160) ? 'text-danger fw-bold' : '';
            const bp = e.maternal_bp
                || ((e.maternal_bp_systolic || e.maternal_bp_diastolic)
                    ? `${e.maternal_bp_systolic || ''}/${e.maternal_bp_diastolic || ''}` : '—');
            const actions = editable
                ? `<button class="btn btn-xs btn-outline-primary me-1" onclick="editMatPartographEntry(${e.id})" title="Edit"><i class="mdi mdi-pencil"></i></button>
                   <button class="btn btn-xs btn-outline-danger" onclick="deleteMatPartographEntry(${enrollmentId}, ${e.id})" title="Delete"><i class="mdi mdi-delete"></i></button>`
                : `<span class="badge bg-warning text-dark">Read-only</span>`;
            return `<tr>
                <td class="small text-nowrap">${e.recorded_at ?? ''}</td>
                <td>${e.cervical_dilation_cm != null ? e.cervical_dilation_cm + ' cm' : '—'}</td>
                <td>${e.descent ?? '—'}</td>
                <td>${e.contractions_per_10min ?? '—'}${e.contraction_duration_sec ? ` <small class="text-muted">(${e.contraction_duration_sec}s)</small>` : ''}</td>
                <td class="${fhrClass}">${e.fetal_heart_rate ?? '—'}</td>
                <td>${e.amniotic_fluid || '—'}</td>
                <td>${e.moulding || '—'}</td>
                <td>${bp}</td>
                <td>${e.maternal_pulse ?? '—'}</td>
                <td>${e.maternal_temp_c ?? '—'}</td>
                <td>${e.urine_protein || '—'}</td>
                <td>${e.oxytocin_dose || '—'}</td>
                <td>${e.iv_fluids || '—'}</td>
                <td class="small">${e.recorded_by_name ?? '—'}</td>
                <td class="text-nowrap">${actions}</td>
            </tr>`;
        }).join('');
        return `
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered partograph-table" style="font-size:0.82rem">
                <thead class="table-light">
                    <tr>
                        <th>Time</th><th>Dilation</th><th>Descent</th><th>Contractions</th>
                        <th>FHR</th><th>Liquor</th><th>Moulding</th><th>BP</th>
                        <th>Pulse</th><th>Temp °C</th><th>Protein</th><th>Oxytocin</th>
                        <th>IV Fluids</th><th>By</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    }

    function renderMatPartographChart(entries) {
        const ctx = document.getElementById('mat-partograph-chart');
        if (!ctx || typeof Chart === 'undefined') return;
        if (matPartographChartInstance) { matPartographChartInstance.destroy(); matPartographChartInstance = null; }

        if (!entries || !entries.length) return;

        const sorted = [...entries].sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at));
        const startTime = new Date(sorted[0].recorded_at);
        const toHours = d => Math.max(0, (new Date(d) - startTime) / 3600000);
        const toNum   = v => { const n = Number(v); return Number.isNaN(n) ? null : n; };

        // Colour by phase
        const phaseColour = { pre_delivery: '#d63384', post_delivery: '#6f42c1', delivery_record: '#fd7e14' };

        const dilationPoints = sorted.map(e => ({
            x: toHours(e.recorded_at),
            y: toNum(e.cervical_dilation_cm),
            phase: e.phase,
        })).filter(p => p.y !== null);

        const fhrPoints = sorted.map(e => ({
            x: toHours(e.recorded_at),
            y: toNum(e.fetal_heart_rate),
        })).filter(p => p.y !== null);

        // WHO alert/action lines anchored at first dilation >= 4 cm
        const alertLine = [], actionLine = [];
        const maxHour = Math.max(...sorted.map(e => toHours(e.recorded_at)), 12);
        let alertStartHour = null;
        for (const pt of dilationPoints) {
            if (pt.y >= 4) { alertStartHour = pt.x; break; }
        }
        if (alertStartHour !== null) {
            for (let h = alertStartHour; h <= maxHour + 2; h += 0.5) {
                const y = Math.min(10, 4 + (h - alertStartHour));
                alertLine.push({ x: h, y });
                const ay = Math.min(10, 4 + Math.max(0, h - alertStartHour - 4));
                if (h >= alertStartHour + 4) actionLine.push({ x: h, y: ay });
            }
        }

        matPartographChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'Cervical Dilation (cm)',
                        data: dilationPoints,
                        borderColor: '#d63384',
                        backgroundColor: 'rgba(214,51,132,0.12)',
                        tension: 0.2, pointRadius: 5, borderWidth: 2.5,
                        yAxisID: 'y', spanGaps: true,
                        pointBackgroundColor: ctx => {
                            const raw = ctx.raw;
                            return phaseColour[raw?.phase] || '#d63384';
                        },
                    },
                    {
                        label: 'FHR (bpm)',
                        data: fhrPoints,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25,135,84,0.08)',
                        tension: 0.2, pointRadius: 3, borderWidth: 1.5,
                        yAxisID: 'y1', spanGaps: true,
                    },
                    {
                        label: 'Alert Line',
                        data: alertLine,
                        borderColor: '#fd7e14', borderDash: [6, 4],
                        pointRadius: 0, borderWidth: 2, yAxisID: 'y',
                    },
                    {
                        label: 'Action Line',
                        data: actionLine,
                        borderColor: '#dc3545', borderDash: [6, 4],
                        pointRadius: 0, borderWidth: 2, yAxisID: 'y', spanGaps: true,
                    },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'nearest', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 14 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const raw = ctx.raw;
                                const phase = raw?.phase ? ` [${raw.phase.replace('_', ' ')}]` : '';
                                return `${ctx.dataset.label}: ${ctx.parsed.y}${phase}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { type: 'linear', title: { display: true, text: 'Hours since first entry' },
                         ticks: { callback: v => v + 'h' }, min: 0 },
                    y:  { min: 0, max: 10, title: { display: true, text: 'Dilation (cm)' } },
                    y1: { position: 'right', min: 60, max: 200,
                          title: { display: true, text: 'FHR (bpm)' },
                          grid: { drawOnChartArea: false } },
                }
            }
        });
    }

    function showMatPartographForm(phase) {
        const form = document.getElementById('mat-partograph-form');
        form.reset();
        $('#mat-partograph-entry-id').val('');
        $('#mat-partograph-enrollment-id').val(currentEnrollment ? currentEnrollment.id : '');
        $('#mat-partograph-phase').val(phase || 'pre_delivery');
        // Default recorded_at to now
        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        form.querySelector('[name=recorded_at]').value =
            `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

        $('#matPartographModalLabel').html('<i class="mdi mdi-chart-timeline-variant"></i> Add Partograph Entry');
        $('#matPartographModal').modal('show');
    }

    function editMatPartographEntry(entryId) {
        const entry = matPartographEntries.find(e => e.id == entryId);
        if (!entry) return;
        const form = document.getElementById('mat-partograph-form');
        form.reset();
        $('#mat-partograph-entry-id').val(entry.id);
        $('#mat-partograph-enrollment-id').val(entry.enrollment_id);
        $('#mat-partograph-phase').val(entry.phase || 'pre_delivery');
        const setVal = (name, val) => { const el = form.querySelector(`[name=${name}]`); if (el && val != null) el.value = val; };
        setVal('recorded_at', entry.recorded_at ? entry.recorded_at.replace(' ', 'T').substring(0, 16) : '');
        setVal('cervical_dilation_cm', entry.cervical_dilation_cm);
        setVal('descent_of_head', entry.descent);
        setVal('contractions_per_10_min', entry.contractions_per_10min);
        setVal('contraction_duration_sec', entry.contraction_duration_sec);
        setVal('foetal_heart_rate', entry.fetal_heart_rate);
        setVal('amniotic_fluid', entry.amniotic_fluid);
        setVal('moulding', entry.moulding);
        setVal('maternal_bp_systolic', entry.maternal_bp_systolic);
        setVal('maternal_bp_diastolic', entry.maternal_bp_diastolic);
        setVal('maternal_pulse', entry.maternal_pulse);
        setVal('maternal_temp', entry.maternal_temp_c);
        setVal('urine_output_ml', entry.urine_output_ml);
        setVal('urine_protein', entry.urine_protein);
        setVal('oxytocin_dose', entry.oxytocin_dose);
        setVal('iv_fluids', entry.iv_fluids);
        setVal('medications', entry.medications);

        $('#matPartographModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Partograph Entry');
        $('#matPartographModal').modal('show');
    }

    function deleteMatPartographEntry(enrollmentId, entryId) {
        if (!confirm('Delete this partograph entry?')) return;
        $.ajax({
            url: wbUrl(`/maternity-workbench/enrollment/${enrollmentId}/maternity-partograph/${entryId}`),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
            success: function(res) {
                if (res.success) {
                    toastr.success('Entry deleted.');
                    loadMatPartographTab();
                } else {
                    toastr.error(res.message || 'Failed to delete.');
                }
            },
            error: function() { toastr.error('Server error deleting entry.'); }
        });
    }

    // Save mat partograph entry (create or update)
    $(document).on('click', '#btn-save-mat-partograph', function() {
        const enrollmentId = $('#mat-partograph-enrollment-id').val();
        const entryId      = $('#mat-partograph-entry-id').val();
        if (!enrollmentId) { toastr.warning('No enrollment selected.'); return; }

        const form = document.getElementById('mat-partograph-form');
        const sys  = form.querySelector('[name=maternal_bp_systolic]').value;
        const dia  = form.querySelector('[name=maternal_bp_diastolic]').value;

        const data = {
            phase:                    form.querySelector('[name=phase]').value,
            recorded_at:              form.querySelector('[name=recorded_at]').value,
            cervical_dilation_cm:     form.querySelector('[name=cervical_dilation_cm]').value || null,
            descent_of_head:          form.querySelector('[name=descent_of_head]').value || null,
            contractions_per_10_min:  form.querySelector('[name=contractions_per_10_min]').value || null,
            contraction_duration_sec: form.querySelector('[name=contraction_duration_sec]').value || null,
            foetal_heart_rate:        form.querySelector('[name=foetal_heart_rate]').value || null,
            amniotic_fluid:           form.querySelector('[name=amniotic_fluid]').value || null,
            moulding:                 form.querySelector('[name=moulding]').value || null,
            maternal_bp:              sys && dia ? `${sys}/${dia}` : (sys || dia || null),
            maternal_pulse:           form.querySelector('[name=maternal_pulse]').value || null,
            maternal_temp:            form.querySelector('[name=maternal_temp]').value || null,
            urine_output_ml:          form.querySelector('[name=urine_output_ml]').value || null,
            urine_protein:            form.querySelector('[name=urine_protein]').value || null,
            oxytocin_dose:            form.querySelector('[name=oxytocin_dose]').value || null,
            iv_fluids:                form.querySelector('[name=iv_fluids]').value || null,
            medications:              form.querySelector('[name=medications]').value || null,
            _token:                   $('meta[name=csrf-token]').attr('content'),
        };

        const url    = entryId
            ? `/maternity-workbench/enrollment/${enrollmentId}/maternity-partograph/${entryId}`
            : `/maternity-workbench/enrollment/${enrollmentId}/maternity-partograph`;
        const method = entryId ? 'PUT' : 'POST';

        $('#btn-save-mat-partograph').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving…');

        $.ajax({ url, method, data, headers: { 'X-CSRF-TOKEN': data._token } })
            .done(function(res) {
                $('#btn-save-mat-partograph').prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Entry');
                if (res.success) {
                    $('#matPartographModal').modal('hide');
                    toastr.success(res.message || 'Entry saved.');
                    loadMatPartographTab();
                } else {
                    const errs = res.errors ? Object.values(res.errors).flat().join(' ') : (res.message || 'Failed to save.');
                    toastr.error(errs);
                }
            })
            .fail(function(xhr) {
                $('#btn-save-mat-partograph').prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Entry');
                const errs = xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : 'Server error.';
                toastr.error(errs);
            });
        // Extend WardDashboard to refresh maternity queues when actions are taken
        if (typeof WardDashboard !== 'undefined') {
            const originalLoadDashboardData = WardDashboard.loadDashboardData;
            WardDashboard.loadDashboardData = function() {
                if (typeof originalLoadDashboardData === 'function') {
                    originalLoadDashboardData.apply(this, arguments);
                }
                loadQueueCounts();
                const currentFilter = $('.queue-item.active').data('filter');
                if (currentFilter === 'bed-requests' || currentFilter === 'discharge-requests') {
                    loadQueueData(currentFilter);
                }
            };
        }
    });
function showBundleRemove(btn) {
    var parentId = btn.dataset.parentId;
    var bundleName = btn.dataset.bundleName;
    var items = JSON.parse(btn.dataset.items || '[]');
    var removeUrl = btn.dataset.removeUrl;
    BundleRemoveModal.show({
        bundleId: parentId,
        bundleName: bundleName,
        items: items,
        onConfirm: function(callback) {
            $.ajax({
                url: removeUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ parent_request_id: parentId }),
                success: function(r) {
                    if (r.success) {
                        toastr.success(r.message || 'Combo removed');
                        callback(false);
                        if ($.fn.DataTable.isDataTable('#mco_presc_history_list')) { $('#mco_presc_history_list').DataTable().ajax.reload(null, false); }
                        if ($.fn.DataTable.isDataTable('#mco_lab_history_list')) { $('#mco_lab_history_list').DataTable().ajax.reload(null, false); }
                        if ($.fn.DataTable.isDataTable('#mco_imaging_history_list')) { $('#mco_imaging_history_list').DataTable().ajax.reload(null, false); }
                    } else {
                        toastr.error(r.message || 'Failed to remove combo');
                        callback(true);
                    }
                },
                error: function() { toastr.error('Error removing combo'); callback(true); }
            });
        }
    });
}
