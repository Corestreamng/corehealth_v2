<script>
window.WORKBENCH_CONFIG = {
    csrf_token: "{{ csrf_token() }}",
    routes: {
        workbench: "{{ route('nhmis.workbench') }}", compile: "{{ route('nhmis.compile') }}",
        saveValues: "{{ route('nhmis.save-values') }}", updateStatus: "{{ route('nhmis.update-status') }}",
        auditDiagnosis: "{{ route('nhmis.audit-diagnosis') }}", serviceMappings: "{{ route('nhmis.service-mappings') }}",
        saveServiceMappings: "{{ route('nhmis.save-service-mappings') }}", autoDetectMappings: "{{ route('nhmis.auto-detect-service-mappings') }}",
        drillDown: "{{ route('nhmis.drill-down') }}", backfillClassifications: "{{ route('nhmis.backfill-classifications') }}"
    },
    report: { id: {{ $report->id }}, year: {{ $year }}, month: {{ $month }}, version: "{{ $version }}", status: "{{ $report->status }}", startDate: "{{ $report->start_date ? \Carbon\Carbon::parse($report->start_date)->format('Y-m-d') : '' }}", endDate: "{{ $report->end_date ? \Carbon\Carbon::parse($report->end_date)->format('Y-m-d') : '' }}" }
};
</script>
<script src="{{ versioned_asset('js/nhmis-workbench.js') }}"></script>
