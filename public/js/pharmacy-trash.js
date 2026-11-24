/* Pharmacy: Dismissed prescriptions. Browse (server-paged, searchable, this patient or everyone) and restore.
   Endpoints: GET /pharmacy-workbench/dismissed-prescriptions/{patient?}, POST /pharmacy-workbench/restore-dismissed. */
(function () {
    var state = { page: 1, per: 10, search: '', mine: false, timer: null };
    var csrf = function () { return (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.csrf) || $('meta[name="csrf-token"]').attr('content'); };
    var esc = function (v) { return $('<div>').text(v == null ? '' : v).html(); };
    var patient = function () { return (typeof currentPatient !== 'undefined' && currentPatient) ? currentPatient : null; };

    function url() {
        var base = '/pharmacy-workbench/dismissed-prescriptions/' + (state.mine && patient() ? patient() : '');
        return wbUrl(base) + '?per_page=' + state.per + '&page=' + state.page + '&search=' + encodeURIComponent(state.search);
    }

    function card(r) {
        var when = r.dismissed_at || '';
        var by = r.by_name ? esc(r.by_name) : '<em class="text-muted">Not recorded</em>';
        var reason = r.reason ? esc(r.reason) : '<em class="text-muted">No reason recorded</em>';
        return '<div class="ph-trash-card">' +
            '<div class="d-flex justify-content-between align-items-start gap-2">' +
            '<div class="ph-trash-title">' + esc(r.product_name) + ' <span class="text-muted fw-normal">&times; ' + esc(r.qty) + '</span></div>' +
            '<button class="btn btn-sm btn-success ph-trash-restore" data-id="' + Number(r.id) + '"><i class="mdi mdi-restore"></i> Restore</button>' +
            '</div><small>' +
            '<div><strong>Patient:</strong> ' + esc(r.patient_name) + (r.file_no ? ' (' + esc(r.file_no) + ')' : '') + '</div>' +
            '<div><strong>Prescribed by:</strong> ' + esc(r.requested_by) + '</div>' +
            '<div><strong>Dismissed:</strong> ' + esc(when) + ' &middot; ' + by + '</div>' +
            '<div><strong>Reason:</strong> ' + reason + '</div>' +
            '</small></div>';
    }

    function load() {
        $('#ph-trash-list').html('<div class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> Loading…</div>');
        $.getJSON(url()).done(function (res) {
            var rows = res.data || [];
            var pages = Math.max(1, Math.ceil((res.total || 0) / state.per));
            $('#ph-trash-count').text(res.total || 0).css('display', (res.total || 0) > 0 ? 'flex' : 'none');
            $('#ph-trash-list').html(rows.length ? rows.map(card).join('') : '<div class="text-center text-muted py-4">No dismissed prescriptions' + (state.search ? ' match your search' : '') + '.</div>');
            $('#ph-trash-page').text('Page ' + state.page + ' of ' + pages + ' · ' + (res.total || 0) + ' dismissed');
            $('#ph-trash-prev').prop('disabled', state.page <= 1);
            $('#ph-trash-next').prop('disabled', state.page >= pages);
        }).fail(function (xhr) {
            $('#ph-trash-list').html('<div class="text-danger py-3">' + esc((xhr.responseJSON && xhr.responseJSON.message) || 'Could not load the dismissed list.') + '</div>');
        });
    }

    function refreshCount() {
        $.getJSON(wbUrl('/pharmacy-workbench/dismissed-prescriptions/') + '?per_page=1&page=1').done(function (res) {
            $('#ph-trash-count').text(res.total || 0).css('display', (res.total || 0) > 0 ? 'flex' : 'none');
        });
    }

    $(document).on('click', '#ph-trash-open', function () { $('#ph-trash-panel').addClass('open'); state.page = 1; load(); });
    $(document).on('click', '#ph-trash-close', function () { $('#ph-trash-panel').removeClass('open'); });
    $(document).on('input', '#ph-trash-search', function () {
        var v = $(this).val();
        clearTimeout(state.timer);
        state.timer = setTimeout(function () { state.search = v.trim(); state.page = 1; load(); }, 350);
    });
    $(document).on('change', '#ph-trash-mine', function () { state.mine = $(this).is(':checked'); state.page = 1; load(); });
    $(document).on('click', '#ph-trash-prev', function () { if (state.page > 1) { state.page--; load(); } });
    $(document).on('click', '#ph-trash-next', function () { state.page++; load(); });

    $(document).on('click', '.ph-trash-restore', function () {
        var $b = $(this).prop('disabled', true);
        $.ajax({ url: wbUrl('/pharmacy-workbench/restore-dismissed'), method: 'POST', data: { _token: csrf(), prescription_ids: [$b.data('id')] } })
            .done(function (res) {
                toastr.success(res.message || 'Restored');
                load();
                if (patient() && typeof initializePrescriptionDataTables === 'function') {
                    initializePrescriptionDataTables(patient());
                    if (typeof loadPrescriptionItems === 'function' && typeof currentStatusFilter !== 'undefined') loadPrescriptionItems(currentStatusFilter);
                }
            })
            .fail(function (xhr) { $b.prop('disabled', false); toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Could not restore'); });
    });

    // keep the badge honest after a dismiss from the desk
    window.pharmRefreshTrashCount = refreshCount;
    $(function () { refreshCount(); });
})();
