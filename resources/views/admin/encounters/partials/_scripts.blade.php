{{-- Scripts Partial — Encounter Intelligence Workbench --}}
{{-- Injects window.ENCOUNTER_WORKBENCH_CONFIG then loads the standalone JS module --}}

<script>
    window.ENCOUNTER_WORKBENCH_CONFIG = @json($config);
</script>

<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/encounter-workbench.js') }}?v={{ filemtime(public_path('js/encounter-workbench.js')) }}"></script>
