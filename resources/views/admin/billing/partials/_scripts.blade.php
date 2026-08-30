@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {
        billing_queueCounts: '{{ route("billing.queue-counts") }}',
        lab_filterDoctors: '{{ route("lab.filterDoctors") }}',
        lab_filterHmos: '{{ route("lab.filterHmos") }}',
        lab_filterServices: '{{ route("lab.filterServices") }}',
        lab_statistics: '{{ route("lab.statistics") }}',
        lab_reports: '{{ route("lab.reports") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/billing-workbench.js') }}?v={{ filemtime(public_path('js/billing-workbench.js')) }}"></script>

{{-- Payment Scripts --}}
@include("admin.partials.payment_scripts")

{{-- Investigation Result View Modal --}}
@include('admin.partials.invest_res_view_modal')
@include('admin.partials.invest_res_view_js')

{{-- Admission Module JS --}}
@include('admin.partials.admissions-module-js')

@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
@endsection
