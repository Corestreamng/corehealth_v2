@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/workbench-notes-shared.js') }}"></script>
<script src="{{ asset('js/speech-dictation.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    addServiceRoute: '{{ route("nursing-workbench.billing.add-service") }}',
    addLabRoute: '{{ route("nursing-workbench.billing.add-lab-bill") }}',
    addImagingRoute: '{{ route("nursing-workbench.billing.add-imaging-bill") }}',
    addConsumableRoute: '{{ route("nursing-workbench.billing.add-consumable") }}',
    removeBillBase: '{{ url("/nursing-workbench/remove-bill") }}',
    pendingBillsBase: '{{ url("/nursing-workbench/patient") }}',
    serviceRequestsBase: '{{ url("/nursing-workbench/patient") }}',
    searchServicesRoute: '{{ route("maternity-workbench.search-services") }}',
    searchProductsRoute: '{{ route("nursing-workbench.search-products") }}',
    productBatchesRoute: '{{ route("maternity-workbench.product-batches") }}',
    investigationCategoryId: '{{ appsettings("investigation_category_id", "") }}',
    imagingCategoryId: 6,
    resolvedStoreId: '{{ $resolvedStore->id ?? "" }}',
    resolvedStoreName: '{{ $resolvedStore->store_name ?? "" }}',
    showMedicationOption: true,
    hmos: @json(\App\Models\Hmo::with('scheme')->orderBy('name')->get()->map(fn($h) => ['id' => $h->id, 'name' => $h->name, 'scheme_name' => $h->scheme->name ?? 'Other'])),
    routes: {
        'maternity-workbench.queue.active-anc': '{{ route("maternity-workbench.queue.active-anc") }}',
        'maternity-workbench.queue.due-visits': '{{ route("maternity-workbench.queue.due-visits") }}',
        'maternity-workbench.queue.upcoming-edd': '{{ route("maternity-workbench.queue.upcoming-edd") }}',
        'maternity-workbench.queue.postnatal': '{{ route("maternity-workbench.queue.postnatal") }}',
        'maternity-workbench.queue.overdue-immunization': '{{ route("maternity-workbench.queue.overdue-immunization") }}',
        'maternity-workbench.queue.high-risk': '{{ route("maternity-workbench.queue.high-risk") }}',
        'maternity-workbench.queue.bed-requests': '{{ route("maternity-workbench.queue.bed-requests") }}',
        'maternity-workbench.queue.discharge-requests': '{{ route("maternity-workbench.queue.discharge-requests") }}',
        'maternity-workbench.queue.admitted-patients': '{{ route("maternity-workbench.queue.admitted-patients") }}',
        'maternity-workbench.queue.counts': '{{ route("maternity-workbench.queue.counts") }}',
        'maternity-workbench.enroll': '{{ route("maternity-workbench.enroll") }}',
        'maternity-workbench.search-patients': '{{ route("maternity-workbench.search-patients") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
@include('admin.shared.modals.request_details')
<script src="{{ asset('js/billing-shared.js') }}"></script>
<script src="{{ asset('js/request-details.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
<script src="{{ asset('js/immunization-module.js') }}"></script>
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
<script src="{{ asset('js/maternity-workbench.js') }}?v={{ filemtime(public_path('js/maternity-workbench.js')) }}"></script>
@endsection
