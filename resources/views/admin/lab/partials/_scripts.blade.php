@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'lab'])

<script src="{{ asset('js/lab-workbench.js') }}?v={{ filemtime(public_path('js/lab-workbench.js')) }}"></script>

@include('admin.partials.clinical_alerts_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}?v={{ filemtime(public_path('js/clinical-orders-shared.js')) }}"></script>
@endsection
