{{--
    Shared Patient Search JS Module
    ================================
    Usage:  @include('admin.partials.patient_search_js', ['search_context' => 'lab'])

    Provides the global `PatientSearch` module on `window`.
    Requires:
      - #patient-search-input  (text input)
      - #patient-search-results (dropdown container)
      - A global `loadPatient(patientId)` function defined by each workbench
    Parameters:
      $search_context  – one of: reception, nursing, lab, imaging, billing, pharmacy
--}}

<script>
window.PatientSearch = (function () {
    'use strict';

    const SEARCH_URL  = @json(route('patient-search'));
    const CONTEXT     = @json($search_context ?? 'reception');
    const DEFAULT_AVA = @json(asset('assets/images/default-avatar.png'));

    let searchTimeout = 0;
    let lastInputTime = 0;
    let inputBuffer   = '';

    // ── helpers ─────────────────────────────────────────────────────
    function doSearch(query, autoSelect) {
        $.ajax({
            url: SEARCH_URL,
            method: 'GET',
            data: { q: query, context: CONTEXT },
            success: function (results) {
                if (autoSelect && results.length === 1) {
                    selectPatient(results[0]);
                    return;
                }
                renderResults(results);
            },
            error: function () { console.error('Patient search failed'); }
        });
    }

    function selectPatient(patient) {
        $('#patient-search-results').hide();
        $('#patient-search-input').val('');
        if (typeof loadPatient === 'function') {
            loadPatient(patient.id);
        }
    }

    function renderResults(results) {
        var $c = $('#patient-search-results');
        $c.empty();

        if (results.length === 0) {
            $c.html('<div class="search-result-item text-muted">No patients found</div>');
            $c.show();
            return;
        }

        results.forEach(function (patient, idx) {
            var photoUrl = patient.photo || DEFAULT_AVA;
            var badge    = '';
            if (patient.pending_count > 0) {
                badge = '<span class="pending-badge">' + patient.pending_count + '</span>';
            } else if (CONTEXT === 'reception' && patient.hmo && patient.hmo !== 'Private') {
                badge = '<span class="badge badge-info">' + patient.hmo + '</span>';
            }

            var html =
                '<div class="search-result-item' + (idx === 0 ? ' active' : '') + '" data-patient-id="' + patient.id + '">' +
                    '<img src="' + photoUrl + '" alt="' + patient.name + '" onerror="this.onerror=null;this.src=\'' + DEFAULT_AVA + '\';">' +
                    '<div class="search-result-info">' +
                        '<div class="search-result-name">' + patient.name + '</div>' +
                        '<div class="search-result-details">' +
                            (patient.file_no || '') + ' | ' + (patient.age || 'N/A') + 'y ' + (patient.gender || '') + ' | ' + (patient.phone || 'N/A') +
                        '</div>' +
                    '</div>' +
                    badge +
                '</div>';

            var $item = $(html);
            $item.on('click', function () { selectPatient(patient); });
            $c.append($item);
        });

        $c.show();
    }

    // ── public api ──────────────────────────────────────────────────
    function init() {
        var $input = $('#patient-search-input');

        // Debounced input – with barcode‑scanner detection
        $input.on('input', function () {
            clearTimeout(searchTimeout);
            var query       = $(this).val().trim();
            var currentTime = Date.now();

            // Barcode scanner sends characters < 50 ms apart
            if (currentTime - lastInputTime < 50 && inputBuffer.length > 0) {
                inputBuffer = query;
                searchTimeout = setTimeout(function () {
                    if (inputBuffer.length >= 3) doSearch(inputBuffer, true);
                    inputBuffer = '';
                }, 100);
            } else {
                inputBuffer = query;
                if (query.length < 2) {
                    $('#patient-search-results').hide();
                    lastInputTime = currentTime;
                    return;
                }
                searchTimeout = setTimeout(function () { doSearch(query, false); }, 300);
            }
            lastInputTime = currentTime;
        });

        // Enter key (barcode terminator)
        $input.on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                var q = $(this).val().trim();
                if (q.length >= 2) doSearch(q, true);
            }
        });

        // Close dropdown on outside click
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.search-container').length) {
                $('#patient-search-results').hide();
            }
        });
    }

    return { init: init, search: doSearch };
})();
</script>
