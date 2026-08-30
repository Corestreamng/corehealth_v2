<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/workbench-notes-shared.js') }}"></script>
<script src="{{ asset('js/speech-dictation.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {
        nursing_workbench_admitted_patients: '{{ route("nursing-workbench.admitted-patients") }}',
        nursing_workbench_vitals_queue: '{{ route("nursing-workbench.vitals-queue") }}',
        nursing_workbench_bed_requests_queue: '{{ route("nursing-workbench.bed-requests-queue") }}',
        nursing_workbench_discharge_queue: '{{ route("nursing-workbench.discharge-queue") }}'
    }
};
window.BILLING_KIT_CONFIG = {
    csrf: '{{ csrf_token() }}',
    addServiceRoute: '{{ route("nursing-workbench.billing.add-service") }}',
    addLabRoute: '{{ route("nursing-workbench.billing.add-lab-bill") }}',
    addImagingRoute: '{{ route("nursing-workbench.billing.add-imaging-bill") }}',
    addConsumableRoute: '{{ route("nursing-workbench.billing.add-consumable") }}',
    removeBillBase: '{{ url("/nursing-workbench/remove-bill") }}',
    pendingBillsBase: '{{ url("/nursing-workbench/patient") }}',
    serviceRequestsBase: '{{ url("/nursing-workbench/patient") }}',
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
<script src="{{ asset('js/workbench-helper.js') }}"></script>
@include('admin.shared.modals.request_details')
<script src="{{ asset('js/billing-shared.js') }}"></script>
<script src="{{ asset('js/request-details.js') }}"></script>
<script src="{{ asset('js/immunization-module.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'nursing'])
@include('admin.partials.invest_res_js')
@include('admin.partials.perform_investigation_modal')
@include('admin.partials.combo_confirm_modal')

<script src="{{ asset('js/nursing-workbench.js') }}?v={{ filemtime(public_path('js/nursing-workbench.js')) }}"></script>

@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.patient_summary_overlay')
@include('admin.partials.ai_quick_actions')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
<script src="{{ asset('js/patient-summary.js') }}"></script>

@endsection
