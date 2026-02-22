/**
 * ClinicalOrdersKit — Shared module for Doctor Encounter + Nursing Workbench
 *
 * Ref: CLINICAL_ORDERS_PLAN.md §2.1 (Shared JS Module)
 *
 * Selector convention:
 *   Doctor  → prefix '' : .dose-amount, .dose-unit, .dose-route, .dose-frequency,
 *             .dose-duration, .dose-duration-unit, .dose-qty, .structured-dose-value,
 *             input[name="consult_presc_dose[]"], input[name="consult_presc_id[]"]
 *   Nurse   → prefix 'cr-' : .cr-dose-amount, .cr-dose-unit, .cr-dose-route,
 *             .cr-dose-freq, .cr-dose-dur, .cr-dose-dur-unit, .cr-dose-qty,
 *             .cr-structured-dose-value, input[name="cr_presc_dose[]"], input[name="cr_presc_id[]"]
 *
 * Each view passes a config object to specify its prefix, selectors, and endpoints.
 */
window.ClinicalOrdersKit = (function ($) {
    'use strict';

    /* ═══════════════════════════════════════════
       CONSTANTS  (Plan §2.1 — FREQ_MULTIPLIER_MAP / DUR_UNIT_MULTIPLIER_MAP)
       ═══════════════════════════════════════════ */
    const FREQ_MULTIPLIER_MAP = {
        'OD': 1, 'BD': 2, 'TDS': 3, 'QID': 4,
        'Q4H': 6, 'Q6H': 4, 'Q8H': 3, 'Q12H': 2,
        'PRN': 1, 'STAT': 1
    };

    const DUR_UNIT_MULTIPLIER_MAP = {
        'days': 1, 'weeks': 7, 'months': 30
    };

    /* ═══════════════════════════════════════════
       UTILITY
       ═══════════════════════════════════════════ */

    /**
     * Debounce helper (Plan §2.1 — new, for auto-save)
     */
    function debounce(fn, ms) {
        let timer;
        return function () {
            const ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    /**
     * Show inline alert message in a container (Plan §2.1 — replaces showMessage in both views)
     * @param {string} containerId  - DOM id of the message container
     * @param {string} msg          - Message text
     * @param {string} type         - 'success' | 'error' | 'warning' | 'info'
     * @param {number} autoClose    - ms before auto-dismiss (default 5000, 0 = no auto-close)
     */
    function showInlineMessage(containerId, msg, type, autoClose) {
        autoClose = autoClose !== undefined ? autoClose : 5000;
        var alertType = type === 'error' ? 'danger' : type;
        var $container = $('#' + containerId);
        $container.html(
            '<div class="alert alert-' + alertType + ' alert-dismissible fade show">' +
            msg +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
            '</div>'
        );
        try { document.getElementById(containerId).scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) { /* noop */ }
        if (autoClose > 0) {
            setTimeout(function () {
                $container.find('.alert').alert('close');
            }, autoClose);
        }
    }

    /**
     * Render HMO / cash coverage badge inline (Plan §2.1 — replaces 8+ inline duplications)
     */
    function renderCoverageBadge(mode, payable, claims) {
        if (!mode || mode === 'null' || mode === 'undefined') return '';
        return '<div class="small mt-1">' +
            '<span class="badge bg-info">' + mode.toUpperCase() + '</span> ' +
            '<span class="text-danger">Pay: ' + (payable || 0) + '</span> ' +
            '<span class="text-success">Claims: ' + (claims || 0) + '</span>' +
            '</div>';
    }

    /* ═══════════════════════════════════════════
       STRUCTURED DOSE BUILDER  (Plan §2.1 — buildStructuredDoseHtml)
       Exact selectors match current codebase:
         Doctor: .dose-amount, .dose-unit, .dose-route, .dose-frequency, .dose-duration,
                 .dose-duration-unit, .dose-qty, .structured-dose-value
         Nurse:  .cr-dose-amount, .cr-dose-unit, .cr-dose-route, .cr-dose-freq,
                 .cr-dose-dur, .cr-dose-dur-unit, .cr-dose-qty, .cr-structured-dose-value
       ═══════════════════════════════════════════ */

    /**
     * Build structured dose HTML for a single drug row.
     * @param {object} cfg
     *   cfg.prefix        - '' for doctor, 'cr-' for nurse
     *   cfg.cssPrefix     - class prefix: '' → '.dose-amount', 'cr-' → '.cr-dose-amount'
     *   cfg.hiddenName    - hidden input name: 'consult_presc_dose[]' or 'cr_presc_dose[]'
     *   cfg.wrapperClass  - 'structured-dose' or 'cr-structured-dose'
     *   cfg.hiddenClass   - 'structured-dose-value' or 'cr-structured-dose-value'
     *   cfg.onchange      - JS string for onchange handler
     *   cfg.drugName      - (optional) product name for inline calculator
     *   cfg.rowId         - (optional) unique row id for inline calculator targeting
     * @returns {string} HTML string
     */
    function buildStructuredDoseHtml(cfg) {
        var p = cfg.cssPrefix || '';
        var oc = cfg.onchange || '';
        var wrapCls = cfg.wrapperClass || (p ? 'cr-structured-dose' : 'structured-dose');
        var hiddenCls = cfg.hiddenClass || (p ? 'cr-structured-dose-value' : 'structured-dose-value');
        var hiddenName = cfg.hiddenName || (p ? 'cr_presc_dose[]' : 'consult_presc_dose[]');

        // Selector class names — must exactly match existing codebase selectors
        var amtCls   = p ? 'cr-dose-amount' : 'dose-amount';
        var unitCls  = p ? 'cr-dose-unit' : 'dose-unit';
        var routeCls = p ? 'cr-dose-route' : 'dose-route';
        var freqCls  = p ? 'cr-dose-freq' : 'dose-frequency';
        var durCls   = p ? 'cr-dose-dur' : 'dose-duration';
        var durUCls  = p ? 'cr-dose-dur-unit' : 'dose-duration-unit';
        var qtyCls   = p ? 'cr-dose-qty' : 'dose-qty';

        var rowIdAttr = cfg.rowId ? ' data-row-id="' + cfg.rowId + '"' : '';

        return '<div class="' + wrapCls + '"' + rowIdAttr + '>' +
            '<div class="row g-1 mb-1">' +
                '<div class="col-4">' +
                    '<input type="number" class="form-control form-control-sm ' + amtCls + '" placeholder="Amt" min="0" step="0.01" onchange="' + oc + '">' +
                '</div>' +
                '<div class="col-4">' +
                    '<select class="form-select form-select-sm ' + unitCls + '" onchange="' + oc + '">' +
                        '<option value="mg">mg</option><option value="g">g</option><option value="ml">ml</option>' +
                        '<option value="IU">IU</option><option value="mcg">mcg</option><option value="units">units</option>' +
                        '<option value="drops">drops</option><option value="puffs">puffs</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-4">' +
                    '<select class="form-select form-select-sm ' + routeCls + '" onchange="' + oc + '">' +
                        '<option value="PO">PO (Oral)</option><option value="IV">IV</option><option value="IM">IM</option>' +
                        '<option value="SC">SC</option><option value="SL">SL</option><option value="PR">PR</option>' +
                        '<option value="INH">INH (Inhaled)</option><option value="TOP">Topical</option>' +
                        '<option value="OPTH">Ophthalmic</option><option value="OT">Otic</option><option value="NGT">NGT</option>' +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="row g-1">' +
                '<div class="col-4">' +
                    '<select class="form-select form-select-sm ' + freqCls + '" onchange="' + oc + '">' +
                        '<option value="OD">OD (once daily)</option><option value="BD">BD (twice daily)</option>' +
                        '<option value="TDS">TDS (3x daily)</option><option value="QID">QID (4x daily)</option>' +
                        '<option value="Q4H">Q4H</option><option value="Q6H">Q6H</option><option value="Q8H">Q8H</option>' +
                        '<option value="Q12H">Q12H</option><option value="PRN">PRN (as needed)</option><option value="STAT">STAT (once)</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-4">' +
                    '<div class="input-group input-group-sm">' +
                        '<input type="number" class="form-control ' + durCls + '" placeholder="Dur" min="1" value="5" onchange="' + oc + '">' +
                        '<select class="form-select ' + durUCls + '" style="max-width:70px;" onchange="' + oc + '">' +
                            '<option value="days">d</option><option value="weeks">w</option><option value="months">m</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="col-4">' +
                    '<div class="input-group input-group-sm">' +
                        '<span class="input-group-text" style="font-size:0.75em;">Qty</span>' +
                        '<input type="number" class="form-control ' + qtyCls + '" placeholder="Qty" min="1">' +
                    '</div>' +
                '</div>' +
            '</div>' +
            // Per-drug calculator button (Plan §2.3)
            (cfg.drugName ? '<button type="button" class="btn btn-sm btn-outline-info mt-1 w-100 calc-toggle-btn" ' +
                'onclick="ClinicalOrdersKit.toggleRowCalc(this, \'' + cfg.drugName.replace(/'/g, "\\'") + '\', \'' + (cfg.rowId || '') + '\', \'' + (p || '') + '\')">' +
                '<i class="fa fa-calculator"></i> Calculator</button>' : '') +
            '<input type="hidden" name="' + hiddenName + '" class="' + hiddenCls + '" value="">' +
        '</div>';
    }

    /**
     * Auto-calculate Qty from frequency × duration (Plan §2.1)
     * Works for both doctor and nurse by reading the correct class selectors.
     */
    function autoCalculateQty($row, cssPrefix) {
        var p = cssPrefix || '';
        var freqSel = p ? '.cr-dose-freq' : '.dose-frequency';
        var durSel  = p ? '.cr-dose-dur' : '.dose-duration';
        var durUSel = p ? '.cr-dose-dur-unit' : '.dose-duration-unit';
        var qtySel  = p ? '.cr-dose-qty' : '.dose-qty';

        var freq = $row.find(freqSel).val() || 'OD';
        var dur = parseFloat($row.find(durSel).val()) || 0;
        var durUnit = $row.find(durUSel).val() || 'days';

        if (dur > 0 && freq !== 'PRN') {
            var totalDays = dur * (DUR_UNIT_MULTIPLIER_MAP[durUnit] || 1);
            var perDay = FREQ_MULTIPLIER_MAP[freq] || 1;
            var qty = Math.ceil(totalDays * perDay);
            $row.find(qtySel).val(qty);
        }
    }

    /**
     * Compose pipe-delimited dose value from structured fields and write to hidden input.
     * (Plan §2.1 — replaces updateStructuredDoseValue / updateDoseVal)
     *
     * @param {HTMLElement} el        - The changed element (any field in the structured-dose row)
     * @param {string}      cssPrefix - '' for doctor, 'cr-' for nurse
     */
    function updateDoseValue(el, cssPrefix) {
        var p = cssPrefix || '';
        var wrapSel = p ? '.cr-structured-dose' : '.structured-dose';
        var $row = $(el).closest(wrapSel);

        autoCalculateQty($row, p);

        var amtSel     = p ? '.cr-dose-amount' : '.dose-amount';
        var unitSel    = p ? '.cr-dose-unit' : '.dose-unit';
        var routeSel   = p ? '.cr-dose-route' : '.dose-route';
        var freqSel    = p ? '.cr-dose-freq' : '.dose-frequency';
        var durSel     = p ? '.cr-dose-dur' : '.dose-duration';
        var durUSel    = p ? '.cr-dose-dur-unit' : '.dose-duration-unit';
        var qtySel     = p ? '.cr-dose-qty' : '.dose-qty';
        var hiddenSel  = p ? '.cr-structured-dose-value' : '.structured-dose-value';

        var amount  = $row.find(amtSel).val() || '';
        var unit    = $row.find(unitSel).val() || '';
        var route   = $row.find(routeSel).val() || '';
        var freq    = $row.find(freqSel).val() || '';
        var dur     = $row.find(durSel).val() || '';
        var durUnit = $row.find(durUSel).val() || '';
        var qty     = $row.find(qtySel).val() || '';

        var parts = [];
        if (amount) parts.push(amount + unit);
        if (route) parts.push(route);
        if (freq) parts.push(freq);
        if (dur) parts.push(dur + ' ' + durUnit);
        if (qty) parts.push('Qty: ' + qty);

        $row.find(hiddenSel).val(parts.join(' | '));

        // Trigger auto-save debounce if a record ID exists on the row (Phase 2)
        var $tr = $row.closest('tr');
        var recordId = $tr.attr('data-record-id');
        if (recordId && _doseUpdateHandlers[p]) {
            _doseUpdateHandlers[p](recordId, parts.join(' | '));
        }
    }

    // Registered per-prefix dose update callbacks for auto-save (Phase 2)
    var _doseUpdateHandlers = {};

    /**
     * Register a debounced handler for dose field changes.
     * Called by each view during initialization.
     */
    function onDoseUpdate(cssPrefix, handler) {
        _doseUpdateHandlers[cssPrefix || ''] = debounce(handler, 800);
    }

    /**
     * Collapse structured dose back to text value (for mode toggle)
     */
    function collapseStructuredDose($td, cssPrefix) {
        var hiddenSel = (cssPrefix || '') ? '.cr-structured-dose-value' : '.structured-dose-value';
        return $td.find(hiddenSel).val() || '';
    }

    /* ═══════════════════════════════════════════
       DOSE MODE TOGGLE  (Plan §2.2 — initDoseModeToggle)
       ═══════════════════════════════════════════ */

    /**
     * Initialize the segmented dose mode toggle (Plan §2.2).
     * @param {object} config
     *   config.prefix         - '' or 'cr_' (radio input name/id prefix)
     *   config.cssPrefix      - '' or 'cr-' (CSS class prefix for dose fields)
     *   config.tableSelector  - e.g. '#selected-products' or '#cr-selected-products'
     *   config.idInputName    - e.g. 'consult_presc_id[]' or 'cr_presc_id[]'
     *   config.doseInputName  - e.g. 'consult_presc_dose[]' or 'cr_presc_dose[]'
     *   config.onchange       - JS string for field onchange (e.g. "updateStructuredDoseValue(this)")
     *   config.onToggle       - optional callback(isStructured)
     * @returns {{ isStructured: boolean }}
     */
    function initDoseModeToggle(config) {
        var simpleRadio = document.getElementById(config.prefix + 'dose_mode_simple');
        var structuredRadio = document.getElementById(config.prefix + 'dose_mode_structured');
        var state = { isStructured: true }; // Structured is default (Plan §2.2)

        if (!simpleRadio || !structuredRadio) {
            // Fallback: try old checkbox
            var checkbox = document.getElementById(config.prefix + 'dose_mode_toggle');
            if (checkbox) {
                state.isStructured = checkbox.checked;
            }
            return state;
        }

        function convertRows(isStructured) {
            $(config.tableSelector + ' tr').each(function () {
                var $td = $(this).find('td:eq(2)');
                var hiddenId = $td.find('input[name="' + config.idInputName + '"]').prop('outerHTML') || '';
                if (isStructured) {
                    var val = $td.find('input[name="' + config.doseInputName + '"]').val() || '';
                    $td.html(buildStructuredDoseHtml({
                        cssPrefix: config.cssPrefix,
                        hiddenName: config.doseInputName,
                        onchange: config.onchange,
                        drugName: $(this).attr('data-drug-name') || '',
                        rowId: $(this).attr('data-row-id') || ''
                    }) + hiddenId);
                } else {
                    var collapsed = collapseStructuredDose($td, config.cssPrefix);
                    $td.html('<input type="text" class="form-control form-control-sm" name="' +
                        config.doseInputName + '" value="' + collapsed + '" required>' + hiddenId);
                }
            });
        }

        simpleRadio.addEventListener('change', function () {
            state.isStructured = false;
            convertRows(false);
            if (config.onToggle) config.onToggle(false);
        });
        structuredRadio.addEventListener('change', function () {
            state.isStructured = true;
            convertRows(true);
            if (config.onToggle) config.onToggle(true);
        });

        return state;
    }

    /* ═══════════════════════════════════════════
       PER-DRUG INLINE CALCULATOR  (Plan §2.3)
       ═══════════════════════════════════════════ */

    /**
     * Parse strength + unit from a drug product name (Plan §2.3).
     * Examples:
     *   "Amoxicillin 500mg Capsules" → { amount: 500, unit: 'mg' }
     *   "Paracetamol Syrup"          → null
     */
    function parseStrengthFromName(name) {
        var match = (name || '').match(/(\d+(?:\.\d+)?)\s*(mg|g|ml|mcg|iu|units?)\b/i);
        if (match) {
            return {
                amount: parseFloat(match[1]),
                unit: match[2].toLowerCase().replace(/^unit$/, 'units')
            };
        }
        return null;
    }

    /**
     * Build the inline calculator row HTML for a drug (Plan §2.3).
     */
    function buildInlineCalculatorHtml(drugName, rowId, cssPrefix) {
        var strength = parseStrengthFromName(drugName);
        var strengthVal = strength ? strength.amount : '';
        var strengthUnit = strength ? strength.unit : 'mg';
        var strengthBadge = strength ? '<span class="badge bg-success ms-1" style="font-size:0.7em;">auto-detected</span>' : '';
        var weight = window.patientWeight || '';
        var id = 'calc_' + (rowId || Date.now());

        return '<tr class="calc-row calc-enter" data-calc-for="' + rowId + '">' +
            '<td colspan="4" class="p-2">' +
                '<div class="dose-calc-inline border rounded p-2 bg-light">' +
                    '<div class="d-flex justify-content-between align-items-center mb-2">' +
                        '<strong><i class="fa fa-calculator text-info"></i> Calculator for ' + $('<span>').text(drugName).html() + '</strong>' +
                        '<button type="button" class="btn-close btn-sm" onclick="ClinicalOrdersKit.closeCalc(\'' + rowId + '\')"></button>' +
                    '</div>' +
                    '<div class="row g-2">' +
                        '<div class="col-6 col-md-3">' +
                            '<label class="form-label small">Weight (kg)</label>' +
                            '<input type="number" class="form-control form-control-sm calc-weight" id="' + id + '_weight" ' +
                                'step="0.1" min="0" value="' + weight + '" oninput="ClinicalOrdersKit.liveCalc(\'' + rowId + '\',\'' + (cssPrefix || '') + '\')">' +
                        '</div>' +
                        '<div class="col-6 col-md-3">' +
                            '<label class="form-label small">Dose/kg (mg/kg)</label>' +
                            '<input type="number" class="form-control form-control-sm calc-dose-per-kg" id="' + id + '_dpk" ' +
                                'step="0.01" min="0" oninput="ClinicalOrdersKit.liveCalc(\'' + rowId + '\',\'' + (cssPrefix || '') + '\')">' +
                        '</div>' +
                        '<div class="col-6 col-md-3">' +
                            '<label class="form-label small">Frequency</label>' +
                            '<select class="form-select form-select-sm calc-freq" id="' + id + '_freq" onchange="ClinicalOrdersKit.liveCalc(\'' + rowId + '\',\'' + (cssPrefix || '') + '\')">' +
                                '<option value="1">OD (once daily)</option><option value="2">BD (twice daily)</option>' +
                                '<option value="3">TDS (three times)</option><option value="4">QID (four times)</option>' +
                                '<option value="6">Q4H (every 4 hrs)</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="col-6 col-md-3">' +
                            '<label class="form-label small">Duration (days)</label>' +
                            '<input type="number" class="form-control form-control-sm calc-duration" id="' + id + '_dur" ' +
                                'min="1" value="5" oninput="ClinicalOrdersKit.liveCalc(\'' + rowId + '\',\'' + (cssPrefix || '') + '\')">' +
                        '</div>' +
                    '</div>' +
                    '<div class="row g-2 mt-1">' +
                        '<div class="col-6 col-md-3">' +
                            '<label class="form-label small">Strength ' + strengthBadge + '</label>' +
                            '<div class="input-group input-group-sm">' +
                                '<input type="number" class="form-control calc-tab-strength" id="' + id + '_str" ' +
                                    'step="0.01" min="0" value="' + strengthVal + '" oninput="ClinicalOrdersKit.liveCalc(\'' + rowId + '\',\'' + (cssPrefix || '') + '\')">' +
                                '<input type="hidden" class="calc-tab-strength-unit" value="' + strengthUnit + '">' +
                                '<span class="input-group-text">' + strengthUnit + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="col-12 col-md-6">' +
                            '<div class="calc-results mt-2 small" id="' + id + '_results">' +
                                '<span class="text-muted">Enter weight and dose/kg to calculate...</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="col-12 col-md-3 d-flex align-items-end gap-1">' +
                            '<button type="button" class="btn btn-sm btn-primary flex-grow-1" ' +
                                'onclick="ClinicalOrdersKit.applyCalcToRow(\'' + rowId + '\',\'' + (cssPrefix || '') + '\')">' +
                                '<i class="fa fa-check"></i> Apply</button>' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary" ' +
                                'onclick="ClinicalOrdersKit.closeCalc(\'' + rowId + '\')">' +
                                '<i class="fa fa-times"></i></button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
        '</tr>';
    }

    /**
     * Toggle inline calculator for a drug row (Plan §2.3).
     */
    function toggleRowCalc(btn, drugName, rowId, cssPrefix) {
        var $existing = $('tr.calc-row[data-calc-for="' + rowId + '"]');
        if ($existing.length) {
            $existing.remove();
            return;
        }
        // Close any other open calculators
        $('tr.calc-row').remove();

        var $drugRow = $(btn).closest('tr');
        var calcHtml = buildInlineCalculatorHtml(drugName, rowId, cssPrefix);
        $drugRow.after(calcHtml);
    }

    /**
     * Close an inline calculator row.
     */
    function closeCalc(rowId) {
        $('tr.calc-row[data-calc-for="' + rowId + '"]').remove();
    }

    /**
     * Live-calculate dose results in the inline calculator (Plan §2.3).
     */
    function liveCalc(rowId, cssPrefix) {
        var $calcRow = $('tr.calc-row[data-calc-for="' + rowId + '"]');
        if (!$calcRow.length) return;

        var weight = parseFloat($calcRow.find('.calc-weight').val()) || 0;
        var dosePerKg = parseFloat($calcRow.find('.calc-dose-per-kg').val()) || 0;
        var freqPerDay = parseInt($calcRow.find('.calc-freq').val()) || 1;
        var duration = parseInt($calcRow.find('.calc-duration').val()) || 1;
        var tabStrength = parseFloat($calcRow.find('.calc-tab-strength').val()) || 1;

        // Update global patientWeight if user changes it (Plan §2.3 — weight persistence)
        if (weight > 0) {
            window.patientWeight = weight;
        }

        var $results = $calcRow.find('.calc-results');

        if (weight <= 0 || dosePerKg <= 0) {
            $results.html('<span class="text-muted">Enter weight and dose/kg to calculate...</span>');
            return;
        }

        var singleDose = weight * dosePerKg;
        var dailyDose = singleDose * freqPerDay;
        var totalCourse = dailyDose * duration;
        var totalUnits = Math.ceil(totalCourse / tabStrength);

        // Read the actual unit from the strength field (or default to 'mg')
        var calcUnit = ($calcRow.find('.calc-tab-strength-unit').val() || 'mg').trim();

        $results.html(
            '<div class="d-flex flex-wrap gap-3">' +
                '<span><strong>Single:</strong> <span class="text-primary">' + singleDose.toFixed(1) + ' ' + calcUnit + '</span></span>' +
                '<span><strong>Daily:</strong> <span class="text-info">' + dailyDose.toFixed(1) + ' ' + calcUnit + '</span></span>' +
                '<span><strong>Course:</strong> <span class="text-warning">' + totalCourse.toFixed(1) + ' ' + calcUnit + '</span></span>' +
                '<span><strong>Qty:</strong> <span class="badge bg-success">' + totalUnits + '</span> × ' + tabStrength + calcUnit + '</span>' +
            '</div>'
        );
    }

    /**
     * Apply calculator results to the parent drug row (Plan §2.3).
     */
    function applyCalcToRow(rowId, cssPrefix) {
        var $calcRow = $('tr.calc-row[data-calc-for="' + rowId + '"]');
        if (!$calcRow.length) return;

        var weight = parseFloat($calcRow.find('.calc-weight').val()) || 0;
        var dosePerKg = parseFloat($calcRow.find('.calc-dose-per-kg').val()) || 0;
        var tabStrength = parseFloat($calcRow.find('.calc-tab-strength').val()) || 1;
        var freqPerDay = parseInt($calcRow.find('.calc-freq').val()) || 1;
        var duration = parseInt($calcRow.find('.calc-duration').val()) || 1;

        if (weight <= 0 || dosePerKg <= 0) {
            if (typeof toastr !== 'undefined') toastr.warning('Enter weight and dose/kg first.');
            return;
        }

        var singleDose = weight * dosePerKg;
        var totalTablets = Math.ceil((singleDose * freqPerDay * duration) / tabStrength);

        // Map freqPerDay → frequency code
        var freqReverseMap = { 1: 'OD', 2: 'BD', 3: 'TDS', 4: 'QID', 6: 'Q4H' };
        var freqCode = freqReverseMap[freqPerDay] || 'OD';

        // Find the drug row (the row before this calc row)
        var p = cssPrefix || '';
        var wrapSel  = p ? '.cr-structured-dose' : '.structured-dose';
        var amtSel   = p ? '.cr-dose-amount' : '.dose-amount';
        var unitSel  = p ? '.cr-dose-unit' : '.dose-unit';
        var freqSel  = p ? '.cr-dose-freq' : '.dose-frequency';
        var durSel   = p ? '.cr-dose-dur' : '.dose-duration';
        var durUSel  = p ? '.cr-dose-dur-unit' : '.dose-duration-unit';
        var qtySel   = p ? '.cr-dose-qty' : '.dose-qty';

        var $drugRow = $calcRow.prev('tr');
        var $dose = $drugRow.find(wrapSel);

        if ($dose.length) {
            // Structured mode — fill individual fields
            $dose.find(amtSel).val(singleDose.toFixed(1));
            $dose.find(unitSel).val('mg');
            $dose.find(freqSel).val(freqCode);
            $dose.find(durSel).val(duration);
            $dose.find(durUSel).val('days');
            $dose.find(qtySel).val(totalTablets);
            updateDoseValue($dose.find(amtSel)[0], p);
        } else {
            // Simple mode — compose pipe-delimited string
            var doseInputName = p ? 'cr_presc_dose[]' : 'consult_presc_dose[]';
            var composed = singleDose.toFixed(1) + 'mg | PO | ' + freqCode + ' | ' + duration + ' days | Qty: ' + totalTablets;
            $drugRow.find('input[name="' + doseInputName + '"]').val(composed);
        }

        // Flash green on the drug row
        $drugRow.css('background-color', '#d4edda');
        setTimeout(function () { $drugRow.css('background-color', ''); }, 1200);

        // Close calculator
        closeCalc(rowId);

        if (typeof toastr !== 'undefined') {
            toastr.success('Calculator applied');
        }
    }

    /* ═══════════════════════════════════════════
       AUTO-SAVE: addItem / removeItem  (Plan §4.2)
       ═══════════════════════════════════════════ */

    /**
     * Tracks added reference IDs for duplicate filtering (Plan §4.4).
     */
    var addedIds = {
        labs: new Set(),
        imaging: new Set(),
        meds: new Set(),
        procedures: new Set()
    };

    /**
     * Instant add & auto-save a clinical order item (Plan §4.2).
     * @param {object} config
     *   config.url           - POST endpoint
     *   config.payload       - { service_id, note } or { product_id, dose } etc.
     *   config.csrfToken     - CSRF token string
     *   config.tableSelector - e.g. '#selected-services'
     *   config.buildRowHtml  - function(serverItem) → HTML string for the <tr>
     *   config.type          - 'labs' | 'imaging' | 'meds' | 'procedures'
     *   config.referenceId   - the service_id or product_id being added
     *   config.onSuccess     - optional callback after success
     */
    function addItem(config) {
        // Check for duplicate
        if (config.type && config.referenceId && addedIds[config.type]) {
            if (addedIds[config.type].has(config.referenceId)) {
                if (typeof toastr !== 'undefined') toastr.warning('Already added');
                return;
            }
        }

        // Optimistic: add to Set BEFORE the AJAX call to prevent double-click race (E2)
        if (config.type && config.referenceId && addedIds[config.type]) {
            addedIds[config.type].add(config.referenceId);
        }

        // Show placeholder row with spinner
        var tempId = 'temp_' + Date.now();
        var $table = $(config.tableSelector);
        var colCount = $table.closest('table').find('thead th').length || 4;
        $table.append(
            '<tr id="' + tempId + '">' +
            '<td colspan="' + colCount + '" class="text-center text-muted">' +
            '<i class="fa fa-spinner fa-spin"></i> Adding...</td></tr>'
        );

        config.payload._token = config.csrfToken;

        $.ajax({
            url: config.url,
            method: 'POST',
            data: config.payload,
            success: function (response) {
                $('#' + tempId).remove();
                if (response.success) {
                    var rowHtml = config.buildRowHtml(response);
                    $table.append(rowHtml);
                    // ID already tracked optimistically
                    // Update status line
                    updateStatusLine(config.tableSelector, config.type);
                    if (config.onSuccess) config.onSuccess(response);
                } else {
                    // Rollback optimistic add on failure
                    if (config.type && config.referenceId && addedIds[config.type]) {
                        addedIds[config.type].delete(config.referenceId);
                    }
                    if (typeof toastr !== 'undefined') toastr.error(response.message || 'Failed to add item');
                }
            },
            error: function (xhr) {
                $('#' + tempId).remove();
                // Rollback optimistic add on error
                if (config.type && config.referenceId && addedIds[config.type]) {
                    addedIds[config.type].delete(config.referenceId);
                }
                var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Server error') : 'Server error';
                if (typeof toastr !== 'undefined') toastr.error(msg);
            }
        });
    }

    /**
     * Remove a clinical order item via DELETE (Plan §4.2).
     * @param {object} config
     *   config.url           - DELETE endpoint  e.g. '/encounters/5/remove-lab/42'
     *   config.csrfToken     - CSRF token
     *   config.rowSelector   - jQuery selector for the <tr> to remove
     *   config.type          - 'labs' | 'imaging' | 'meds' | 'procedures'
     *   config.referenceId   - the service_id or product_id being removed
     *   config.onSuccess     - optional callback
     */
    function removeItem(config) {
        var $row = $(config.rowSelector);
        $row.css('opacity', '0.5');

        $.ajax({
            url: config.url,
            method: 'DELETE',
            data: { _token: config.csrfToken },
            success: function (response) {
                $row.fadeOut(300, function () { $(this).remove(); });
                if (config.type && config.referenceId && addedIds[config.type]) {
                    addedIds[config.type].delete(config.referenceId);
                }
                updateStatusLine(config.tableSelector, config.type);
                if (config.onSuccess) config.onSuccess(response);
            },
            error: function (xhr) {
                $row.css('opacity', '1');
                var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Error removing item') : 'Error removing item';
                if (typeof toastr !== 'undefined') toastr.error(msg);
            }
        });
    }

    /**
     * Update auto-save status line below selection table (Plan §4.5).
     */
    function updateStatusLine(tableSelector, type) {
        var count = $(tableSelector + ' tr[data-record-id]').length;
        var $status = $(tableSelector).closest('.table-responsive').find('.auto-save-status');
        if ($status.length === 0) {
            $(tableSelector).closest('.table-responsive').after(
                '<div class="auto-save-status text-muted small mt-1"></div>'
            );
            $status = $(tableSelector).closest('.table-responsive').next('.auto-save-status');
        }
        if (count > 0) {
            $status.html('<i class="fa fa-check-circle text-success"></i> ' + count + ' item' + (count > 1 ? 's' : '') + ' added (auto-saved)');
        } else {
            $status.html('');
        }
    }

    /**
     * Check if a reference ID is already added for a given type (Plan §4.4 — duplicate filtering).
     * Used by search result renderers.
     */
    function isAlreadyAdded(type, referenceId) {
        return addedIds[type] ? addedIds[type].has(referenceId) : false;
    }

    /**
     * Manually mark an ID as added (for pre-loading existing items).
     */
    function markAdded(type, referenceId) {
        if (addedIds[type]) addedIds[type].add(referenceId);
    }

    /**
     * Scan existing rows in a selection table and populate addedIds (E7).
     * Call after page load to sync duplicate tracking with server-rendered or pre-existing items.
     * @param {string} tableSelector  e.g. '#selected-services'
     * @param {string} type           addedIds key: 'labs' | 'imaging' | 'meds' | 'procedures'
     */
    function scanExistingRows(tableSelector, type) {
        $(tableSelector + ' tr[data-record-id]').each(function () {
            var refId = parseInt($(this).data('service-id') || $(this).data('product-id') || 0);
            if (refId && addedIds[type]) addedIds[type].add(refId);
        });
    }

    /**
     * Clear all tracked IDs (e.g. when switching patients in nurse workbench).
     */
    function clearAddedIds() {
        addedIds.labs.clear();
        addedIds.imaging.clear();
        addedIds.meds.clear();
        addedIds.procedures.clear();
    }

    /**
     * Debounced note/field update via PUT (for lab notes, imaging notes, dose).
     * @param {object} config
     *   config.url       - PUT endpoint
     *   config.payload   - { note: '...' } or { dose: '...' }
     *   config.csrfToken - CSRF token
     *   config.onSuccess - optional callback
     */
    var _noteTimers = {};
    function debouncedUpdate(config) {
        var key = config.url;
        if (_noteTimers[key]) clearTimeout(_noteTimers[key]);
        _noteTimers[key] = setTimeout(function () {
            config.payload._token = config.csrfToken;
            config.payload._method = 'PUT';
            $.ajax({
                url: config.url,
                method: 'POST', // laravel method spoofing
                data: config.payload,
                success: function (response) {
                    if (config.onSuccess) config.onSuccess(response);
                },
                error: function () { /* silent fail for debounced updates */ }
            });
        }, 800);
    }

    /* ═══════════════════════════════════════════
       TREATMENT PLANS  (Plan §6.4)
       ═══════════════════════════════════════════ */

    var _tpCurrentPlanId = null;
    var _tpApplyConfig = null; // { url, csrfToken, extraPayload, onSuccess }

    /**
     * Initialize the treatment plans module.
     * @param {object} config
     *   config.applyUrl    - POST endpoint (doctor or nurse)
     *   config.csrfToken   - CSRF token
     *   config.extraPayload - e.g. { patient_id: ... } for nurse
     *   config.onApplySuccess - callback after items are applied
     *   config.currentItemsGatherer - function() → items[] for "save as template"
     */
    function initTreatmentPlans(config) {
        _tpApplyConfig = config;

        // Browse: search input
        var _tpSearchTimer;
        $('#tp-search-input').off('keyup.tp').on('keyup.tp', function () {
            clearTimeout(_tpSearchTimer);
            var q = $(this).val();
            _tpSearchTimer = setTimeout(function () { _tpBrowsePlans(q); }, 400);
        });

        // Browse: specialty filter
        $('#tp-specialty-filter').off('change.tp').on('change.tp', function () {
            _tpBrowsePlans($('#tp-search-input').val());
        });

        // Apply button
        $('#tp-apply-btn').off('click.tp').on('click.tp', function () {
            _tpApplyPlan();
        });

        // Save template confirm button
        $('#save-tpl-confirm-btn').off('click.tp').on('click.tp', function () {
            _tpSaveAsTemplate();
        });

        // On modal show, load plans
        $('#treatmentPlanModal').off('shown.bs.modal.tp').on('shown.bs.modal.tp', function () {
            _tpBrowsePlans('');
        });
    }

    /**
     * Browse/search treatment plans.
     */
    var _tpTypeLabels = { lab: 'Lab Tests', imaging: 'Imaging', medication: 'Medications', procedure: 'Procedures' };
    var _tpTypeIcons  = { lab: 'mdi-test-tube', imaging: 'mdi-radioactive', medication: 'mdi-pill', procedure: 'mdi-hospital' };

    function _tpBuildItemPreviewHtml(items) {
        var groups = { lab: [], imaging: [], medication: [], procedure: [] };
        (items || []).forEach(function (item) {
            if (groups[item.item_type]) groups[item.item_type].push(item);
        });

        var html = '';
        Object.keys(groups).forEach(function (type) {
            var list = groups[type];
            if (list.length === 0) return;
            html += '<div class="mb-1"><small class="fw-bold text-muted"><i class="mdi ' + _tpTypeIcons[type] + '"></i> ' + _tpTypeLabels[type] + '</small>';
            html += '<ul class="list-unstyled ms-3 mb-0">';
            list.forEach(function (item) {
                var name = $('<span>').text(item.display_name || 'Unknown').html();
                var extra = '';
                if (item.item_type === 'medication' && item.dose) extra = ' <small class="text-muted">(' + $('<span>').text(item.dose).html() + ')</small>';
                html += '<li class="py-0"><small>' + name + extra + '</small></li>';
            });
            html += '</ul></div>';
        });
        return html || '<small class="text-muted">No items</small>';
    }

    function _tpBrowsePlans(search) {
        var specialty = $('#tp-specialty-filter').val() || '';
        var params = { search: search || '', specialty: specialty };
        var url = '/treatment-plans?' + $.param(params);

        $('#tp-plan-list').html('<div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');

        $.get(url, function (response) {
            var plans = response.data || [];
            if (plans.length === 0) {
                $('#tp-plan-list').html('<div class="text-center text-muted py-3">No plans found</div>');
                return;
            }

            var html = '';
            plans.forEach(function (plan) {
                var itemCount = plan.items_count || (plan.items ? plan.items.length : 0);
                var badge = plan.is_global ? '<span class="badge bg-info ms-1">Global</span>' : '';
                var previewHtml = _tpBuildItemPreviewHtml(plan.items || []);

                html += '<div class="list-group-item list-group-item-action tp-plan-row" data-plan-id="' + plan.id + '" style="cursor:pointer;">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                        '<div><strong>' + $('<span>').text(plan.name).html() + '</strong>' + badge +
                            (plan.specialty ? ' <small class="text-muted">(' + $('<span>').text(plan.specialty).html() + ')</small>' : '') +
                        '</div>' +
                        '<div>' +
                            '<span class="badge bg-secondary">' + itemCount + ' items</span>' +
                            ' <i class="fa fa-chevron-down tp-expand-icon text-muted" style="font-size:0.7rem; transition:transform 0.2s;"></i>' +
                        '</div>' +
                    '</div>' +
                    (plan.description ? '<small class="text-muted d-block">' + $('<span>').text(plan.description).html() + '</small>' : '') +
                    '<div class="tp-inline-preview mt-1 pt-1 border-top" style="display:none;">' +
                        previewHtml +
                        '<div class="text-end mt-2"><button type="button" class="btn btn-primary btn-sm tp-select-plan-btn" data-plan-id="' + plan.id + '">' +
                            '<i class="fa fa-check-circle"></i> Select &amp; Apply</button></div>' +
                    '</div>' +
                '</div>';
            });
            $('#tp-plan-list').html('<div class="list-group">' + html + '</div>');

            // Single click to expand/collapse inline preview
            $('.tp-plan-row').off('click.expand').on('click.expand', function (e) {
                // Don't toggle if clicking the Select & Apply button
                if ($(e.target).closest('.tp-select-plan-btn').length) return;

                var $row = $(this);
                var $preview = $row.find('.tp-inline-preview');
                var $icon = $row.find('.tp-expand-icon');
                var isOpen = $preview.is(':visible');

                // Collapse other open previews
                $('.tp-inline-preview:visible').not($preview).slideUp(150);
                $('.tp-expand-icon').css('transform', 'rotate(0deg)');

                if (isOpen) {
                    $preview.slideUp(150);
                    $icon.css('transform', 'rotate(0deg)');
                } else {
                    $preview.slideDown(150);
                    $icon.css('transform', 'rotate(180deg)');
                }
            });

            // "Select & Apply" button → opens full preview tab with checkboxes + Apply button
            $(document).off('click.tpselect').on('click.tpselect', '.tp-select-plan-btn', function (e) {
                e.stopPropagation();
                _tpPreviewPlan($(this).data('plan-id'));
            });
        }).fail(function () {
            $('#tp-plan-list').html('<div class="text-center text-danger py-3">Failed to load plans</div>');
        });
    }

    /**
     * Preview a treatment plan — load items, show checkboxes.
     */
    function _tpPreviewPlan(planId) {
        _tpCurrentPlanId = planId;
        // Switch to preview tab
        var triggerEl = document.querySelector('#tp-preview-tab');
        if (triggerEl) new bootstrap.Tab(triggerEl).show();

        $('#tp-preview-content').html('<div class="text-center py-3"><i class="fa fa-spinner fa-spin"></i></div>');
        $('#tp-modal-footer').show();
        $('#tp-apply-btn').prop('disabled', true);

        $.get('/treatment-plans/' + planId, function (response) {
            if (!response.success) {
                $('#tp-preview-content').html('<div class="text-danger">Failed to load plan</div>');
                return;
            }

            var plan = response.plan;
            var html = '<h6>' + $('<span>').text(plan.name).html() + '</h6>';
            if (plan.description) html += '<p class="text-muted small">' + $('<span>').text(plan.description).html() + '</p>';

            // Group by type
            var groups = { lab: [], imaging: [], medication: [], procedure: [] };
            (plan.items || []).forEach(function (item) {
                if (groups[item.item_type]) groups[item.item_type].push(item);
            });

            Object.keys(groups).forEach(function (type) {
                var items = groups[type];
                if (items.length === 0) return;

                html += '<div class="mb-2">';
                html += '<strong><i class="mdi ' + _tpTypeIcons[type] + '"></i> ' + _tpTypeLabels[type] + '</strong>';
                html += '<div class="list-group list-group-flush">';
                items.forEach(function (item) {
                    var extra = '';
                    if (item.item_type === 'medication' && item.dose) extra = ' <small class="text-muted">Dose: ' + $('<span>').text(item.dose).html() + '</small>';
                    if (item.price) extra += ' <small class="text-muted">₦' + Number(item.price).toLocaleString() + '</small>';

                    html += '<label class="list-group-item list-group-item-sm py-1">' +
                        '<input type="checkbox" class="form-check-input me-2 tp-item-check" value="' + item.id + '" checked> ' +
                        $('<span>').text(item.display_name || 'Unknown').html() + extra +
                    '</label>';
                });
                html += '</div></div>';
            });

            $('#tp-preview-content').html(html);
            $('#tp-apply-btn').prop('disabled', false);

            // Enable/disable apply based on checkboxes
            $(document).off('change.tpcheck').on('change.tpcheck', '.tp-item-check', function () {
                var anyChecked = $('.tp-item-check:checked').length > 0;
                $('#tp-apply-btn').prop('disabled', !anyChecked);
            });
        }).fail(function () {
            $('#tp-preview-content').html('<div class="text-danger">Failed to load plan</div>');
        });
    }

    /**
     * Apply the previewed treatment plan.
     */
    function _tpApplyPlan() {
        if (!_tpCurrentPlanId || !_tpApplyConfig) return;

        var selectedIds = [];
        $('.tp-item-check:checked').each(function () {
            selectedIds.push(parseInt($(this).val()));
        });

        if (selectedIds.length === 0) {
            if (typeof toastr !== 'undefined') toastr.warning('No items selected');
            return;
        }

        var $btn = $('#tp-apply-btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Applying...');

        var payload = $.extend({}, _tpApplyConfig.extraPayload || {}, {
            _token: _tpApplyConfig.csrfToken,
            treatment_plan_id: _tpCurrentPlanId,
            selected_item_ids: selectedIds
        });

        $.ajax({
            url: _tpApplyConfig.applyUrl,
            method: 'POST',
            data: payload,
            success: function (response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') toastr.success(response.message || 'Treatment plan applied');
                    $('#treatmentPlanModal').modal('hide');
                    if (_tpApplyConfig.onApplySuccess) _tpApplyConfig.onApplySuccess(response);
                } else {
                    if (typeof toastr !== 'undefined') toastr.error(response.message || 'Failed to apply plan');
                }
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Apply Selected Items');
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Server error') : 'Server error';
                if (typeof toastr !== 'undefined') toastr.error(msg);
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Apply Selected Items');
            }
        });
    }

    /**
     * Open the "Save as template" modal.
     * Gathers current items from the view and saves as a new plan.
     */
    function openSaveTemplateModal() {
        // Reset form
        $('#save-tpl-name').val('');
        $('#save-tpl-desc').val('');
        $('#save-tpl-specialty').val('');
        $('#save-tpl-global').prop('checked', false);

        // Populate items preview
        var previewHtml = '<div class="text-center text-muted py-3">No items to save</div>';
        if (_tpApplyConfig && _tpApplyConfig.currentItemsGatherer) {
            var items = _tpApplyConfig.currentItemsGatherer();
            if (items && items.length > 0) {
                var groups = { lab: [], imaging: [], medication: [], procedure: [] };
                items.forEach(function (item) {
                    if (groups[item.item_type]) groups[item.item_type].push(item);
                });

                previewHtml = '';
                Object.keys(groups).forEach(function (type) {
                    var list = groups[type];
                    if (list.length === 0) return;
                    previewHtml += '<div class="mb-2"><strong class="text-muted"><i class="mdi ' + _tpTypeIcons[type] + '"></i> ' + _tpTypeLabels[type] + ' (' + list.length + ')</strong>';
                    previewHtml += '<ul class="list-unstyled ms-3 mb-0">';
                    list.forEach(function (item) {
                        var name = item.display_name || item.name || ('ID: ' + item.reference_id);
                        var extra = '';
                        if (item.item_type === 'medication' && item.dose) extra = ' <small class="text-muted">(' + $('<span>').text(item.dose).html() + ')</small>';
                        previewHtml += '<li class="py-0"><i class="fa fa-check-circle text-success" style="font-size:0.7rem;"></i> <small>' + $('<span>').text(name).html() + extra + '</small></li>';
                    });
                    previewHtml += '</ul></div>';
                });
                previewHtml += '<div class="border-top pt-1 mt-1"><small class="text-muted fw-bold">Total: ' + items.length + ' item(s)</small></div>';
            }
        }
        $('#save-tpl-preview').html(previewHtml);

        var modal = new bootstrap.Modal(document.getElementById('saveTemplateModal'));
        modal.show();
    }

    function _tpSaveAsTemplate() {
        var name = $('#save-tpl-name').val().trim();
        if (!name) {
            if (typeof toastr !== 'undefined') toastr.warning('Please enter a template name');
            return;
        }

        if (!_tpApplyConfig || !_tpApplyConfig.currentItemsGatherer) {
            if (typeof toastr !== 'undefined') toastr.error('Cannot gather current items');
            return;
        }

        var items = _tpApplyConfig.currentItemsGatherer();
        if (!items || items.length === 0) {
            if (typeof toastr !== 'undefined') toastr.warning('No items to save. Add some orders first.');
            return;
        }

        var $btn = $('#save-tpl-confirm-btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '/treatment-plans/from-current',
            method: 'POST',
            data: {
                _token: _tpApplyConfig.csrfToken,
                name: name,
                description: $('#save-tpl-desc').val().trim(),
                specialty: $('#save-tpl-specialty').val().trim(),
                is_global: $('#save-tpl-global').is(':checked') ? 1 : 0,
                items: items
            },
            success: function (response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') toastr.success(response.message || 'Template saved');
                    $('#saveTemplateModal').modal('hide');
                } else {
                    if (typeof toastr !== 'undefined') toastr.error(response.message || 'Failed to save template');
                }
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Template');
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Server error') : 'Server error';
                if (typeof toastr !== 'undefined') toastr.error(msg);
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Template');
            }
        });
    }

    /**
     * Update treatment plan config (e.g. on nurse patient switch) without re-binding events.
     * Fixes A5: keeps extraPayload.patient_id current when nurse changes patients.
     * @param {Object} overrides - keys to merge into existing _tpApplyConfig
     */
    function updateTreatmentPlanConfig(overrides) {
        if (_tpApplyConfig) {
            $.extend(true, _tpApplyConfig, overrides);
        }
    }

    /* ═══════════════════════════════════════════
       RE-PRESCRIBE FROM ENCOUNTER (Plan §5.3)
       ═══════════════════════════════════════════ */

    var _rpConfig = {};

    /**
     * Initialize re-prescribe from encounter feature.
     * @param {Object} config
     *   - recentUrl:       URL to GET recent encounters list
     *   - encounterItemsUrl: URL template with {id} placeholder, e.g. '/encounters/5/encounter-items/{id}'
     *   - rePrescribeUrl:  URL to POST re-prescribe
     *   - csrfToken:       CSRF token
     *   - extraPayload:    Extra payload fields (e.g. { patient_id: 123 } for nurse)
     *   - onRePrescribed:  Callback after re-prescribe success (receives { type, count })
     *   - dropdownSelector: CSS selector for dropdown container
     */
    function initRePrescribeFromEncounter(config) {
        _rpConfig = config;
        _rpLoadRecentEncounters();
    }

    /**
     * Update re-prescribe config and reload encounters (e.g. on nurse patient switch).
     * Fixes A5: keeps extraPayload.patient_id current and refreshes encounter list.
     * @param {Object} overrides - keys to merge into existing _rpConfig
     */
    function updateRePrescribeConfig(overrides) {
        if (_rpConfig) {
            $.extend(true, _rpConfig, overrides);
            _rpLoadRecentEncounters();
        }
    }

    /**
     * Load recent encounters into the dropdown.
     */
    function _rpLoadRecentEncounters() {
        var $dropdown = $(_rpConfig.dropdownSelector);
        if (!$dropdown.length) return;

        var $menu = $dropdown.find('.rp-encounter-menu');
        $menu.html('<li class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>');

        var url = _rpConfig.recentUrl;
        if (_rpConfig.extraPayload && _rpConfig.extraPayload.patient_id) {
            url += (url.indexOf('?') !== -1 ? '&' : '?') + 'patient_id=' + _rpConfig.extraPayload.patient_id;
        }

        $.get(url, function (response) {
            $menu.empty();
            if (!response.success || !response.encounters || response.encounters.length === 0) {
                $menu.html('<li class="dropdown-item text-muted">No recent encounters</li>');
                return;
            }

            response.encounters.forEach(function (enc) {
                var totalItems = (enc.lab_count || 0) + (enc.imaging_count || 0) + (enc.rx_count || 0) + (enc.proc_count || 0);
                var badges = [];
                if (enc.lab_count > 0) badges.push('<span class="badge bg-info me-1">' + enc.lab_count + ' Labs</span>');
                if (enc.imaging_count > 0) badges.push('<span class="badge bg-warning text-dark me-1">' + enc.imaging_count + ' Imaging</span>');
                if (enc.rx_count > 0) badges.push('<span class="badge bg-success me-1">' + enc.rx_count + ' Rx</span>');
                if (enc.proc_count > 0) badges.push('<span class="badge bg-secondary me-1">' + enc.proc_count + ' Proc</span>');

                if (totalItems === 0) return; // Skip empty encounters

                $menu.append(
                    '<li><a class="dropdown-item rp-encounter-item" href="javascript:;" data-encounter-id="' + enc.id + '">' +
                        '<div><strong>' + enc.date + '</strong> &mdash; ' + enc.doctor + '</div>' +
                        '<div class="mt-1">' + badges.join('') + '</div>' +
                    '</a></li>'
                );
            });

            if ($menu.children().length === 0) {
                $menu.html('<li class="dropdown-item text-muted">No encounters with items</li>');
            }

            // Bind click handlers
            $menu.find('.rp-encounter-item').off('click').on('click', function () {
                var encId = $(this).data('encounter-id');
                _rpPreviewEncounter(encId);
            });
        }).fail(function () {
            $menu.html('<li class="dropdown-item text-danger">Error loading encounters</li>');
        });
    }

    /**
     * Preview items from a selected encounter in a modal.
     */
    function _rpPreviewEncounter(encounterId) {
        var url = _rpConfig.encounterItemsUrl.replace('{id}', encounterId);
        var $modal = $('#rePrescribeEncounterModal');

        // Show modal with loading state
        $modal.find('.rp-preview-body').html('<div class="text-center py-3"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading items...</div>');
        try { new bootstrap.Modal($modal[0]).show(); } catch(e) { $modal.modal('show'); }

        $.get(url, function (response) {
            if (!response.success || !response.items) {
                $modal.find('.rp-preview-body').html('<div class="alert alert-warning">Could not load encounter items</div>');
                return;
            }

            var html = '';
            var items = response.items;

            // Labs
            if (items.labs && items.labs.length > 0) {
                html += '<h6 class="mt-2"><i class="fa fa-flask text-info"></i> Labs (' + items.labs.length + ')</h6>';
                html += '<div class="list-group list-group-flush mb-2">';
                items.labs.forEach(function (l) {
                    var alreadyAdded = isAlreadyAdded('labs', l.service_id);
                    html += '<label class="list-group-item d-flex align-items-center">' +
                        '<input type="checkbox" class="form-check-input me-2 rp-item-check" ' +
                            'data-type="labs" data-id="' + l.id + '" data-ref-id="' + l.service_id + '" ' +
                            (alreadyAdded ? 'disabled' : 'checked') + '> ' +
                        '<span>' + l.name + (l.note ? ' <small class="text-muted">(' + l.note + ')</small>' : '') + '</span>' +
                        (alreadyAdded ? ' <span class="badge bg-warning ms-auto">Already Added</span>' : '') +
                    '</label>';
                });
                html += '</div>';
            }

            // Imaging
            if (items.imaging && items.imaging.length > 0) {
                html += '<h6 class="mt-2"><i class="fa fa-x-ray text-warning"></i> Imaging (' + items.imaging.length + ')</h6>';
                html += '<div class="list-group list-group-flush mb-2">';
                items.imaging.forEach(function (i) {
                    var alreadyAdded = isAlreadyAdded('imaging', i.service_id);
                    html += '<label class="list-group-item d-flex align-items-center">' +
                        '<input type="checkbox" class="form-check-input me-2 rp-item-check" ' +
                            'data-type="imaging" data-id="' + i.id + '" data-ref-id="' + i.service_id + '" ' +
                            (alreadyAdded ? 'disabled' : 'checked') + '> ' +
                        '<span>' + i.name + (i.note ? ' <small class="text-muted">(' + i.note + ')</small>' : '') + '</span>' +
                        (alreadyAdded ? ' <span class="badge bg-warning ms-auto">Already Added</span>' : '') +
                    '</label>';
                });
                html += '</div>';
            }

            // Prescriptions
            if (items.prescriptions && items.prescriptions.length > 0) {
                html += '<h6 class="mt-2"><i class="fa fa-pills text-success"></i> Medications (' + items.prescriptions.length + ')</h6>';
                html += '<div class="list-group list-group-flush mb-2">';
                items.prescriptions.forEach(function (p) {
                    var alreadyAdded = isAlreadyAdded('meds', p.product_id);
                    html += '<label class="list-group-item d-flex align-items-center">' +
                        '<input type="checkbox" class="form-check-input me-2 rp-item-check" ' +
                            'data-type="prescriptions" data-id="' + p.id + '" data-ref-id="' + p.product_id + '" ' +
                            (alreadyAdded ? 'disabled' : 'checked') + '> ' +
                        '<span>' + p.name + (p.dose ? ' <small class="text-muted">(' + p.dose + ')</small>' : '') + '</span>' +
                        (alreadyAdded ? ' <span class="badge bg-warning ms-auto">Already Added</span>' : '') +
                    '</label>';
                });
                html += '</div>';
            }

            // Procedures
            if (items.procedures && items.procedures.length > 0) {
                html += '<h6 class="mt-2"><i class="fa fa-procedures text-danger"></i> Procedures (' + items.procedures.length + ')</h6>';
                html += '<div class="list-group list-group-flush mb-2">';
                items.procedures.forEach(function (proc) {
                    var alreadyAdded = isAlreadyAdded('procedures', proc.service_id);
                    html += '<label class="list-group-item d-flex align-items-center">' +
                        '<input type="checkbox" class="form-check-input me-2 rp-item-check" ' +
                            'data-type="procedures" data-id="' + proc.id + '" data-ref-id="' + proc.service_id + '" ' +
                            (alreadyAdded ? 'disabled' : 'checked') + '> ' +
                        '<span>' + proc.name +
                            (proc.priority ? ' <span class="badge bg-secondary ms-1">' + proc.priority + '</span>' : '') +
                            (proc.note ? ' <small class="text-muted">(' + proc.note + ')</small>' : '') +
                        '</span>' +
                        (alreadyAdded ? ' <span class="badge bg-warning ms-auto">Already Added</span>' : '') +
                    '</label>';
                });
                html += '</div>';
            }

            if (!html) {
                html = '<div class="alert alert-info">No items found in this encounter</div>';
            }

            $modal.find('.rp-preview-body').html(html);
            $modal.find('.rp-enc-id').val(encounterId);

        }).fail(function () {
            $modal.find('.rp-preview-body').html('<div class="alert alert-danger">Error loading items</div>');
        });
    }

    /**
     * Re-prescribe selected items from the preview modal.
     */
    function _rpApplySelected() {
        var $modal = $('#rePrescribeEncounterModal');
        var $checked = $modal.find('.rp-item-check:checked:not(:disabled)');

        if ($checked.length === 0) {
            if (typeof toastr !== 'undefined') toastr.warning('No items selected');
            return;
        }

        // Group checked items by type
        var byType = {};
        $checked.each(function () {
            var type = $(this).data('type');
            var id = $(this).data('id');
            if (!byType[type]) byType[type] = [];
            byType[type].push(id);
        });

        var $applyBtn = $modal.find('.rp-apply-btn');
        $applyBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Re-prescribing...');

        var promises = [];

        // For each type that has selected items, POST to re-prescribe
        Object.keys(byType).forEach(function (type) {
            var payload = $.extend({
                _token: _rpConfig.csrfToken,
                source_type: type,
                source_ids: byType[type]
            }, _rpConfig.extraPayload || {});

            promises.push(
                $.ajax({
                    url: _rpConfig.rePrescribeUrl,
                    method: 'POST',
                    data: payload
                })
            );
        });

        $.when.apply($, promises).done(function () {
            // Collect total counts
            var total = 0;
            var args = promises.length === 1 ? [arguments] : Array.prototype.slice.call(arguments);
            args.forEach(function (a) {
                var data = a[0] || a;
                if (data && data.count) total += data.count;
            });

            if (typeof toastr !== 'undefined') toastr.success(total + ' item(s) re-prescribed from previous encounter');
            try { bootstrap.Modal.getInstance($modal[0]).hide(); } catch(e) { $modal.modal('hide'); }
            $applyBtn.prop('disabled', false).html('<i class="fa fa-redo"></i> Re-prescribe Selected');

            if (_rpConfig.onRePrescribed) _rpConfig.onRePrescribed({ total: total });

        }).fail(function () {
            if (typeof toastr !== 'undefined') toastr.error('Error re-prescribing items');
            $applyBtn.prop('disabled', false).html('<i class="fa fa-redo"></i> Re-prescribe Selected');
        });
    }

    /**
     * Refresh the encounter dropdown (call after patient change in nurse view).
     */
    function refreshRecentEncounters() {
        if (_rpConfig.recentUrl) _rpLoadRecentEncounters();
    }

    /* ═══════════════════════════════════════════
       PUBLIC API
       ═══════════════════════════════════════════ */
    return {
        // Constants
        FREQ_MULTIPLIER_MAP: FREQ_MULTIPLIER_MAP,
        DUR_UNIT_MULTIPLIER_MAP: DUR_UNIT_MULTIPLIER_MAP,

        // Utilities
        debounce: debounce,
        showInlineMessage: showInlineMessage,
        renderCoverageBadge: renderCoverageBadge,

        // Structured dose
        buildStructuredDoseHtml: buildStructuredDoseHtml,
        updateDoseValue: updateDoseValue,
        autoCalculateQty: autoCalculateQty,
        collapseStructuredDose: collapseStructuredDose,
        onDoseUpdate: onDoseUpdate,

        // Dose mode toggle
        initDoseModeToggle: initDoseModeToggle,

        // Per-drug calculator
        parseStrengthFromName: parseStrengthFromName,
        buildInlineCalculatorHtml: buildInlineCalculatorHtml,
        toggleRowCalc: toggleRowCalc,
        closeCalc: closeCalc,
        liveCalc: liveCalc,
        applyCalcToRow: applyCalcToRow,

        // Auto-save
        addItem: addItem,
        removeItem: removeItem,
        debouncedUpdate: debouncedUpdate,
        updateStatusLine: updateStatusLine,

        // Duplicate filtering
        addedIds: addedIds,
        isAlreadyAdded: isAlreadyAdded,
        markAdded: markAdded,
        clearAddedIds: clearAddedIds,
        scanExistingRows: scanExistingRows,

        // Treatment plans (Plan §6.4)
        initTreatmentPlans: initTreatmentPlans,
        updateTreatmentPlanConfig: updateTreatmentPlanConfig,
        openSaveTemplateModal: openSaveTemplateModal,

        // Re-prescribe from encounter (Plan §5.3)
        initRePrescribeFromEncounter: initRePrescribeFromEncounter,
        updateRePrescribeConfig: updateRePrescribeConfig,
        refreshRecentEncounters: refreshRecentEncounters,
        _rpApplySelected: _rpApplySelected
    };

})(jQuery);
