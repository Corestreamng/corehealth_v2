/**
 * CoreHealth v2 - NHMIS Monthly Summary Reporting Workbench
 * Standalone front-end module consuming window.WORKBENCH_CONFIG
 */

(function ($) {
    'use strict';

    if (!window.WORKBENCH_CONFIG) {
        console.warn('WORKBENCH_CONFIG is not defined.');
        return;
    }

    const CONFIG = window.WORKBENCH_CONFIG;
    let pendingOverrides = {};
    let isDirty = false;

    // Setup global CSRF for AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': CONFIG.csrf_token,
            'Accept': 'application/json'
        }
    });

    $(document).ready(function () {
        initPeriodSelectors();
        initAutoCompile();
        initCellCalculations();
        initSaveDraft();
        initLockVerification();
        initClinicalDiagnosisAudit();
        initPrintTrigger();
        initCellOverrideModal();
        initServiceDelegations();
        initDrillDown();
    });

    /**
     * 1. Period & Version Selectors
     */
    function initPeriodSelectors() {
        $('#nhmis-month-select').on('change', function () {
            const m = $(this).val();
            if (m === '255') {
                $('#custom-date-start-col, #custom-date-end-col').show();
            } else {
                $('#custom-date-start-col, #custom-date-end-col').hide();
                navigateWithCurrentParams();
            }
        });

        $('#nhmis-year-select, #nhmis-version-select').on('change', function () {
            navigateWithCurrentParams();
        });

        $('#nhmis-start-date, #nhmis-end-date').on('change', function () {
            const s = $('#nhmis-start-date').val();
            const e = $('#nhmis-end-date').val();
            if (s && e) {
                navigateWithCurrentParams();
            }
        });
    }

    function navigateWithCurrentParams() {
        if (isDirty) {
            if (!confirm('You have unsaved changes in this report. Switching periods will discard unsaved edits. Proceed?')) {
                return;
            }
        }
        const y = $('#nhmis-year-select').val();
        const m = $('#nhmis-month-select').val();
        const v = $('#nhmis-version-select').val();
        let url = `${CONFIG.routes.workbench}?year=${y}&month=${m}&version=${v}`;
        if (m === '255') {
            const s = $('#nhmis-start-date').val();
            const e = $('#nhmis-end-date').val();
            if (s) url += `&start_date=${encodeURIComponent(s)}`;
            if (e) url += `&end_date=${encodeURIComponent(e)}`;
        }
        window.location.href = url;
    }

    /**
     * 2. 1-Click Auto Compile
     */
    function initAutoCompile() {
        $('#btn-compile-report').on('click', function () {
            const $btn = $(this);
            const origHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Compiling...');

            const postData = {
                year: CONFIG.report.year,
                month: CONFIG.report.month,
                version: CONFIG.report.version
            };
            if (CONFIG.report.month == 255) {
                postData.start_date = $('#nhmis-start-date').val() || CONFIG.report.startDate;
                postData.end_date = $('#nhmis-end-date').val() || CONFIG.report.endDate;
            }

            $.ajax({
                url: CONFIG.routes.compile,
                type: 'POST',
                data: postData
            }).done(function (res) {
                if (res.success) {
                    // Update cell values
                    if (res.values) {
                        $.each(res.values, function (cellKey, valObj) {
                            const $input = $(`input.nhmis-input[data-cell-key="${cellKey}"]`);
                            if ($input.length) {
                                const targetVal = valObj.final !== null ? valObj.final : valObj.auto;
                                $input.val(targetVal);
                                $input.attr('data-auto-val', valObj.auto);
                                $input.attr('data-initial-val', targetVal);
                                $input.removeClass('is-dirty');

                                if (valObj.is_overridden) {
                                    $input.addClass('is-overridden');
                                } else {
                                    $input.removeClass('is-overridden');
                                    $input.siblings('.override-dot').remove();
                                }
                            }
                        });
                    }

                    // Recalculate row sums
                    recalculateAllRows();

                    // Update status badge
                    $('#nhmis-status-badge').removeClass('nhmis-badge-draft').addClass('nhmis-badge-compiled');
                    $('#nhmis-status-text').text('COMPILED');
                    $('#nhmis-unsaved-badge').hide();
                    isDirty = false;

                    if (window.Swal) {
                        Swal.fire({
                            title: 'Compilation Complete!',
                            text: res.message,
                            icon: 'success',
                            confirmButtonColor: '#011b33'
                        });
                    } else {
                        alert(res.message);
                    }
                }
            }).fail(function (xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error executing NHMIS compilation';
                if (window.Swal) {
                    Swal.fire({ title: 'Compilation Failed', text: msg, icon: 'error' });
                } else {
                    alert(msg);
                }
            }).always(function () {
                $btn.prop('disabled', false).html(origHtml);
            });
        });
    }

    /**
     * 3. Row Auto-Sum Calculations and Dirty State Tracking
     */
    function initCellCalculations() {
        $(document).on('input change', 'input.nhmis-input:not([data-is-total="1"])', function () {
            const $this = $(this);
            const initialVal = parseFloat($this.attr('data-initial-val')) || 0;
            const currentVal = parseFloat($this.val()) || 0;

            if (currentVal !== initialVal) {
                $this.addClass('is-dirty');
                isDirty = true;
                $('#nhmis-unsaved-badge').show();
            } else {
                $this.removeClass('is-dirty');
                checkGlobalDirty();
            }

            // Recalculate row total
            recalculateRow($this.closest('tr'));
        });
    }

    function recalculateRow($tr) {
        let sum = 0;
        let hasTotalCol = false;
        const $totalInput = $tr.find('input.nhmis-input[data-is-total="1"]');

        if ($totalInput.length) {
            hasTotalCol = true;
            $tr.find('input.nhmis-input:not([data-is-total="1"])').each(function () {
                sum += parseFloat($(this).val()) || 0;
            });
            $totalInput.val(sum);
        }
    }

    function recalculateAllRows() {
        $('tr[data-row-id]').each(function () {
            recalculateRow($(this));
        });
    }

    function checkGlobalDirty() {
        if ($('input.nhmis-input.is-dirty').length === 0 && Object.keys(pendingOverrides).length === 0) {
            isDirty = false;
            $('#nhmis-unsaved-badge').hide();
        }
    }

    /**
     * 4. Save Draft Changes
     */
    function initSaveDraft() {
        $('#btn-save-report').on('click', function () {
            const $btn = $(this);
            const origHtml = $btn.html();
            const payloadValues = {};

            $('input.nhmis-input.is-dirty').each(function () {
                const key = $(this).attr('data-cell-key');
                const val = $(this).val();
                payloadValues[key] = {
                    override_value: val,
                    override_reason: pendingOverrides[key]?.reason || 'Direct cell adjustment on workbench'
                };
            });

            // Also include pending overrides that may not have trigger input change
            $.each(pendingOverrides, function (key, data) {
                payloadValues[key] = data;
            });

            if (Object.keys(payloadValues).length === 0) {
                if (window.Swal) {
                    Swal.fire({ title: 'No Changes', text: 'No cell values have been modified.', icon: 'info' });
                } else {
                    alert('No cell values have been modified.');
                }
                return;
            }

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

            $.ajax({
                url: CONFIG.routes.saveValues,
                type: 'POST',
                data: {
                    report_id: CONFIG.report.id,
                    values: payloadValues
                }
            }).done(function (res) {
                if (res.success) {
                    $('input.nhmis-input.is-dirty').each(function () {
                        $(this).attr('data-initial-val', $(this).val());
                        $(this).removeClass('is-dirty').addClass('is-overridden');
                        if (!$(this).siblings('.override-dot').length) {
                            $(this).after('<span class="override-dot" title="Manually adjusted"></span>');
                        }
                    });

                    pendingOverrides = {};
                    isDirty = false;
                    $('#nhmis-unsaved-badge').hide();

                    if (window.Swal) {
                        Swal.fire({ title: 'Saved!', text: 'Changes saved successfully.', icon: 'success', timer: 1800 });
                    } else {
                        alert('Changes saved successfully.');
                    }
                }
            }).fail(function (xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to save changes';
                if (window.Swal) {
                    Swal.fire({ title: 'Save Failed', text: msg, icon: 'error' });
                } else {
                    alert(msg);
                }
            }).always(function () {
                $btn.prop('disabled', false).html(origHtml);
            });
        });
    }

    /**
     * 5. Lock Verification Workflow
     */
    function initLockVerification() {
        $('#btn-lock-report').on('click', function () {
            $('#modalVerifyLock').modal('show');
        });

        $('#btn-confirm-lock').on('click', function () {
            const $btn = $(this);
            const notes = $('#modal-lock-notes').val();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Locking...');

            $.ajax({
                url: CONFIG.routes.updateStatus,
                type: 'POST',
                data: {
                    report_id: CONFIG.report.id,
                    status: 'locked',
                    notes: notes
                }
            }).done(function (res) {
                if (res.success) {
                    $('#modalVerifyLock').modal('hide');
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Report Verified & Locked!',
                            text: 'This monthly summary has been locked against alterations.',
                            icon: 'success'
                        }).then(function () {
                            location.reload();
                        });
                    } else {
                        alert('Report verified and locked.');
                        location.reload();
                    }
                }
            }).fail(function (xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error locking report';
                alert(msg);
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="mdi mdi-lock-check"></i> Confirm Lock & Verify');
            });
        });
    }

    /**
     * 6. Clinical Diagnosis Audit & Encounter Insight (reusing Reception Clinical Report patterns)
     */
    function initClinicalDiagnosisAudit() {
        $('#btn-clinical-audit').on('click', function () {
            $('#nhmis-audit-keyword').val('');
            $('#modalNhmisAudit').modal('show');
            executeAuditSearch('');
        });

        $(document).on('click', '.btn-audit-row', function () {
            const rowNum = $(this).attr('data-row-id');
            const rowLabel = $(this).attr('data-label') || '';
            $('#nhmis-audit-keyword').val('');
            $('#modalNhmisAudit').modal('show');
            executeAuditSearch('', rowNum, rowLabel);
        });

        $('#btn-exec-audit').on('click', function () {
            const kw = $('#nhmis-audit-keyword').val();
            executeAuditSearch(kw);
        });

        $('#nhmis-audit-keyword').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                executeAuditSearch($(this).val());
            }
        });
    }

    function executeAuditSearch(keyword, rowId, rowLabel) {
        const $tbody = $('#tbody-audit-results');
        $tbody.html('<tr><td colspan="10" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Querying clinical encounters...</td></tr>');

        $.ajax({
            url: CONFIG.routes.auditDiagnosis,
            type: 'GET',
            data: {
                year: CONFIG.report.year,
                month: CONFIG.report.month,
                keyword: keyword,
                row_id: rowId
            }
        }).done(function (res) {
            if (res.success) {
                $('#audit-stat-patients').html(`<i class="mdi mdi-account-multiple"></i> Unique Patients: ${res.unique_patients}`);
                $('#audit-stat-encounters').html(`<i class="mdi mdi-file-document-outline"></i> Encounters: ${res.total_encounters}`);

                if (!res.encounters || res.encounters.length === 0) {
                    $tbody.html('<tr><td colspan="10" class="text-center text-muted py-4"><i class="mdi mdi-alert-circle-outline"></i> No encounters found matching this criteria for this reporting period.</td></tr>');
                    return;
                }

                let html = '';
                $.each(res.encounters, function (idx, e) {
                    html += `<tr>
                        <td class="text-center">${idx + 1}</td>
                        <td>${e.date}</td>
                        <td class="font-weight-bold">${e.patient_name}</td>
                        <td>${e.file_no}</td>
                        <td>${e.gender}</td>
                        <td>${e.age}</td>
                        <td>${e.doctor_name}</td>
                        <td>${e.clinic}</td>
                        <td><span class="badge badge-light border text-dark">${e.diagnoses}</span></td>
                        <td class="small text-muted">${e.notes}</td>
                    </tr>`;
                });
                $tbody.html(html);
            }
        }).fail(function () {
            $tbody.html('<tr><td colspan="10" class="text-center text-danger py-4"><i class="mdi mdi-alert"></i> Failed to retrieve encounter details.</td></tr>');
        });
    }

    /**
     * 7. Cell Override Modal
     */
    function initCellOverrideModal() {
        let activeCellKey = null;
        let $activeInput = null;

        $(document).on('contextmenu', 'input.nhmis-input:not([data-is-total="1"])', function (e) {
            e.preventDefault();
            $activeInput = $(this);
            activeCellKey = $activeInput.attr('data-cell-key');

            $('#modal-override-cellkey').text(activeCellKey);
            $('#modal-override-autoval').text($activeInput.attr('data-auto-val') || 0);
            $('#modal-override-input').val($activeInput.val());
            $('#modal-override-reason').val('');

            $('#modalCellOverride').modal('show');
        });

        $('#btn-save-cell-override').on('click', function () {
            const newVal = $('#modal-override-input').val();
            const reason = $('#modal-override-reason').val().trim();

            if (newVal === '') {
                alert('Please enter a count value.');
                return;
            }

            if (!reason) {
                alert('Please provide an audit reason for adjusting this count.');
                return;
            }

            if ($activeInput) {
                $activeInput.val(newVal).addClass('is-dirty');
                pendingOverrides[activeCellKey] = {
                    override_value: newVal,
                    override_reason: reason
                };
                isDirty = true;
                $('#nhmis-unsaved-badge').show();
                recalculateRow($activeInput.closest('tr'));
            }

            $('#modalCellOverride').modal('hide');
        });
    }

    /**
     * 8. Unified Print Preview Trigger
     */
    function initPrintTrigger() {
        $('#btn-print-report').on('click', function () {
            const y = $('#nhmis-year-select').val();
            const m = $('#nhmis-month-select').val();
            const v = $('#nhmis-version-select').val();
            let url = `${CONFIG.routes.workbench}?action=print&year=${y}&month=${m}&version=${v}`;
            if (m === '255') {
                const s = $('#nhmis-start-date').val();
                const e = $('#nhmis-end-date').val();
                if (s) url += `&start_date=${encodeURIComponent(s)}`;
                if (e) url += `&end_date=${encodeURIComponent(e)}`;
            }
            window.open(url, '_blank');
        });
    }

    /**
     * 9. NHMIS Service Delegations (Facility Service Mapping)
     */
    let availableServicesList = [];
    let currentMappingsData = {};

    function initServiceDelegations() {
        $('#btn-service-mappings').on('click', function () {
            $('#modalServiceMappings').modal('show');
            loadServiceMappings();
        });

        $('#mapping-modality-tabs a').on('click', function () {
            $('#mapping-modality-tabs a').removeClass('active');
            $(this).addClass('active');
            const modality = $(this).data('modality');
            if (modality === 'all') {
                $('#tbody-service-mappings tr').show();
            } else {
                $('#tbody-service-mappings tr').each(function () {
                    const rowModality = $(this).data('modality');
                    $(this).toggle(rowModality === modality);
                });
            }
        });

        $('#btn-autodetect-mappings').on('click', function () {
            if (!confirm('This will auto-detect matching services by name keywords and assign them to standard NHMIS indicators. Existing mappings will be refreshed. Proceed?')) {
                return;
            }
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Detecting...');

            $.ajax({
                url: CONFIG.routes.autoDetectMappings,
                type: 'POST'
            }).done(function (res) {
                if (res.success) {
                    alert(res.message);
                    loadServiceMappings();
                } else {
                    alert(res.message || 'Auto-detection failed.');
                }
            }).fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Error executing auto-detection.');
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="mdi mdi-auto-fix"></i> Auto-Detect Defaults');
            });
        });

        $('#btn-run-historical-backfill').on('click', function () {
            if (!confirm('This will execute the clinical backfill engine over historical laboratory records to auto-classify outcomes based on current delegations. This runs non-destructively in the background. Proceed?')) {
                return;
            }
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Classifying...');

            $.ajax({
                url: CONFIG.routes.backfillClassifications,
                type: 'POST'
            }).done(function (res) {
                if (res.success) {
                    alert(res.message);
                } else {
                    alert(res.message || 'Backfill execution failed.');
                }
            }).fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Error executing historical backfill.');
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="mdi mdi-history"></i> Run Historical Backfill');
            });
        });

        $('#btn-save-service-mappings').on('click', function () {
            const mappingsPayload = {};
            $('#tbody-service-mappings tr[data-indicator]').each(function () {
                const code = $(this).attr('data-indicator');
                const selected = $(this).find('select.select-indicator-services').val() || [];
                mappingsPayload[code] = selected;
            });

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: CONFIG.routes.saveServiceMappings,
                type: 'POST',
                data: { mappings: mappingsPayload }
            }).done(function (res) {
                if (res.success) {
                    alert(res.message);
                    $('#modalServiceMappings').modal('hide');
                } else {
                    alert(res.message || 'Failed to save delegations.');
                }
            }).fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Error saving service mappings.');
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Delegations');
            });
        });
    }

    function loadServiceMappings() {
        const $tbody = $('#tbody-service-mappings');
        $tbody.html('<tr><td colspan="4" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Loading configured service delegations...</td></tr>');

        $.ajax({
            url: CONFIG.routes.serviceMappings,
            type: 'GET'
        }).done(function (res) {
            if (res.success) {
                availableServicesList = res.services || [];
                currentMappingsData = res.mappings || {};
                renderServiceMappingsTable(res.indicators, currentMappingsData, availableServicesList);
            }
        }).fail(function () {
            $tbody.html('<tr><td colspan="4" class="text-center text-danger py-4">Failed to load service delegations.</td></tr>');
        });
    }

    function renderServiceMappingsTable(indicators, mappings, services) {
        const $tbody = $('#tbody-service-mappings');
        $tbody.empty();

        $.each(indicators, function (code, meta) {
            const m = mappings[code] || {};
            const assigned = m.service_ids || [];
            const modality = meta.service_type || 'investigation';
            const catId = meta.category_id || 2;

            // Filter services by category if possible
            const relevantServices = services.filter(function (s) {
                return s.category_id == catId || assigned.indexOf(s.id) !== -1;
            });

            let optionsHtml = '';
            $.each(relevantServices, function (idx, s) {
                const isSelected = assigned.indexOf(s.id) !== -1 ? 'selected' : '';
                optionsHtml += `<option value="${s.id}" ${isSelected}>${s.service_name} (${s.service_code || 'ID: ' + s.id})</option>`;
            });

            const statusBadge = assigned.length > 0
                ? `<span class="badge badge-success"><i class="mdi mdi-check"></i> ${assigned.length} mapped</span>`
                : `<span class="badge badge-warning"><i class="mdi mdi-alert"></i> Unmapped</span>`;

            let presetBadge = `<span class="badge badge-info">${meta.preset_key || 'qualitative'}</span>`;
            if (m.supported_outcomes && m.supported_outcomes.length) {
                presetBadge += `<br><small class="text-muted">${m.supported_outcomes.slice(0, 3).join(', ')}...</small>`;
            }

            const modalityBadge = modality === 'imaging'
                ? '<span class="badge badge-primary">Imaging</span>'
                : (modality === 'procedure' ? '<span class="badge badge-danger">Procedure</span>' : '<span class="badge badge-secondary">Investigation</span>');

            const rowHtml = `
                <tr data-indicator="${code}" data-modality="${modality}">
                    <td>
                        <strong>${meta.label || meta.name}</strong><br>
                        <small class="text-muted font-monospace">${code}</small><br>
                        <small class="text-muted">${meta.description || ''}</small>
                    </td>
                    <td>
                        ${modalityBadge}<br>
                        <small class="text-muted">${meta.section || ''}</small>
                    </td>
                    <td>
                        <select class="form-control form-control-sm select-indicator-services" multiple size="3" style="font-size: 12px;">
                            ${optionsHtml}
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple hospital services</small>
                    </td>
                    <td class="text-center align-middle">
                        ${presetBadge}<br>
                        <div class="mt-1">${statusBadge}</div>
                    </td>
                </tr>
            `;
            $tbody.append(rowHtml);
        });
    }

    /**
     * 10. Universal Cell & Row Drill-Down with Server-Side Pagination, Debounced Search & HMO Filters
     */
    let currentDrillDownCellKey = null;
    let currentDrillDownLabel = '';
    let currentDrillDownPage = 1;
    let drillDownDebounceTimer = null;
    let cachedHmoList = null;
    let cachedSchemeList = null;

    function initDrillDown() {
        // Universal Row Drill-Down button click
        $(document).on('click', '.btn-row-drilldown', function (e) {
            e.preventDefault();
            const rowId = $(this).attr('data-row-id');
            const label = $(this).attr('data-label') || '';
            const $tr = $(this).closest('tr');
            
            // Prefer total cell, else first input cell of the row
            let $targetInput = $tr.find('.nhmis-input[data-is-total="1"]');
            if (!$targetInput.length) {
                $targetInput = $tr.find('.nhmis-input').first();
            }
            const cellKey = $targetInput.attr('data-cell-key') || `${rowId}:total`;
            openCellDrillDown(cellKey, label);
        });

        // Double-click on cell input
        $(document).on('dblclick', '.nhmis-input', function () {
            const cellKey = $(this).attr('data-cell-key');
            const $tr = $(this).closest('tr');
            const label = $tr.find('.row-label-text').text().trim() || $tr.find('.cell-label').text().trim();
            if (cellKey) {
                openCellDrillDown(cellKey, label);
            }
        });

        // Click on audit / inspect button
        $(document).on('click', '.btn-cell-drilldown', function () {
            const cellKey = $(this).attr('data-cell-key');
            const label = $(this).attr('data-label') || '';
            if (cellKey) {
                openCellDrillDown(cellKey, label);
            }
        });

        // Debounced search input (300ms)
        $('#drilldown-search-input').on('keyup input', function () {
            const val = $(this).val().trim();
            if (val.length > 0) {
                $('#drilldown-search-clear').show();
            } else {
                $('#drilldown-search-clear').hide();
            }

            clearTimeout(drillDownDebounceTimer);
            drillDownDebounceTimer = setTimeout(function () {
                currentDrillDownPage = 1;
                fetchDrillDownData();
            }, 300);
        });

        // Clear search button
        $('#drilldown-search-clear').on('click', function () {
            $('#drilldown-search-input').val('');
            $(this).hide();
            currentDrillDownPage = 1;
            fetchDrillDownData();
        });

        // HMO, Scheme and Per-Page filters
        $('#drilldown-hmo-filter, #drilldown-scheme-filter, #drilldown-per-page').on('change', function () {
            currentDrillDownPage = 1;
            fetchDrillDownData();
        });

        // Reset filters button
        $('#drilldown-btn-reset').on('click', function () {
            $('#drilldown-search-input').val('');
            $('#drilldown-search-clear').hide();
            $('#drilldown-hmo-filter').val('');
            $('#drilldown-scheme-filter').val('');
            $('#drilldown-per-page').val('25');
            currentDrillDownPage = 1;
            fetchDrillDownData();
        });

        // Pagination links click
        $(document).on('click', '#drilldown-pagination-links a[data-page]', function (e) {
            e.preventDefault();
            const targetPage = parseInt($(this).attr('data-page'), 10);
            if (targetPage && targetPage !== currentDrillDownPage) {
                currentDrillDownPage = targetPage;
                fetchDrillDownData();
            }
        });
    }

    function openCellDrillDown(cellKey, label) {
        currentDrillDownCellKey = cellKey;
        currentDrillDownLabel = label || cellKey;
        currentDrillDownPage = 1;

        $('#modalNhmisDrillDown').modal('show');
        $('#drilldown-badge-cellkey').text(cellKey);
        $('#drilldown-row-label').text(currentDrillDownLabel);
        $('#drilldown-period-text').text(`Period: ${CONFIG.report.month_name || ''} ${CONFIG.report.year}`);
        $('#drilldown-search-input').val('');
        $('#drilldown-search-clear').hide();
        $('#drilldown-hmo-filter').val('');
        $('#drilldown-scheme-filter').val('');
        $('#drilldown-per-page').val('25');

        fetchDrillDownData();
    }

    function fetchDrillDownData() {
        if (!currentDrillDownCellKey) return;

        $('#tbody-drilldown-results').html('<tr><td colspan="9" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Loading underlying records...</td></tr>');
        $('#drilldown-stat-patients').html('<i class="mdi mdi-account-multiple"></i> Unique Patients: ...');
        $('#drilldown-stat-records').html('<i class="mdi mdi-file-document-outline"></i> Total Records: ...');

        const params = {
            cell_key: currentDrillDownCellKey,
            report_id: CONFIG.report.id,
            page: currentDrillDownPage,
            per_page: $('#drilldown-per-page').val() || 25,
            search: $('#drilldown-search-input').val().trim(),
            hmo_id: $('#drilldown-hmo-filter').val(),
            scheme_id: $('#drilldown-scheme-filter').val()
        };

        $.ajax({
            url: CONFIG.routes.drillDown,
            type: 'GET',
            data: params
        }).done(function (res) {
            if (res.success) {
                $('#drilldown-stat-patients').html(`<i class="mdi mdi-account-multiple"></i> Unique Patients: ${res.unique_patients}`);
                $('#drilldown-stat-records').html(`<i class="mdi mdi-file-document-outline"></i> Total Records: ${res.total_records}`);

                populateHmoFilters(res.hmos, res.schemes);
                renderDrillDownTable(res.records, res.from);
                renderDrillDownPagination(res.current_page, res.last_page, res.total_records, res.from, res.to);
            } else {
                $('#tbody-drilldown-results').html('<tr><td colspan="9" class="text-center text-danger py-4">Error loading records.</td></tr>');
            }
        }).fail(function (xhr) {
            $('#tbody-drilldown-results').html(`<tr><td colspan="9" class="text-center text-danger py-4">${xhr.responseJSON?.message || 'Failed to load drill-down records.'}</td></tr>`);
        });
    }

    function populateHmoFilters(hmos, schemes) {
        if (hmos && hmos.length && !cachedHmoList) {
            cachedHmoList = hmos;
            const $hmoSelect = $('#drilldown-hmo-filter');
            $hmoSelect.find('option:gt(1)').remove();
            $.each(hmos, function (i, h) {
                $hmoSelect.append(`<option value="${h.id}">${h.name}</option>`);
            });
        }

        if (schemes && schemes.length && !cachedSchemeList) {
            cachedSchemeList = schemes;
            const $schemeSelect = $('#drilldown-scheme-filter');
            $schemeSelect.find('option:gt(0)').remove();
            $.each(schemes, function (i, s) {
                $schemeSelect.append(`<option value="${s.id}">${s.name}</option>`);
            });
        }
    }

    function renderDrillDownTable(records, fromIdx) {
        const $tbody = $('#tbody-drilldown-results');
        $tbody.empty();

        if (!records || !records.length) {
            $tbody.html('<tr><td colspan="9" class="text-center text-muted py-4"><i class="mdi mdi-information-outline"></i> No matching underlying records found for this cell in the selected period.</td></tr>');
            return;
        }

        $.each(records, function (idx, r) {
            const itemNum = (fromIdx || 1) + idx;
            const hmoMarkup = r.hmo_html || '<span class="text-muted" style="font-size:0.75rem;">Cash</span>';
            const rowHtml = `
                <tr>
                    <td class="text-muted font-monospace">${itemNum}</td>
                    <td class="font-monospace small">${r.date || 'N/A'}</td>
                    <td><strong class="text-dark">${r.patient_name || 'Unknown'}</strong></td>
                    <td class="font-monospace text-primary">#${r.file_no || 'N/A'}</td>
                    <td>${hmoMarkup}</td>
                    <td><span class="badge badge-light border">${r.gender || 'N/A'}</span></td>
                    <td>${r.age || 'N/A'}</td>
                    <td><small class="text-dark font-weight-bold">${r.doctor_name || 'N/A'}</small></td>
                    <td><small class="text-muted">${r.details || 'N/A'}</small></td>
                </tr>
            `;
            $tbody.append(rowHtml);
        });
    }

    function renderDrillDownPagination(currentPage, lastPage, totalRecords, from, to) {
        $('#drilldown-pagination-info').text(`Showing ${from} to ${to} of ${totalRecords} records`);
        const $links = $('#drilldown-pagination-links');
        $links.empty();

        if (lastPage <= 1) {
            return;
        }

        // Previous button
        const prevDisabled = currentPage <= 1 ? 'disabled' : '';
        $links.append(`<li class="page-item ${prevDisabled}"><a class="page-link" href="#" data-page="${currentPage - 1}">&laquo; Prev</a></li>`);

        // Pagination window
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(lastPage, currentPage + 2);

        if (startPage > 1) {
            $links.append(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (startPage > 2) {
                $links.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            const active = (p === currentPage) ? 'active' : '';
            $links.append(`<li class="page-item ${active}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`);
        }

        if (endPage < lastPage) {
            if (endPage < lastPage - 1) {
                $links.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            $links.append(`<li class="page-item"><a class="page-link" href="#" data-page="${lastPage}">${lastPage}</a></li>`);
        }

        // Next button
        const nextDisabled = currentPage >= lastPage ? 'disabled' : '';
        $links.append(`<li class="page-item ${nextDisabled}"><a class="page-link" href="#" data-page="${currentPage + 1}">Next &raquo;</a></li>`);
    }

})(jQuery);

