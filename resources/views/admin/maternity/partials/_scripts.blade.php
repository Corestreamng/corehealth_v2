@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/workbench-notes-shared.js') }}"></script>
<script src="{{ asset('js/speech-dictation.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
<script src="{{ asset('js/immunization-module.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        addServiceRoute: '{{ route("nursing-workbench.billing.add-service") }}',
        addLabRoute: '{{ route("nursing-workbench.billing.add-lab-bill") }}',
        addImagingRoute: '{{ route("nursing-workbench.billing.add-imaging-bill") }}',
        addConsumableRoute: '{{ route("nursing-workbench.billing.add-consumable") }}',
        removeBillBase: '/nursing-workbench/remove-bill',
        pendingBillsBase: '/nursing-workbench/patient',
        serviceRequestsBase: '/nursing-workbench/patient',
        searchServicesRoute: '{{ route("nursing-workbench.search-services") }}',
        searchProductsRoute: '{{ route("nursing-workbench.search-products") }}',
        productBatchesRoute: '{{ route("nursing-workbench.product-batches") }}',
        investigationCategoryId: '{{ appsettings("investigation_category_id", "") }}',
        imagingCategoryId: 6,
        resolvedStoreId: '{{ $resolvedStore->id ?? "" }}',
        resolvedStoreName: '{{ $resolvedStore->store_name ?? "" }}',
        showMedicationOption: true,
    };
</script>
@include('admin.shared.modals.request_details')
<script src="{{ asset('js/billing-shared.js') }}"></script>
<script src="{{ asset('js/request-details.js') }}"></script>
@include('admin.partials.patient_search_js', [
    'search_context' => 'maternity',
    'search_url' => route('maternity-workbench.search-patients')
])
@include('admin.partials.invest_res_js')
@include('admin.partials.perform_investigation_modal')
@include('admin.partials.combo_confirm_modal')
@include('admin.partials.patient_summary_overlay')
@include('admin.partials.ai_quick_actions')
<script src="{{ asset('js/patient-summary.js') }}"></script>
<script src="{{ asset('js/maternity-workbench.js') }}"></script>
@endsection
