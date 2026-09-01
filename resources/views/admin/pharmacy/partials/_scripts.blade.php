@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<!-- Chart.js for transaction analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- SheetJS for Excel export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {
        'pharmacy.queue-counts': '{{ route("pharmacy.queue-counts") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'pharmacy'])
<script src="{{ asset('js/pharmacy-dispensing.js') }}?v={{ filemtime(public_path('js/pharmacy-dispensing.js')) }}"></script>
<script src="{{ asset('js/pharmacy-returns.js') }}?v={{ filemtime(public_path('js/pharmacy-returns.js')) }}"></script>
<script src="{{ asset('js/pharmacy-stock.js') }}?v={{ filemtime(public_path('js/pharmacy-stock.js')) }}"></script>
<script src="{{ asset('js/pharmacy-reports.js') }}?v={{ filemtime(public_path('js/pharmacy-reports.js')) }}"></script>
<script src="{{ asset('js/pharmacy-core.js') }}?v={{ filemtime(public_path('js/pharmacy-core.js')) }}"></script>
@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal', ['products_only' => true])
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>

<script src="{{ asset('js/clinical-orders-shared.js') }}?v={{ filemtime(public_path('js/clinical-orders-shared.js')) }}"></script>
@endsection
