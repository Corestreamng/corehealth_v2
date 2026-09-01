@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {
        'billing.queue-counts': '{{ route("billing.queue-counts") }}',
        'lab.filterDoctors': '{{ route("lab.filterDoctors") }}',
        'lab.filterHmos': '{{ route("lab.filterHmos") }}',
        'lab.filterServices': '{{ route("lab.filterServices") }}',
        'lab.statistics': '{{ route("lab.statistics") }}',
        'lab.reports': '{{ route("lab.reports") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'billing'])<script src="{{ asset('js/billing-core.js') }}?v={{ filemtime(public_path('js/billing-core.js')) }}"></script>
<script src="{{ asset('js/billing-payments.js') }}?v={{ filemtime(public_path('js/billing-payments.js')) }}"></script>
<script src="{{ asset('js/billing-hmo.js') }}?v={{ filemtime(public_path('js/billing-hmo.js')) }}"></script>
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
