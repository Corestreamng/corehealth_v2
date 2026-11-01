/**
 * CoreHealth v2 — Ops Audit Workbench Standalone Module
 * Stepped Storyboard Workflow, In-Place Stamping & Non-Resetting Pagination
 */

(function($) {
    'use strict';

    // Global helper: reload active datatables with explicit pagination preservation option
    window.opsReloadActiveTables = function(resetPaging) {
        if (typeof resetPaging === 'undefined') {
            resetPaging = false; // Default: PRESERVE pagination
        }
        $('.ops-datatable:visible').each(function() {
            if ($.fn.DataTable && $.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().ajax.reload(null, resetPaging);
            }
        });
        window.opsUpdateVisiblePageCount();
        window.opsUpdateScopeBadge();
    };

    // Currency formatter
    window.opsFormatCurrency = function(amount) {
        if (!amount && amount !== 0) return '₦0.00';
        return '₦' + parseFloat(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // Daily Operations Story Context Resolver for KPI Cards
    function getKpiStoryContext(label, index) {
        var l = (label || '').toLowerCase();

        // Requisitions & Stock Flow
        if (l.includes('requested') || l.includes('req value') || l.includes('total requisitions')) {
            return { stage: '1. Demand', icon: 'mdi-clipboard-arrow-down', subtext: 'Gross requisitions initiated from units' };
        }
        if ((l.includes('approved') || l.includes('appr value')) && !l.includes('received')) {
            return { stage: '2. Authorization', icon: 'mdi-clipboard-check', subtext: 'Authorized by HOD / Admin for release' };
        }
        if (l.includes('fulfilled') || l.includes('ful value') || l.includes('issued')) {
            return { stage: '3. Execution', icon: 'mdi-package-variant-closed-check', subtext: 'Physical stock issued & consumed' };
        }
        if (l.includes('pending') || l.includes('partial')) {
            return { stage: '4. Variance', icon: 'mdi-clock-alert-outline', subtext: 'Unfulfilled balance awaiting restock' };
        }
        if (l.includes('rejected') || l.includes('rej value') || l.includes('declined')) {
            return { stage: '5. Declined', icon: 'mdi-close-octagon-outline', subtext: 'Requests declined by store / HOD' };
        }

        // Billing, Tenders & Revenue Flow
        if (l.includes('total collected') || l.includes('gross') || l.includes('total billed') || l.includes('total revenue')) {
            return { stage: '1. Inflow', icon: 'mdi-cash-register', subtext: 'Gross collections in period' };
        }
        if (l === 'cash' || l.includes('cash in till') || l.includes('cash collections')) {
            return { stage: '2. Till Cash', icon: 'mdi-cash', subtext: 'Physical currency in drawer' };
        }
        if (l === 'pos' || l.includes('pos terminal')) {
            return { stage: '3. Card POS', icon: 'mdi-credit-card-outline', subtext: 'Electronic POS settlements' };
        }
        if (l === 'transfer' || l.includes('bank transfer')) {
            return { stage: '4. Transfer', icon: 'mdi-bank-transfer', subtext: 'Direct bank credits verified' };
        }
        if (l.includes('hmo') || l.includes('scheme') || l.includes('nhis')) {
            return { stage: '5. Claims AR', icon: 'mdi-shield-account', subtext: 'Receivables billed to scheme' };
        }
        if (l.includes('refund')) {
            return { stage: '6. Outflow', icon: 'mdi-cash-refund', subtext: 'Patient refunds / payout' };
        }
        if (l.includes('discount') || l.includes('waiver')) {
            return { stage: '7. Concession', icon: 'mdi-tag-outline', subtext: 'Approved fee waivers applied' };
        }
        if (l.includes('outstanding') || l.includes('balance') || l.includes('deficit')) {
            return { stage: '8. Unsettled', icon: 'mdi-alert-circle-outline', subtext: 'Pending payment balance' };
        }

        // Clinical Intake & Execution
        if (l.includes('encounter') || l.includes('consultation') || l.includes('visit') || l.includes('registered') || l.includes('admissions') || l.includes('total po') || l.includes('total orders')) {
            return { stage: '1. Intake', icon: 'mdi-account-group', subtext: 'Clinical demand initiated' };
        }
        if (l.includes('completed') || l.includes('dispensed') || l.includes('received') || l.includes('delivered') || l.includes('performed')) {
            return { stage: '2. Execution', icon: 'mdi-check-decagram', subtext: 'Clinical service delivered' };
        }
        if (l.includes('ongoing') || l.includes('in progress') || l.includes('waiting') || l.includes('draft') || l.includes('submitted')) {
            return { stage: '3. Active Queue', icon: 'mdi-progress-clock', subtext: 'Patients / orders currently in service' };
        }
        if (l.includes('duration') || l.includes('avg time') || l.includes('tat')) {
            return { stage: '4. Efficiency', icon: 'mdi-timer-outline', subtext: 'Average service turnaround duration' };
        }
        if (l.includes('audited')) {
            return { stage: 'Compliance', icon: 'mdi-shield-check', subtext: 'Certified by internal audit' };
        }
        if (l.includes('quer') || l.includes('flag')) {
            return { stage: 'Exception', icon: 'mdi-alert-circle', subtext: 'Held for staff explanation' };
        }

        // Fallback chronological story progression based on index
        var stageNum = index + 1;
        return {
            stage: 'Stage ' + stageNum,
            icon: 'mdi-chart-timeline-variant',
            subtext: 'Daily operational telemetry'
        };
    }

    // KPI renderer
    window.renderOpsKpis = function(kpis, containerId) {
        if (!kpis || !kpis.length) return;
        var html = '';

        // Daily Operations Story Header Strip
        html += '<div class="col-12 mb-1">' +
            '<div class="ops-kpi-story-strip d-flex align-items-center justify-content-between flex-wrap px-2 py-1">' +
            '<div class="d-flex align-items-center gap-1.5 text-muted" style="font-size: 0.72rem;">' +
            '<i class="mdi mdi-book-open-page-variant-outline text-primary"></i> ' +
            '<span class="fw-bold text-dark text-uppercase tracking-wide">Daily Operations Telemetry & Accountability Story</span>' +
            '</div>' +
            '<div class="d-flex align-items-center gap-2 text-muted d-none d-sm-flex" style="font-size: 0.7rem;">' +
            '<span><i class="mdi mdi-circle text-primary" style="font-size: 0.45rem;"></i> Demand</span>' +
            '<i class="mdi mdi-chevron-right text-muted" style="font-size: 0.7rem;"></i>' +
            '<span><i class="mdi mdi-circle text-success" style="font-size: 0.45rem;"></i> Execution</span>' +
            '<i class="mdi mdi-chevron-right text-muted" style="font-size: 0.7rem;"></i>' +
            '<span><i class="mdi mdi-circle text-info" style="font-size: 0.45rem;"></i> Settlement</span>' +
            '<i class="mdi mdi-chevron-right text-muted" style="font-size: 0.7rem;"></i>' +
            '<span><i class="mdi mdi-circle text-warning" style="font-size: 0.45rem;"></i> Exceptions</span>' +
            '</div>' +
            '</div></div>';

        var colSize = kpis.length <= 4 ? 3 : (kpis.length <= 6 ? 2 : 'auto');
        kpis.forEach(function(k, idx) {
            var borderColor = k.color || '#0d6efd';
            var story = getKpiStoryContext(k.label, idx);
            var stageText = k.stage || story.stage;
            var subtext = k.subtext || k.context || story.subtext;
            var iconClass = k.icon || story.icon;

            html += '<div class="col-md-' + colSize + ' col-6 col-xl-' + colSize + '">' +
                '<div class="kpi-card" style="border-left: 3px solid ' + borderColor + ';">' +
                '<div class="kpi-story-tag">' +
                '<i class="mdi ' + iconClass + ' me-1" style="color: ' + borderColor + ';"></i> ' + stageText +
                '</div>' +
                '<div class="kpi-value" style="color: ' + borderColor + ';">' + k.value + '</div>' +
                '<div class="kpi-label">' + k.label + '</div>' +
                '<div class="kpi-context-subtext">' + subtext + '</div>' +
                '</div></div>';
        });
        $('#' + containerId).html(html);
        window.opsUpdateVisiblePageCount();
    };

    // Global DataTables configuration: auto-highlight queried rows
    if (window.jQuery && $.fn && $.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            createdRow: function(row, data, dataIndex) {
                var str = typeof data === 'object' ? JSON.stringify(data) : String(data);
                if (str.includes('Resolve Query') || str.includes('Active Query Flagged') || str.includes('btn-warning')) {
                    $(row).addClass('table-warning bg-warning bg-opacity-10');
                }
            },
            drawCallback: function() {
                window.opsUpdateVisiblePageCount();
            }
        });
    }

    // Step 1: Time Horizon Date Presets Handler
    window.opsSetDatePreset = function(preset) {
        var form = $('#ops_audit_filter_form');
        if (!form.length) return;

        var startInput = form.find('input[name="start_date"]');
        var endInput = form.find('input[name="end_date"]');
        var today = new Date();
        var formatDate = function(d) {
            var month = '' + (d.getMonth() + 1);
            var day = '' + d.getDate();
            var year = d.getFullYear();
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            return [year, month, day].join('-');
        };

        var startStr = '';
        var endStr = formatDate(today);

        if (preset === 'today') {
            startStr = formatDate(today);
        } else if (preset === 'yesterday') {
            var yest = new Date(today);
            yest.setDate(yest.getDate() - 1);
            startStr = formatDate(yest);
            endStr = formatDate(yest);
        } else if (preset === '7d') {
            var d7 = new Date(today);
            d7.setDate(d7.getDate() - 7);
            startStr = formatDate(d7);
        } else if (preset === '30d') {
            var d30 = new Date(today);
            d30.setDate(d30.getDate() - 30);
            startStr = formatDate(d30);
        } else if (preset === 'month') {
            var mStart = new Date(today.getFullYear(), today.getMonth(), 1);
            var mEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            startStr = formatDate(mStart);
            endStr = formatDate(mEnd);
        }

        if (startStr && endStr) {
            startInput.val(startStr);
            endInput.val(endStr);
            $('.ops-chip').removeClass('active');
            $('.ops-chip[data-preset="' + preset + '"]').addClass('active');

            try {
                sessionStorage.setItem('ops_audit_date_preset', preset);
                sessionStorage.setItem('ops_audit_start_date', startStr);
                sessionStorage.setItem('ops_audit_end_date', endStr);
            } catch (e) {}

            window.opsUpdateAuditContextBanner();

            // Reload active tables starting from page 1 on date horizon change
            window.opsReloadActiveTables(true);
        }
    };

    // Step 2: Duty Window (Shift & Custom Time Range & Care Stream)
    window.opsUpdateShiftUI = function(shift, start, end) {
        var form = $('#ops_audit_filter_form');
        var badgeText = $('#opsShiftRangeBadge');
        var timeDisplay = $('#opsShiftTimeDisplay');
        var customBtn = $('#btnShiftCustom');

        form.find('.shift-btn').removeClass('active btn-primary').addClass('btn-outline-secondary');

        if (shift === 'morning' || (start === '00:00' && end === '12:59')) {
            form.find('.shift-btn[data-shift="morning"]').addClass('active btn-primary').removeClass('btn-outline-secondary');
            if (badgeText.length) badgeText.text('AM Duty (till 1pm)');
            if (timeDisplay.length) timeDisplay.text('00:00 - 12:59');
            if (customBtn.length) customBtn.addClass('d-none');
            form.find('[name="shift"]').val('morning');
        } else if (shift === 'afternoon' || (start === '13:00' && end === '17:59')) {
            form.find('.shift-btn[data-shift="afternoon"]').addClass('active btn-primary').removeClass('btn-outline-secondary');
            if (badgeText.length) badgeText.text('PM Duty (1pm-6pm)');
            if (timeDisplay.length) timeDisplay.text('13:00 - 17:59');
            if (customBtn.length) customBtn.addClass('d-none');
            form.find('[name="shift"]').val('afternoon');
        } else if (shift === 'night' || (start === '18:00' && (end === '07:59' || end === '08:00'))) {
            form.find('.shift-btn[data-shift="night"]').addClass('active btn-primary').removeClass('btn-outline-secondary');
            if (badgeText.length) badgeText.text('Night (Overnight)');
            if (timeDisplay.length) timeDisplay.text('18:00 - 07:59');
            if (customBtn.length) customBtn.addClass('d-none');
            form.find('[name="shift"]').val('night');
        } else if (shift === 'all' || (!start && !end)) {
            form.find('.shift-btn[data-shift="all"]').addClass('active btn-primary').removeClass('btn-outline-secondary');
            if (badgeText.length) badgeText.text('Full Day (24h)');
            if (timeDisplay.length) timeDisplay.text('00:00 - 23:59');
            if (customBtn.length) customBtn.addClass('d-none');
            form.find('[name="shift"]').val('all');
        } else {
            // Truly custom time window entered by user
            if (customBtn.length) {
                customBtn.removeClass('d-none').addClass('active btn-primary').removeClass('btn-outline-secondary');
            }
            if (badgeText.length) badgeText.text('Custom Range');
            if (timeDisplay.length) timeDisplay.text((start || '00:00') + ' - ' + (end || '23:59'));
            form.find('[name="shift"]').val('custom');
        }

        window.opsUpdateAuditContextBanner();
    };

    // Live Audit Scope Narrative & Operational Context Explainer
    window.opsUpdateAuditContextBanner = function() {
        var form = $('#ops_audit_filter_form');
        if (!form.length) return;

        var startVal = form.find('input[name="start_date"]').val();
        var endVal = form.find('input[name="end_date"]').val();
        var shift = form.find('input[name="shift"]').val() || 'all';
        var shiftStart = form.find('input[name="shift_start"]').val();
        var shiftEnd = form.find('input[name="shift_end"]').val();
        var careStream = form.find('input[name="care_stream"]').val() || 'all';
        var activePreset = $('.ops-chip.active').data('preset');

        var isSingleDay = false;
        var dayCount = 1;
        var dateDesc = '';

        if (startVal && endVal) {
            var d1 = new Date(startVal + 'T00:00:00');
            var d2 = new Date(endVal + 'T00:00:00');
            var diffTime = d2.getTime() - d1.getTime();
            dayCount = Math.round(diffTime / (1000 * 60 * 60 * 24)) + 1;
            if (dayCount < 1) dayCount = 1;

            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var fmtD1 = d1.getDate() + ' ' + months[d1.getMonth()] + ' ' + d1.getFullYear();
            var fmtD2 = d2.getDate() + ' ' + months[d2.getMonth()] + ' ' + d2.getFullYear();

            if (startVal === endVal) {
                isSingleDay = true;
                if (activePreset === 'today') {
                    dateDesc = 'Today (' + fmtD1 + ')';
                } else if (activePreset === 'yesterday') {
                    dateDesc = 'Yesterday (' + fmtD1 + ')';
                } else {
                    dateDesc = fmtD1 + ' (Single Day)';
                }
            } else {
                if (activePreset === 'month') {
                    dateDesc = 'This Month (' + fmtD1 + ' – ' + fmtD2 + ', ' + dayCount + ' days)';
                } else if (activePreset === '7d') {
                    dateDesc = 'Past 7 Days (' + fmtD1 + ' – ' + fmtD2 + ', 7 days)';
                } else if (activePreset === '30d') {
                    dateDesc = 'Past 30 Days (' + fmtD1 + ' – ' + fmtD2 + ', 30 days)';
                } else {
                    dateDesc = fmtD1 + ' – ' + fmtD2 + ' (' + dayCount + ' days)';
                }
            }
        } else {
            dateDesc = 'All dates';
        }

        var shiftDesc = '';
        var explainer = '';

        if (shift === 'morning' || (shiftStart === '00:00' && shiftEnd === '12:59')) {
            shiftDesc = 'AM Duty (00:00 – 12:59)';
            if (isSingleDay) {
                explainer = 'Single shift window: Inspecting morning duty (till 1:00 PM) for cashier handover & morning clinical triage.';
            } else {
                explainer = 'Isolating morning duty shifts (00:00 – 12:59) across all ' + dayCount + ' days in the period.';
            }
        } else if (shift === 'afternoon' || (shiftStart === '13:00' && shiftEnd === '17:59')) {
            shiftDesc = 'PM Duty (13:00 – 17:59)';
            if (isSingleDay) {
                explainer = 'Single shift window: Inspecting afternoon duty (1:00 PM – 6:00 PM) for cashier till balancing & shift handover certification.';
            } else {
                explainer = 'Isolating afternoon shifts (1:00 PM – 5:59 PM) across all ' + dayCount + ' days in this period. Ideal for peak-hour cashier reconciliation & cross-shift pattern analysis.';
            }
        } else if (shift === 'night' || (shiftStart === '18:00' && (shiftEnd === '07:59' || shiftEnd === '08:00'))) {
            shiftDesc = 'Night Duty (18:00 – 07:59 overnight)';
            if (isSingleDay) {
                explainer = 'Single shift window: Inspecting overnight duty (6:00 PM onwards) for emergency care & night handover.';
            } else {
                explainer = 'Isolating night duty hours (18:00 – 07:59 cross-midnight) across all ' + dayCount + ' days in the period.';
            }
        } else if (shift === 'all' || (!shiftStart && !shiftEnd)) {
            shiftDesc = 'Full Day (24h)';
            if (isSingleDay) {
                explainer = 'Covering all operational hours (24h) for today.';
            } else {
                explainer = 'Covering all operational hours (24h) across each of the ' + dayCount + ' days in the period.';
            }
        } else {
            var s = shiftStart || '00:00';
            var e = shiftEnd || '23:59';
            shiftDesc = 'Custom Hours (' + s + ' – ' + e + ')';
            explainer = 'Targeting records created between ' + s + ' and ' + e + ' on each day in the ' + dayCount + '-day period.';
        }

        var streamDesc = '';
        if (careStream === 'general') streamDesc = ' · Stream: General (Outpatient)';
        else if (careStream === 'inpatient') streamDesc = ' · Stream: Inpatient (Admitted)';
        else if (careStream === 'nhis') streamDesc = ' · Stream: Insured (HMO / Schemes)';

        var narrativeHtml = '<strong>' + shiftDesc + '</strong> across <strong>' + dateDesc + '</strong>' + streamDesc;
        $('#opsScopeNarrative').html(narrativeHtml);
        $('#opsScopeExplainerText').text(explainer);

        // Update Step 1 & Step 2 inline context hints
        if (isSingleDay) {
            $('#opsHorizonContextText').text('Single day scope (1 day)');
            $('#opsShiftContextText').text('Single shift handover window');
        } else {
            $('#opsHorizonContextText').text('Multi-day span (' + dayCount + ' days)');
            $('#opsShiftContextText').text('Applies to every day in period');
        }
    };

    $(document).on('click', '.shift-btn', function() {
        var shift = $(this).data('shift');
        var form = $(this).closest('.ops-story-ribbon, .ops-filter-bar, form');
        if (!form.length) form = $('#ops_audit_filter_form');

        var startInput = form.find('[name="shift_start"]');
        var endInput = form.find('[name="shift_end"]');

        var startVal = '';
        var endVal = '';

        if (shift === 'morning') {
            startVal = '00:00';
            endVal = '12:59';
        } else if (shift === 'afternoon') {
            startVal = '13:00';
            endVal = '17:59';
        } else if (shift === 'night') {
            startVal = '18:00';
            endVal = '07:59';
        } else if (shift === 'all') {
            startVal = '';
            endVal = '';
        }

        if (shift !== 'custom') {
            startInput.val(startVal);
            endInput.val(endVal);
        }

        window.opsUpdateShiftUI(shift, startInput.val(), endInput.val());

        try {
            sessionStorage.setItem('ops_audit_shift', form.find('[name="shift"]').val());
            sessionStorage.setItem('ops_audit_shift_start', startInput.val());
            sessionStorage.setItem('ops_audit_shift_end', endInput.val());
        } catch (e) {}

        window.opsReloadActiveTables(true);
    });

    // Custom Time Inputs Change Listeners
    var timeInputDebounce;
    $(document).on('input', '.ops-time-input', function() {
        var form = $(this).closest('.ops-story-ribbon, .ops-filter-bar, form');
        if (!form.length) form = $('#ops_audit_filter_form');
        var start = form.find('[name="shift_start"]').val();
        var end = form.find('[name="shift_end"]').val();

        window.opsUpdateShiftUI('detect', start, end);
    });

    $(document).on('change', '.ops-time-input', function() {
        var form = $(this).closest('.ops-story-ribbon, .ops-filter-bar, form');
        if (!form.length) form = $('#ops_audit_filter_form');
        var start = form.find('[name="shift_start"]').val();
        var end = form.find('[name="shift_end"]').val();

        window.opsUpdateShiftUI('detect', start, end);

        try {
            sessionStorage.setItem('ops_audit_shift', form.find('[name="shift"]').val());
            sessionStorage.setItem('ops_audit_shift_start', start);
            sessionStorage.setItem('ops_audit_shift_end', end);
        } catch (e) {}

        clearTimeout(timeInputDebounce);
        timeInputDebounce = setTimeout(function() {
            window.opsReloadActiveTables(true);
        }, 300);
    });

    $(document).on('click', '.care-stream-btn', function() {
        var stream = $(this).data('stream');
        var form = $(this).closest('.ops-story-ribbon, .ops-filter-bar, form');
        if (!form.length) form = $('#ops_audit_filter_form');

        $(this).siblings('.care-stream-btn').removeClass('active btn-primary').addClass('btn-outline-secondary');
        $(this).addClass('active btn-primary').removeClass('btn-outline-secondary');

        form.find('[name="care_stream"]').val(stream);

        try {
            sessionStorage.setItem('ops_audit_care_stream', stream);
        } catch (e) {}

        window.opsReloadActiveTables(true);
    });

    // Step 3: Scoping Indicator & Reset
    window.opsUpdateScopeBadge = function() {
        var form = $('#ops_audit_filter_form');
        if (!form.length) return;

        var activeCount = 0;
        // Count non-date, non-shift form elements that have non-empty values
        form.find('select, input:not([name="start_date"]):not([name="end_date"]):not([name="shift"]):not([name="shift_start"]):not([name="shift_end"]):not([name="care_stream"]):not([name="_token"])').each(function() {
            var val = $(this).val();
            if (val && val !== 'all' && val !== '') {
                activeCount++;
            }
        });

        // Also check active tab-level filters
        var activeTabPane = $('.tab-pane.active');
        if (activeTabPane.length) {
            activeTabPane.find('.ops-tab-filter').each(function() {
                var val = $(this).val();
                if (val && val !== 'all' && val !== '') {
                    activeCount++;
                }
            });
        }

        var badge = $('#opsScopeCountBadge');
        if (badge.length) {
            if (activeCount > 0) {
                badge.removeClass('d-none').html('<i class="mdi mdi-filter-check me-1"></i> ' + activeCount + ' active filter' + (activeCount > 1 ? 's' : ''));
                $('#btnOpsClearScopes').removeClass('d-none');
            } else {
                badge.addClass('d-none');
                $('#btnOpsClearScopes').addClass('d-none');
            }
        }
    };

    window.opsClearScopes = function() {
        var form = $('#ops_audit_filter_form');
        if (form.length) {
            form.find('select').each(function() {
                $(this).val('').trigger('change.select2').trigger('change');
            });
        }
        var activeTabPane = $('.tab-pane.active');
        if (activeTabPane.length) {
            activeTabPane.find('.ops-tab-filter').each(function() {
                $(this).val('').trigger('change.select2').trigger('change');
            });
        }
        window.opsReloadActiveTables(true);
    };

    // Update visible page unstamped counter on the "Stamp Page" button
    window.opsUpdateVisiblePageCount = function() {
        var activeTable = $('.audit-content .tab-pane.active table.ops-datatable');
        if (activeTable.length === 0) activeTable = $('.audit-content table.ops-datatable:visible');

        var unstampedCount = 0;
        if (activeTable.length) {
            unstampedCount = activeTable.find('tbody .audit-tick-btn:visible').length;
        }

        var btn = $('#btnStampVisiblePage');
        if (btn.length) {
            var counterBadge = btn.find('.ops-page-count');
            if (counterBadge.length) {
                counterBadge.text(unstampedCount);
            }
            if (unstampedCount === 0) {
                btn.prop('disabled', true).addClass('opacity-50');
            } else {
                btn.prop('disabled', false).removeClass('opacity-50');
            }
        }
    };

    // Step 4: High-Efficiency Page-Level Stamping
    window.stampCurrentPage = function() {
        var activeTable = $('.audit-content .tab-pane.active table.ops-datatable');
        if (activeTable.length === 0) activeTable = $('.audit-content table.ops-datatable:visible');

        if (!activeTable.length) {
            toastr.warning('No active table found on the current view.');
            return;
        }

        var unstampedButtons = activeTable.find('tbody .audit-tick-btn:visible');
        if (unstampedButtons.length === 0) {
            toastr.info('All records on this visible page have already been audited.');
            return;
        }

        // Group items by model type
        var itemsByModel = {};
        var totalItems = 0;

        unstampedButtons.each(function() {
            var btn = $(this);
            var modelType = btn.data('model-type');
            var modelId = btn.data('model-id');

            // Fallback parsing from onclick attribute if data attributes are not yet present
            if (!modelType || !modelId) {
                var onclick = btn.attr('onclick') || '';
                var m = onclick.match(/markAudited\s*\(\s*(?:this\s*,\s*)?['"]([^'"]+)['"]\s*,\s*(\d+)/);
                if (m) {
                    modelType = m[1];
                    modelId = parseInt(m[2], 10);
                }
            }

            if (modelType && modelId) {
                if (!itemsByModel[modelType]) {
                    itemsByModel[modelType] = [];
                }
                itemsByModel[modelType].push(modelId);
                totalItems++;
            }
        });

        if (totalItems === 0) {
            toastr.warning('No stampable item IDs could be extracted from this page.');
            return;
        }

        var confirmMsg = 'You are about to mark all ' + totalItems + ' unstamped records visible on this page as audited.\n\nDo you want to proceed?';
        if (!window.confirm(confirmMsg)) {
            return;
        }

        var btn = $('#btnStampVisiblePage');
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Stamping ' + totalItems + ' items...');

        // Mutate buttons in-place immediately for instant tactile feedback
        unstampedButtons.each(function() {
            $(this).replaceWith('<button class="btn btn-sm btn-success px-2 py-1 text-nowrap"><i class="mdi mdi-check-decagram me-1"></i> Audited</button>');
        });

        var promises = [];
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        $.each(itemsByModel, function(modelType, ids) {
            promises.push(
                $.ajax({
                    url: '/audit-workbench/audit/mark/bulk-stamp',
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        model_type: modelType,
                        ids: ids,
                        zone_key: 'ops_audit'
                    }
                })
            );
        });

        $.when.apply($, promises).done(function() {
            toastr.success(totalItems + ' records on this page marked as audited successfully.');
            // Reload silently with page preservation (false = KEEP current page)
            window.opsReloadActiveTables(false);
        }).fail(function() {
            toastr.error('Some items could not be stamped. Refreshing table...');
            window.opsReloadActiveTables(false);
        }).always(function() {
            btn.prop('disabled', false).html(originalHtml);
        });
    };

    // Stamping Modal & Execution (Single & Bulk)
    window.markAudited = function(arg1, arg2, arg3) {
        var btnElement, modelType, modelId;
        if (typeof arg1 === 'object') {
            btnElement = arg1; modelType = arg2; modelId = arg3;
        } else {
            modelType = arg1; modelId = arg2; btnElement = arg3;
        }
        window._currentStampTargetBtn = btnElement;
        window.openUniversalStampModal('single', modelType, modelId, btnElement);
    };

    window.openUniversalStampModal = function(mode, modelType, modelId, btnElement) {
        document.getElementById('stamp_mode').value = mode;
        document.getElementById('stamp_model_type').value = modelType || '';
        document.getElementById('stamp_model_id').value = modelId || '';

        var titleEl = document.getElementById('stamp_modal_title');
        var textEl = document.getElementById('stamp_modal_text');
        var checkbox = document.getElementById('stamp_confirm_check');
        checkbox.checked = false;
        checkbox.disabled = false;
        document.getElementById('btnSubmitUniversalStamp').disabled = true;

        var $myModal = $('#universalStampModal');
        var timelineContainer = document.getElementById('stamp_timeline_container');

        if (mode === 'bulk') {
            timelineContainer.innerHTML = '<div class="text-center text-muted mt-5"><i class="mdi mdi-information-outline fs-1"></i><br>Timeline not available for bulk operations.</div>';
            titleEl.innerHTML = '<i class="mdi mdi-check-all"></i> Bulk Stamp View';
            var activeTab = document.querySelector('.audit-content .ops-tabs .nav-link.active') || document.querySelector('.audit-content .nav-tabs .nav-link.active');
            var tabName = activeTab ? activeTab.innerText.trim() : 'the current';

            textEl.innerHTML = 'Fetching preview... <i class="mdi mdi-loading mdi-spin"></i>';
            $myModal.modal('show');

            var filterData = $('#ops_audit_filter_form').serializeArray();
            filterData.push({name: 'action', value: 'bulk_stamp_preview'});
            filterData.push({name: '_token', value: $('meta[name="csrf-token"]').attr('content')});

            var activeTable = $('.audit-content .tab-pane.active table.ops-datatable');
            if (activeTable.length === 0) activeTable = $('.audit-content table.ops-datatable:visible');
            var targetUrl = activeTable.length > 0 ? activeTable.DataTable().ajax.url() : window.location.href;

            $.ajax({
                url: targetUrl,
                type: 'POST',
                data: $.param(filterData),
                success: function(res) {
                    if (res.success) {
                        var msg = 'You are about to stamp <strong>' + res.valid + '</strong> records in the <strong>' + tabName + '</strong> view.';
                        if (res.monetary) {
                            msg += '<div class="row g-2 mt-2">' +
                                '<div class="col-4"><div class="bg-light p-2 rounded text-center"><small class="text-muted d-block">Total Amount</small><strong>' + window.opsFormatCurrency(res.monetary.total_amount) + '</strong></div></div>' +
                                '<div class="col-4"><div class="bg-light p-2 rounded text-center"><small class="text-muted d-block">Payable</small><strong class="text-success">' + window.opsFormatCurrency(res.monetary.total_payable) + '</strong></div></div>' +
                                '<div class="col-4"><div class="bg-light p-2 rounded text-center"><small class="text-muted d-block">Claims</small><strong class="text-info">' + window.opsFormatCurrency(res.monetary.total_claims) + '</strong></div></div>' +
                                '</div>';
                            if (res.monetary.unique_patients) msg += '<div class="mt-2"><small class="text-muted"><i class="mdi mdi-account-group me-1"></i> ' + res.monetary.unique_patients + ' unique patients</small></div>';
                            if (res.monetary.staff_summary) msg += '<div><small class="text-muted"><i class="mdi mdi-account-tie me-1"></i> Staff: ' + res.monetary.staff_summary + '</small></div>';
                        }
                        if (res.queried > 0) {
                            msg += '<br><span class="text-danger"><i class="mdi mdi-alert"></i> ' + res.queried + ' records with active queries will be skipped.</span>';
                        } else {
                            msg += '<br><span class="text-success"><i class="mdi mdi-check-circle"></i> No active queries in this selection.</span>';
                        }
                        if (res.valid === 0) {
                            msg = '<span class="text-danger"><i class="mdi mdi-alert-circle"></i> No valid records to stamp.</span>';
                            checkbox.disabled = true;
                        }
                        textEl.innerHTML = msg;
                    } else {
                        textEl.innerHTML = '<span class="text-danger">Failed to load preview data.</span>';
                    }
                },
                error: function() {
                    textEl.innerHTML = '<span class="text-danger">Error fetching preview.</span>';
                }
            });
        } else {
            titleEl.innerHTML = '<i class="mdi mdi-check-decagram"></i> Confirm Stamp';
            textEl.innerHTML = 'You are about to mark this record as audited.';
            timelineContainer.innerHTML = '<div class="text-center text-muted mt-5"><i class="mdi mdi-loading mdi-spin fs-2"></i><br>Loading timeline...</div>';
            $myModal.modal('show');
            window.fetchAuditTimeline(modelType, modelId, 'stamp_timeline_container');
        }
    };

    window.toggleUniversalStampButton = function(checkbox) {
        document.getElementById('btnSubmitUniversalStamp').disabled = !checkbox.checked;
    };

    window.submitUniversalStamp = function() {
        var mode = document.getElementById('stamp_mode').value;
        var btn = document.getElementById('btnSubmitUniversalStamp');
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Processing...';

        if (mode === 'bulk') {
            var filterData = $('#ops_audit_filter_form').serializeArray();
            filterData.push({name: 'action', value: 'bulk_stamp'});
            filterData.push({name: '_token', value: $('meta[name="csrf-token"]').attr('content')});

            var activeTable = $('.audit-content .tab-pane.active table.ops-datatable');
            if (activeTable.length === 0) activeTable = $('.audit-content table.ops-datatable:visible');
            var targetUrl = activeTable.length > 0 ? activeTable.DataTable().ajax.url() : window.location.href;

            $.ajax({
                url: targetUrl,
                type: 'POST',
                data: $.param(filterData),
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        if (res.skipped_count > 0) toastr.warning(res.skipped_count + ' items were skipped due to active queries.');
                        // Silent reload with pagination preservation (resetPaging = false)
                        setTimeout(function() {
                            window.opsReloadActiveTables(false);
                        }, 300);
                        $('#universalStampModal').modal('hide');
                    } else {
                        toastr.error(res.message || 'Failed to bulk stamp.');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error during bulk stamp.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            });
        } else {
            var modelType = document.getElementById('stamp_model_type').value;
            var modelId = document.getElementById('stamp_model_id').value;

            $.ajax({
                url: '/audit-workbench/audit/mark/stamp',
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    model_type: modelType,
                    model_id: modelId,
                    zone_key: 'ops_audit'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        // In-place UI mutation if target button was saved
                        if (window._currentStampTargetBtn) {
                            $(window._currentStampTargetBtn).replaceWith('<button class="btn btn-sm btn-success px-2 py-1 text-nowrap"><i class="mdi mdi-check-decagram me-1"></i> Audited</button>');
                            window._currentStampTargetBtn = null;
                        }
                        // Non-resetting reload (preserves current page and scroll position)
                        setTimeout(function() {
                            window.opsReloadActiveTables(false);
                        }, 300);
                        $('#universalStampModal').modal('hide');
                    } else {
                        toastr.error(response.message);
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            });
        }
    };

    // Query Management: Raise & Resolve
    window.openRaiseQueryModal = function(modelType, modelId) {
        document.getElementById('query_model_type').value = modelType;
        document.getElementById('query_model_id').value = modelId;
        document.getElementById('query_notes').value = '';
        document.getElementById('raise_timeline_container').innerHTML = '<div class="text-center text-muted mt-5"><i class="mdi mdi-loading mdi-spin fs-2"></i><br>Loading timeline...</div>';
        $('#raiseQueryModal').modal('show');
        window.fetchAuditTimeline(modelType, modelId, 'raise_timeline_container');
    };

    window.submitRaiseQuery = function() {
        var btn = document.getElementById('btnSubmitQuery');
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Submitting...';
        var data = $('#formRaiseQuery').serializeArray();
        data.push({name: 'zone_key', value: 'ops_audit'});

        $.ajax({
            url: '/audit-workbench/audit/mark/raise-query',
            type: 'POST',
            data: $.param(data),
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    setTimeout(function() {
                        window.opsReloadActiveTables(false);
                    }, 300);
                    $('#raiseQueryModal').modal('hide');
                } else {
                    toastr.error(res.message);
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    };

    window.openResolveQueryModal = function(modelType, modelId) {
        document.getElementById('resolve_model_type').value = modelType;
        document.getElementById('resolve_model_id').value = modelId;
        document.getElementById('resolve_notes').value = '';
        document.getElementById('resolve_timeline_container').innerHTML = '<div class="text-center text-muted mt-5"><i class="mdi mdi-loading mdi-spin fs-2"></i><br>Loading timeline...</div>';
        $('#resolveQueryModal').modal('show');
        window.fetchAuditTimeline(modelType, modelId, 'resolve_timeline_container');
    };

    window.submitResolveQuery = function() {
        var btn = document.getElementById('btnSubmitResolve');
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Resolving...';

        $.ajax({
            url: '/audit-workbench/audit/mark/resolve-query',
            type: 'POST',
            data: $('#formResolveQuery').serialize(),
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    setTimeout(function() {
                        window.opsReloadActiveTables(false);
                    }, 300);
                    $('#resolveQueryModal').modal('hide');
                } else {
                    toastr.error(res.message);
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    };

    // Generic Details Modal
    window.openOpsAuditDetail = function(type, id) {
        $('#opsAuditGenericModalTitle').html('<i class="mdi mdi-information-outline me-2 text-primary"></i> Details Loading...');
        $('#opsAuditGenericModalBody').html('<div class="d-flex justify-content-center align-items-center h-100 p-5"><i class="mdi mdi-loading mdi-spin text-primary" style="font-size: 3rem;"></i></div>');
        $('#opsAuditGenericModal').modal('show');

        $.ajax({
            url: '/ops-audit/details/' + type + '/' + id,
            type: 'GET',
            success: function(res) {
                if (res.html) {
                    $('#opsAuditGenericModalBody').html(res.html);
                    if (res.title) {
                        $('#opsAuditGenericModalTitle').html(res.title);
                    } else {
                        $('#opsAuditGenericModalTitle').html('<i class="mdi mdi-text-box-search-outline me-2 text-primary"></i> Record Details');
                    }
                } else {
                    $('#opsAuditGenericModalBody').html('<div class="alert alert-danger m-3">Unexpected response format.</div>');
                }
            },
            error: function(err) {
                console.error(err);
                $('#opsAuditGenericModalBody').html('<div class="alert alert-danger m-3">Error loading details.</div>');
            }
        });
    };

    // Timelines & Query View Modals
    window.viewTimeline = function(modelType, modelId) {
        var container = document.getElementById('view_timeline_container');
        container.innerHTML = '<div class="text-center text-muted mt-5"><i class="mdi mdi-loading mdi-spin fs-2"></i><br>Loading timeline...</div>';
        $('#viewTimelineModal').modal('show');
        window.fetchAuditTimeline(modelType, modelId, 'view_timeline_container');
    };

    window.fetchAuditTimeline = function(modelType, modelId, containerId) {
        $.ajax({
            url: '/audit-workbench/audit/mark/timeline',
            type: 'GET',
            data: { model_type: modelType, model_id: modelId },
            success: function(res) {
                var container = document.getElementById(containerId);
                if (res.success && res.timeline.length > 0) {
                    var html = '<h6 class="text-dark font-weight-bold mb-4 border-bottom pb-2"><i class="mdi mdi-history text-secondary me-1"></i> Audit Ledger History</h6><div class="timeline-wrapper" style="position:relative; padding-left:24px; border-left:3px solid #cbd5e1;">';
                    res.timeline.forEach(function(item) {
                        var icon = 'mdi-check-decagram text-white';
                        var iconBg = 'bg-success';
                        var bg = 'bg-white';
                        var border = 'border-success';
                        var title = 'Stamped as Audited';
                        if (item.status === 'queried') {
                            icon = 'mdi-alert-circle text-white'; iconBg = 'bg-danger'; border = 'border-danger'; title = 'Queried / Flagged';
                        } else if (item.status === 'resolved') {
                            icon = 'mdi-check-circle text-white'; iconBg = 'bg-info'; border = 'border-info'; title = 'Query Resolved';
                        }

                        html += '<div class="timeline-item position-relative mb-4">' +
                            '<div class="timeline-icon position-absolute d-flex align-items-center justify-content-center rounded-circle shadow-sm ' + iconBg + '" style="width: 36px; height: 36px; left: -43px; top: 0; z-index: 10;">' +
                            '<i class="mdi ' + icon + ' fs-5"></i>' +
                            '</div>' +
                            '<div class="timeline-content rounded-3 p-3 shadow-sm border-start border-4 ' + border + ' ' + bg + '">' +
                            '<div class="d-flex justify-content-between align-items-start mb-2">' +
                            '<strong class="text-dark font-weight-bold" style="font-size:0.95rem;">' + title + '</strong>' +
                            '<small class="text-muted font-weight-bold" title="' + item.created_at + '">' + item.created_at_human + '</small>' +
                            '</div>' +
                            '<div class="small text-muted mb-2"><i class="mdi mdi-account-circle text-secondary fs-6 me-1"></i> <strong>' + item.auditor_name + '</strong></div>' +
                            (item.notes ? '<div class="p-2 mt-2 rounded bg-light border text-dark font-weight-bold" style="font-size:0.85rem; white-space:pre-wrap;">' + item.notes + '</div>' : '') +
                            '</div></div>';
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-center text-muted mt-5 py-5 bg-white rounded-3 border border-dashed"><i class="mdi mdi-text-box-search-outline fs-1 text-secondary"></i><p class="mt-3 mb-0 font-weight-bold">No Audit History</p><small>This record has a clean ledger.</small></div>';
                }
            },
            error: function() {
                document.getElementById(containerId).innerHTML = '<div class="text-center text-danger mt-5"><i class="mdi mdi-alert fs-2"></i><br>Failed to load timeline.</div>';
            }
        });
    };

    window.viewQueryDetails = function(queryId) {
        var $modal = $('#viewQueryModal');
        $('#viewQueryModalTitle').html('<i class="mdi mdi-spin mdi-loading me-2 text-info"></i> Loading Query Details...');
        $('#viewQueryModalBody').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
        $modal.modal('show');

        var url = '/ops-audit/queries/details/' + queryId;

        $.get(url, function(res) {
            var statusBadge = res.status === 'resolved'
                ? '<span class="badge bg-success px-3 py-1.5 font-weight-bold fs-14"><i class="mdi mdi-check-decagram me-1"></i> Resolved</span>'
                : '<span class="badge bg-warning text-dark px-3 py-1.5 font-weight-bold fs-14"><i class="mdi mdi-alert-circle me-1"></i> Active Query</span>';

            $('#viewQueryModalTitle').html('<i class="mdi mdi-shield-search text-info me-2"></i> Query Details & Ledger #' + res.id);

            var targetHtml = '';
            if (res.target_details && (res.target_details.patient_name || res.target_details.item_name)) {
                var t = res.target_details;
                targetHtml += '<div class="card-modern border-0 shadow-sm mb-4 rounded-3 overflow-hidden" style="background: #ffffff; border-left: 4px solid #3b82f6 !important;">' +
                    '<div class="card-modern-body p-3">' +
                    '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<span class="badge bg-primary px-2.5 py-1 font-weight-bold"><i class="mdi mdi-cube-outline me-1"></i> ' + res.model_type + ' #' + res.model_id + '</span>' +
                    '<span class="text-muted small"><i class="mdi mdi-folder me-1"></i> Zone: <strong>' + res.zone_key + '</strong></span>' +
                    '</div>' +
                    '<div class="row g-2 align-items-center">' +
                    (t.patient_name ? '<div class="col-md-6"><small class="text-muted d-block text-uppercase font-weight-bold" style="font-size:0.7rem;">Patient Details</small><div class="font-weight-bold text-dark"><i class="mdi mdi-account text-primary me-1"></i> ' + t.patient_name + ' <span class="badge bg-light text-dark border">#' + (t.file_no || 'N/A') + '</span></div>' + (t.hmo_name ? '<small class="text-info font-weight-bold"><i class="mdi mdi-hospital-building me-1"></i> ' + t.hmo_name + (t.hmo_scheme ? ' (' + t.hmo_scheme + ')' : '') + '</small>' : '') + '</div>' : '') +
                    (t.item_name ? '<div class="col-md-6"><small class="text-muted d-block text-uppercase font-weight-bold" style="font-size:0.7rem;">Item / Service Requested</small><div class="font-weight-bold text-dark"><i class="mdi mdi-pill text-success me-1"></i> ' + t.item_name + '</div>' + (t.amount ? '<small class="text-danger font-weight-bold me-2">Amount: ' + t.amount + '</small>' : '') + (t.qty ? '<small class="text-muted font-weight-bold">Qty: ' + t.qty + '</small>' : '') + '</div>' : '') +
                    '</div></div></div>';
            }

            var bodyHtml = '<div class="d-flex justify-content-between align-items-center mb-3">' +
                '<div><h5 class="mb-0 font-weight-bold text-dark">' + res.model_type + ' Query Audit Log</h5>' +
                '<small class="text-muted">Query ID #' + res.id + ' &bull; Flagged in ' + res.zone_key + '</small></div>' +
                '<div>' + statusBadge + '</div></div>' +
                targetHtml +
                '<div class="card-modern border-0 shadow-sm mb-3 rounded-3" style="background-color: #fef2f2; border-left: 4px solid #ef4444 !important;">' +
                '<div class="card-modern-body p-3">' +
                '<h6 class="font-weight-bold text-danger mb-2 d-flex align-items-center"><i class="mdi mdi-alert-circle me-1" style="font-size:1.1rem;"></i> Query Reason & Flag Notes</h6>' +
                '<p class="mb-2 text-dark font-weight-bold" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.5;">' + (res.query_notes || 'No query notes provided.') + '</p>' +
                '<small class="text-muted d-block mt-2 border-top pt-2 border-danger border-opacity-10"><i class="mdi mdi-account me-1"></i> Flagged by <strong>' + res.auditor + '</strong> on ' + res.created_at + '</small>' +
                '</div></div>';

            if (res.status === 'resolved') {
                bodyHtml += '<div class="card-modern border-0 shadow-sm mb-2 rounded-3" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important;">' +
                    '<div class="card-modern-body p-3">' +
                    '<h6 class="font-weight-bold text-success mb-2 d-flex align-items-center"><i class="mdi mdi-check-decagram me-1" style="font-size:1.1rem;"></i> Resolution Details & Outcome</h6>' +
                    '<p class="mb-2 text-dark font-weight-bold" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.5;">' + (res.resolution_notes || 'No resolution details recorded.') + '</p>' +
                    '<small class="text-muted d-block mt-2 border-top pt-2 border-success border-opacity-10"><i class="mdi mdi-account-check me-1"></i> Resolved by <strong>' + (res.resolver || 'System User') + '</strong> on ' + (res.resolved_at || 'N/A') + '</small>' +
                    '</div></div>';
                $('#viewQueryModalResolveBtn').hide();
            } else {
                $('#viewQueryModalResolveBtn').show().off('click').on('click', function() {
                    $modal.modal('hide');
                    window.openResolveQueryModal(res.full_model_type, res.model_id);
                });
            }

            $('#viewQueryModalBody').html(bodyHtml);
        }).fail(function() {
            $('#viewQueryModalBody').html('<div class="alert alert-danger mb-0 font-weight-bold"><i class="mdi mdi-alert-circle me-1"></i> Failed to load query details. Please try again.</div>');
        });
    };

    // Executive Duty Shift Service Handover Printout
    window.openShiftServiceSummaryPrint = function() {
        var form = $('#ops_audit_filter_form');
        var startDate = form.find('[name="start_date"]').val() || '';
        var endDate = form.find('[name="end_date"]').val() || '';
        var shift = form.find('.shift-btn.active').data('shift') || form.find('[name="shift"]').val() || 'all';
        var careStream = form.find('[name="care_stream"]').val() || 'all';
        var cashierId = form.find('[name="cashier_id"]').val() || '';
        var departmentId = form.find('[name="department_id"]').val() || '';

        var params = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            shift: shift,
            care_stream: careStream,
            cashier_id: cashierId,
            department_id: departmentId
        });

        var url = '/ops-audit/shift-service-summary?' + params.toString();
        window.open(url, '_blank', 'width=1150,height=900,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes');
    };

    // Document Ready Initialization
    $(document).ready(function() {
        // Select2 setups
        if ($.fn.select2) {
            $('.ajax-entity-search').select2({
                placeholder: 'Search Entity/Patient...',
                allowClear: true,
                ajax: {
                    url: '/ops-audit/search-entities',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term };
                    },
                    processResults: function(data) {
                        return { results: data.results };
                    },
                    cache: true
                }
            });

            $('.ops-hmo-select2').select2({
                placeholder: "All HMOs",
                allowClear: true
            });

            $('select.ops-tab-filter:not(.ajax-entity-search):not(.ajax-product-search):not(.ajax-service-search):not(.ops-hmo-select2)').select2({
                minimumResultsForSearch: 10,
                width: '100%'
            });
        }

        // Apply filters button click handler
        $(document).on('click', '#btnApplyFilters', function() {
            window.opsReloadActiveTables(true);
        });

        // Quick date preset chip click
        $(document).on('click', '.ops-chip', function() {
            var preset = $(this).data('preset');
            if (preset) {
                window.opsSetDatePreset(preset);
            }
        });

        // Manual date change sets chip to inactive
        $(document).on('change', '#ops_audit_filter_form input[name="start_date"], #ops_audit_filter_form input[name="end_date"]', function() {
            $('.ops-chip').removeClass('active');
            window.opsUpdateScopeBadge();
        });

        // Scope change listener
        $(document).on('change', '#ops_audit_filter_form select, .ops-tab-filter', function() {
            window.opsUpdateScopeBadge();
        });

        // Tab change listener: update visible counter and active tab filters
        $(document).on('shown.bs.tab', '.ops-tabs a[data-bs-toggle="tab"]', function() {
            window.opsUpdateVisiblePageCount();
            window.opsUpdateScopeBadge();
        });

        // Initial scope badge and shift/time calculation
        setTimeout(function() {
            var initStart = $('#ops_shift_start').val();
            var initEnd = $('#ops_shift_end').val();
            var initShift = $('#ops_shift_input').val();
            if (initStart || initEnd) {
                window.opsUpdateShiftUI('detect', initStart, initEnd);
            } else if (initShift && initShift !== 'all') {
                window.opsUpdateShiftUI(initShift, initStart, initEnd);
            }

            window.opsUpdateScopeBadge();
            window.opsUpdateVisiblePageCount();
            window.opsUpdateAuditContextBanner();
        }, 300);
    });

})(window.jQuery);
