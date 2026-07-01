{{--
    Shared Patient Search JS Module (Bridged to PatientSearchWidget)
    ================================
    Usage:  @include('admin.partials.patient_search_js', ['search_context' => 'lab'])
--}}

<script src="{{ asset('js/patient-search-widget.js') }}?v={{ time() }}"></script>
<script>
    // Bridge for legacy implementations
    window.onLegacyPatientSelected = function(patientData) {
        if (typeof loadPatient === 'function') {
            loadPatient(patientData.id);
        }
    };

    window.PatientSearch = {
        init: function() {
            // Widget auto-initializes via document.ready in patient-search-widget.js,
            // but we keep this method for legacy compatibility so no errors are thrown.
            console.log('Legacy PatientSearch.init() called, handled by widget.');
        },
        search: function(query) {
            // Programmatic search wrapper if any workbench uses it manually
            const $input = $('#patient-search-input');
            if ($input.length) {
                $input.val(query).trigger('input');
            }
        }
    };
</script>
