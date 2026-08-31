@section('scripts')
<script src="{{ asset('assets/js/chosen.jquery.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        procedureId: '{{ $procedure->id }}',
        patientId: '{{ $procedure->patient_id ?? "" }}',
        scheduledDate: '{{ $procedure->scheduled_date ?? "" }}',
        scheduledTime: '{{ $procedure->scheduled_time ?? "" }}',
        addServiceRoute: '{{ route("patient-procedures.items.service", $procedure->id) }}',
        addLabRoute: '{{ route("patient-procedures.items.lab", $procedure->id) }}',
        addImagingRoute: '{{ route("patient-procedures.items.imaging", $procedure->id) }}',
        addConsumableRoute: '{{ route("patient-procedures.items.medication", $procedure->id) }}',
        removeBillBase: '/patient-procedures/{{ $procedure->id }}/items',
        pendingBillsBase: '/patient-procedures/{{ $procedure->id }}/pending-bills',
        serviceRequestsBase: '/patient-procedures/{{ $procedure->id }}/service-requests',
        searchServicesRoute: '{{ route("nursing-workbench.search-services") }}',
        searchProductsRoute: '{{ route("nursing-workbench.search-products") }}',
        productBatchesRoute: '{{ route("nursing-workbench.product-batches") }}',
        investigationCategoryId: '{{ appsettings("investigation_category_id", "") }}',
        accessibleStores: {!! json_encode($accessibleStores ?? []) !!},
    };
    window.BILLING_KIT_CONFIG = window.WORKBENCH_CONFIG;
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/billing-shared.js') }}"></script>
<script src="{{ asset('js/request-details.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}?v={{ filemtime(public_path('js/clinical-orders-shared.js')) }}"></script>

@hasanyrole('SUPERADMIN|ADMIN|DOCTOR|Nurse|RECORD')
@include('admin.partials.treatment-plan-viewer-modal')
@endhasanyrole

<script src="{{ asset('js/procedure-show.js') }}"></script>
@endsection
