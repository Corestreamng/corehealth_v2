@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/select2/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/js/jsbarcode.all.min.js') }}"></script>
<script src="{{ asset('plugins/daterangepicker/moment.js') }}"></script>
<script src="{{ asset('plugins/fullcalendar/fullcalendar.min.js') }}"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    hmos: @json(\App\Models\Hmo::with('scheme')->orderBy('name')->get()->map(fn($h) => ['id' => $h->id, 'name' => $h->name, 'scheme_name' => $h->scheme->name ?? 'Other'])),
    routes: {
        'reception.patient.quick-register': '{{ route("reception.patient.quick-register") }}',
        'reception.clinics': '{{ route("reception.clinics") }}',
        'reception.hmos': '{{ route("reception.hmos") }}',
        'reception.services.consultation': '{{ route("reception.services.consultation") }}',
        'reception.services.lab': '{{ route("reception.services.lab") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'reception'])
<script src="{{ asset('js/request-details.js') }}"></script>
<script src="{{ asset('js/reception-workbench.js') }}?v={{ filemtime(public_path('js/reception-workbench.js')) }}"></script>

{{-- Admission Module JS --}}
@include('admin.partials.admissions-module-js')

{{-- Clinical Reports JS --}}
@include('admin.partials.clinical-reports-scripts')

@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>

@hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS|BILLER')
    @include('admin.partials.payment_modal')
    @include('admin.partials.payment_scripts')
@endhasanyrole
@endsection
