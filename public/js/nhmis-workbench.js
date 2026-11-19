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
                            let $input = $(`input.nhmis-input[data-cell-key="${cellKey}"]`);
                            if (!$input.length) {
                                if (/^\d+:/.test(cellKey)) {
                                    $input = $(`input.nhmis-input[data-cell-key="row_${cellKey}"]`);
                                } else if (/^row_\d+:/.test(cellKey)) {
                                    const altKey = cellKey.replace(/^row_/, '');
                                    $input = $(`input.nhmis-input[data-cell-key="${altKey}"]`);
                                }
                            }
                            if ($input.length) {
                                const targetVal = (valObj.final !== null && valObj.final !== undefined) ? valObj.final : (valObj.auto ?? 0);
                                $input.val(targetVal);
                                $input.attr('data-auto-val', valObj.auto ?? 0);
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

                    // Recalculate row sums (only applies to rows with sub-columns)
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
        $(document).on('input change', 'input.nhmis-input', function () {
            const $this = $(this);
            const isTotal = $this.attr('data-is-total') === '1';
            const $tr = $this.closest('tr');
            const hasSubInputs = $tr.find('input.nhmis-input:not([data-is-total="1"])').length > 0;

            // If it's a calculated total in a row that has sub-inputs, do not handle manual typing
            if (isTotal && hasSubInputs) {
                return;
            }

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

            // Recalculate row total only if this row has sub-inputs
            if (hasSubInputs) {
                recalculateRow($tr);
            }
        });
    }

    function recalculateRow($tr) {
        const $totalInput = $tr.find('input.nhmis-input[data-is-total="1"]');
        const $subInputs = $tr.find('input.nhmis-input:not([data-is-total="1"])');

        // Only calculate total if there are actually sub-column inputs to sum!
        // Single-column rows (total only) must never have their values overwritten with 0.
        if ($totalInput.length && $subInputs.length > 0) {
            let sum = 0;
            $subInputs.each(function () {
                sum += parseFloat($(this).val()) || 0;
            });
            $totalInput.val(sum);
            if (!$totalInput.hasClass('is-dirty')) {
                $totalInput.attr('data-initial-val', sum);
            }
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
     * 9. NHMIS Service Linking (Facility Service ↔ Indicator Mapping)
     *
     * State model: `nhm.original` holds server state, `nhm.working` holds edits.
     * Only indicators whose linked set differs are sent on save (as JSON so an
     * emptied list still reaches the server and clears the links).
     */
    const nhm = {
        indicators: {},
        meta: {},
        services: [],
        serviceById: {},
        original: {},
        working: {},
        selected: null,
        modality: 'all',
        unlinkedOnly: false,
        search: '',
        catalogSearch: '',
        catalogAll: false,
        loaded: false,
        forceClose: false
    };

    function nhmEsc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function nhmNotify(type, msg) {
        if (window.toastr) { toastr[type](msg); return; }
        if (window.Swal) {
            Swal.fire({ icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'), text: msg, timer: 2200, showConfirmButton: false });
            return;
        }
        alert(msg);
    }

    function nhmConfirm(title, text, confirmText) {
        if (window.Swal) {
            return Swal.fire({ title: title, text: text, icon: 'question', showCancelButton: true, confirmButtonText: confirmText || 'Proceed' })
                .then(function (r) { return !!r.isConfirmed; });
        }
        return Promise.resolve(confirm(title + '\n\n' + text));
    }

    function nhmSameSet(a, b) {
        if (a.length !== b.length) return false;
        const sa = a.slice().sort(), sb = b.slice().sort();
        return sa.every(function (v, i) { return v === sb[i]; });
    }

    function nhmDirtyCodes() {
        return Object.keys(nhm.working).filter(function (c) {
            return !nhmSameSet(nhm.working[c] || [], nhm.original[c] || []);
        });
    }

    function nhmIndicatorsUsing(serviceId, exceptCode) {
        return Object.keys(nhm.working).filter(function (c) {
            return c !== exceptCode && nhm.working[c].indexOf(serviceId) !== -1;
        });
    }

    function initServiceDelegations() {
        const $modal = $('#modalServiceMappings');

        $('#btn-service-mappings').on('click', function () {
            $modal.modal('show');
            if (!nhm.loaded || nhmDirtyCodes().length === 0) loadServiceMappings();
        });

        // Warn before closing with unsaved edits
        $modal.on('hide.bs.modal', function (e) {
            if (nhm.forceClose || nhmDirtyCodes().length === 0) { nhm.forceClose = false; return; }
            e.preventDefault();
            nhmConfirm('Discard unsaved changes?', 'You have unsaved link changes on ' + nhmDirtyCodes().length + ' indicator(s).', 'Discard')
                .then(function (ok) {
                    if (!ok) return;
                    nhmResetWorking();
                    nhm.forceClose = true;
                    $modal.modal('hide');
                });
        });

        // Left pane filters
        $('#nhm-indicator-search').on('input', function () { nhm.search = this.value.trim().toLowerCase(); nhmRenderIndicatorList(); });
        $('#mapping-modality-tabs').on('click', '.nhm-chip', function () {
            $('#mapping-modality-tabs .nhm-chip').removeClass('active');
            $(this).addClass('active');
            nhm.modality = $(this).data('modality');
            nhmRenderIndicatorList();
        });
        $('#nhm-unlinked-only').on('change', function () { nhm.unlinkedOnly = this.checked; nhmRenderIndicatorList(); });

        $('#nhm-indicator-list').on('click', '.nhm-ind', function () {
            nhm.selected = $(this).data('code');
            nhm.catalogSearch = '';
            nhmRenderIndicatorList();
            nhmRenderDetail();
        });

        // Right pane: link / unlink / bulk actions
        const $detail = $('#nhm-detail');
        $detail.on('click', '.nhm-unlink', function () {
            const id = Number($(this).data('id'));
            nhm.working[nhm.selected] = nhm.working[nhm.selected].filter(function (x) { return x !== id; });
            nhmRefresh();
        });
        $detail.on('click', '.nhm-link', function () {
            const id = Number($(this).data('id'));
            if (nhm.working[nhm.selected].indexOf(id) === -1) nhm.working[nhm.selected].push(id);
            nhmRefresh(true);
        });
        $detail.on('click', '#nhm-unlink-all', function () {
            nhm.working[nhm.selected] = [];
            nhmRefresh();
        });
        $detail.on('click', '#nhm-revert-one', function () {
            nhm.working[nhm.selected] = (nhm.original[nhm.selected] || []).slice();
            nhmRefresh();
        });
        $detail.on('click', '#nhm-link-all-visible', function () {
            nhmCatalogMatches().forEach(function (s) {
                const id = Number(s.id);
                if (nhm.working[nhm.selected].indexOf(id) === -1) nhm.working[nhm.selected].push(id);
            });
            nhm.catalogSearch = '';
            nhmRefresh();
        });
        $detail.on('input', '#nhm-catalog-search', function () {
            nhm.catalogSearch = this.value.trim().toLowerCase();
            nhmRenderCatalog();
        });
        $detail.on('change', '#nhm-catalog-all', function () {
            nhm.catalogAll = this.checked;
            nhmRenderCatalog();
        });

        $('#btn-discard-mappings').on('click', function () {
            nhmResetWorking();
            nhmRefresh();
        });

        $('#btn-autodetect-mappings').on('click', function () {
            const $btn = $(this);
            nhmConfirm('Auto-detect links?', 'Services will be matched to NHMIS indicators by name keywords. Unsaved edits in this window will be reloaded from the server.', 'Auto-Detect')
                .then(function (ok) {
                    if (!ok) return;
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Detecting...');
                    $.ajax({ url: CONFIG.routes.autoDetectMappings, type: 'POST' })
                        .done(function (res) {
                            nhmNotify(res.success ? 'success' : 'error', res.message || 'Auto-detection finished.');
                            loadServiceMappings();
                        })
                        .fail(function (xhr) { nhmNotify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Error executing auto-detection.'); })
                        .always(function () { $btn.prop('disabled', false).html('<i class="mdi mdi-auto-fix"></i> Auto-Detect'); });
                });
        });

        $('#btn-run-historical-backfill').on('click', function () {
            const $btn = $(this);
            if (nhmDirtyCodes().length) {
                nhmNotify('warning', 'Save your link changes first — backfill uses the saved links.');
                return;
            }
            nhmConfirm('Backfill historical results?', 'Past lab results will be re-classified using the current saved links. Existing data is not deleted.', 'Run Backfill')
                .then(function (ok) {
                    if (!ok) return;
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Classifying...');
                    $.ajax({ url: CONFIG.routes.backfillClassifications, type: 'POST' })
                        .done(function (res) { nhmNotify(res.success ? 'success' : 'error', res.message || 'Backfill finished.'); })
                        .fail(function (xhr) { nhmNotify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Error executing historical backfill.'); })
                        .always(function () { $btn.prop('disabled', false).html('<i class="mdi mdi-history"></i> Backfill History'); });
                });
        });

        $('#btn-save-service-mappings').on('click', function () {
            const dirty = nhmDirtyCodes();
            if (!dirty.length) return;

            const payload = {};
            dirty.forEach(function (code) {
                const m = nhm.meta[code] || {};
                payload[code] = {
                    service_ids: nhm.working[code],
                    supported_outcomes: m.supported_outcomes || null,
                    positive_outcomes: m.positive_outcomes || null
                };
            });

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: CONFIG.routes.saveServiceMappings,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ mappings: payload })
            }).done(function (res) {
                if (res.success) {
                    dirty.forEach(function (c) { nhm.original[c] = nhm.working[c].slice(); });
                    nhmNotify('success', 'Saved links for ' + dirty.length + ' indicator(s).');
                    nhmRefresh();
                } else {
                    nhmNotify('error', res.message || 'Failed to save links.');
                }
            }).fail(function (xhr) {
                nhmNotify('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Error saving service links.');
            }).always(function () {
                nhmUpdateFooter();
            });
        });
    }

    function nhmResetWorking() {
        nhm.working = {};
        Object.keys(nhm.original).forEach(function (c) { nhm.working[c] = nhm.original[c].slice(); });
    }

    function loadServiceMappings() {
        $('#nhm-indicator-list').html('<div class="nhm-empty"><span class="spinner-border spinner-border-sm"></span> Loading indicators...</div>');

        $.ajax({ url: CONFIG.routes.serviceMappings, type: 'GET' }).done(function (res) {
            if (!res.success) return;
            nhm.indicators = res.indicators || {};
            nhm.meta = res.mappings || {};
            nhm.services = (res.services || []).map(function (s) { s.id = Number(s.id); return s; });
            nhm.serviceById = {};
            nhm.services.forEach(function (s) { nhm.serviceById[s.id] = s; });
            nhm.original = {};
            Object.keys(nhm.indicators).forEach(function (code) {
                const m = nhm.meta[code] || {};
                nhm.original[code] = (m.service_ids || []).map(Number);
                // Keep names for linked services outside the active catalogue (inactive / other category)
                (m.services || []).forEach(function (s) {
                    if (!nhm.serviceById[s.id]) {
                        nhm.serviceById[s.id] = { id: Number(s.id), service_name: s.name, service_code: '', category_id: null, category_name: 'Not in active catalogue' };
                    }
                });
            });
            nhmResetWorking();
            nhm.loaded = true;
            if (!nhm.selected || !nhm.indicators[nhm.selected]) nhm.selected = Object.keys(nhm.indicators)[0] || null;
            nhmRefresh();
        }).fail(function () {
            $('#nhm-indicator-list').html('<div class="nhm-empty text-danger">Failed to load indicators.</div>');
        });
    }

    // keepCatalogFocus: after linking from a search, keep the cursor in the search box
    function nhmRefresh(keepCatalogFocus) {
        nhmRenderIndicatorList();
        nhmRenderDetail();
        nhmUpdateFooter();
        if (keepCatalogFocus && nhm.catalogSearch) {
            const el = document.getElementById('nhm-catalog-search');
            if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
        }
    }

    function nhmUpdateFooter() {
        const codes = Object.keys(nhm.working);
        const linked = codes.filter(function (c) { return nhm.working[c].length > 0; }).length;
        const dirty = nhmDirtyCodes().length;
        $('#nhm-stat-linked').text(linked);
        $('#nhm-stat-unlinked').text(codes.length - linked);
        $('#nhm-dirty-count').text(dirty);
        $('#nhm-stat-dirty').prop('hidden', dirty === 0);
        $('#btn-save-service-mappings').prop('disabled', dirty === 0)
            .html('<i class="mdi mdi-content-save"></i> ' + (dirty ? 'Save Changes (' + dirty + ')' : 'Save Changes'));
        $('#btn-discard-mappings').prop('disabled', dirty === 0);
    }

    function nhmModalityOf(meta) { return (meta && meta.service_type) || 'investigation'; }

    function nhmModalityIcon(mod) {
        return mod === 'imaging' ? 'mdi-radiology-box' : (mod === 'procedure' ? 'mdi-needle' : 'mdi-flask-outline');
    }

    function nhmRenderIndicatorList() {
        const $list = $('#nhm-indicator-list');
        const groups = {};
        Object.keys(nhm.indicators).forEach(function (code) {
            const meta = nhm.indicators[code];
            const mod = nhmModalityOf(meta);
            const count = (nhm.working[code] || []).length;
            if (nhm.modality !== 'all' && mod !== nhm.modality) return;
            if (nhm.unlinkedOnly && count > 0 && code !== nhm.selected) return;
            if (nhm.search) {
                const hay = (code + ' ' + (meta.label || '') + ' ' + (meta.section || '') + ' ' + (meta.description || '')).toLowerCase();
                if (hay.indexOf(nhm.search) === -1) return;
            }
            const sec = meta.section || 'Other';
            (groups[sec] = groups[sec] || []).push(code);
        });

        const sections = Object.keys(groups);
        if (!sections.length) {
            $list.html('<div class="nhm-empty">No indicators match your filters.</div>');
            return;
        }

        let html = '';
        sections.forEach(function (sec) {
            html += '<div class="nhm-group">' + nhmEsc(sec) + '</div>';
            groups[sec].forEach(function (code) {
                const meta = nhm.indicators[code];
                const count = nhm.working[code].length;
                const dirty = !nhmSameSet(nhm.working[code], nhm.original[code] || []);
                html += '<button type="button" class="nhm-ind' + (code === nhm.selected ? ' active' : '') + '" data-code="' + nhmEsc(code) + '">'
                    + '<i class="mdi ' + nhmModalityIcon(nhmModalityOf(meta)) + ' nhm-ind-icon"></i>'
                    + '<span class="nhm-ind-text"><span class="nhm-ind-label">' + nhmEsc(meta.label || code) + '</span>'
                    + '<span class="nhm-ind-code">' + nhmEsc(code) + '</span></span>'
                    + (dirty ? '<span class="nhm-dot" title="Unsaved changes"></span>' : '')
                    + '<span class="nhm-count ' + (count ? 'is-linked' : 'is-empty') + '" title="' + count + ' linked service(s)">' + (count || '0') + '</span>'
                    + '</button>';
            });
        });
        $list.html(html);
    }

    function nhmCatalogMatches() {
        const code = nhm.selected;
        const meta = nhm.indicators[code] || {};
        const linked = nhm.working[code] || [];
        const q = nhm.catalogSearch;
        return nhm.services.filter(function (s) {
            if (linked.indexOf(s.id) !== -1) return false;
            if (!nhm.catalogAll && meta.category_id && Number(s.category_id) !== Number(meta.category_id)) return false;
            if (q) {
                const hay = ((s.service_name || '') + ' ' + (s.service_code || '')).toLowerCase();
                return q.split(/\s+/).every(function (t) { return hay.indexOf(t) !== -1; });
            }
            return true;
        });
    }

    function nhmServiceRow(s, action, code, extraClass) {
        const others = nhmIndicatorsUsing(Number(s.id), code);
        const otherBadge = others.length
            ? '<span class="nhm-also" title="Also linked to: ' + nhmEsc(others.join(', ')) + '">also in ' + others.length + '</span>'
            : '';
        const btn = action === 'unlink'
            ? '<button type="button" class="nhm-btn nhm-unlink" data-id="' + s.id + '" title="Unlink this service"><i class="mdi mdi-close"></i> Unlink</button>'
            : '<button type="button" class="nhm-btn nhm-link" data-id="' + s.id + '" title="Link this service"><i class="mdi mdi-plus"></i> Link</button>';
        return '<div class="nhm-svc' + (extraClass ? ' ' + extraClass : '') + '">'
            + '<div class="nhm-svc-text"><span class="nhm-svc-name">' + nhmEsc(s.service_name) + '</span>'
            + '<span class="nhm-svc-meta">' + nhmEsc(s.service_code || ('ID ' + s.id)) + (s.category_name ? ' · ' + nhmEsc(s.category_name) : '') + '</span></div>'
            + otherBadge + btn + '</div>';
    }

    function nhmRenderDetail() {
        const $detail = $('#nhm-detail');
        const code = nhm.selected;
        if (!code || !nhm.indicators[code]) {
            $detail.html('<div class="nhm-empty nhm-empty-lg"><i class="mdi mdi-gesture-tap"></i><div>Select an indicator on the left to view and edit its linked services.</div></div>');
            return;
        }
        const meta = nhm.indicators[code];
        const m = nhm.meta[code] || {};
        const linked = nhm.working[code] || [];
        const original = nhm.original[code] || [];
        const dirty = !nhmSameSet(linked, original);
        const outcomes = (m.supported_outcomes || []).map(function (o) {
            const pos = (m.positive_outcomes || []).indexOf(o) !== -1;
            return '<span class="nhm-outcome' + (pos ? ' is-positive' : '') + '"' + (pos ? ' title="Counted as positive"' : '') + '>' + nhmEsc(o) + '</span>';
        }).join('');

        // Unsaved additions first so a fresh link is always visible without scrolling
        const ordered = linked.filter(function (id) { return original.indexOf(id) === -1; })
            .concat(linked.filter(function (id) { return original.indexOf(id) !== -1; }));
        let linkedHtml = ordered.map(function (id) {
            const s = nhm.serviceById[id] || { id: id, service_name: 'Service #' + id };
            return nhmServiceRow(s, 'unlink', code, original.indexOf(id) === -1 ? 'is-new' : '');
        }).join('');
        const removedCount = original.filter(function (id) { return linked.indexOf(id) === -1; }).length;
        if (!linked.length) {
            linkedHtml = '<div class="nhm-empty nhm-empty-sm"><i class="mdi mdi-link-variant-off"></i> No services linked — results for this indicator will not be counted.</div>';
        }

        $detail.html(
            '<div class="nhm-detail-head">'
            + '<div class="nhm-detail-title"><i class="mdi ' + nhmModalityIcon(nhmModalityOf(meta)) + '"></i> ' + nhmEsc(meta.label || code) + '</div>'
            + '<div class="nhm-detail-sub"><code>' + nhmEsc(code) + '</code>' + (meta.section ? ' · ' + nhmEsc(meta.section) : '') + '</div>'
            + (meta.description ? '<div class="nhm-detail-desc">' + nhmEsc(meta.description) + '</div>' : '')
            + (outcomes ? '<div class="nhm-outcomes"><span class="nhm-outcomes-label">Outcomes:</span>' + outcomes + '</div>' : '')
            + '</div>'

            + '<div class="nhm-block">'
            + '<div class="nhm-block-head"><span><i class="mdi mdi-link-variant"></i> Linked services <span class="nhm-pill">' + linked.length + '</span>'
            + (removedCount ? ' <span class="nhm-removed">' + removedCount + ' will be unlinked</span>' : '') + '</span>'
            + '<span class="nhm-block-actions">'
            + (dirty ? '<button type="button" class="btn btn-link btn-sm p-0" id="nhm-revert-one"><i class="mdi mdi-undo"></i> Revert</button>' : '')
            + (linked.length ? '<button type="button" class="btn btn-link btn-sm p-0 text-danger" id="nhm-unlink-all"><i class="mdi mdi-link-variant-off"></i> Unlink all</button>' : '')
            + '</span></div>'
            + '<div class="nhm-svc-list nhm-linked">' + linkedHtml + '</div></div>'

            + '<div class="nhm-block nhm-block-grow">'
            + '<div class="nhm-block-head"><span><i class="mdi mdi-plus-circle-outline"></i> Add services</span>'
            + '<label class="nhm-check mb-0"><input type="checkbox" id="nhm-catalog-all"' + (nhm.catalogAll ? ' checked' : '') + '> All categories</label></div>'
            + '<div class="nhm-search mb-2"><i class="mdi mdi-magnify"></i>'
            + '<input type="search" class="form-control form-control-sm" id="nhm-catalog-search" placeholder="Search hospital services by name or code..." autocomplete="off" value="' + nhmEsc(nhm.catalogSearch) + '"></div>'
            + '<div class="nhm-svc-list nhm-catalog" id="nhm-catalog"></div></div>'
        );
        nhmRenderCatalog();
    }

    function nhmRenderCatalog() {
        const matches = nhmCatalogMatches();
        const LIMIT = 150;
        let html = matches.slice(0, LIMIT).map(function (s) { return nhmServiceRow(s, 'link', nhm.selected); }).join('');
        if (!matches.length) {
            html = '<div class="nhm-empty nhm-empty-sm">'
                + (nhm.catalogSearch ? 'No services match "' + nhmEsc(nhm.catalogSearch) + '".' : 'No more services in this category.')
                + (nhm.catalogAll ? '' : ' Try ticking <b>All categories</b>.') + '</div>';
        } else {
            html += '<div class="nhm-catalog-foot"><span>'
                + (matches.length > LIMIT ? 'Showing ' + LIMIT + ' of ' + matches.length + ' — refine your search' : matches.length + ' available') + '</span>'
                + (nhm.catalogSearch && matches.length <= 25
                    ? '<button type="button" class="btn btn-link btn-sm p-0" id="nhm-link-all-visible"><i class="mdi mdi-playlist-plus"></i> Link all ' + matches.length + '</button>'
                    : '')
                + '</div>';
        }
        $('#nhm-catalog').html(html);
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

