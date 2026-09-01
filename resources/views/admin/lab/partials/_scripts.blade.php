@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {
        'lab.workbench': '{{ route("lab.workbench") }}',
        'lab.queue': '{{ route("lab.queue") }}',
        'lab.queue-counts': '{{ route("lab.queue-counts") }}',
        'lab.recordBilling': '{{ route("lab.recordBilling") }}',
        'lab.applyCombo': '{{ route("lab.applyCombo") }}',
        'lab.removeBundle': '{{ route("lab.removeBundle") }}',
        'lab.claimSelfPerform': '{{ route("lab.claimSelfPerform") }}',
        'lab.collectSample': '{{ route("lab.collectSample") }}',
        'lab.dismissRequests': '{{ route("lab.dismissRequests") }}',
        'lab.saveResult': '{{ route("lab.saveResult") }}',
        'lab.storeRequest': '{{ route("lab.storeRequest") }}',
        'lab.checkLabNumber': '{{ route("lab.checkLabNumber") }}',
        'lab.nextLabNumber': '{{ route("lab.nextLabNumber") }}',
        'lab.filterDoctors': '{{ route("lab.filterDoctors") }}',
        'lab.filterHmos': '{{ route("lab.filterHmos") }}',
        'lab.filterServices': '{{ route("lab.filterServices") }}',
        'lab.statistics': '{{ route("lab.statistics") }}',
        'lab.reports': '{{ route("lab.reports") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'lab'])
@include('admin.partials.invest_res_js', ['resultContext' => 'lab'])
@include('admin.partials.bulk_result_entry_js', ['resultContext' => 'lab'])
@include('admin.partials.perform_investigation_modal')
@include('admin.partials.combo_confirm_modal')
<script src="{{ asset('js/lab-core.js') }}?v={{ filemtime(public_path('js/lab-core.js')) }}"></script>
<script src="{{ asset('js/lab-results.js') }}?v={{ filemtime(public_path('js/lab-results.js')) }}"></script>
<script src="{{ asset('js/lab-reports.js') }}?v={{ filemtime(public_path('js/lab-reports.js')) }}"></script>
@include('admin.partials.clinical_alerts_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}?v={{ filemtime(public_path('js/clinical-orders-shared.js')) }}"></script>
@endsection
