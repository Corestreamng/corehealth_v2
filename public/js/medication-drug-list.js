/**
 * Medication chart: the searchable list of a patient's medications (left), with the calendar of the
 * chosen one on the right. Shared by the nursing workbench and the encounter/patient nurse chart.
 *
 * The page scripts still load the prescriptions into the hidden #drug-select (one <option> per drug,
 * with the data the calendar code reads). This list only renders those options and, on a click, sets
 * the select and triggers its change event, so every existing handler keeps working.
 */
(function ($) {
    'use strict';

    var $list, $search, $count;

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function badge(text, cls) {
        return '<span class="badge ' + cls + '">' + esc(text) + '</span>';
    }

    /** Builds one row from an <option> (a prescription or a direct entry). */
    function rowFor($opt) {
        var val = $opt.val();
        var direct = $opt.data('direct-entry');
        var rx = $opt.data('rx');
        var icon = $opt.data('status-icon') || '';
        var name, code, meta = [], badges = '', locked = false;

        if (direct) {
            var own = direct.drug_source === 'patient_own';
            name = direct.product_name || direct.external_drug_name || 'Unknown';
            code = direct.product_code || '';
            badges = badge(own ? "Patient's own" : 'Ward stock', own ? 'bg-purple' : 'bg-info');
            meta.push('Given ' + (direct.times_administered || 0) + '×');
            if (direct.nurse_name) meta.push('by ' + direct.nurse_name);
        } else if (rx) {
            name = rx.product_name;
            code = rx.product_code || '';
            badges = $opt.data('status-badge') || '';
            meta.push('Qty ' + rx.qty_prescribed);
            if ($opt.data('admin-text')) meta.push($opt.data('admin-text'));
            if ($opt.data('doctor-text')) meta.push($opt.data('doctor-text'));
            locked = $opt.prop('disabled');
        } else {
            return null;
        }

        var done = rx && rx.remaining_doses === 0 && rx.is_dispensed;
        var $row = $(
            '<button type="button" class="med-drug-item' + (locked ? ' is-locked' : '') + '" data-val="' + esc(val) + '"' +
            ' data-search="' + esc((name + ' ' + code + ' ' + (direct ? 'direct ward stock patient own' : 'prescription')).toLowerCase()) + '">' +
            '<span class="med-drug-icon">' + icon + '</span>' +
            '<span class="med-drug-body">' +
            '<span class="med-drug-name">' + esc(name) + (code ? ' <small class="text-muted">(' + esc(code) + ')</small>' : '') + '</span>' +
            '<span class="med-drug-meta">' + badges + ' ' + esc(meta.join(' · ')) +
            (done ? ' <span class="text-success fw-bold">✓ Fully administered</span>' : '') + '</span>' +
            (locked ? '<span class="med-drug-lock"><i class="mdi mdi-lock"></i> ' + esc(rx.status_label) + ': cannot chart yet</span>' : '') +
            '</span></button>'
        );
        return $row;
    }

    function applyFilter() {
        var q = ($search.val() || '').toLowerCase().trim();
        var shown = 0;
        $list.find('.med-drug-item').each(function () {
            var hit = !q || String($(this).data('search')).indexOf(q) !== -1;
            $(this).toggle(hit);
            if (hit) shown++;
        });
        $list.find('.med-drug-empty').toggle(shown === 0);
        $count.text(shown);
        updateStepper();
    }

    function syncActive() {
        var v = $('#drug-select').val();
        $list.find('.med-drug-item').removeClass('is-selected').filter(function () {
            return v && String($(this).data('val')) === String(v);
        }).addClass('is-selected');
        $('#med-calendar-placeholder').toggle(!v);
        $('#med-controls-card').toggle(!!v);
        updateStepper();
    }

    /** The medications that can be charted, in the order the list shows them (respects the search). */
    function steps() {
        return $list.find('.med-drug-item:not(.is-locked)').filter(function () { return $(this).css('display') !== 'none'; });
    }

    function updateStepper() {
        var $s = steps();
        var idx = $s.index($list.find('.med-drug-item.is-selected'));
        $('#med-drug-pos').text(idx < 0 ? $s.length + ' drugs' : (idx + 1) + ' of ' + $s.length);
        $('#med-prev-drug').prop('disabled', idx <= 0);
        $('#med-next-drug').prop('disabled', idx < 0 || idx >= $s.length - 1);
    }

    function step(dir) {
        var $s = steps();
        var idx = $s.index($list.find('.med-drug-item.is-selected'));
        var $to = $s.eq(idx + dir);
        if (!$to.length) return;
        $('#drug-select').val($to.data('val')).trigger('change');
        // Scroll only the list (scrollIntoView would also move the page).
        var list = $list[0], el = $to[0], top = el.offsetTop, bottom = top + el.offsetHeight;
        if (top < list.scrollTop) list.scrollTop = Math.max(0, top - 6);
        else if (bottom > list.scrollTop + list.clientHeight) list.scrollTop = bottom - list.clientHeight + 6;
    }

    function render() {
        $list = $('#med-drug-list');
        $search = $('#med-drug-search');
        $count = $('#med-drug-count');
        if (!$list.length) return;

        $list.find('.med-drug-item, .med-drug-group, .med-drug-empty').remove();
        var groups = { rx: [], direct: [] };
        $('#drug-select option').each(function () {
            var $opt = $(this);
            if (!$opt.val() || $opt.data('is-separator')) return;
            var $row = rowFor($opt);
            if ($row) groups[$opt.data('direct-entry') ? 'direct' : 'rx'].push($row);
        });

        if (groups.rx.length) {
            $list.append('<div class="med-drug-group">Prescriptions</div>');
            groups.rx.forEach(function ($r) { $list.append($r); });
        }
        if (groups.direct.length) {
            $list.append('<div class="med-drug-group">Direct administrations</div>');
            groups.direct.forEach(function ($r) { $list.append($r); });
        }
        $list.append('<div class="med-drug-empty text-muted text-center py-4" style="display:none;">No medication matches your search.</div>');
        if (!groups.rx.length && !groups.direct.length) {
            $list.find('.med-drug-empty').text('No medications found for this patient.').show();
        }
        applyFilter();
        syncActive();
    }

    $(document).on('click', '.med-drug-item', function () {
        if ($(this).hasClass('is-locked')) {
            if (window.toastr) toastr.info('This drug can be charted once it is billed and dispensed.');
            return;
        }
        $('#drug-select').val($(this).data('val')).trigger('change');
    });
    $(document).on('click', '#med-prev-drug', function () { step(-1); });
    $(document).on('click', '#med-next-drug', function () { step(1); });
    $(document).on('input', '#med-drug-search', applyFilter);
    $(document).on('change', '#drug-select', syncActive);

    window.MedDrugList = { render: render };
})(jQuery);
