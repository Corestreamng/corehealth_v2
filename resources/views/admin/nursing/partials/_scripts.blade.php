@section('scripts')
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
    labRequiresApproval: {{ (bool) appsettings('lab_results_require_approval') ? 'true' : 'false' }},
    imagingRequiresApproval: {{ (bool) appsettings('imaging_results_require_approval') ? 'true' : 'false' }},
    doctorSelfApproveLab: {{ (bool) appsettings('doctor_self_approve_lab_result') ? 'true' : 'false' }},
    nurseSelfApproveLab: {{ (bool) appsettings('nurse_self_approve_lab_result') ? 'true' : 'false' }},
    doctorSelfApproveImaging: {{ (bool) appsettings('doctor_self_approve_imaging_result') ? 'true' : 'false' }},
    nurseSelfApproveImaging: {{ (bool) appsettings('nurse_self_approve_imaging_result') ? 'true' : 'false' }},
    routes: {
        'nursing-workbench.admitted-patients': '{{ route("nursing-workbench.admitted-patients") }}',
        'nursing-workbench.vitals-queue': '{{ route("nursing-workbench.vitals-queue") }}',
        'nursing-workbench.bed-requests-queue': '{{ route("nursing-workbench.bed-requests-queue") }}',
        'nursing-workbench.discharge-queue': '{{ route("nursing-workbench.discharge-queue") }}',
        'nursing-workbench.notes.store': '{{ route("nursing-workbench.notes.store") }}',
        'nursing-workbench.notes.list': '{{ url("nursing-workbench/patient") }}',
        'nursing-workbench.injection.administer': '{{ route("nursing-workbench.injection.administer") }}',
        'nursing-workbench.immunization.administer': '{{ route("nursing-workbench.immunization.administer") }}',
        'nursing-workbench.search-products': '{{ route("nursing-workbench.search-products") }}',
        'nursing-workbench.product-batches': '{{ route("nursing-workbench.product-batches") }}',
        'nursing-workbench.search-services': '{{ route("nursing-workbench.search-services") }}',
        'nursing-workbench.billing.add-service': '{{ route("nursing-workbench.billing.add-service") }}',
        'nursing-workbench.billing.add-consumable': '{{ route("nursing-workbench.billing.add-consumable") }}',
        'nursing-workbench.billing.add-lab-bill': '{{ route("nursing-workbench.billing.add-lab-bill") }}',
        'nursing-workbench.billing.add-imaging-bill': '{{ route("nursing-workbench.billing.add-imaging-bill") }}',
        'nursing-workbench.billing.remove': '{{ url("/nursing-workbench/remove-bill") }}'
    }
};
window.INVEST_RES_SOURCE = 'nursing';
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
<script src="{{ versioned_asset('js/nursing-core.js') }}"></script>
<script src="{{ versioned_asset('js/nursing-vitals.js') }}"></script>
<script src="{{ versioned_asset('js/nursing-medication-chart.js') }}"></script>
<script src="{{ versioned_asset('js/nursing-notes.js') }}"></script>
<script src="{{ versioned_asset('js/nursing-clinical-requests.js') }}"></script>
@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.patient_summary_overlay')
@include('admin.partials.ai_quick_actions')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
<script src="{{ asset('js/patient-summary.js') }}"></script>
@endsection
